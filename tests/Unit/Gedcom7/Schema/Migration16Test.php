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
use Fisharebest\Webtrees\Gedcom7\Schema\Migration16;
use Fisharebest\Webtrees\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Migration16::class)]
class Migration16Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::createDatabase();
    }

    public function testSettings(): void
    {
        DB::table('gedcom')->insert(['gedcom_id' => 1, 'gedcom_name' => 'test', 'sort_order' => 0]);
        DB::table('gedcom_setting')->insert([
            ['gedcom_id' => 1, 'setting_name' => 'INDI_FACTS_QUICK', 'setting_value' => 'BIRT,AFN,_MILI,DEAT'],
            ['gedcom_id' => 1, 'setting_name' => 'FAM_FACTS_QUICK', 'setting_value' => 'MARR,RIN'],
        ]);
        DB::table('site_setting')->whereIn('setting_name', ['HIDE_NAME_ROMN', 'HIDE_AFN', 'CUSTOM_FAMILY_TAGS'])->delete();
        DB::table('site_setting')->insert([
            ['setting_name' => 'HIDE_NAME_ROMN', 'setting_value' => '0'],
            ['setting_name' => 'HIDE_AFN', 'setting_value' => '1'],
            ['setting_name' => 'CUSTOM_FAMILY_TAGS', 'setting_value' => 'FACT,_COML'],
        ]);

        (new Migration16())->upgrade();

        $tree = DB::table('gedcom_setting')->where('gedcom_id', '=', 1)->pluck('setting_value', 'setting_name')->all();
        $site = DB::table('site_setting')->pluck('setting_value', 'setting_name')->all();

        self::assertSame('BIRT,_MILI,DEAT', $tree['INDI_FACTS_QUICK']);
        self::assertSame('MARR', $tree['FAM_FACTS_QUICK']);
        self::assertSame('0', $site['HIDE_NAME_TRAN']);
        self::assertArrayNotHasKey('HIDE_NAME_ROMN', $site);
        self::assertArrayNotHasKey('HIDE_AFN', $site);
        self::assertSame('_COML', $site['CUSTOM_FAMILY_TAGS']);
    }
}
