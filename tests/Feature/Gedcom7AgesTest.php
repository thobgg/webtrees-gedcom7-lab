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
use Fisharebest\Webtrees\Elements\AgeAtEvent;
use Fisharebest\Webtrees\Gedcom7\Gedcom551Downgrade;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Tests\TestCase;
use Fisharebest\Webtrees\Tree;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AgeAtEvent::class)]
class Gedcom7AgesTest extends TestCase
{
    private const string INDIVIDUAL = "0 @I1@ INDI\n1 NAME John /Smith/\n1 DEAT\n2 AGE < 8y\n3 PHRASE Child\n1 BURI\n2 AGE\n3 PHRASE about 30\n1 CENS\n2 AGE 30y\n3 PHRASE thirty";

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

    public function testRecordsHaveGedcom7Ages(): void
    {
        self::assertSame(self::INDIVIDUAL, DB::table('individuals')->where('i_file', '=', $this->tree->id())->where('i_id', '=', 'I1')->value('i_gedcom'));
    }

    public function testEditWithGedcom551Ages(): void
    {
        (new GedcomImportService())->updateRecord("0 @I1@ INDI\n1 NAME John /Smith/\n1 DEAT\n2 AGE child", $this->tree, false);

        self::assertSame("0 @I1@ INDI\n1 NAME John /Smith/\n1 DEAT\n2 AGE < 8y\n3 PHRASE Child", DB::table('individuals')->where('i_file', '=', $this->tree->id())->where('i_id', '=', 'I1')->value('i_gedcom'));
    }

    public function testValueWithPhrase(): void
    {
        $element = new AgeAtEvent('');

        self::assertSame('child', $element->valueWithPhrase('< 8y', 'Child', $this->tree));
        self::assertSame('about 30', $element->valueWithPhrase('', 'about 30', $this->tree));
        self::assertSame('30 years (thirty)', $element->valueWithPhrase('30y', 'thirty', $this->tree));
        self::assertSame('30 years', $element->valueWithPhrase('30y', '', $this->tree));
    }

    public function testExportGedcom551(): void
    {
        self::assertSame(
            "0 @I1@ INDI\n1 NAME John /Smith/\n1 DEAT\n2 AGE CHILD\n1 BURI\n2 AGE about 30\n1 CENS\n2 AGE 30y\n3 _PHRASE thirty",
            (new Gedcom551Downgrade())->downgrade(self::INDIVIDUAL)
        );
    }
}
