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
use Fisharebest\Webtrees\Elements\ExternalIdentifierType;
use Fisharebest\Webtrees\Elements\FamilySearchFamilyTreeId;
use Fisharebest\Webtrees\Elements\UniqueIdentifier;
use Fisharebest\Webtrees\Enums\Role;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Gedcom7\Gedcom551Downgrade;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Converter;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Identifiers;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Tests\TestCase;
use Fisharebest\Webtrees\Tree;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Gedcom7Identifiers::class)]
class Gedcom7IdentifiersTest extends TestCase
{
    private const string INDIVIDUAL = "0 @I1@ INDI\n1 NAME John /Smith/\n1 EXID 12345\n2 TYPE https://gedcom.io/terms/v7/AFN\n1 EXID KWCB-QZ2\n2 TYPE https://gedcom.io/exid-type/FamilySearch-PersonId\n1 EXID 77\n2 TYPE https://gedcom.io/terms/v7/RIN\n1 UID f096b664-5e40-40e2-bb72-c1664a46fe45";

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

        foreach (["0 HEAD\n1 GEDC\n2 VERS 7.0", self::INDIVIDUAL, '0 TRLR'] as $record) {
            $gedcom_import_service->importRecord($record, $this->tree, false);
        }
    }

    private function gedcom(string $xref): string
    {
        return (string) DB::table('individuals')->where('i_file', '=', $this->tree->id())->where('i_id', '=', $xref)->value('i_gedcom');
    }

    public function testRecordsHaveGedcom7Identifiers(): void
    {
        self::assertSame(self::INDIVIDUAL, $this->gedcom('I1'));
    }

    public function testRecordIdNumber(): void
    {
        self::assertSame('77', DB::table('individuals')->where('i_file', '=', $this->tree->id())->where('i_id', '=', 'I1')->value('i_rin'));
    }

    public function testDisplay(): void
    {
        self::assertSame('<span title="https://gedcom.io/terms/v7/AFN">Ancestral file number</span>', (new ExternalIdentifierType(''))->value(Gedcom7Converter::EXID_AFN, $this->tree));
        self::assertInstanceOf(FamilySearchFamilyTreeId::class, Gedcom7Identifiers::element(Gedcom7Converter::EXID_FAMILYSEARCH));
        self::assertNull(Gedcom7Identifiers::element('https://example.com/'));
    }

    public function testUniqueIdentifiers(): void
    {
        $element = Registry::elementFactory()->make('FAM:UID');

        self::assertInstanceOf(UniqueIdentifier::class, $element);
        self::assertSame('f096b664-5e40-40e2-bb72-c1664a46fe45', $element->canonical(' F096B6645E4040E2BB72C1664A46FE45 '));
        self::assertSame('0A17AD566A3E5D8A8799975FF728DFD89FA1', $element->canonical('0a17ad566a3e5d8a8799975ff728dfd89fa1'));
        self::assertSame('urn:example:42', $element->canonical('urn:example:42'));
    }

    public function testEditWithGedcom551Identifiers(): void
    {
        (new GedcomImportService())->updateRecord("0 @I1@ INDI\n1 NAME John /Smith/\n1 AFN 12345\n1 _UID f096b664-5e40-40e2-bb72-c1664a46fe45", $this->tree, false);

        self::assertSame("0 @I1@ INDI\n1 NAME John /Smith/\n1 EXID 12345\n2 TYPE https://gedcom.io/terms/v7/AFN\n1 UID f096b664-5e40-40e2-bb72-c1664a46fe45", $this->gedcom('I1'));
    }

    public function testGenerateUids(): void
    {
        $this->tree->setPreference('GENERATE_UIDS', '1');
        (new GedcomImportService())->importRecord("0 @I2@ INDI\n1 NAME Mary /Smith/", $this->tree, false);

        self::assertMatchesRegularExpression('/\n1 UID [0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $this->gedcom('I2'));
    }

    public function testExportGedcom551(): void
    {
        self::assertSame(
            "0 @I1@ INDI\n1 NAME John /Smith/\n1 AFN 12345\n1 _FSFTID KWCB-QZ2\n1 RIN 77\n1 _UID f096b664-5e40-40e2-bb72-c1664a46fe45",
            (new Gedcom551Downgrade())->downgrade(self::INDIVIDUAL)
        );
    }
}
