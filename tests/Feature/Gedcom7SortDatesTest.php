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
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Enums\Role;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Tests\TestCase;
use Fisharebest\Webtrees\Tree;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * GEDCOM 7.0 sort dates (SDATE) in the index of dates.
 */
#[CoversClass(GedcomImportService::class)]
class Gedcom7SortDatesTest extends TestCase
{
    private Tree $tree;

    private GedcomImportService $import_service;

    protected function tearDown(): void
    {
        Auth::logout();

        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        self::createDatabase();
        (new Gedcom())->registerTags(Registry::elementFactory(), true);

        $this->import_service = new GedcomImportService();
        $this->tree           = (new TreeService($this->import_service))->create('test', 'Test');

        $user = (new UserService(new SystemClock()))->create('test', 'test', 'test', '*');
        $this->tree->setUserPreference($user, UserInterface::PREF_TREE_ROLE, Role::Manager->value);
        $user->setPreference(UserInterface::PREF_AUTO_ACCEPT_EDITS, '1');
        Auth::login($user);

        DB::table('other')->where('o_file', '=', $this->tree->id())->delete();
    }

    /**
     * @param list<string> $records
     */
    private function import(array $records): void
    {
        foreach (["0 HEAD\n1 GEDC\n2 VERS 7.0", ...$records, '0 TRLR'] as $record) {
            $this->import_service->importRecord($record, $this->tree, false);
        }
    }

    public function testSortDatesAreIndexed(): void
    {
        $this->import([
            "0 @I1@ INDI\n1 NAME Test /Person/\n1 BIRT\n2 DATE\n3 PHRASE um Ostern\n2 SDATE 1850\n1 BAPM\n2 SDATE 15 APR 1850\n1 DEAT\n2 DATE 1900\n2 SDATE 1901",
        ]);

        $years = static fn (string $fact): array => DB::table('dates')
            ->where('d_gid', '=', 'I1')
            ->where('d_fact', '=', $fact)
            ->orderBy('d_julianday1')
            ->pluck('d_year')
            ->map(static fn (mixed $year): int => (int) $year)
            ->unique()
            ->values()
            ->all();

        // A date that is only a phrase is indexed by its sort date
        self::assertSame([1850], $years('BIRT'));

        // A fact without a date is indexed by its sort date
        self::assertSame([1850], $years('BAPM'));

        // A fact with a date ignores its sort date
        self::assertSame([1900], $years('DEAT'));
    }

    public function testFactsWithValuesAreIndexed(): void
    {
        $this->import([
            "0 @I1@ INDI\n1 NAME Test /Person/\n1 OCCU Schmied\n2 DATE 1880\n1 _TODO Taufe suchen\n2 DATE 1 JAN 1890\n1 EVEN\n2 TYPE Umzug\n2 SDATE 1895",
        ]);

        $years = static fn (string $fact): array => DB::table('dates')
            ->where('d_gid', '=', 'I1')
            ->where('d_fact', '=', $fact)
            ->pluck('d_year')
            ->map(static fn (mixed $year): int => (int) $year)
            ->unique()
            ->values()
            ->all();

        // The value on the first line of the fact does not hide its date
        self::assertSame([1880], $years('OCCU'));
        self::assertSame([1890], $years('_TODO'));
        self::assertSame([1895], $years('EVEN'));
    }
}
