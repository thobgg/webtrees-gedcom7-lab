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

use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Converter;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Storage;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Record;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Tests\TestCase;
use Fisharebest\Webtrees\Tree;
use PHPUnit\Framework\Attributes\CoversClass;

use function array_filter;
use function file_get_contents;
use function preg_match;
use function preg_split;
use function str_starts_with;
use function trim;

#[CoversClass(Gedcom7Storage::class)]
#[CoversClass(GedcomImportService::class)]
class ImportGedcom7Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::createDatabase();
    }

    /**
     * Import records, as GedcomLoad does.
     *
     * @param list<string> $records
     */
    private function import(array $records): Tree
    {
        $gedcom_import_service = new GedcomImportService();
        $tree                  = (new TreeService($gedcom_import_service))->create('test', 'Test');

        DB::table('individuals')->where('i_file', '=', $tree->id())->delete();
        DB::table('other')->where('o_file', '=', $tree->id())->delete();
        DB::table('name')->where('n_file', '=', $tree->id())->delete();

        foreach ($records as $record) {
            $gedcom_import_service->importRecord($record, $tree, false);
        }

        return $tree;
    }

    /**
     * The GEDCOM 7.0 of a record, with its "@@" escapes.
     */
    private static function gedcom7(Tree $tree, string $table, string $prefix, string $xref): string
    {
        $gedcom = DB::table($table)
            ->where($prefix . '_file', '=', $tree->id())
            ->where($prefix . '_id', '=', $xref)
            ->value($prefix . '_gedcom');

        return (new Gedcom7Converter())->convertRecord((string) $gedcom);
    }

    public function testHeader(): void
    {
        self::assertTrue(Gedcom7Storage::isGedcom7Header("0 HEAD\n1 GEDC\n2 VERS 7.0"));
        self::assertTrue(Gedcom7Storage::isGedcom7Header("0 HEAD\n1 SOUR X\n2 VERS 1.0\n1 GEDC\n2 VERS 7.0.14"));
        self::assertFalse(Gedcom7Storage::isGedcom7Header("0 HEAD\n1 SOUR X\n2 VERS 7.0\n1 GEDC\n2 VERS 5.5.1"));
        self::assertFalse(Gedcom7Storage::isGedcom7Header("0 HEAD\n1 GEDC\n2 VERS 5.5.1\n2 FORM LINEAGE-LINKED"));
    }

    public function testImportDemoTree(): void
    {
        $text    = (string) file_get_contents(__DIR__ . '/../data/falkenrath-gedcom7.ged');
        $records = preg_split('/\n(?=0 )/', trim($text));
        $tree    = $this->import($records);

        self::assertTrue(Gedcom7Storage::isGedcom7Tree($tree));
        self::assertSame(492, DB::table('individuals')->where('i_file', '=', $tree->id())->count());
        self::assertSame(154, DB::table('families')->where('f_file', '=', $tree->id())->count());

        $gedcom7_record = new Gedcom7Record();
        $tables       = ['INDI' => ['individuals', 'i'], 'FAM' => ['families', 'f'], 'SOUR' => ['sources', 's'], 'OBJE' => ['media', 'm']];

        foreach ($records as $record) {
            if (preg_match('/^0 @([^@]+)@ (\w+)/', $record, $match) === 1) {
                [$table, $prefix] = $tables[$match[2]] ?? ['other', 'o'];

                // The stored GEDCOM 7.0 is the GEDCOM 7.0 of the file.
                self::assertSame($gedcom7_record->normalize($record), self::gedcom7($tree, $table, $prefix, $match[1]), $match[1]);
            }
        }

        // webtrees stores GEDCOM 7.0
        $working = DB::table('individuals')->where('i_file', '=', $tree->id())->where('i_id', '=', 'I1')->value('i_gedcom');
        self::assertStringContainsString("\n2 ASSO @I10@\n3 ROLE GODP", $working);
        self::assertStringContainsString("\n1 UID 12396EBF1ED85C5C98EDE0E0CA479DAEC772", $working);
        self::assertStringNotContainsString("\n1 _UID ", $working);
        self::assertSame(0, DB::table('link')->where('l_file', '=', $tree->id())->where('l_to', '=', 'VOID')->count());
    }

    public function testImportGedcom7Structures(): void
    {
        $tree = $this->import([
            "0 HEAD\n1 GEDC\n2 VERS 7.0",
            "0 @I1@ INDI\n1 NAME John /Smith/\n1 SEX X\n1 BIRT\n2 DATE JULIAN 1 JAN 1700\n1 SNOTE @N1@\n1 ASSO @VOID@\n2 PHRASE Mr Brown\n2 ROLE GODP\n1 NOTE @@me and a@@b",
            "0 @N1@ SNOTE A shared note",
            "0 INDI\n1 NAME No /Xref/",
            "0 @THEXREFPRODUCTIONDOESNOTHAVEAMAXIMUMLENGTH@ INDI\n1 FAMS @F1@",
            "0 @F1@ FAM\n1 HUSB @THEXREFPRODUCTIONDOESNOTHAVEAMAXIMUMLENGTH@",
            '0 TRLR',
        ]);

        $working = DB::table('individuals')->where('i_file', '=', $tree->id())->where('i_id', '=', 'I1')->value('i_gedcom');

        self::assertSame("0 @I1@ INDI\n1 NAME John /Smith/\n1 SEX X\n1 BIRT\n2 DATE JULIAN 1 JAN 1700\n1 SNOTE @N1@\n1 ASSO @VOID@\n2 PHRASE Mr Brown\n2 ROLE GODP\n1 NOTE @me and a@@b", $working);
        self::assertSame('X', DB::table('individuals')->where('i_file', '=', $tree->id())->where('i_id', '=', 'I1')->value('i_sex'));
        self::assertSame("0 @I1@ INDI\n1 NAME John /Smith/\n1 SEX X\n1 BIRT\n2 DATE JULIAN 1 JAN 1700\n1 SNOTE @N1@\n1 ASSO @VOID@\n2 PHRASE Mr Brown\n2 ROLE GODP\n1 NOTE @@me and a@@b", self::gedcom7($tree, 'individuals', 'i', 'I1'));
        self::assertSame('SNOTE', DB::table('other')->where('o_file', '=', $tree->id())->where('o_id', '=', 'N1')->value('o_type'));
        self::assertSame("0 @N1@ SNOTE A shared note", self::gedcom7($tree, 'other', 'o', 'N1'));

        // A record without an XREF, and an XREF that is too long
        $xrefs = DB::table('individuals')->where('i_file', '=', $tree->id())->pluck('i_id')->all();
        self::assertCount(1, array_filter($xrefs, static fn (string $xref): bool => str_starts_with($xref, '_X7_')));
        self::assertSame("0 @F1@ FAM\n1 HUSB @THEXREFPROD_7563D320@", self::gedcom7($tree, 'families', 'f', 'F1'));
        self::assertSame("0 @THEXREFPROD_7563D320@ INDI\n1 FAMS @F1@", self::gedcom7($tree, 'individuals', 'i', 'THEXREFPROD_7563D320'));
    }

    public function testImportGedcom551(): void
    {
        // However the file is imported, the trailer converts the tree to GEDCOM 7.0
        $tree = $this->import(["0 HEAD\n1 GEDC\n2 VERS 5.5.1", "0 @I1@ INDI\n1 NAME John /Smith/\n1 NOTE @@me\n1 BIRT\n2 DATE @#DJULIAN@ 1700", '0 TRLR']);

        self::assertTrue(Gedcom7Storage::isGedcom7Tree($tree));
        self::assertSame("0 @I1@ INDI\n1 NAME John /Smith/\n1 NOTE @me\n1 BIRT\n2 DATE JULIAN 1700", DB::table('individuals')->where('i_file', '=', $tree->id())->where('i_id', '=', 'I1')->value('i_gedcom'));
    }

    public function testTheGedcom7OfTheFileIsKept(): void
    {
        // reformatRecord() would remove the Y, and the second space in the name.
        $record = "0 @F1@ FAM\n1 MARR Y\n2 DATE 1900\n1 NOTE Two  spaces";
        $tree   = $this->import(["0 HEAD\n1 GEDC\n2 VERS 7.0", $record, '0 TRLR']);

        self::assertSame($record, self::gedcom7($tree, 'families', 'f', 'F1'));
        self::assertTrue(Gedcom7Storage::isGedcom7((string) DB::table('families')->where('f_file', '=', $tree->id())->value('f_gedcom')));
    }

    public function testEditsBecomeGedcom7First(): void
    {
        $tree = $this->import(["0 HEAD\n1 GEDC\n2 VERS 7.0", "0 @I1@ INDI\n1 NAME John /Smith/\n1 SNOTE @N1@", "0 @N1@ SNOTE Note", '0 TRLR']);

        // The existing code writes 5.5.1 forms.
        (new GedcomImportService())->updateRecord("0 @I1@ INDI\n1 NAME John /Smith/\n1 NOTE @N1@\n1 BIRT\n2 DATE abt 1850", $tree, false);

        self::assertSame("0 @I1@ INDI\n1 NAME John /Smith/\n1 SNOTE @N1@\n1 BIRT\n2 DATE ABT 1850", self::gedcom7($tree, 'individuals', 'i', 'I1'));
        self::assertSame("0 @I1@ INDI\n1 NAME John /Smith/\n1 SNOTE @N1@\n1 BIRT\n2 DATE ABT 1850", DB::table('individuals')->where('i_file', '=', $tree->id())->value('i_gedcom'));
        // The index tables are built from the GEDCOM 7.0.
        self::assertSame(1, DB::table('dates')->where('d_file', '=', $tree->id())->where('d_gid', '=', 'I1')->count());
    }

    public function testIsGedcom7(): void
    {
        self::assertTrue(Gedcom7Storage::isGedcom7("0 @I1@ INDI\n1 SNOTE @N1@\n1 NOTE @me"));
        // GEDCOM 5.5.1, e.g. after a merge with a GEDCOM 5.5.1 tree
        self::assertFalse(Gedcom7Storage::isGedcom7("0 @I1@ INDI\n1 NOTE @N1@"));
        self::assertFalse(Gedcom7Storage::isGedcom7("0 @F1@ FAM\n1 MARR\n2 SOUR Inline source"));
    }

    public function testUpdatedRecordsAreStored(): void
    {
        $tree = $this->import(["0 HEAD\n1 GEDC\n2 VERS 7.0", "0 @I1@ INDI\n1 NAME John /Smith/", '0 TRLR']);

        // An accepted edit, written by the existing code with GEDCOM 5.5.1 forms.
        (new GedcomImportService())->updateRecord("0 @I1@ INDI\n1 NAME John /Smith/\n1 DEAT\n2 DATE @#DJULIAN@ 1750\n2 AGE CHILD", $tree, false);

        self::assertSame("0 @I1@ INDI\n1 NAME John /Smith/\n1 DEAT\n2 DATE JULIAN 1750\n2 AGE < 8y\n3 PHRASE Child", self::gedcom7($tree, 'individuals', 'i', 'I1'));
        self::assertSame((new Gedcom7Converter())->convertRecord("0 @I1@ INDI\n1 NAME John /Smith/\n1 DEAT\n2 DATE @#DJULIAN@ 1750\n2 AGE CHILD"), self::gedcom7($tree, 'individuals', 'i', 'I1'));
    }
}
