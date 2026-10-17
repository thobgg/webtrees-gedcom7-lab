<?php

/**
 * Check that the throwaway webtrees of docker/check-gedcom7.sh stores, shows, edits and exports GEDCOM 7.0.
 * Run by docker/check-gedcom7.sh, after docker/check-gedcom7/setup.sh.
 */

declare(strict_types=1);

namespace Webtrees7Check;

use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Enums\GedcomVersion;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Storage;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomExportService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Webtrees;
use Nyholm\Psr7\Factory\Psr17Factory;

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/Gedcom7Validator.php';
require __DIR__ . '/Report.php';
require __DIR__ . '/Editor.php';

const WORK_DIR = __DIR__ . '/../../build/gedcom7-check/work';

// The 7.0 tags that GEDCOM 5.5.1 does not have
const GEDCOM7_ONLY_TAGS = ['CREA', 'CROP', 'EXID', 'HEIGHT', 'INIL', 'LEFT', 'MIME', 'NO', 'PHRASE', 'SCHMA', 'SDATE', 'SNOTE', 'TAG', 'TOP', 'TRAN', 'UID', 'WIDTH'];

Webtrees::new()->bootstrap();

$config = parse_ini_file(Webtrees::CONFIG_FILE);

DB::connect(
    driver: $config['dbtype'],
    host: $config['dbhost'],
    port: $config['dbport'],
    database: $config['dbname'],
    username: $config['dbuser'],
    password: $config['dbpass'],
    prefix: $config['tblpfx'],
    key: $config['dbkey'],
    certificate: $config['dbcert'],
    ca: $config['dbca'],
    verify_certificate: (bool) $config['dbverify'],
);

I18N::init('en-US');
(new Gedcom())->registerTags(Registry::elementFactory(), false);

$json   = (string) file_get_contents((string) glob(__DIR__ . '/../../build/gedcom7-check/g7validation-*.json')[0]);
$report = new Report();

@mkdir(WORK_DIR, 0777, true);

/**
 * @return array<string,Tree>
 */
function trees(): array
{
    $trees = [];

    foreach (DB::table('gedcom')->where('gedcom_id', '>', 0)->orderBy('gedcom_id')->get() as $row) {
        $tree = Tree::fromDB($row);

        $trees[$tree->name()] = $tree;
    }

    return $trees;
}

/**
 * Every record of a tree, as webtrees stores it, indexed by XREF.
 *
 * @return array<string,string>
 */
function storedRecords(Tree $tree): array
{
    return DB::table('individuals')->where('i_file', '=', $tree->id())->pluck('i_gedcom', 'i_id')
        ->union(DB::table('families')->where('f_file', '=', $tree->id())->pluck('f_gedcom', 'f_id'))
        ->union(DB::table('sources')->where('s_file', '=', $tree->id())->pluck('s_gedcom', 's_id'))
        ->union(DB::table('media')->where('m_file', '=', $tree->id())->pluck('m_gedcom', 'm_id'))
        ->union(DB::table('other')->where('o_file', '=', $tree->id())->pluck('o_gedcom', 'o_id'))
        ->map(static fn (mixed $gedcom): string => (string) $gedcom)
        ->all();
}

/**
 * @param array<string,string> $records
 *
 * @return array<string,string> Record type of each XREF, as "@XREF@" => "INDI"
 */
function recordTypes(array $records): array
{
    $types = [];

    foreach ($records as $record) {
        if (preg_match('/^0 (@[^@]+@) (\w+)/', $record, $match) === 1) {
            $types[$match[1]] = $match[2];
        }
    }

    return $types;
}

function export(Tree $tree, GedcomVersion $version): string
{
    $resource = (new GedcomExportService(new Psr17Factory(), new Psr17Factory()))->export(tree: $tree, line_endings: 'LF', version: $version);
    $gedcom   = (string) stream_get_contents($resource);
    fclose($resource);

    return $gedcom;
}

/**
 * @return list<string>
 */
function fileRecords(string $gedcom): array
{
    $gedcom = (string) preg_replace('/^\xEF\xBB\xBF/', '', $gedcom);

    return preg_split('/\n(?=0 )/', trim(strtr($gedcom, ["\r\n" => "\n", "\r" => "\n"]))) ?: [];
}

