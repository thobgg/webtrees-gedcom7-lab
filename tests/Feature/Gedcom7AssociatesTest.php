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
use Fisharebest\Webtrees\Gedcom7\Gedcom551Downgrade;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Associates;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Module\RelationshipsChartModule;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomEditService;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\IndividualFactsService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Tests\TestCase;
use Fisharebest\Webtrees\Tree;
use PHPUnit\Framework\Attributes\CoversClass;

use function strip_tags;
use function view;

#[CoversClass(Gedcom7Associates::class)]
class Gedcom7AssociatesTest extends TestCase
{
    private const string INDIVIDUAL = "0 @I1@ INDI\n1 NAME John /Smith/\n1 BAPM\n2 DATE 1 JUN 1900\n2 ASSO @I2@\n3 ROLE GODP\n4 PHRASE Godmother\n2 ASSO @VOID@\n3 PHRASE Martin Hoppe\n3 ROLE WITN\n1 ASSO @I3@\n2 ROLE OTHER\n3 PHRASE Servant";

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
        DB::table('other')->where('o_file', '=', $this->tree->id())->delete();
        DB::table('name')->where('n_file', '=', $this->tree->id())->delete();

        $records = [
            "0 HEAD\n1 GEDC\n2 VERS 7.0",
            self::INDIVIDUAL,
            "0 @I2@ INDI\n1 NAME Mary /Smith/\n1 SEX F",
            "0 @I3@ INDI\n1 NAME Tom /Brown/",
            '0 TRLR',
        ];

        foreach ($records as $record) {
            $gedcom_import_service->importRecord($record, $this->tree, false);
        }
    }

    private function gedcom(): string
    {
        return DB::table('individuals')->where('i_file', '=', $this->tree->id())->where('i_id', '=', 'I1')->value('i_gedcom');
    }

    public function testRecordsHaveRoles(): void
    {
        self::assertSame(self::INDIVIDUAL, $this->gedcom());
    }

    public function testRelationships(): void
    {
        self::assertSame('godparent', Gedcom7Associates::relationship('GODP', null));
        self::assertSame('godmother', Gedcom7Associates::relationship('GODP', 'Godmother'));
        self::assertSame('godfather', Gedcom7Associates::relationship('GODP', 'godfather'));
        self::assertSame('servant', Gedcom7Associates::relationship('OTHER', 'Servant'));
        self::assertSame('Pate', Gedcom7Associates::relationship('OTHER', 'Pate'));
        self::assertSame('Father', Gedcom7Associates::relationship('FATH', null));
        self::assertNull(Gedcom7Associates::relationship('GODP', 'Patenonkel'));
        self::assertNull(Gedcom7Associates::relationship('OTHER', 'Godparent'));
    }

    public function testAssociatesAreLinked(): void
    {
        $links = DB::table('link')
            ->where('l_file', '=', $this->tree->id())
            ->where('l_from', '=', 'I1')
            ->orderBy('l_to')
            ->pluck('l_type', 'l_to')
            ->all();

        self::assertSame(['I2' => 'ASSO', 'I3' => 'ASSO'], $links);

        $mary = Registry::individualFactory()->make('I2', $this->tree);
        self::assertInstanceOf(Individual::class, $mary);

        $facts = Registry::container()->get(IndividualFactsService::class)->associateFacts($mary);
        self::assertSame(['INDI:BAPM'], $facts->map(static fn ($fact): string => $fact->tag())->all());
    }

    public function testDisplay(): void
    {
        $john = Registry::individualFactory()->make('I1', $this->tree);
        self::assertInstanceOf(Individual::class, $john);

        // The links to the relationship chart need its route
        Registry::container()->get(RelationshipsChartModule::class)->boot();

        $html = strip_tags(view('fact-association-structure', ['fact' => $john->facts(['BAPM'])->first()]));

        self::assertStringContainsString('Godmother', $html);
        self::assertStringContainsString('Mary Smith', $html);
        self::assertStringContainsString('Witness', $html);
        self::assertStringContainsString('Martin Hoppe', $html);
    }

    public function testEditRelationships(): void
    {
        $john = Registry::individualFactory()->make('I1', $this->tree);
        self::assertInstanceOf(Individual::class, $john);

        $gedcom = Registry::container()->get(GedcomEditService::class)->insertMissingFactSubtags($john->facts(['BAPM'])->first(), false);

        self::assertStringContainsString("\n2 ASSO @I2@\n3 PHRASE\n3 ROLE GODP\n4 PHRASE Godmother", $gedcom);
        self::assertStringContainsString("\n2 ASSO @VOID@\n3 PHRASE Martin Hoppe\n3 ROLE WITN\n4 PHRASE", $gedcom);
    }

    public function testFormsSuggestRelationshipsAsPhrases(): void
    {
        $html = Registry::elementFactory()->make('INDI:BAPM:ASSO:ROLE')->edit('id', 'values[]', 'GODP', $this->tree);

        self::assertStringContainsString('<option value="GODP" selected="selected"', $html);
        self::assertStringNotContainsString('godmother', $html);

        $html = Registry::elementFactory()->make('INDI:BAPM:ASSO:ROLE:PHRASE')->edit('id', 'values[]', '', $this->tree);

        self::assertStringContainsString('<option value="Godmother"></option>', $html);
        self::assertStringContainsString('<option value="Servant"></option>', $html);
        self::assertStringNotContainsString('<option value="Witness"', $html);
    }

    public function testPhrasesInTheLanguageOfTheUser(): void
    {
        I18N::init('de');

        self::assertSame('godmother', Gedcom7Associates::relationship('GODP', 'Patin'));
        self::assertSame('employer', Gedcom7Associates::relationship('OTHER', 'Arbeitgeber'));
        self::assertNull(Gedcom7Associates::relationship('WITN', 'Patin'));

        I18N::init('en-US');
    }

    public function testEditAssociateWithoutRecord(): void
    {
        $gedcom = Registry::container()->get(GedcomEditService::class)->editLinesToGedcom(
            'INDI',
            ['1', '2', '2', '3', '3', '2', '3', '3'],
            ['BAPM', 'DATE', 'ASSO', 'PHRASE', 'RELA', 'ASSO', 'PHRASE', 'RELA'],
            ['', '1 JUN 1900', '', 'Martin Hoppe', 'witness', '', '', 'friend'],
        );

        self::assertSame("\n1 BAPM\n2 DATE 1 JUN 1900\n2 ASSO @VOID@\n3 PHRASE Martin Hoppe\n3 RELA witness", $gedcom);
    }

    public function testAcceptEditedRelationships(): void
    {
        (new GedcomImportService())->updateRecord("0 @I1@ INDI\n1 NAME John /Smith/\n1 BAPM\n2 DATE 1 JUN 1900\n2 ASSO @I2@\n3 RELA godmother\n2 ASSO @VOID@\n3 PHRASE Martin Hoppe\n3 RELA witness\n1 ASSO @I3@\n2 RELA servant", $this->tree, false);

        self::assertSame(self::INDIVIDUAL, $this->gedcom());
    }

    public function testExportGedcom551(): void
    {
        self::assertSame(
            "0 @I1@ INDI\n1 NAME John /Smith/\n1 BAPM\n2 DATE 1 JUN 1900\n2 _ASSO @I2@\n3 RELA godmother\n2 _WITN Martin Hoppe\n1 ASSO @I3@\n2 RELA servant",
            (new Gedcom551Downgrade())->downgrade(self::INDIVIDUAL)
        );
    }
}
