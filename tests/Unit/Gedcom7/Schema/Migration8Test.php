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
use Fisharebest\Webtrees\Gedcom7\Schema\EnumColumn;
use Fisharebest\Webtrees\Gedcom7\Schema\Migration8;
use Fisharebest\Webtrees\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Migration8::class)]
class Migration8Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::createDatabase();
    }

    public function testCalendarNames(): void
    {
        EnumColumn::change('dates', 'd_type', ['@#DJULIAN@', '@#DFRENCH R@', 'GREGORIAN', 'JULIAN', 'HEBREW', 'FRENCH_R', '_HIJRI', '_JALALI', '_ROMAN']);

        $row = ['d_day' => 1, 'd_month' => 'JAN', 'd_mon' => 1, 'd_year' => 1700, 'd_julianday1' => 2341973, 'd_julianday2' => 2341973, 'd_fact' => 'BIRT', 'd_gid' => 'I1', 'd_file' => -1];

        DB::table('dates')->insert(['d_type' => '@#DJULIAN@'] + $row);
        DB::table('dates')->insert(['d_type' => '@#DFRENCH R@', 'd_gid' => 'I2'] + $row);

        (new Migration8())->upgrade();

        self::assertSame('JULIAN', DB::table('dates')->where('d_gid', '=', 'I1')->value('d_type'));
        self::assertSame('FRENCH_R', DB::table('dates')->where('d_gid', '=', 'I2')->value('d_type'));
    }
}
