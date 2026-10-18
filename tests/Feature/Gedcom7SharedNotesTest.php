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
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\SharedNote;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\LinkedRecordService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Tests\TestCase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;

use function view;

#[CoversClass(LinkedRecordService::class)]
class Gedcom7SharedNotesTest extends TestCase
{
    protected function tearDown(): void
    {
        Auth::logout();
        parent::tearDown();
    }

    public function testUses(): void
    {
        self::createDatabase();
        (new Gedcom())->registerTags(Registry::elementFactory(), true);

        $import = new GedcomImportService();
        $tree   = (new TreeService($import))->create('test', 'Test');
        $user   = (new UserService(new SystemClock()))->create('test', 'test', 'test', '*');
        $tree->setUserPreference($user, UserInterface::PREF_TREE_ROLE, Role::Manager->value);
        Auth::login($user);

        DB::table('individuals')->where('i_file', '=', $tree->id())->delete();
        DB::table('other')->where('o_file', '=', $tree->id())->delete();

        foreach (["0 HEAD\n1 GEDC\n2 VERS 7.0", '0 @N1@ SNOTE Emigrated to America', "0 @I1@ INDI\n1 NAME John /Smith/\n1 BIRT\n2 DATE 1830\n2 SNOTE @N1@", "0 @I2@ INDI\n1 NAME Mary /Smith/\n1 SNOTE @N1@", '0 TRLR'] as $record) {
            $import->importRecord($record, $tree, false);
        }

        $note = Registry::sharedNoteFactory()->make('N1', $tree);
        self::assertInstanceOf(SharedNote::class, $note);

        $uses = (new LinkedRecordService())->sharedNoteUses($note);

        self::assertCount(2, $uses);
        self::assertSame(['INDI:BIRT'], $uses->first()[1]->map(static fn (Fact $fact): string => $fact->tag())->values()->all());

        $html = view('note-page-details', ['clipboard_facts' => new Collection(), 'record' => $note, 'uses' => $uses]);

        self::assertStringContainsString('Used by', $html);
        self::assertMatchesRegularExpression('/John.*Smith.*— Birth 1830/s', $html);
        self::assertStringContainsString('Mary', $html);
    }
}
