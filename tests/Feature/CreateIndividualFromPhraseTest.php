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
use Fisharebest\Webtrees\Http\Controllers\CreateIndividualFromPhrase;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CreateIndividualFromPhrase::class)]
class CreateIndividualFromPhraseTest extends TestCase
{
    public function testAssociateBecomesAnIndividual(): void
    {
        self::createDatabase();
        (new Gedcom())->registerTags(Registry::elementFactory(), true);

        $gedcom_import_service = new GedcomImportService();
        $tree                  = (new TreeService($gedcom_import_service))->create('test', 'Test');
        $user                  = (new UserService(new SystemClock()))->create('test', 'test', 'test', '*');
        $user->setPreference(UserInterface::PREF_AUTO_ACCEPT_EDITS, '1');
        $tree->setUserPreference($user, UserInterface::PREF_TREE_ROLE, Role::Manager->value);
        Auth::login($user);

        DB::table('individuals')->where('i_file', '=', $tree->id())->delete();
        DB::table('other')->where('o_file', '=', $tree->id())->delete();
        DB::table('name')->where('n_file', '=', $tree->id())->delete();

        $records = [
            "0 HEAD\n1 GEDC\n2 VERS 7.0",
            "0 @I1@ INDI\n1 NAME John /Smith/\n1 BAPM\n2 ASSO @VOID@\n3 PHRASE Mary Brown\n3 ROLE GODP\n2 ASSO @VOID@\n3 PHRASE Peter /Miller/\n3 ROLE CLERGY",
            '0 TRLR',
        ];

        foreach ($records as $record) {
            $gedcom_import_service->importRecord($record, $tree, false);
        }

        $individual = Registry::individualFactory()->make('I1', $tree);
        self::assertInstanceOf(Individual::class, $individual);

        $fact    = $individual->facts(['BAPM'])->first();
        $request = self::createRequest('POST', [], [], [], ['tree' => $tree, 'xref' => 'I1', 'fact_id' => $fact->id(), 'index' => '1']);

        Registry::container()->get(CreateIndividualFromPhrase::class)->post($request, $tree);

        $individual = Registry::individualFactory()->make('I1', $tree);
        self::assertInstanceOf(Individual::class, $individual);
        self::assertMatchesRegularExpression('/\n2 ASSO @VOID@\n3 PHRASE Mary Brown\n3 ROLE GODP\n2 ASSO @(\w+)@\n3 ROLE CLERGY/', $individual->gedcom());

        preg_match('/\n2 ASSO @(\w+)@\n3 ROLE CLERGY/', $individual->gedcom(), $match);
        $clergy = Registry::individualFactory()->make($match[1], $tree);
        self::assertInstanceOf(Individual::class, $clergy);
        self::assertStringStartsWith('0 @' . $clergy->xref() . "@ INDI\n1 NAME Peter /Miller/\n1 SEX U", $clergy->gedcom());

        Auth::logout();
    }
}
