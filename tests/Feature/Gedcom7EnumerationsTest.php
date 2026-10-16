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
use Fisharebest\Webtrees\Enums\AccessLevel;
use Fisharebest\Webtrees\Enums\Role;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Gedcom7\Gedcom551Downgrade;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Enumerations;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomEditService;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Source;
use Fisharebest\Webtrees\Tests\TestCase;
use Fisharebest\Webtrees\Tree;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Gedcom7Enumerations::class)]
class Gedcom7EnumerationsTest extends TestCase
{
    private const string INDIVIDUAL = "0 @I1@ INDI\n1 NAME John /Smith/\n2 TYPE OTHER\n3 PHRASE Estate\n1 FAMC @F1@\n2 PEDI OTHER\n3 PHRASE Rada\n1 BAPL\n2 STAT DNS_CAN\n3 DATE 27 MAR 2022\n1 SOUR @S1@\n2 EVEN CHR\n3 ROLE OTHER\n4 PHRASE Patin";

    private const string FAMILY = "0 @F1@ FAM\n1 CHIL @I1@";

    private const string SOURCE = "0 @S1@ SOUR\n1 TITL Diary\n1 _RESN CONFIDENTIAL";

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
        Auth::login($user);

        DB::table('individuals')->where('i_file', '=', $this->tree->id())->delete();
        DB::table('families')->where('f_file', '=', $this->tree->id())->delete();
        DB::table('sources')->where('s_file', '=', $this->tree->id())->delete();
        DB::table('other')->where('o_file', '=', $this->tree->id())->delete();
        DB::table('name')->where('n_file', '=', $this->tree->id())->delete();

        foreach (["0 HEAD\n1 GEDC\n2 VERS 7.0", self::INDIVIDUAL, self::FAMILY, self::SOURCE, "0 @S2@ SOUR\n1 TITL Letter", '0 TRLR'] as $record) {
            $gedcom_import_service->importRecord($record, $this->tree, false);
        }
    }

    private function individual(): Individual
    {
        $individual = Registry::individualFactory()->make('I1', $this->tree);
        self::assertInstanceOf(Individual::class, $individual);

        return $individual;
    }

    public function testRecordsHaveGedcom7Enumerations(): void
    {
        self::assertSame(self::INDIVIDUAL, DB::table('individuals')->where('i_file', '=', $this->tree->id())->where('i_id', '=', 'I1')->value('i_gedcom'));
    }

    public function testAttributes(): void
    {
        $individual = $this->individual();

        self::assertSame('ESTATE', $individual->facts(['NAME'])->first()->attribute('TYPE'));
        self::assertSame('RADA', $individual->facts(['FAMC'])->first()->attribute('PEDI'));
        self::assertSame('DNS_CAN', $individual->facts(['BAPL'])->first()->attribute('STAT'));

        $family = Registry::familyFactory()->make('F1', $this->tree);
        self::assertInstanceOf(Family::class, $family);
        self::assertSame('Family with rada parents', $individual->getChildFamilyLabel($family));
    }

    public function testEditing(): void
    {
        $fact = $this->individual()->facts(['FAMC'])->first();

        self::assertStringContainsString("\n2 PEDI RADA", (new GedcomEditService())->insertMissingFactSubtags($fact, false));
    }

    public function testEditWithGedcom551Values(): void
    {
        (new GedcomImportService())->updateRecord("0 @I1@ INDI\n1 NAME John /Smith/\n2 TYPE estate\n1 FAMC @F1@\n2 PEDI rada", $this->tree, false);

        self::assertSame("0 @I1@ INDI\n1 NAME John /Smith/\n2 TYPE OTHER\n3 PHRASE Estate\n1 FAMC @F1@\n2 PEDI OTHER\n3 PHRASE Rada", DB::table('individuals')->where('i_file', '=', $this->tree->id())->where('i_id', '=', 'I1')->value('i_gedcom'));
    }

    public function testRestrictionOfSource(): void
    {
        $source = Registry::sourceFactory()->make('S1', $this->tree);
        self::assertInstanceOf(Source::class, $source);

        self::assertTrue(Registry::sourceFactory()->make('S2', $this->tree)?->canShow(AccessLevel::Member));
        self::assertFalse($source->canShow(AccessLevel::Member));
        self::assertTrue($source->canShow(AccessLevel::Manager));
    }

    public function testLanguage(): void
    {
        $element = Registry::elementFactory()->make('SUBM:LANG');

        self::assertSame('Deutsch', $element->value('de', $this->tree));
        self::assertSame('de', $element->canonical('German'));
    }

    public function testExportGedcom551(): void
    {
        self::assertSame(
            "0 @I1@ INDI\n1 NAME John /Smith/\n2 TYPE ESTATE\n1 FAMC @F1@\n2 PEDI RADA\n1 BAPL\n2 STAT DNS/CAN\n3 DATE 27 MAR 2022\n1 SOUR @S1@\n2 EVEN CHR\n3 ROLE (Patin)",
            (new Gedcom551Downgrade())->downgrade(self::INDIVIDUAL)
        );
    }
}
