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
use Fisharebest\Webtrees\Gedcom7\Gedcom7Storage;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Module\RelationshipsChartModule;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Tests\TestCase;
use Fisharebest\Webtrees\Tree;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Gedcom7Storage::class)]
class Gedcom7PendingChangesTest extends TestCase
{
    private const string INDIVIDUAL = "0 @I1@ INDI\n1 NAME John /Smith/\n1 BIRT\n2 DATE 1700";

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
        (new Gedcom())->registerTags(Registry::elementFactory(), true);
        Registry::container()->get(RelationshipsChartModule::class)->boot();

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

    public function testPendingChangesAreGedcom7(): void
    {
        $individual = Registry::individualFactory()->make('I1', $this->tree);
        self::assertInstanceOf(Individual::class, $individual);

        $fact = $individual->facts(['BIRT'])->first();
        $individual->updateFact($fact->id(), "1 BIRT\n2 DATE @#DJULIAN@ 1700\n2 AGE child\n2 _ASSO @I2@\n3 RELA godmother", false);

        self::assertSame(
            "0 @I1@ INDI\n1 NAME John /Smith/\n1 BIRT\n2 DATE JULIAN 1700\n2 AGE < 8y\n3 PHRASE Child\n2 ASSO @I2@\n3 ROLE GODP\n4 PHRASE Godmother",
            DB::table('change')->where('gedcom_id', '=', $this->tree->id())->where('xref', '=', 'I1')->value('new_gedcom')
        );
    }

    public function testNewRecordsAreGedcom7(): void
    {
        $individual = $this->tree->createIndividual("0 @@ INDI\n1 NAME Mary /Smith/\n1 BIRT\n2 DATE @#DJULIAN@ 1700");

        self::assertStringStartsWith(
            '0 @' . $individual->xref() . "@ INDI\n1 NAME Mary /Smith/\n1 BIRT\n2 DATE JULIAN 1700\n1 CHAN",
            DB::table('change')->where('gedcom_id', '=', $this->tree->id())->where('xref', '=', $individual->xref())->value('new_gedcom')
        );
    }

    public function testPendingChangesInOtherTreesAreUnchanged(): void
    {
        $tree       = (new TreeService(new GedcomImportService()))->create('other', 'Other');
        $individual = $tree->createIndividual("0 @@ INDI\n1 BIRT\n2 DATE @#DJULIAN@ 1700");

        self::assertStringStartsWith(
            '0 @' . $individual->xref() . "@ INDI\n1 BIRT\n2 DATE @#DJULIAN@ 1700\n1 CHAN",
            DB::table('change')->where('gedcom_id', '=', $tree->id())->where('xref', '=', $individual->xref())->value('new_gedcom')
        );
    }
}
