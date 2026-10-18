<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2026 webtrees development team
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Fisharebest\Webtrees\Tests\Feature;

use Fisharebest\Webtrees\Cache;
use Fisharebest\Webtrees\Contracts\CacheFactoryInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Enums\GedcomVersion;
use Fisharebest\Webtrees\Factories\CacheFactory;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Converter;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Storage;
use Fisharebest\Webtrees\Gedcom7\Gedcom7TreeConverter;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomExportService;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Tests\TestCase;
use Fisharebest\Webtrees\Tree;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Cache\Adapter\NullAdapter;

use function fclose;
use function preg_match;
use function preg_replace;
use function preg_split;
use function str_starts_with;
use function stream_get_contents;
use function trim;

#[CoversClass(Gedcom7TreeConverter::class)]
class ConvertTreeGedcom7Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::createDatabase();

        // Records are cached during the import.  Don't use them for the export.
        $cache_factory = self::createStub(CacheFactoryInterface::class);
        $cache_factory->method('array')->willReturn(new Cache(new NullAdapter()));
        Registry::cache($cache_factory);
    }

    /**
     * @return array<string,string> GEDCOM 7.0 records, indexed by XREF
     */
    private static function exportGedcom7(Tree $tree): array
    {
        $resource = (new GedcomExportService(new Psr17Factory(), new Psr17Factory()))->export(tree: $tree, line_endings: 'LF', version: GedcomVersion::V70);
        $gedcom   = (string) stream_get_contents($resource);
        fclose($resource);

        $records = [];

        foreach (preg_split('/\n(?=0 )/', trim((string) preg_replace('/^\xEF\xBB\xBF/', '', $gedcom))) as $record) {
            if (preg_match('/^0 @([^@]+)@/', $record, $match) === 1) {
                $records[$match[1]] = $record;
            }
        }

        return $records;
    }

    public function testConvertDemoTree(): void
    {
        $tree = $this->importTree('demo.ged');

        self::assertFalse(Gedcom7Storage::isGedcom7Tree($tree));
        self::assertContains('f1', DB::table('families')->where('f_file', '=', $tree->id())->pluck('f_id')->all());

        // Settings that refer to records
        DB::table('favorite')->insert(['gedcom_id' => $tree->id(), 'xref' => 'f1', 'favorite_type' => 'FAM']);
        $tree->setPreference('PEDIGREE_ROOT_ID', 'f2');

        $expected = self::exportGedcom7($tree);

        $converter = new Gedcom7TreeConverter(new GedcomExportService(new Psr17Factory(), new Psr17Factory()), new GedcomImportService());
        $renamed   = $converter->convert($tree);

        self::assertSame('F1', $renamed['f1']);
        self::assertTrue(Gedcom7Storage::isGedcom7Tree($tree));
        // MariaDB compares XREFs without regard to case, so compare them here.
        $xrefs = DB::table('families')->where('f_file', '=', $tree->id())->pluck('f_id')->all();
        self::assertContains('F1', $xrefs);
        self::assertNotContains('f1', $xrefs);
        self::assertSame('F1', DB::table('favorite')->where('gedcom_id', '=', $tree->id())->value('xref'));
        self::assertSame('F2', DB::table('gedcom_setting')->where('gedcom_id', '=', $tree->id())->where('setting_name', '=', 'PEDIGREE_ROOT_ID')->value('setting_value'));

        // Every record stores the GEDCOM 7.0 of the export, apart from webtrees' usual tidying.
        $tables = ['INDI' => ['individuals', 'i'], 'FAM' => ['families', 'f'], 'SOUR' => ['sources', 's'], 'OBJE' => ['media', 'm']];

        foreach ($expected as $xref => $record) {
            preg_match('/^0 @[^@]+@ (\w+)/', $record, $match);
            [$table, $prefix] = $tables[$match[1]] ?? ['other', 'o'];

            $row = DB::table($table)->where($prefix . '_file', '=', $tree->id())->where($prefix . '_id', '=', $xref)->first();

            self::assertNotNull($row, $xref);
            self::assertTrue(Gedcom7Storage::isGedcom7($row->{$prefix . '_gedcom'}), $xref);

            $tidy = static fn (string $gedcom): string => (string) preg_replace('/^(1 [A-Z]+) Y$/m', '$1', $gedcom);
            self::assertSame($tidy($record), $tidy((new Gedcom7Converter())->convertRecord($row->{$prefix . '_gedcom'})), $xref);
        }

        // Links are rebuilt with the new XREFs.
        $links = DB::table('link')->where('l_file', '=', $tree->id())->pluck('l_to')->all();
        self::assertNotContains('f1', $links);
        self::assertContains('F1', $links);
    }

    public function testPlacesAfterImportInTheSameRequest(): void
    {
        // Place::id() caches place IDs during the import.
        Registry::cache(new CacheFactory());

        $tree = $this->importTree('demo.ged');

        (new Gedcom7TreeConverter(new GedcomExportService(new Psr17Factory(), new Psr17Factory()), new GedcomImportService()))->convert($tree);

        $places = DB::table('places')->where('p_file', '=', $tree->id())->pluck('p_id')->all();

        self::assertNotSame([], $places);
        self::assertSame(0, DB::table('placelinks')->where('pl_file', '=', $tree->id())->whereNotIn('pl_p_id', $places)->count());
    }

    public function testGedcom7TreeIsBuiltFromItsRecords(): void
    {
        $tree      = $this->importTree('demo.ged');
        $converter = new Gedcom7TreeConverter(new GedcomExportService(new Psr17Factory(), new Psr17Factory()), new GedcomImportService());
        $converter->convert($tree);

        // A record was changed directly, e.g. with SQL, and is no longer GEDCOM 7.0.
        DB::table('individuals')->where('i_file', '=', $tree->id())->where('i_id', '=', 'X1030')->update(['i_gedcom' => "0 @X1030@ INDI\n1 NAME New /Name/\n1 BIRT\n2 DATE abt 1850"]);

        $converter->convert($tree);

        self::assertSame("0 @X1030@ INDI\n1 NAME New /Name/\n1 BIRT\n2 DATE ABT 1850", DB::table('individuals')->where('i_file', '=', $tree->id())->where('i_id', '=', 'X1030')->value('i_gedcom'));
    }

    public function testPendingChangesAreConverted(): void
    {
        $tree = $this->importTree('demo.ged');

        DB::table('change')->insert(['gedcom_id' => $tree->id(), 'xref' => 'X1', 'old_gedcom' => '', 'new_gedcom' => "0 @X1@ NOTE x", 'status' => 'pending', 'user_id' => -1]);

        (new Gedcom7TreeConverter(new GedcomExportService(new Psr17Factory(), new Psr17Factory()), new GedcomImportService()))->convert($tree);

        self::assertSame('0 @X1@ SNOTE x', DB::table('change')->where('gedcom_id', '=', $tree->id())->where('xref', '=', 'X1')->value('new_gedcom'));
    }
}