/**
 * Import a file into a new tree, with the command line, as an administrator would.
 */
function importFile(string $tree_name, string $gedcom): Tree
{
    $file = WORK_DIR . '/' . $tree_name . '.ged';
    file_put_contents($file, $gedcom);

    foreach ([['tree', $tree_name, '--create', '--title=' . $tree_name], ['tree-import', $tree_name, $file]] as $arguments) {
        exec('php index.php ' . implode(' ', array_map(escapeshellarg(...), $arguments)) . ' 2>&1', $output, $status);

        if ($status !== 0) {
            throw new \RuntimeException(implode("\n", $output));
        }
    }

    return trees()[$tree_name];
}

/**
 * The lines of a file, without those that change with each export.
 *
 * @return list<string>
 */
function comparableLines(string $gedcom): array
{
    $lines = explode("\n", trim(strtr((string) preg_replace('/^\xEF\xBB\xBF/', '', $gedcom), ["\r\n" => "\n"])));

    // HEAD:DATE and HEAD:DATE:TIME, HEAD:FILE
    $head = true;

    return array_values(array_filter($lines, static function (string $line) use (&$head): bool {
        if (str_starts_with($line, '0 ') && $line !== '0 HEAD') {
            $head = false;
        }

        return !$head || preg_match('/^(1 DATE|2 TIME|1 FILE) /', $line) !== 1;
    }));
}

/**
 * The differences between two lists of lines, ignoring their order.
 *
 * @param list<string> $expected
 * @param list<string> $actual
 *
 * @return list<string>
 */
function differences(array $expected, array $actual): array
{
    $counts = [];

    foreach ($expected as $line) {
        $counts[$line] = ($counts[$line] ?? 0) + 1;
    }

    foreach ($actual as $line) {
        $counts[$line] = ($counts[$line] ?? 0) - 1;
    }

    $differences = [];

    foreach ($counts as $line => $count) {
        if ($count > 0) {
            $differences[] = 'missing: ' . $line;
        } elseif ($count < 0) {
            $differences[] = 'added:   ' . $line;
        }
    }

    return $differences;
}

/**
 * Each line of a file with the tags above it, e.g. "INDI:BIRT:DATE 1 JAN 1900", so that the order does not matter.
 * XREFs are kept, as webtrees keeps them.
 *
 * @return list<string>
 */
function structurePaths(string $gedcom, bool $skip_head): array
{
    $paths = [];

    foreach (fileRecords($gedcom) as $record) {
        if ($skip_head && str_starts_with($record, '0 HEAD')) {
            continue;
        }

        $tags = [];
        $xref = '';

        foreach (explode("\n", $record) as $line) {
            if (preg_match('/^(\d+) (?:(@[^@]+@) )?(\w+)(?: (.*))?$/', $line, $match) !== 1) {
                continue;
            }

            $level = (int) $match[1];
            $xref  = $level === 0 ? $match[2] : $xref;

            if ($match[3] === 'CONT') {
                $paths[] = $xref . ' ' . implode(':', $tags) . ':CONT ' . ($match[4] ?? '');
                continue;
            }

            $tags         = array_slice($tags, 0, $level);
            $tags[$level] = $match[3];
            $paths[]      = $xref . ' ' . implode(':', $tags) . ' ' . ($match[4] ?? '');
        }
    }

    return $paths;
}

$trees = trees();

// The trees imported by setup.sh, and the GEDCOM 7.0 test files they came from
$gedcom_io_files = [];

foreach (glob(__DIR__ . '/../../build/gedcom7-check/gedcom.io-*/*.ged') ?: [] as $file) {
    $gedcom_io_files[basename($file, '.ged')] = $file;
}

$report->section('Trees');

$errors = [];

foreach ($trees as $tree) {
    if (!Gedcom7Storage::isGedcom7Tree($tree)) {
        $errors[] = $tree->name() . ': does not store GEDCOM 7.0';
    }
}

$report->check(count($trees) . ' trees store GEDCOM 7.0', $errors);

