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
use Fisharebest\Webtrees\Gedcom7\Gedcom7Media;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\MediaFile;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\MediaFileService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Tests\TestCase;
use Fisharebest\Webtrees\Tree;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Gedcom7Media::class)]
#[CoversClass(MediaFile::class)]
class Gedcom7MediaTest extends TestCase
{
    private const string MEDIA = "0 @M1@ OBJE\n1 FILE media/my%20photo.jpg\n2 FORM image/jpeg\n3 MEDI OTHER\n4 PHRASE Certificate\n2 TITL Photo\n1 FILE https://example.com/a%20b.mp3\n2 FORM audio/mpeg\n3 MEDI AUDIO";

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
        DB::table('media')->where('m_file', '=', $this->tree->id())->delete();
        DB::table('media_file')->where('m_file', '=', $this->tree->id())->delete();
        DB::table('other')->where('o_file', '=', $this->tree->id())->delete();
        DB::table('name')->where('n_file', '=', $this->tree->id())->delete();

        foreach (["0 HEAD\n1 GEDC\n2 VERS 7.0", self::MEDIA, '0 TRLR'] as $record) {
            $gedcom_import_service->importRecord($record, $this->tree, false);
        }
    }

    private function media(): Media
    {
        $media = Registry::mediaFactory()->make('M1', $this->tree);
        self::assertInstanceOf(Media::class, $media);

        return $media;
    }

    public function testTheWorkingCopyHasGedcom7MediaFiles(): void
    {
        self::assertSame(self::MEDIA, DB::table('media')->where('m_file', '=', $this->tree->id())->where('m_id', '=', 'M1')->value('m_gedcom'));
    }

    public function testMediaFiles(): void
    {
        [$photo, $audio] = $this->media()->mediaFiles()->all();

        self::assertSame('media/my photo.jpg', $photo->filename());
        self::assertSame('image/jpeg', $photo->format());
        self::assertSame('CERTIFICATE', $photo->type());
        self::assertSame('image/jpeg', $photo->mimeType());
        self::assertFalse($photo->isExternal());

        self::assertSame('https://example.com/a%20b.mp3', $audio->filename());
        self::assertSame('audio/mpeg', $audio->mimeType());
        self::assertSame('AUDIO', $audio->type());
        self::assertTrue($audio->isExternal());
    }

    public function testMediaFileTable(): void
    {
        $rows = DB::table('media_file')
            ->where('m_file', '=', $this->tree->id())
            ->orderBy('id')
            ->get(['multimedia_file_refn', 'multimedia_format', 'source_media_type'])
            ->map(static fn (object $row): array => [$row->multimedia_file_refn, $row->multimedia_format, $row->source_media_type])
            ->all();

        self::assertSame([
            ['media/my photo.jpg', 'image/jpeg', 'CERTIFICATE'],
            ['https://example.com/a%20b.mp3', 'audio/mpeg', 'AUDIO'],
        ], $rows);
    }

    public function testEditMediaFile(): void
    {
        $media  = $this->media();
        $photo  = $media->mediaFiles()->first();
        $gedcom = Registry::container()->get(MediaFileService::class)->createMediaFileGedcom('media/new photo.png', 'PAINTING', 'Painting', '');

        $media->updateFact($photo->factId(), $gedcom, false);

        self::assertStringStartsWith(
            "0 @M1@ OBJE\n1 FILE media/new%20photo.png\n2 FORM image/png\n3 MEDI OTHER\n4 PHRASE Painting\n2 TITL Painting\n1 FILE https://example.com/a%20b.mp3",
            DB::table('change')->where('gedcom_id', '=', $this->tree->id())->where('xref', '=', 'M1')->value('new_gedcom')
        );
    }

    public function testExportGedcom551(): void
    {
        self::assertSame(
            "0 @M1@ OBJE\n1 FILE media/my photo.jpg\n2 FORM jpg\n3 TYPE CERTIFICATE\n2 TITL Photo\n1 FILE https://example.com/a%20b.mp3\n2 FORM mp3\n3 TYPE AUDIO",
            (new Gedcom551Downgrade())->downgrade(self::MEDIA)
        );
    }
}
