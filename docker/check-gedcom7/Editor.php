<?php

declare(strict_types=1);

namespace Webtrees7Check;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Enums\AccessLevel;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Storage;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Http\Routes\WebRoutes;
use Fisharebest\Webtrees\Http\Routing\GedcomRecordParameterResolver;
use Fisharebest\Webtrees\Http\Routing\ParameterResolver;
use Fisharebest\Webtrees\Http\Routing\ParameterResolverInterface;
use Fisharebest\Webtrees\Http\Routing\Route;
use Fisharebest\Webtrees\Http\Routing\RouteCollection;
use Fisharebest\Webtrees\Http\Routing\ScalarParameterResolver;
use Fisharebest\Webtrees\Http\Routing\TreeParameterResolver;
use Fisharebest\Webtrees\Http\Routing\UrlGenerator;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Module\ModuleThemeInterface;
use Fisharebest\Webtrees\Module\WebtreesTheme;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomEditService;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Tree;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;

use function array_keys;
use function array_map;
use function array_unique;
use function count;
use function explode;
use function implode;
use function in_array;
use function preg_match;
use function sort;
use function str_contains;
use function strlen;
use function strpos;
use function substr_replace;
use function view;

/**
 * Open each edit form, as a browser would show it, and save it without changes.
 *
 * A gap is a form that sends something other than GEDCOM 7.0, so that the converter has to change it when saving.
 * A loss is a form that changes the record, although nothing was changed.
 */
final class Editor
{
    // Facts that are edited elsewhere, e.g. on the family pages
    private const array SKIP_FACTS = ['CHAN', 'CHIL', 'FAMC', 'FAMS', 'HUSB', 'WIFE'];

    // Records with their own editors, or none
    private const array SKIP_RECORDS = ['HEAD', 'NOTE', 'SNOTE', 'SUBN', 'TRLR'];

    // The areas of stage B, by the structures that the converter changes, e.g. ":ASSO:" matches "INDI:BIRT:ASSO:ROLE"
    private const array AREAS = [
        'ordinances'   => [':BAPL:', ':CONL:', ':ENDL:', ':INIL:', ':SLGC:', ':SLGS:', ':_SLGC:'],
        'associates'   => [':ASSO:', ':_ASSO:', ':_GODP:', ':_WITN:'],
        'sources'      => [':SOUR:'],
        'media'        => [':OBJE:', ':FILE:'],
        'translations' => [':TRAN:', ':ROMN:', ':FONE:'],
        'identifiers'  => [':EXID:', ':UID:', ':_UID:', ':AFN:', ':RFN:', ':RIN:', ':_FSFTID:', ':_GOV:'],
        'notes'        => [':NOTE:', ':SNOTE:'],
        'enumerations' => [':TYPE:', ':PEDI:', ':STAT:', ':LANG:', ':RESN:', ':_RESN:', ':SEX:'],
        'dates'        => [':DATE:', ':AGE:', ':TIME:'],
    ];

    /** @var array<string,array{count:int,trees:array<string,int>,example:string}> */
    public array $gaps = [];

    /** @var array<string,int> */
    public array $forms = [];

    public function __construct(private readonly GedcomEditService $gedcom_edit_service = new GedcomEditService())
    {
        // This is normally set in middleware.
        Registry::container()->set(ModuleThemeInterface::class, new WebtreesTheme());

        $routes             = new RouteCollection();
        $parameter_resolver = new ParameterResolver([
            new TreeParameterResolver(new TreeService(new GedcomImportService())),
            new GedcomRecordParameterResolver(),
            new ScalarParameterResolver(),
        ]);

        (new WebRoutes())->load($routes);

        Registry::container()->set(RouteCollection::class, $routes);
        Registry::container()->set(ParameterResolverInterface::class, $parameter_resolver);
        Registry::container()->set(UrlGenerator::class, new UrlGenerator($routes, '/', $parameter_resolver));
    }

    public function login(UserInterface $user): void
    {
        Auth::login($user);
    }

