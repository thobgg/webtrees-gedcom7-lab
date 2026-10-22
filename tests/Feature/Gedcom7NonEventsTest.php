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
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Tests\TestCase;
use Fisharebest\Webtrees\Tree;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * GEDCOM 7.0 non-events, e.g. "1 NO DEAT".
 */
#[CoversClass(Individual::class)]
class Gedcom7NonEventsTest extends TestCase
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

    public function testNoDeathMeansAlive(): void
    {
        $this->import([
            "0 @I1@ INDI\n1 NAME Alive /Person/\n1 BIRT\n2 DATE 1700\n1 NO DEAT",
            "0 @I2@ INDI\n1 NAME Dated /Person/\n1 BIRT\n2 DATE 1700\n1 NO DEAT\n2 DATE TO 1750",
            "0 @I3@ INDI\n1 NAME Old /Person/\n1 BIRT\n2 DATE 1700",
        ]);

        // "NO DEAT" without a date says that the individual did not die, whatever the dates suggest
        self::assertFalse(Registry::individualFactory()->make('I1', $this->tree)?->isDead());

        // "NO DEAT" with a date only says that the individual did not die in that period
        self::assertTrue(Registry::individualFactory()->make('I2', $this->tree)?->isDead());
        self::assertTrue(Registry::individualFactory()->make('I3', $this->tree)?->isDead());
    }
}
