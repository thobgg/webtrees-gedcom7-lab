<?php

declare(strict_types=1);

namespace Webtrees7Check;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Census\CensusOfEngland1881;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Schema;
use Fisharebest\Webtrees\Http\Controllers\AddSourceEntry;
use Fisharebest\Webtrees\Http\Controllers\CreateIndividualFromPhrase;
use Fisharebest\Webtrees\Http\Controllers\CreateLocation;
use Fisharebest\Webtrees\Http\Controllers\CreateMediaObjectFromFile;
use Fisharebest\Webtrees\Http\Controllers\CreateNote;
use Fisharebest\Webtrees\Http\Controllers\CreateRepository;
use Fisharebest\Webtrees\Http\Controllers\CreateSource;
use Fisharebest\Webtrees\Http\Controllers\CreateSubmitter;
use Fisharebest\Webtrees\Http\Controllers\EditMediaFile;
use Fisharebest\Webtrees\Http\Controllers\LinkChildToFamily;
use Fisharebest\Webtrees\Http\Routing\Route;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\Module\CensusAssistantModule;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\SourceEntryService;
use Fisharebest\Webtrees\Tree;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

use function array_fill;
use function array_keys;
use function array_slice;
use function count;
use function explode;
use function implode;
use function preg_match;
use function preg_replace;

/**
 * Use the dialogs that write GEDCOM without an edit form, e.g. the media dialogs and the assistants.
 * The converter logs what it still changes when they save (Gedcom7Storage::pendingChange()).
 */
final class Dialogs
{
    public int $dialogs = 0;

    /** @var array<string,array{count:int,example:string}> */
    public array $gaps = [];

