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

namespace Fisharebest\Webtrees\Tests\Unit\Gedcom7\Schema;

use Fisharebest\Webtrees\Clock\SystemClock;
use Fisharebest\Webtrees\Contracts\FilesystemFactoryInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Storage;
use Fisharebest\Webtrees\Gedcom7\Gedcom7TreeConverter;
use Fisharebest\Webtrees\Gedcom7\Schema\Migration17;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Tests\TestCase;
use Fisharebest\Webtrees\Tree;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\StorageAttributes;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Migration17::class)]
#[CoversClass(Gedcom7TreeConverter::class)]
class Migration17Test extends TestCase
{
    private Filesystem $data;

    protected function setUp(): void
    {
        parent::setUp();
        self::createDatabase();
        (new Gedcom())->registerTags(Registry::elementFactory(), true);

        $this->data = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem = self::createStub(FilesystemFactoryInterface::class);
        $filesystem->method('data')->willReturn($this->data);
        Registry::filesystem($filesystem);
    }

    private function gedcom551Tree(string $name): Tree
    {
        $gedcom_import_service = new GedcomImportService();
        $tree                  = (new TreeService($gedcom_import_service))->create($name, $name);

        DB::table('individuals')->where('i_file', '=', $tree->id())->delete();
        DB::table('other')->where('o_file', '=', $tree->id())->delete();
        DB::table('name')->where('n_file', '=', $tree->id())->delete();

        foreach (["0 HEAD\n1 GEDC\n2 VERS 5.5.1", "0 @I1@ INDI\n1 NAME John /Smith/\n1 BIRT\n2 DATE @#DJULIAN@ 1700", '0 TRLR'] as $record) {
            $gedcom_import_service->importRecord($record, $tree, false);
        }

        return $tree;
    }

    public function testConvertWithACopy(): void
    {
        $tree = $this->gedcom551Tree('test');

        self::assertFalse(Gedcom7Storage::isGedcom7Tree($tree));

        (new Migration17())->upgrade();

        $tree = Tree::fromDB(DB::table('gedcom')->where('gedcom_id', '=', $tree->id())->first());

        self::assertTrue(Gedcom7Storage::isGedcom7Tree($tree));
        self::assertStringContainsString("\n2 DATE JULIAN 1700", (string) DB::table('individuals')->where('i_file', '=', $tree->id())->where('i_id', '=', 'I1')->value('i_gedcom'));

        $files = $this->data->listContents('')->map(static fn (StorageAttributes $file): string => $file->path())->toArray();
        self::assertCount(1, $files);
        self::assertMatchesRegularExpression('/^test-gedcom551-\d{8}-\d{6}\.ged$/', $files[0]);
        self::assertStringContainsString('2 DATE @#DJULIAN@ 1700', $this->data->read($files[0]));
    }

    public function testPendingChanges(): void
    {
        $tree = $this->gedcom551Tree('pending');
        $user = (new UserService(new SystemClock()))->create('test', 'test', 'test', '*');

        DB::table('change')->insert([
            'gedcom_id'  => $tree->id(),
            'xref'       => 'I1',
            'old_gedcom' => '',
            'new_gedcom' => "0 @I1@ INDI\n1 NAME Jane /Smith/",
            'status'     => 'pending',
            'user_id'    => $user->id(),
        ]);

        (new Migration17())->upgrade();

        $tree = Tree::fromDB(DB::table('gedcom')->where('gedcom_id', '=', $tree->id())->first());

        self::assertFalse(Gedcom7Storage::isGedcom7Tree($tree));
        self::assertSame([], $this->data->listContents('')->toArray());
    }
}
