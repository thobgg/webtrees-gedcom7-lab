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
use Fisharebest\Webtrees\Gedcom7\Gedcom7Problems;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Gedcom7Problems::class)]
class Gedcom7ProblemsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::createDatabase();
        (new Gedcom())->registerTags(Registry::elementFactory(), true);
    }

    public function testValidFacts(): void
    {
        self::assertSame([], Gedcom7Problems::fact('INDI', "1 BIRT\n2 DATE ABT 1850\n2 ASSO @VOID@\n3 ROLE GODP\n4 PHRASE Godmother"));
        self::assertSame([], Gedcom7Problems::fact('INDI', "1 BAPL\n2 STAT BIC\n3 DATE 1 JAN 1900"));
        self::assertSame([], Gedcom7Problems::fact('INDI', "1 BIRT\n2 _POST 12345"));
    }

    public function testRequiredSubstructures(): void
    {
        self::assertSame(['Status: Status change date is missing.'], Gedcom7Problems::fact('INDI', "1 BAPL\n2 STAT BIC"));
        self::assertSame(['Associate: Role is missing.'], Gedcom7Problems::fact('INDI', "1 BIRT\n2 ASSO @VOID@\n3 PHRASE Martin"));
    }

    public function testInvalidValues(): void
    {
        self::assertSame(['Events: the value is missing or not valid.'], Gedcom7Problems::fact('SOUR', "1 DATA\n2 EVEN\n3 DATE 1850"));
    }

    public function testNotPermitted(): void
    {
        self::assertSame(['INDI:NAME:ROMN is not permitted here.'], Gedcom7Problems::fact('INDI', "1 NAME John /Smith/\n2 ROMN John /Smith/\n3 TYPE romaji"));
    }

    public function testRecords(): void
    {
        self::assertSame([], Gedcom7Problems::record("0 @R1@ REPO\n1 NAME Archive"));
        self::assertSame(['Repository: Name is missing.'], Gedcom7Problems::record("0 @R1@ REPO\n1 ADDR Street"));
        self::assertSame(['Associate: Role is missing.'], Gedcom7Problems::record("0 @I1@ INDI\n1 BIRT\n2 ASSO @VOID@"));
    }

    public function testLocationRecords(): void
    {
        self::assertSame([], Gedcom7Problems::record("0 @L1@ _LOC\n1 NAME Celle\n2 LANG de\n1 _LOC @L2@\n2 TYPE POLI\n1 EXID CELLE1JO52QO\n2 TYPE https://gov.genealogy.net/\n1 _STAE Niedersachsen"));
        self::assertSame(['Location: Place is missing.'], Gedcom7Problems::record("0 @L1@ _LOC\n1 TYPE Stadt"));
        self::assertSame(['Parent location: Hierarchical relationship is missing.'], Gedcom7Problems::record("0 @L1@ _LOC\n1 NAME Celle\n1 _LOC @L2@"));
        self::assertSame(['Hierarchical relationship: the value is missing or not valid.'], Gedcom7Problems::record("0 @L1@ _LOC\n1 NAME Celle\n1 _LOC @L2@\n2 TYPE"));
        self::assertSame([], Gedcom7Problems::fact('_LOC', "1 _LOC @L2@\n2 TYPE POLI"));
        self::assertSame(['Parent location: Hierarchical relationship is missing.'], Gedcom7Problems::fact('_LOC', "1 _LOC @L2@"));
    }
}