    public function run(Tree $tree): void
    {
        $start   = (int) DB::table('log')->max('log_id');
        $factory = Registry::elementFactory();

        $child  = $tree->createIndividual("0 @@ INDI\n1 NAME Child /Dialog/\n1 SEX M");
        $father = $tree->createIndividual("0 @@ INDI\n1 NAME Father /Dialog/\n1 SEX M");
        $mother = $tree->createIndividual("0 @@ INDI\n1 NAME Mother /Dialog/\n1 SEX F");
        $family = $tree->createFamily("0 @@ FAM\n1 HUSB @" . $father->xref() . "@\n1 WIFE @" . $mother->xref() . '@');
        $source = $tree->createRecord("0 @@ SOUR\n1 TITL Register");

        // Link a child to a family, with each pedigree
        foreach (['', ...Gedcom7Schema::ENUMERATIONS['PEDI']] as $pedigree) {
            $this->dialog('link child, PEDI ' . $pedigree, $tree, LinkChildToFamily::class, ['xref' => $child->xref()], ['famid' => $family->xref(), 'PEDI' => (string) $pedigree]);
        }

        // Media objects, with each medium, a webtrees medium and a phrase
        foreach ([...array_keys($factory->make('OBJE:FILE:FORM:MEDI')->values()), 'CERTIFICATE'] as $medium) {
            $this->dialog('media object, MEDI ' . $medium, $tree, CreateMediaObjectFromFile::class, [], ['file' => 'folder/dialog test.jpg', 'type' => (string) $medium, 'title' => 'Title', 'note' => "Line 1\nLine 2", 'phrase' => $medium === 'OTHER' ? 'Postcard' : '']);
        }

        $media = Registry::mediaFactory()->make((string) DB::table('media')->where('m_file', '=', $tree->id())->max('m_id'), $tree);

        if ($media instanceof Media) {
            $file = $media->mediaFiles()->first();

            $this->dialog('media file, a URL', $tree, EditMediaFile::class, ['xref' => $media->xref(), 'fact_id' => $file?->factId() ?? ''], ['folder' => '', 'new_file' => 'x.png', 'remote' => 'https://example.com/a b.png', 'title' => 'URL', 'type' => 'PHOTO', 'phrase' => '']);
        }

        // The source assistant, with each template
        foreach (SourceEntryService::TEMPLATES as $event => $roles) {
            $count  = count($roles);
            $person = array_fill(0, $count, '');
            $name   = array_fill(0, $count, '');

            $person[0] = '@' . ($event === 'MARR' ? $father : $child)->xref() . '@';
            $person[1] = $event === 'MARR' || $event === 'CENS' ? '@' . $mother->xref() . '@' : '@VOID@';
            $name[1]   = $person[1] === '@VOID@' ? 'Name /Without record/' : '';
            $name[2]   = 'New /Individual/';

            $this->dialog('source assistant, ' . $event, $tree, AddSourceEntry::class, ['fact' => $event], [
                'source'      => '@' . $source->xref() . '@',
                'page'        => 'p. 1',
                'entry_date'  => 'abt 1850',
                'text'        => "Line 1\nLine 2",
                'media'       => '',
                'quality'     => '3',
                'date'        => '@#DJULIAN@ 1 JAN 1700',
                'place'       => 'Place',
                'role'        => $roles,
                'role_phrase' => array_fill(0, $count, ''),
                'person'      => $person,
                'name'        => $name,
                'sex'         => array_fill(0, $count, ''),
                'url'         => '',
            ]);
        }

        // An associate without a record becomes an individual
        $individual = Registry::individualFactory()->make($child->xref(), $tree);
        $fact       = $individual?->facts(['BAPM'])->first();

        if ($fact !== null) {
            $this->dialog('individual from a phrase', $tree, CreateIndividualFromPhrase::class, ['xref' => $child->xref(), 'fact_id' => $fact->id(), 'index' => '0'], []);
        }

        // The census assistant
        $this->dialogs++;

        try {
            $request = $this->request($tree, [], [
                'ca_census'      => CensusOfEngland1881::class,
                'ca_title'       => 'Census 1881',
                'ca_place'       => 'London',
                'ca_citation'    => 'RG11/1',
                'ca_notes'       => 'Notes',
                'ca_individuals' => ['xref' => [$father->xref(), $mother->xref()]] + array_fill(0, count((new CensusOfEngland1881())->columns()), ['', 'Value']),
            ]);
            $gedcom  = (new CensusAssistantModule())->updateCensusAssistant($request, $father, '', "1 CENS\n2 DATE 1881\n2 PLAC London", false);
            $father->createFact($gedcom, true);
        } catch (Throwable $ex) {
            $this->count('[exception] census assistant: ' . $ex->getMessage(), 'census assistant');
        }

        // The dialogs to create records
        foreach ([
            'source'     => static fn () => Registry::container()->get(CreateSource::class)->post($tree, 'Title', 'Abbr', 'Author', 'Publication', '', '', "Text\nText", 'confidential'),
            'repository' => static fn () => Registry::container()->get(CreateRepository::class)->post($tree, 'Name', "Street\nTown", 'https://example.com', 'locked'),
            'submitter'  => static fn () => Registry::container()->get(CreateSubmitter::class)->post($tree, 'Name', "Street\nTown", 'test@example.com', '123', 'privacy'),
            'note'       => static fn () => Registry::container()->get(CreateNote::class)->post($tree, "Line 1\nLine 2", 'none'),
            'location'   => fn () => Registry::container()->get(CreateLocation::class)->post($this->request($tree, [], ['location_name' => 'Place']), $tree),
        ] as $label => $create) {
            $this->dialogs++;

            try {
                $create();
            } catch (Throwable $ex) {
                $this->count('[exception] create ' . $label . ': ' . $ex->getMessage(), 'create ' . $label);
            }
        }

        // What the converter changed
        $messages = DB::table('log')
            ->where('log_id', '>', $start)
            ->where('log_message', 'LIKE', 'GEDCOM 5.5.1 converted%')
            ->pluck('log_message');

        foreach ($messages as $message) {
            $lines     = explode("\n", (string) $message);
            $signature = [];

            foreach (array_slice($lines, 1) as $line) {
                if (preg_match('/^([+-]) (\d+ (?:@[^@]+@ )?\w+)/', $line, $match) === 1) {
                    $signature[] = $match[1] . preg_replace('/@[^@]+@ /', '', $match[2]);
                }
            }

            $this->count('[converter] ' . preg_replace('/^.*: 0 (@[^@]+@ )?/', '', $lines[0]) . ': ' . implode(' ', $signature), implode(' | ', $lines));
        }
    }

    /**
     * @param array<string,string>              $attributes
     * @param array<string,string|list<string>> $params
     */
    private function dialog(string $label, Tree $tree, string $controller, array $attributes, array $params): void
    {
        $this->dialogs++;

        try {
            Registry::container()->get($controller)->post($this->request($tree, $attributes, $params), $tree);
        } catch (Throwable $ex) {
            $this->count('[exception] ' . $label . ': ' . $ex->getMessage(), $label);
        }
    }

    /**
     * @param array<string,string> $attributes
     * @param array<string,mixed>  $params
     */
    private function request(Tree $tree, array $attributes, array $params): ServerRequestInterface
    {
        $request = Registry::container()->get(ServerRequestFactoryInterface::class)
            ->createServerRequest('POST', 'http://localhost:8080/index.php')
            ->withParsedBody($params)
            ->withAttribute('base_url', 'http://localhost:8080')
            ->withAttribute('client-ip', '127.0.0.1')
            ->withAttribute('user', Auth::user())
            ->withAttribute('route', new Route(url: '/', controller: self::class, middleware: []))
            ->withAttribute('tree', $tree);

        foreach ($attributes as $key => $value) {
            $request = $request->withAttribute($key, $value);
        }

        Registry::container()->set(ServerRequestInterface::class, $request);

        return $request;
    }

    private function count(string $kind, string $example): void
    {
        $this->gaps[$kind] ??= ['count' => 0, 'example' => $example];
        $this->gaps[$kind]['count']++;
    }
}
