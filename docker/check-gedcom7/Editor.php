<?php

declare(strict_types=1);

namespace Webtrees7Check;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\ElementInterface;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Elements\AbstractXrefElement;
use Fisharebest\Webtrees\Elements\AgeAtEvent;
use Fisharebest\Webtrees\Elements\DateValue;
use Fisharebest\Webtrees\Enums\AccessLevel;
use Fisharebest\Webtrees\Fact;
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

use function array_diff;
use function array_filter;
use function array_keys;
use function array_map;
use function array_slice;
use function array_unique;
use function array_values;
use function count;
use function end;
use function explode;
use function implode;
use function in_array;
use function preg_match;
use function preg_replace;
use function sort;
use function str_contains;
use function str_starts_with;
use function strlen;
use function strpos;
use function strrpos;
use function substr;
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

    // Records to which facts are added
    private const array NEW_FACT_RECORDS = [
        'INDI' => "0 @I1@ INDI\n1 NAME Test /Person/",
        'FAM'  => '0 @F1@ FAM',
        'SOUR' => "0 @S1@ SOUR\n1 TITL Test",
        'REPO' => "0 @R1@ REPO\n1 NAME Test",
        'OBJE' => "0 @M1@ OBJE\n1 FILE test.jpg\n2 FORM image/jpeg",
        'SUBM' => "0 @U1@ SUBM\n1 NAME Test",
    ];

    // What people type into text fields, e.g. dates as in GEDCOM 5.5.1
    private const array DATES = ['abt 1850', 'from 1850 to 1860', '@#DJULIAN@ 1 JAN 1700', 'INT 1850 (Easter)', '50 B.C.'];
    private const array AGES  = ['63y', 'CHILD', '< 8y'];

    // Valid values for text fields, by tag
    private const array SAMPLES = [
        'AGE'    => '63y',
        'DATE'   => '1 JAN 1850',
        'EMAIL'  => 'test@example.com',
        'FILE'   => 'test.jpg',
        'FORM'   => 'image/jpeg',
        'HEIGHT' => '10',
        'LATI'   => 'N50.5',
        'LEFT'   => '0',
        'LONG'   => 'E7.5',
        'NCHI'   => '2',
        'NMR'    => '1',
        'SDATE'  => '1 JAN 1850',
        'TIME'   => '12:00',
        'TOP'    => '0',
        'WIDTH'  => '10',
        'WWW'    => 'https://example.com',
    ];

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

    /** @var array<string,array{count:int,example:string}> */
    public array $new_gaps = [];

    public int $new_forms = 0;

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
        self::request($tree);

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

                    return $prefix . $this->submit($record, $gedcom, [$record->tag()], true) . $this->gedcom_edit_service->automaticStructures($record);
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
     * Add each fact, and fill each field of its form in turn, e.g. with each value of a list.
     */
    public function newFacts(Tree $tree): void
    {
        self::request($tree);

        $storage = new Gedcom7Storage();

        foreach (self::NEW_FACT_RECORDS as $type => $base) {
            $record = match ($type) {
                'INDI'  => Registry::individualFactory()->new('I1', $base, null, $tree),
                'FAM'   => Registry::familyFactory()->new('F1', $base, null, $tree),
                'SOUR'  => Registry::sourceFactory()->new('S1', $base, null, $tree),
                'REPO'  => Registry::repositoryFactory()->new('R1', $base, null, $tree),
                'OBJE'  => Registry::mediaFactory()->new('M1', $base, null, $tree),
                default => Registry::submitterFactory()->new('U1', $base, null, $tree),
            };

            foreach (array_keys(Registry::elementFactory()->make($type)->subtags()) as $tag) {
                if (in_array($tag, self::SKIP_FACTS, true)) {
                    continue;
                }

                $element = Registry::elementFactory()->make($type . ':' . $tag);
                $fact    = new Fact(trim('1 ' . $tag . ' ' . $element->default($tree)), $record, 'new');
                $gedcom  = $this->gedcom_edit_service->insertMissingFactSubtags($fact, true);
                $html    = view('edit/edit-gedcom-fields', ['gedcom' => $gedcom, 'hierarchy' => [$type, $tag], 'tree' => $tree, 'prefix' => '']);

                [$levels, $tags, $slots] = self::controls($html);

                if (count($levels) !== count($slots)) {
                    self::count($this->new_gaps, '[form] ' . $type . ':' . $tag . ' cannot be sent without scripts', $type . ':' . $tag);
                    continue;
                }

                $paths  = self::paths($type, $levels, $tags);
                $values = [];

                // Fill every field, then change one field at a time.  Below an empty list, we leave the fields empty.
                $empty = 99;

                foreach ($slots as $n => $slot) {
                    $level = (int) $levels[$n];
                    $empty = $level <= $empty ? 99 : $empty;

                    if ($level > $empty) {
                        $values[] = $slot['kind'] === 'hidden' ? $slot['value'] ?? '' : '';
                    } elseif ($level === 1) {
                        // e.g. the event that did not occur
                        $values[] = $slot['value'] === '' ? self::SAMPLES[$tag] ?? self::candidates($slot, $element)[0] ?? '' : $slot['value'] ?? '';
                    } else {
                        $values[] = self::sample($slot, $paths[$n]);
                        $empty    = $slot['options'] !== [] && end($values) === '' ? $level : 99;
                    }
                }

                $filled = $this->newFact($storage, $base, $type, $levels, $tags, $values, $type . ':' . $tag . ' filled', []);

                foreach ($slots as $n => $slot) {
                    foreach (self::candidates($slot, Registry::elementFactory()->make($paths[$n])) as $candidate) {
                        if ($candidate !== $values[$n]) {
                            $changed     = self::parents($values, $levels, $slots, $n);
                            $changed[$n] = $candidate;

                            $this->newFact($storage, $base, $type, $levels, $tags, $changed, $paths[$n] . ' = ' . $candidate, $filled);
                        }
                    }
                }
            }
        }
    }

    /**
     * Save a new fact, and record what the converter changes.
     *
     * @param list<string> $levels
     * @param list<string> $tags
     * @param list<string> $values
     * @param list<string> $known  What the converter changes in the filled form, which we do not count again
     *
     * @return list<string> What the converter changes, e.g. ["-INDI:*:PLAC:ROMN", "+INDI:*:PLAC:TRAN"]
     */
    private function newFact(Gedcom7Storage $storage, string $base, string $type, array $levels, array $tags, array $values, string $input, array $known): array
    {
        $this->new_forms++;

        $edited = $this->gedcom_edit_service->editLinesToGedcom($type, $levels, $tags, $values, false);

        if ($edited === '') {
            return [];
        }

        $edited = $base . "\n" . $edited;
        $saved  = $storage->edit($edited);
        $gap    = differences(lines($edited), lines($saved));

        // e.g. "INDI:BIRT:DATE" and "INDI:DEAT:DATE" are "INDI:*:DATE"
        $field = self::anyFact((string) preg_replace(['/ = .*/', '/:[A-Z0-9_]+ filled$/'], ['', ':* filled'], $input));

        $parts = $gap === [] ? [] : explode(' ', self::anyFact(self::signature($gap)));
        $new   = array_values(array_diff(array_unique($parts), $known));

        if ($new !== []) {
            self::count($this->new_gaps, $field . ': ' . implode(' ', $new), $input . ': ' . implode(' | ', $gap));
        }

        if (!Gedcom7Storage::isGedcom7($saved) && !in_array('not-7.0', $known, true)) {
            self::count($this->new_gaps, $field . ': not GEDCOM 7.0 after saving', $input);
            $parts[] = 'not-7.0';
        }

        return $parts;
    }

    /**
     * Choose the empty lists above a field, as a person would before filling it.
     *
     * @param list<string>                                                     $values
     * @param list<string>                                                     $levels
     * @param list<array{kind:string,value:string|null,options:list<string>}> $slots
     *
     * @return list<string>
     */
    private static function parents(array $values, array $levels, array $slots, int $n): array
    {
        $level = (int) $levels[$n];

        for ($i = $n - 1; $i >= 0 && $level > 2; $i--) {
            if ((int) $levels[$i] < $level) {
                $level = (int) $levels[$i];

                foreach ($values[$i] === '' ? $slots[$i]['options'] : [] as $option) {
                    if ($option !== '') {
                        $values[$i] = $option;
                        break;
                    }
                }
            }
        }

        return $values;
    }

    /**
     * Any fact, e.g. "INDI:BIRT:DATE" becomes "INDI:*:DATE".
     */
    private static function anyFact(string $text): string
    {
        return (string) preg_replace('/\b(INDI|FAM|SOUR|REPO|OBJE|SUBM):[A-Z0-9_]+(?=:)/', '$1:*', $text);
    }

    /**
     * The tags above each field, e.g. "INDI:BIRT:DATE".
     *
     * @param list<string> $levels
     * @param list<string> $tags
     *
     * @return list<string>
     */
    private static function paths(string $type, array $levels, array $tags): array
    {
        $hierarchy = [$type];
        $paths     = [];

        foreach ($levels as $n => $level) {
            $hierarchy[(int) $level] = $tags[$n];
            $paths[]                 = implode(':', array_slice($hierarchy, 0, 1 + (int) $level));
        }

        return $paths;
    }

    /**
     * A valid value for a field: a pointer to nothing, the first value of a list if one is required, or a sample.
     *
     * @param array{kind:string,value:string|null,options:list<string>} $slot
     */
    private static function sample(array $slot, string $path): string
    {
        if ($slot['kind'] === 'hidden') {
            return $slot['value'] ?? '';
        }

        $parent   = substr($path, 0, (int) strrpos($path, ':'));
        $tag      = substr($path, strlen($parent) + 1);
        $required = str_starts_with(Registry::elementFactory()->make($parent)->subtags()[$tag] ?? '0', '1');

        if ($slot['options'] !== []) {
            foreach ($slot['options'] as $option) {
                if ($option === '@VOID@' || $required && $option !== '') {
                    return $option;
                }
            }

            return $slot['value'] ?? '';
        }

        return self::SAMPLES[$tag] ?? 'Test';
    }

    /**
     * Values to try in a field: each value of a list, or what people type.
     *
     * @param array{kind:string,value:string|null,options:list<string>} $slot
     *
     * @return list<string>
     */
    private static function candidates(array $slot, ElementInterface $element): array
    {
        if ($slot['options'] !== []) {
            return array_values(array_filter($slot['options'], static fn (string $option): bool => $option !== ''));
        }

        if ($element instanceof DateValue) {
            return self::DATES;
        }

        if ($element instanceof AgeAtEvent) {
            return self::AGES;
        }

        return [];
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

    public static function request(Tree $tree): void
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
     * The fields that a browser sends.
     *
     * @return array{0:list<string>,1:list<string>,2:list<string>}
     */
    private static function fields(string $html): array
    {
        [$levels, $tags, $slots] = self::controls($html);

        $values = [];

        foreach ($slots as $slot) {
            if ($slot['value'] !== null) {
                $values[] = $slot['value'];
            }
        }

        return [$levels, $tags, $values];
    }

    /**
     * The controls of a form, in order.  A group of radio buttons is one control.  Its value is null if none is checked.
     * The script of note-structure shows inline notes for new notes.
     *
     * @return array{0:list<string>,1:list<string>,2:list<array{kind:string,value:string|null,options:list<string>}>}
     */
    private static function controls(string $html): array
    {
        $document = new DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_NOERROR);

        $xpath  = new DOMXPath($document);
        $levels = [];
        $tags   = [];
        $slots  = [];
        $radios = false;

        foreach ($xpath->query('//input | //select | //textarea') ?: [] as $element) {
            if (!$element instanceof DOMElement || $element->hasAttribute('disabled')) {
                continue;
            }

            $name = $element->getAttribute('name');

            if ($name === 'levels[]' || $name === 'tags[]') {
                if ($name === 'levels[]') {
                    $levels[] = $element->getAttribute('value');
                } else {
                    $tags[] = $element->getAttribute('value');
                }

                continue;
            }

            if ($name !== 'values[]' || $xpath->query('ancestor::*[@data-wt-note-shared]', $element)?->length > 0) {
                continue;
            }

            $type = $element->getAttribute('type');

            if ($element->tagName === 'input' && $type === 'radio') {
                if (!$radios) {
                    $slots[] = ['kind' => 'radio', 'value' => null, 'options' => []];
                    $radios  = true;
                }

                $slot = count($slots) - 1;

                $slots[$slot]['options'][] = $element->getAttribute('value');

                if ($element->hasAttribute('checked')) {
                    $slots[$slot]['value'] = $element->getAttribute('value');
                }

                continue;
            }

            $radios = false;

            if ($element->tagName === 'textarea') {
                $slots[] = ['kind' => 'textarea', 'value' => $element->textContent, 'options' => []];
            } elseif ($element->tagName === 'select') {
                if ($element->hasAttribute('multiple')) {
                    throw new FormException('multiple select: ' . $element->getAttribute('id'));
                }

                $options  = [];
                $selected = null;

                foreach ($xpath->query('.//option', $element) ?: [] as $option) {
                    if ($option instanceof DOMElement) {
                        $value     = $option->hasAttribute('value') ? $option->getAttribute('value') : $option->textContent;
                        $options[] = $value;
                        $selected  = $selected ?? ($option->hasAttribute('selected') ? $value : null);
                    }
                }

                $slots[] = ['kind' => 'select', 'value' => $selected ?? $options[0] ?? '', 'options' => $options];
            } elseif ($type === 'checkbox') {
                $slots[] = ['kind' => 'checkbox', 'value' => $element->hasAttribute('checked') ? $element->getAttribute('value') : null, 'options' => [$element->getAttribute('value')]];
            } else {
                $slots[] = ['kind' => $type === 'hidden' ? 'hidden' : 'text', 'value' => $element->getAttribute('value'), 'options' => []];
            }
        }

        return [$levels, $tags, $slots];
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
