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
use Fisharebest\Webtrees\Gedcom7\Schema\Migration15;
use Fisharebest\Webtrees\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(Migration15::class)]
class Migration15Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::createDatabase();
    }

    private function insert(string $gedcom, string $gedcom7): void
    {
        DB::table('individuals')->insert([
            'i_id'      => 'I1',
            'i_file'    => -1,
            'i_rin'     => 'I1',
            'i_sex'     => 'U',
            'i_gedcom'  => $gedcom,
            'i_gedcom7' => $gedcom7,
        ]);
    }

    public function testGedcom7ColumnsAreRemoved(): void
    {
        self::assertFalse(DB::schema()->hasColumn('individuals', 'i_gedcom7'));
        self::assertFalse(DB::schema()->hasColumn('other', 'o_gedcom7'));
    }

    public function testRecordsThatAgree(): void
    {
        (new Migration0())->upgrade();
        $this->insert("0 @I1@ INDI\n1 NOTE @me", "0 @I1@ INDI\n1 NOTE @@me");

        (new Migration15())->upgrade();

        self::assertFalse(DB::schema()->hasColumn('individuals', 'i_gedcom7'));
        self::assertSame("0 @I1@ INDI\n1 NOTE @me", DB::table('individuals')->where('i_id', '=', 'I1')->value('i_gedcom'));
    }

    public function testRecordsThatDisagree(): void
    {
        (new Migration0())->upgrade();
        $this->insert("0 @I1@ INDI\n1 NAME Wrong", "0 @I1@ INDI\n1 NAME Right");

        try {
            (new Migration15())->upgrade();
            self::fail('The migration must not remove the GEDCOM 7.0');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('-1:I1', $exception->getMessage());
        }

        self::assertTrue(DB::schema()->hasColumn('individuals', 'i_gedcom7'));
    }
}