$report->section('Database');

foreach ($trees as $tree) {
    $records   = storedRecords($tree);
    $validator = new Gedcom7Validator($json, recordTypes($records));
    $errors    = [];

    foreach ($records as $xref => $record) {
        foreach ($validator->validate($record) as $error) {
            $errors[] = $xref . ': ' . $error;
        }
    }

    if (preg_match('/\n1 GEDC\n2 VERS 7\.0(\n|$)/', $records['HEAD'] ?? '') !== 1) {
        $errors[] = 'HEAD: not GEDC:VERS 7.0';
    }

    $pending = DB::table('change')->where('gedcom_id', '=', $tree->id())->where('status', '=', 'pending')->where('new_gedcom', '<>', '')->pluck('new_gedcom', 'xref');

    foreach ($pending as $xref => $record) {
        foreach ($validator->validate((string) $record) as $error) {
            $errors[] = 'pending change ' . $xref . ': ' . $error;
        }
    }

    $report->check($tree->name() . ': ' . count($records) . ' records, ' . count($pending) . ' pending changes', $errors);

    if ($validator->extension_tags !== []) {
        ksort($validator->extension_tags);
        $report->info($tree->name(), 'extension tags: ' . implode(', ', array_map(static fn (string $tag, int $count): string => $tag . ' ' . $count, array_keys($validator->extension_tags), $validator->extension_tags)));
    }
}

// The index tables use the GEDCOM 7.0 values.
$calendars = array_merge(array_keys(json_decode($json, true)['calendar']), ['_HIJRI', '_JALALI']);
$errors    = DB::table('dates')->whereNotIn('d_type', $calendars)->distinct()->pluck('d_type')->map(static fn (mixed $type): string => 'dates.d_type ' . $type)->all();
$report->check('dates.d_type: ' . implode(', ', DB::table('dates')->distinct()->orderBy('d_type')->pluck('d_type')->all()), $errors);

$errors = DB::table('media_file')->distinct()->pluck('multimedia_format')
    ->filter(static fn (mixed $format): bool => $format !== '' && preg_match('~^[a-z0-9!#$&^_.+-]+/[a-z0-9!#$&^_.+-]+$~i', (string) $format) !== 1)
    ->map(static fn (mixed $format): string => 'media_file.multimedia_format ' . $format)
    ->values()
    ->all();
$report->check('media_file.multimedia_format holds media types', $errors);

$report->section('Editor: each form saved without changes');

$editor = new Editor();
$editor->login(Registry::container()->get(UserService::class)->findByUserName('admin'));

$lines = [];

foreach ($trees as $tree) {
    $losses = $editor->tree($tree, array_keys(storedRecords($tree)));

    $report->check($tree->name() . ': ' . ($editor->forms[$tree->name()] ?? 0) . ' forms, the same record', $losses);

    foreach ($losses as $loss) {
        $lines[] = $tree->name() . ': ' . $loss;
    }
}

file_put_contents(WORK_DIR . '/../editor-losses.txt', implode("\n", $lines) . "\n");

// Stage B0: what the converter still changes when a form is saved
$lines = [];

foreach ($editor->gapsByArea() as $area => $gaps) {
    $count   = array_sum(array_column($gaps, 'count'));
    $lines[] = $area . ': ' . $count . ' forms';

    $report->info('gaps ' . $area, $count . ' forms, ' . count($gaps) . ' kinds');

    foreach ($gaps as $signature => $gap) {
        $trees_text = implode(', ', array_map(static fn (string $name, int $n): string => $name . ' ' . $n, array_keys($gap['trees']), $gap['trees']));
        $lines[]    = '  ' . $gap['count'] . '  ' . $signature;
        $lines[]    = '        trees:   ' . $trees_text;
        $lines[]    = '        example: ' . $gap['example'];
    }
}

file_put_contents(WORK_DIR . '/../editor-gaps.txt', implode("\n", $lines) . "\n");
$report->info('gaps', 'details in build/gedcom7-check/editor-gaps.txt and editor-losses.txt');

if (($argv[1] ?? '') === 'editor') {
    exit($report->summary());
}

