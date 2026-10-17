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
use Fisharebest\Webtrees\Gedcom7\Gedcom7Storage;
use Fisharebest\Webtrees\Gedcom7\Schema\Migration1;
use Fisharebest\Webtrees\Tests\TestCase;
use Fisharebest\Webtrees\Tree;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Migration1::class)]
class Migration1Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::createDatabase();
    }

    public function testExistingTreesAreNotConverted(): void
    {
        $tree = $this->importTree('demo.ged');

        self::assertFalse(Gedcom7Storage::isGedcom7Tree($tree));

        (new Migration1())->upgrade();

        $tree = DB::table('gedcom')->where('gedcom_id', '=', $tree->id())->get()->map(Tree::fromDB(...))->first();

        // A manager converts it, when it suits them.
        self::assertFalse(Gedcom7Storage::isGedcom7Tree($tree));
    }

    public function testTreesWithPendingChangesAreNotConverted(): void
    {
        $tree = $this->importTree('demo.ged');

        DB::table('change')->insert(['gedcom_id' => $tree->id(), 'xref' => 'X1', 'old_gedcom' => '', 'new_gedcom' => '0 @X1@ NOTE x', 'status' => 'pending', 'user_id' => -1]);

        (new Migration1())->upgrade();

        $tree = DB::table('gedcom')->where('gedcom_id', '=', $tree->id())->get()->map(Tree::fromDB(...))->first();

        self::assertFalse(Gedcom7Storage::isGedcom7Tree($tree));
    }
}
