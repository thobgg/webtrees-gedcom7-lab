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
use Fisharebest\Webtrees\Contracts\FilesystemFactoryInterface;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Enums\Role;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Storage;
use Fisharebest\Webtrees\Http\Controllers\ConvertTreeToGedcom7;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Tests\TestCase;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\StorageAttributes;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ConvertTreeToGedcom7::class)]
class ConvertTreeToGedcom7Test extends TestCase
{
    public function testConvertWithACopy(): void
    {
        self::createDatabase();
        (new Gedcom())->registerTags(Registry::elementFactory(), true);

        $gedcom_import_service = new GedcomImportService();
        $tree                  = (new TreeService($gedcom_import_service))->create('test', 'Test');
        $user                  = (new UserService(new SystemClock()))->create('test', 'test', 'test', '*');
        $tree->setUserPreference($user, UserInterface::PREF_TREE_ROLE, Role::Manager->value);
        Auth::login($user);

        DB::table('individuals')->where('i_file', '=', $tree->id())->delete();
        DB::table('other')->where('o_file', '=', $tree->id())->delete();
        DB::table('name')->where('n_file', '=', $tree->id())->delete();

        foreach (["0 HEAD\n1 GEDC\n2 VERS 5.5.1", "0 @I1@ INDI\n1 NAME John /Smith/\n1 BIRT\n2 DATE @#DJULIAN@ 1700", '0 TRLR'] as $record) {
            $gedcom_import_service->importRecord($record, $tree, false);
        }

        // Trees that store GEDCOM 5.5.1 are read-only.
        self::assertFalse(Gedcom7Storage::isGedcom7Tree($tree));
        self::assertFalse(Auth::isEditor($tree));

        $data       = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem = self::createStub(FilesystemFactoryInterface::class);
        $filesystem->method('data')->willReturn($data);
        Registry::filesystem($filesystem);

        Registry::container()->get(ConvertTreeToGedcom7::class)->post($tree);

        self::assertTrue(Gedcom7Storage::isGedcom7Tree($tree));
        self::assertTrue(Auth::isEditor($tree));
        self::assertStringContainsString("\n2 DATE JULIAN 1700", (string) DB::table('individuals')->where('i_file', '=', $tree->id())->where('i_id', '=', 'I1')->value('i_gedcom'));

        $files = $data->listContents('')->map(static fn (StorageAttributes $file): string => $file->path())->toArray();
        self::assertCount(1, $files);
        self::assertMatchesRegularExpression('/^test-gedcom551-\d{8}-\d{6}\.ged$/', $files[0]);
        self::assertStringContainsString('2 DATE @#DJULIAN@ 1700', $data->read($files[0]));

        Auth::logout();
    }
}
