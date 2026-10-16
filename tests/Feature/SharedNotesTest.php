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
use Fisharebest\Webtrees\Gedcom7\Gedcom551Downgrade;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\LinkedRecordService;
use Fisharebest\Webtrees\Services\SearchService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\SharedNote;
use Fisharebest\Webtrees\Tests\TestCase;
use Fisharebest\Webtrees\Tree;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SharedNote::class)]
class SharedNotesTest extends TestCase
{
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

        foreach (["0 HEAD\n1 GEDC\n2 VERS 7.0", "0 @I1@ INDI\n1 NAME John /Smith/\n1 SNOTE @N1@\n1 BIRT\n2 SNOTE @N1@", "0 @N1@ SNOTE A shared note\n1 MIME text/plain", '0 TRLR'] as $record) {
            $gedcom_import_service->importRecord($record, $this->tree, false);
        }
    }

    public function testSharedNotesAreRecords(): void
    {
        self::assertSame('SNOTE', DB::table('other')->where('o_file', '=', $this->tree->id())->where('o_id', '=', 'N1')->value('o_type'));
        self::assertInstanceOf(SharedNote::class, Registry::gedcomRecordFactory()->make('N1', $this->tree));
        self::assertInstanceOf(SharedNote::class, Registry::noteFactory()->make('N1', $this->tree));
        self::assertSame('A shared note', Registry::noteFactory()->make('N1', $this->tree)->getNote());
    }

    public function testSharedNotesAreLinked(): void
    {
        $note = Registry::noteFactory()->make('N1', $this->tree);
        self::assertInstanceOf(SharedNote::class, $note);

        $linked = Registry::container()->get(LinkedRecordService::class)->linkedIndividuals($note);
        self::assertSame(['I1'], $linked->map(static fn ($individual): string => $individual->xref())->all());
    }

    public function testSearch(): void
    {
        $notes = Registry::container()->get(SearchService::class)->searchNotes([$this->tree], ['shared']);

        self::assertSame(['N1'], $notes->map(static fn ($note): string => $note->xref())->all());
    }

    public function testGedcom551(): void
    {
        $downgrade = new Gedcom551Downgrade();

        self::assertSame("0 @N1@ NOTE A shared note\n1 _MIME text/plain", $downgrade->downgrade("0 @N1@ SNOTE A shared note\n1 MIME text/plain"));
        self::assertSame("0 @I1@ INDI\n1 NAME John /Smith/\n1 NOTE @N1@\n1 BIRT\n2 NOTE @N1@", $downgrade->downgrade("0 @I1@ INDI\n1 NAME John /Smith/\n1 SNOTE @N1@\n1 BIRT\n2 SNOTE @N1@"));
    }
}