    /**
     * Save every form of a tree without changes.
     *
     * @param list<int|string> $xrefs
     *
     * @return list<string> The losses, by kind, with an example
     */
    public function tree(Tree $tree, array $xrefs): array
    {
        $this->request($tree);

        $losses = [];

        foreach ($xrefs as $xref) {
            $xref = (string) $xref;

            if (in_array($xref, self::SKIP_RECORDS, true)) {
                continue;
            }

            $record = Registry::gedcomRecordFactory()->make($xref, $tree);

            if ($record === null || in_array($record->tag(), self::SKIP_RECORDS, true)) {
                continue;
            }

            $prefix = '0 @' . $record->xref() . '@ ' . $record->tag();

            if (!$record instanceof Individual && !$record instanceof Family) {
                $this->form($tree, $record, 'record', $record->gedcom(), $losses, function () use ($record, $prefix): string {
                    $gedcom = $this->gedcom_edit_service->insertMissingRecordSubtags($record, true);

                    return $prefix . $this->submit($record, $gedcom, [$record->tag()], true);
                });
            }

            foreach ($record->facts([], false, AccessLevel::Hidden, true) as $fact) {
                if (in_array($fact->tag(), array_map(static fn (string $tag): string => $record->tag() . ':' . $tag, self::SKIP_FACTS), true) || !$fact->canEdit()) {
                    continue;
                }

                // As GedcomRecord::updateFact(), the fact is replaced in its record.
                $this->form($tree, $record, $fact->tag(), $record->gedcom(), $losses, function () use ($fact, $record): string {
                    $gedcom = $this->gedcom_edit_service->insertMissingFactSubtags($fact, true);
                    $edited = $this->submit($record, $gedcom, explode(':', $fact->tag()), false);
                    $offset = strpos($record->gedcom(), "\n" . $fact->gedcom());

                    if ($offset === false) {
                        throw new FormException('fact not found in record');
                    }

                    return substr_replace($record->gedcom(), "\n" . $edited, $offset, strlen($fact->gedcom()) + 1);
                });
            }
        }

        uasort($losses, static fn (array $x, array $y): int => $y['count'] <=> $x['count']);

        return array_map(static fn (string $kind, array $loss): string => $loss['count'] . "x " . $kind . "\n            e.g. " . $loss['example'], array_keys($losses), $losses);
    }

    /**
     * The gaps, by area, with their signatures.
     *
     * @return array<string,array<string,array{count:int,trees:array<string,int>,example:string}>>
     */
    public function gapsByArea(): array
    {
        $areas = [];

        foreach ($this->gaps as $signature => $gap) {
            $areas[$this->area($signature)][$signature] = $gap;
        }

        foreach ($areas as &$gaps) {
            uasort($gaps, static fn (array $x, array $y): int => $y['count'] <=> $x['count']);
        }

        return $areas;
    }

    private function request(Tree $tree): void
    {
        $request = Registry::container()->get(ServerRequestFactoryInterface::class)
            ->createServerRequest('GET', 'http://localhost:8080/index.php')
            ->withAttribute('base_url', 'http://localhost:8080')
            ->withAttribute('client-ip', '127.0.0.1')
            ->withAttribute('user', Auth::user())
            ->withAttribute('route', new Route(url: '/', controller: self::class, middleware: []))
            ->withAttribute('tree', $tree);

        Registry::container()->set(Tree::class, $tree);
        Registry::container()->set(ServerRequestInterface::class, $request);
    }

    /**
     * Show the form, and send it as a browser would, without changes.
     *
     * @param array<int,string> $hierarchy
     */
    private function submit(GedcomRecord $record, string $gedcom, array $hierarchy, bool $append): string
    {
        $html = view('edit/edit-gedcom-fields', ['gedcom' => $gedcom, 'hierarchy' => $hierarchy, 'tree' => $record->tree(), 'prefix' => '']);

        [$levels, $tags, $values] = self::fields($html);

        if (count($levels) !== count($tags) || count($tags) !== count($values)) {
            throw new FormException('cannot be sent without scripts: ' . count($levels) . ' levels, ' . count($tags) . ' tags, ' . count($values) . ' values');
        }

        return $this->gedcom_edit_service->editLinesToGedcom($record->tag(), $levels, $tags, $values, $append);
    }

    /**
     * The fields that a browser sends.  The script of note-structure shows inline notes for new notes.
     *
     * @return array{0:list<string>,1:list<string>,2:list<string>}
     */
    private static function fields(string $html): array
    {
        $document = new DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_NOERROR);

        $xpath  = new DOMXPath($document);
        $fields = ['levels[]' => [], 'tags[]' => [], 'values[]' => []];

