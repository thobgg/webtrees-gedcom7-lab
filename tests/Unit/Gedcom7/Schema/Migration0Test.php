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
use Fisharebest\Webtrees\Gedcom7\Schema\Migration0;
use Fisharebest\Webtrees\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Migration0::class)]
class Migration0Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::createDatabase();
    }

    public function testGedcom7Columns(): void
    {
        self::assertTrue(DB::schema()->hasColumn('individuals', 'i_gedcom7'));
        self::assertTrue(DB::schema()->hasColumn('families', 'f_gedcom7'));
        self::assertTrue(DB::schema()->hasColumn('sources', 's_gedcom7'));
        self::assertTrue(DB::schema()->hasColumn('media', 'm_gedcom7'));
        self::assertTrue(DB::schema()->hasColumn('other', 'o_gedcom7'));
    }

    public function testSexX(): void
    {
        DB::table('individuals')->insert([
            'i_id'      => 'I1',
            'i_file'    => -1,
            'i_rin'     => 'I1',
            'i_sex'     => 'X',
            'i_gedcom'  => "0 @I1@ INDI\n1 SEX X",
            'i_gedcom7' => "0 @I1@ INDI\n1 SEX X",
        ]);

        self::assertSame('X', DB::table('individuals')->where('i_id', '=', 'I1')->value('i_sex'));
    }

    public function testUpgradeIsRepeatable(): void
    {
        (new Migration0())->upgrade();

        self::assertTrue(DB::schema()->hasColumn('individuals', 'i_gedcom7'));
    }
}
