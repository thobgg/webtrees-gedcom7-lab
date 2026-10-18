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

use Fisharebest\Webtrees\Contracts\FilesystemFactoryInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Enums\GedcomVersion;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomExportService;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Tests\TestCase;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\StorageAttributes;
use PHPUnit\Framework\Attributes\CoversClass;
use ZipArchive;

use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[CoversClass(TreeService::class)]
class GedzipTest extends TestCase
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

    public function testExportAndImport(): void
    {
        $import       = new GedcomImportService();
        $tree_service = new TreeService($import);
        $tree         = $tree_service->create('test', 'Test');

        DB::table('individuals')->where('i_file', '=', $tree->id())->delete();
        DB::table('other')->where('o_file', '=', $tree->id())->delete();

        foreach (["0 HEAD\n1 GEDC\n2 VERS 7.0", "0 @I1@ INDI\n1 NAME John /Smith/\n1 OBJE @M1@", "0 @M1@ OBJE\n1 FILE photos/john%20smith.jpg\n2 FORM image/jpeg", '0 TRLR'] as $record) {
            $import->importRecord($record, $tree, false);
        }

        $this->data->write('photos/john smith.jpg', 'JPEG');

        $response = Registry::container()->get(GedcomExportService::class)->downloadResponse($tree, false, 'UTF-8', 'none', 'LF', 'test', 'gedzip', null, GedcomVersion::V70);
        $path     = (string) tempnam(sys_get_temp_dir(), 'gdz');
        file_put_contents($path, (string) $response->getBody());

        // The media file of the new tree is already there, from the export
        $this->data->delete('photos/john smith.jpg');

        $copy  = $tree_service->create('copy', 'Copy');
        $added = $tree_service->importGedzipFile($copy, $path, 'test.gdz');
        unlink($path);

        self::assertSame(1, $added);
        self::assertSame('JPEG', $this->data->read('photos/john smith.jpg'));
        self::assertStringContainsString("1 FILE photos/john%20smith.jpg", DB::table('gedcom_chunk')->where('gedcom_id', '=', $copy->id())->implode('chunk_data'));
    }

    public function testPathsOutsideTheMediaFolder(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'gdz');
        $zip  = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('gedcom.ged', "0 HEAD\n1 GEDC\n2 VERS 7.0\n0 TRLR\n");
        $zip->addFromString('../evil.txt', 'evil');
        $zip->addFromString('ok.txt', 'ok');
        $zip->close();

        $tree_service = new TreeService(new GedcomImportService());
        $added        = $tree_service->importGedzipFile($tree_service->create('test', 'Test'), $path, 'test.gdz');
        unlink($path);

        self::assertSame(1, $added);
        self::assertSame(['ok.txt'], $this->data->listContents('', true)->map(static fn (StorageAttributes $file): string => $file->path())->toArray());
    }
}