        foreach ($xpath->query('//input | //select | //textarea') ?: [] as $element) {
            if (!$element instanceof DOMElement || !isset($fields[$element->getAttribute('name')]) || $element->hasAttribute('disabled')) {
                continue;
            }

            if ($xpath->query('ancestor::*[@data-wt-note-shared]', $element)?->length > 0) {
                continue;
            }

            $name = $element->getAttribute('name');

            if ($element->tagName === 'textarea') {
                $fields[$name][] = $element->textContent;
            } elseif ($element->tagName === 'select') {
                $selected = $xpath->query('.//option[@selected]', $element);
                $first    = $xpath->query('.//option', $element);
                $option   = $selected?->item(0) ?? $first?->item(0);

                if ($element->hasAttribute('multiple')) {
                    throw new FormException('multiple select: ' . $element->getAttribute('id'));
                }

                $fields[$name][] = $option instanceof DOMElement ? ($option->hasAttribute('value') ? $option->getAttribute('value') : $option->textContent) : '';
            } elseif (in_array($element->getAttribute('type'), ['checkbox', 'radio'], true)) {
                if ($element->hasAttribute('checked')) {
                    $fields[$name][] = $element->getAttribute('value');
                }
            } else {
                $fields[$name][] = $element->getAttribute('value');
            }
        }

        return [$fields['levels[]'], $fields['tags[]'], $fields['values[]']];
    }

    /**
     * Open a form, save it as webtrees would, and record the gap and any loss.
     *
     * @param array<string,array{count:int,example:string}> $losses
     * @param callable():string                              $submit
     */
    private function form(Tree $tree, GedcomRecord $record, string $form, string $original, array &$losses, callable $submit): void
    {
        $this->forms[$tree->name()] = ($this->forms[$tree->name()] ?? 0) + 1;

        $label = $record->xref() . ' ' . $form;

        try {
            $edited = $submit();
        } catch (FormException $ex) {
            self::count($losses, '[form] ' . $form . ' ' . $ex->getMessage(), $label);

            return;
        }

        $saved = Gedcom7Storage::pendingChange($tree, $edited);
        $gap   = differences(lines($edited), lines($saved));

        if ($gap !== []) {
            $signature = self::signature($gap);

            $this->gaps[$signature] ??= ['count' => 0, 'trees' => [], 'example' => $label . ': ' . implode(' | ', $gap)];
            $this->gaps[$signature]['count']++;
            $this->gaps[$signature]['trees'][$tree->name()] = ($this->gaps[$signature]['trees'][$tree->name()] ?? 0) + 1;
        }

        // Saving a record updates its CHAN.
        $by_form = differences(lines($original, true), lines($edited, true));
        $loss    = differences(lines($original, true), lines($saved, true));

        if ($loss !== []) {
            $cause = $by_form === [] ? '[converter] ' : '[form] ';

            self::count($losses, $cause . self::signature($loss), $label . ': ' . implode(' | ', $loss));
        }

        if (!Gedcom7Storage::isGedcom7($saved)) {
            self::count($losses, '[converter] not GEDCOM 7.0 after saving', $label);
        }
    }

    /**
     * @param array<string,array{count:int,example:string}> $list
     */
    private static function count(array &$list, string $kind, string $example): void
    {
        $list[$kind] ??= ['count' => 0, 'example' => $example];
        $list[$kind]['count']++;
    }

    /**
     * The tags that the converter removes and adds, e.g. "-INDI:ASSO:RELA +INDI:ASSO:ROLE".
     *
     * @param list<string> $differences
     */
    private static function signature(array $differences): string
    {
        $parts = [];

        foreach ($differences as $difference) {
            if (preg_match('/^(missing|added): +(\S+)/', $difference, $match) === 1) {
                $parts[] = ($match[1] === 'missing' ? '-' : '+') . $match[2];
            }
        }

        $parts = array_unique($parts);
        sort($parts);

        return implode(' ', $parts);
    }

    private function area(string $signature): string
    {
        foreach (self::AREAS as $area => $structures) {
            foreach ($structures as $structure) {
                foreach (explode(' ', $signature) as $part) {
                    if (str_contains(':' . substr($part, 1) . ':', $structure)) {
                        return $area;
                    }
                }
            }
        }

        return 'other';
    }
}

/**
 * A form that a browser without scripts cannot send.
 */
final class FormException extends \RuntimeException
{
}

/**
 * Each line of a record with the tags above it, e.g. "INDI:BIRT:DATE 1 JAN 1900", so that the order does not matter.
 *
 * @return list<string>
 */
function lines(string $gedcom, bool $skip_chan = false): array
{
    $paths = [];
    $tags  = [];

    foreach (explode("\n", $gedcom) as $line) {
        if (preg_match('/^(\d+) (?:@[^@]+@ )?(\w+)(?: (.*))?$/', $line, $match) !== 1) {
            continue;
        }

        $level        = (int) $match[1];
        $tags         = array_slice($tags, 0, $level);
        $tags[$level] = $match[2];
        $path         = implode(':', $tags);

        if (!$skip_chan || !str_contains($path, ':CHAN')) {
            $paths[] = $path . ' ' . ($match[3] ?? '');
        }
    }

    return $paths;
}
