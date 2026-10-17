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
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomEditService;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Source;
use Fisharebest\Webtrees\Tests\TestCase;
use Fisharebest\Webtrees\Tree;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Fact::class)]
#[CoversClass(GedcomEditService::class)]
#[CoversClass(Tree::class)]
class Gedcom7ChangeAndCreationTest extends TestCase
{
    private const string SOURCE = "0 @S1@ SOUR\n1 TITL Register\n1 CREA\n2 DATE 1 JAN 2020\n1 CHAN\n2 DATE 2 JAN 2020\n3 TIME 12:00";

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

        $gedcom_import_service = new GedcomImportService();
        $this->tree            = (new TreeService($gedcom_import_service))->create('test', 'Test');

        $user = (new UserService(new SystemClock()))->create('test', 'test', 'test', '*');
        $this->tree->setUserPreference($user, UserInterface::PREF_TREE_ROLE, Role::Manager->value);
        $user->setPreference(UserInterface::PREF_AUTO_ACCEPT_EDITS, '1');
        Auth::login($user);

        DB::table('sources')->where('s_file', '=', $this->tree->id())->delete();
        DB::table('other')->where('o_file', '=', $this->tree->id())->delete();

        foreach (["0 HEAD\n1 GEDC\n2 VERS 7.0", self::SOURCE, '0 TRLR'] as $record) {
            $gedcom_import_service->importRecord($record, $this->tree, false);
        }
    }

    private function source(): Source
    {
        $source = Registry::sourceFactory()->make('S1', $this->tree);
        self::assertInstanceOf(Source::class, $source);

        return $source;
    }

    public function testNewRecordsHaveACreation(): void
    {
        $individual = $this->tree->createIndividual("0 @@ INDI\n1 NAME John /Smith/");

        self::assertMatchesRegularExpression('/\n1 CREA\n2 DATE \d\d [A-Z]{3} \d{4}\n3 TIME [\d:]+\n1 CHAN\n/', $individual->gedcom());
    }

    public function testExtensionRecordsHaveNoCreation(): void
    {
        $location = $this->tree->createRecord("0 @@ _LOC\n1 NAME Berlin");

        self::assertStringNotContainsString('CREA', $location->gedcom());
    }

    public function testChangeAndCreationAreNotEdited(): void
    {
        $gedcom_edit_service = new GedcomEditService();
        $source              = $this->source();

        self::assertStringNotContainsString('CHAN', $gedcom_edit_service->insertMissingRecordSubtags($source, true));
        self::assertStringNotContainsString('CREA', $gedcom_edit_service->insertMissingRecordSubtags($source, true));
        self::assertSame("\n1 CREA\n2 DATE 1 JAN 2020\n1 CHAN\n2 DATE 2 JAN 2020\n3 TIME 12:00", $gedcom_edit_service->automaticStructures($source));
        self::assertArrayNotHasKey('CHAN', $source->missingFacts());
        self::assertArrayNotHasKey('CREA', $source->missingFacts());

        foreach ($source->facts(['CHAN', 'CREA']) as $fact) {
            self::assertFalse($fact->canEdit());
        }
    }
}
