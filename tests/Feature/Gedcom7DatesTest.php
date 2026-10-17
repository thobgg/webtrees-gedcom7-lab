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

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Clock\SystemClock;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Date;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Enums\Role;
use Fisharebest\Webtrees\Gedcom7\Gedcom551Downgrade;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\CalendarService;
use Fisharebest\Webtrees\Services\GedcomEditService;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Tests\TestCase;
use Fisharebest\Webtrees\Tree;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Date::class)]
class Gedcom7DatesTest extends TestCase
{
    private const string INDIVIDUAL = "0 @I1@ INDI\n1 NAME John /Smith/\n1 BIRT\n2 DATE JULIAN 1 JAN 1700\n1 CHR\n2 DATE 1700\n3 PHRASE about Easter\n1 DEAT\n2 DATE\n3 PHRASE in the war\n1 EVEN\n2 TYPE Legend\n2 DATE 44 BCE";

    private Tree $tree;

    protected function tearDown(): void
    {
        Auth::logout();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::createDatabase();

        $gedcom_import_service = new GedcomImportService();
        $this->tree            = (new TreeService($gedcom_import_service))->create('test', 'Test');

        $user = (new UserService(new SystemClock()))->create('test', 'test', 'test', '*');
        $this->tree->setUserPreference($user, UserInterface::PREF_TREE_ROLE, Role::Manager->value);
        Auth::login($user);

        DB::table('individuals')->where('i_file', '=', $this->tree->id())->delete();
        DB::table('other')->where('o_file', '=', $this->tree->id())->delete();
        DB::table('name')->where('n_file', '=', $this->tree->id())->delete();

        foreach (["0 HEAD\n1 GEDC\n2 VERS 7.0", self::INDIVIDUAL, '0 TRLR'] as $record) {
            $gedcom_import_service->importRecord($record, $this->tree, false);
        }
    }

    public function testRecordsHaveGedcom7Dates(): void
    {
        self::assertSame(self::INDIVIDUAL, DB::table('individuals')->where('i_file', '=', $this->tree->id())->where('i_id', '=', 'I1')->value('i_gedcom'));
    }

    public function testDatesTableUsesGedcom7Calendars(): void
    {
        $rows = DB::table('dates')
            ->where('d_file', '=', $this->tree->id())
            ->where('d_gid', '=', 'I1')
            ->orderBy('d_fact')
            ->get(['d_fact', 'd_type', 'd_year', 'd_julianday1'])
            ->map(static fn (object $row): array => [$row->d_fact, $row->d_type, (int) $row->d_year, (int) $row->d_julianday1])
            ->all();

        self::assertSame([
            ['BIRT', 'JULIAN', 1700, (new Date('@#DJULIAN@ 1 JAN 1700'))->minimumJulianDay()],
            ['CHR', 'GREGORIAN', 1700, (new Date('1700'))->minimumJulianDay()],
            ['EVEN', 'JULIAN', -44, (new Date('44 B.C.'))->minimumJulianDay()],
        ], $rows);
    }

    public function testDates(): void
    {
        $individual = Registry::individualFactory()->make('I1', $this->tree);
        self::assertInstanceOf(Individual::class, $individual);

        // As the GEDCOM 5.5.1 dates, but the phrase comes first
        self::assertSame((new Date('@#DJULIAN@ 1 JAN 1700'))->display(), $individual->facts(['BIRT'])->first()->date()->display());
        self::assertSame('about Easter (' . (new Date('1700'))->display() . ')', $individual->facts(['CHR'])->first()->date()->display());
        self::assertSame('in the war', $individual->facts(['DEAT'])->first()->date()->display());
        self::assertSame((new Date('44 B.C.'))->display(), $individual->facts(['EVEN'])->first()->date()->display());
        self::assertSame(-44, $individual->facts(['EVEN'])->first()->date()->minimumDate()->year());
        self::assertSame(1700, $individual->getBirthDate()->minimumDate()->year());
    }

    public function testAnniversaries(): void
    {
        $jd     = (new Date('@#DJULIAN@ 1 JAN 1800'))->minimumJulianDay();
        $events = (new CalendarService())->getAnniversaryEvents($jd, 'BIRT', $this->tree);

        self::assertCount(1, $events);
    }

    public function testEditWithGedcom551Dates(): void
    {
        (new GedcomImportService())->updateRecord("0 @I1@ INDI\n1 NAME John /Smith/\n1 BIRT\n2 DATE @#DJULIAN@ 1 JAN 1700\n1 CHR\n2 DATE INT 1700 (about Easter)\n1 EVEN\n2 TYPE Legend\n2 DATE 44 B.C.", $this->tree, false);

        self::assertSame(
            "0 @I1@ INDI\n1 NAME John /Smith/\n1 BIRT\n2 DATE JULIAN 1 JAN 1700\n1 CHR\n2 DATE 1700\n3 PHRASE about Easter\n1 EVEN\n2 TYPE Legend\n2 DATE 44 BCE",
            DB::table('individuals')->where('i_file', '=', $this->tree->id())->where('i_id', '=', 'I1')->value('i_gedcom')
        );
    }

    public function testEditWithGedcom7Dates(): void
    {
        (new GedcomImportService())->updateRecord(self::INDIVIDUAL, $this->tree, false);

        self::assertSame(self::INDIVIDUAL, DB::table('individuals')->where('i_file', '=', $this->tree->id())->where('i_id', '=', 'I1')->value('i_gedcom'));
    }

    public function testExportGedcom551(): void
    {
        self::assertSame(
            "0 @I1@ INDI\n1 NAME John /Smith/\n1 BIRT\n2 DATE @#DJULIAN@ 1 JAN 1700\n1 CHR\n2 DATE INT 1700 (about Easter)\n1 DEAT\n2 DATE (in the war)\n1 EVEN\n2 TYPE Legend\n2 DATE 44 B.C.",
            (new Gedcom551Downgrade())->downgrade(self::INDIVIDUAL)
        );
    }

    public function testEditFormsWriteGedcom7Dates(): void
    {
        self::createRequest(attributes: ['tree' => $this->tree]);

        $gedcom = Registry::container()->get(GedcomEditService::class)->editLinesToGedcom(
            'INDI',
            ['1', '2', '3', '2', '3', '1', '2', '3', '1', '2', '3'],
            ['BIRT', 'DATE', 'PHRASE', 'AGE', 'PHRASE', 'CHR', 'DATE', 'PHRASE', 'DEAT', 'DATE', 'PHRASE'],
            ['', 'abt @#DJULIAN@ 1700', '', 'child', '', '', 'INT 1700 (about Easter)', '', '', '50 B.C.', ''],
        );

        self::assertSame("\n1 BIRT\n2 DATE ABT JULIAN 1700\n2 AGE < 8y\n3 PHRASE Child\n1 CHR\n2 DATE 1700\n3 PHRASE about Easter\n1 DEAT\n2 DATE 50 BCE", $gedcom);
    }
}
