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

use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Structure;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Gedcom7Structure::class)]
class Gedcom7StructureTest extends TestCase
{
    public function testStructures(): void
    {
        self::createDatabase();
        (new Gedcom())->registerTags(Registry::elementFactory(), true);

        $birth = Gedcom7Structure::fact('INDI', "1 BIRT\n2 DATE 1 JAN 1900\n2 _MILITARY Navy\n2 RELA godfather");

        self::assertSame(Gedcom7Structure::OK, $birth['status']);
        self::assertSame('INDI:BIRT:DATE', $birth['children'][0]['path']);
        self::assertSame(Gedcom7Structure::OK, $birth['children'][0]['status']);
        self::assertSame(Gedcom7Structure::EXTENSION, $birth['children'][1]['status']);
        self::assertSame(Gedcom7Structure::ERROR, $birth['children'][2]['status']);

        $ordinance = Gedcom7Structure::fact('INDI', "1 BAPL\n2 STAT BIC");

        self::assertSame(Gedcom7Structure::ERROR, $ordinance['children'][0]['status']);
        self::assertCount(1, $ordinance['children'][0]['messages']);

        $no = Gedcom7Structure::fact('INDI', '1 NO');

        self::assertSame(Gedcom7Structure::ERROR, $no['status']);
    }
}
