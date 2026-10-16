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
use Fisharebest\Webtrees\Elements\TranslationLanguage;
use Fisharebest\Webtrees\Enums\Role;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Gedcom7\Gedcom551Downgrade;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Place;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Tests\TestCase;
use Fisharebest\Webtrees\Tree;
use PHPUnit\Framework\Attributes\CoversClass;

use function array_map;

#[CoversClass(TranslationLanguage::class)]
class Gedcom7TranslationsTest extends TestCase
{
    private const string INDIVIDUAL = "0 @I1@ INDI\n1 NAME 山田 /太郎/\n2 TRAN Yamada /Taro/\n3 LANG ja-Latn\n2 TRAN やまだ /たろう/\n3 LANG ja-hrkt\n1 BIRT\n2 PLAC Москва\n3 TRAN Moskva\n4 LANG und-Latn";

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

    private function individual(): Individual
    {
        $individual = Registry::individualFactory()->make('I1', $this->tree);
        self::assertInstanceOf(Individual::class, $individual);

        return $individual;
    }

    public function testTheWorkingCopyHasGedcom7Translations(): void
    {
        self::assertSame(self::INDIVIDUAL, DB::table('individuals')->where('i_file', '=', $this->tree->id())->where('i_id', '=', 'I1')->value('i_gedcom'));
    }

    public function testNames(): void
    {
        $names = DB::table('name')
            ->where('n_file', '=', $this->tree->id())
            ->where('n_id', '=', 'I1')
            ->orderBy('n_num')
            ->pluck('n_type')
            ->all();

        self::assertSame(['NAME', 'TRAN', 'TRAN'], $names);
        self::assertSame('TRAN', $this->individual()->getAllNames()[1]['type']);
    }

    public function testPlaces(): void
    {
        $places = array_map(static fn (Place $place): string => $place->gedcomName(), $this->individual()->getAllBirthPlaces());

        self::assertSame(['Москва', 'Moskva'], $places);
    }

    public function testLanguages(): void
    {
        $element = new TranslationLanguage('');

        self::assertSame('Romanized – romaji', $element->value('ja-Latn', $this->tree));
        self::assertSame('Romanized', $element->value('und-Latn', $this->tree));
        self::assertSame('Phonetic', $element->value('und', $this->tree));
        self::assertSame('<span class="ut">el</span>', $element->value('el', $this->tree));
    }

    public function testEditWithGedcom551Translations(): void
    {
        (new GedcomImportService())->updateRecord("0 @I1@ INDI\n1 NAME 山田 /太郎/\n2 ROMN Yamada /Taro/\n3 TYPE romaji", $this->tree, false);

        self::assertSame("0 @I1@ INDI\n1 NAME 山田 /太郎/\n2 TRAN Yamada /Taro/\n3 LANG ja-Latn", DB::table('individuals')->where('i_file', '=', $this->tree->id())->where('i_id', '=', 'I1')->value('i_gedcom'));
    }

    public function testExportGedcom551(): void
    {
        self::assertSame(
            "0 @I1@ INDI\n1 NAME 山田 /太郎/\n2 ROMN Yamada /Taro/\n3 TYPE ROMAJI\n2 FONE やまだ /たろう/\n3 TYPE KANA\n1 BIRT\n2 PLAC Москва\n3 ROMN Moskva",
            (new Gedcom551Downgrade())->downgrade(self::INDIVIDUAL)
        );
    }
}