$report->section('Export as GEDCOM 7.0');

$exports7 = [];

foreach ($trees as $tree) {
    $gedcom = export($tree, GedcomVersion::V70);
    $lines  = fileRecords($gedcom);
    $errors = [];

    $exports7[$tree->name()] = $gedcom;

    if (!str_starts_with($gedcom, "\xEF\xBB\xBF0 HEAD\n")) {
        $errors[] = 'does not start with a byte-order mark and HEAD';
    }

    if (end($lines) !== '0 TRLR') {
        $errors[] = 'does not end with TRLR';
    }

    $validator = new Gedcom7Validator($json, recordTypes($lines), true);

    foreach ($lines as $record) {
        foreach ($validator->validate($record) as $error) {
            $errors[] = strtok($record, "\n") . ': ' . $error;
        }
    }

    preg_match_all('/^2 TAG (_\w+) /m', $lines[0], $match);
    $undocumented = array_diff(array_keys($validator->extension_tags), $match[1]);

    $report->check($tree->name() . ': ' . count($lines) . ' records', $errors);

    if ($undocumented !== []) {
        $report->info($tree->name(), 'not in HEAD:SCHMA: ' . implode(', ', $undocumented));
    }
}

$report->section('Export, import and export again as GEDCOM 7.0');

foreach ($trees as $tree) {
    $copy   = importFile('copy7-' . $tree->id(), $exports7[$tree->name()]);
    $errors = differences(comparableLines($exports7[$tree->name()]), comparableLines(export($copy, GedcomVersion::V70)));

    $report->check($tree->name() . ': the same GEDCOM 7.0', $errors);
}

$report->section('Export as GEDCOM 5.5.1');

$exports551 = [];

foreach ($trees as $tree) {
    $gedcom = export($tree, GedcomVersion::V551);
    $errors = [];

    $exports551[$tree->name()] = $gedcom;

    if (preg_match('/^0 HEAD\n(?:[1-9].*\n)*1 GEDC\n(?:[2-9].*\n)*2 VERS 5\.5\.1\n/', (string) preg_replace('/^\xEF\xBB\xBF/', '', $gedcom)) !== 1) {
        $errors[] = 'HEAD: not GEDC:VERS 5.5.1';
    }

    foreach (explode("\n", trim($gedcom)) as $number => $line) {
        $line = rtrim($line, "\r");

        if (strlen($line) > 255) {
            $errors[] = 'line ' . ($number + 1) . ': longer than 255 bytes';
        }

        if (preg_match('/^\d+ (?:@[^@]+@ )?(\w+)(?: (.*))?$/', $line, $match) !== 1) {
            $errors[] = 'line ' . ($number + 1) . ': not a GEDCOM line';
            continue;
        }

        if (in_array($match[1], GEDCOM7_ONLY_TAGS, true)) {
            $errors[] = 'line ' . ($number + 1) . ': GEDCOM 7.0 tag: ' . $line;
        }

        if ($match[1] === 'DATE' && preg_match('/\b(?:GREGORIAN|JULIAN|HEBREW|FRENCH_R|_HIJRI|_JALALI|BCE)\b/', $match[2] ?? '') === 1) {
            $errors[] = 'line ' . ($number + 1) . ': GEDCOM 7.0 date: ' . $line;
        }
    }

    $report->check($tree->name(), $errors);
}

$report->section('Export as GEDCOM 5.5.1, import and export as GEDCOM 7.0');

foreach ($trees as $tree) {
    $copy   = importFile('copy551-' . $tree->id(), $exports551[$tree->name()]);
    $errors = differences(structurePaths($exports7[$tree->name()], true), structurePaths(export($copy, GedcomVersion::V70), true));

    $report->check($tree->name() . ': the same GEDCOM 7.0', $errors);
}

$report->section('GEDCOM 7.0 test files of FamilySearch, imported and exported');

foreach ($gedcom_io_files as $name => $file) {
    $errors = differences(structurePaths((string) file_get_contents($file), true), structurePaths($exports7[$name] ?? '', true));

    $report->check($name, $errors);
}

exit($report->summary());
