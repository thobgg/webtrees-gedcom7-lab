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

use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Gedcom7\Schema\Migration11;
use Fisharebest\Webtrees\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Migration11::class)]
class Migration11Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::createDatabase();
    }

    public function testMediaTypes(): void
    {
        $media_type = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

        DB::table('media_file')->insert([
            'm_id'                 => 'M1',
            'm_file'               => -1,
            'multimedia_file_refn' => 'letter.docx',
            'multimedia_format'    => $media_type,
            'source_media_type'    => 'DOCUMENT',
            'descriptive_title'    => '',
        ]);

        self::assertSame($media_type, DB::table('media_file')->where('m_id', '=', 'M1')->value('multimedia_format'));
    }
}
