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

namespace Fisharebest\Webtrees\Tests\Unit\Gedcom7;

use Fisharebest\Webtrees\Gedcom7\Gedcom551Downgrade;
use Fisharebest\Webtrees\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Gedcom551Downgrade::class)]
class Gedcom551DowngradeTest extends TestCase
{
    /**
     * @return array<string,array{0:string,1:string}>
     */
    public static function records(): array
    {
        return [
            'gedcom 5.5.1'          => ["0 @I1@ INDI\n1 NAME John /Smith/\n1 BIRT\n2 DATE @#DJULIAN@ 1700\n2 SOUR @S1@\n3 PAGE 12\n1 _UID 1234", "0 @I1@ INDI\n1 NAME John /Smith/\n1 BIRT\n2 DATE @#DJULIAN@ 1700\n2 SOUR @S1@\n3 PAGE 12\n1 _UID 1234"],
            'identifier'            => ["0 @I1@ INDI\n1 EXID 42\n2 TYPE https://example.com/", "0 @I1@ INDI\n1 _EXID 42\n2 TYPE https://example.com/"],
            'phrase'                => ["0 @I1@ INDI\n1 BIRT\n2 DATE FROM 1700 TO 1800\n3 PHRASE 18th century", "0 @I1@ INDI\n1 BIRT\n2 DATE FROM 1700 TO 1800\n3 _PHRASE 18th century"],
            'associate of an event' => ["0 @I1@ INDI\n1 BAPM\n2 ASSO @I2@\n3 RELA godparent", "0 @I1@ INDI\n1 BAPM\n2 _ASSO @I2@\n3 RELA godparent"],
            'void pointer'          => ["0 @I1@ INDI\n1 FAMC @VOID@\n2 PEDI BIRTH", "0 @I1@ INDI\n1 _FAMC\n2 PEDI BIRTH"],
            'non-event'             => ["0 @I1@ INDI\n1 NO MARR\n2 DATE 1800", "0 @I1@ INDI\n1 _NO MARR\n2 DATE 1800"],
            'shared note record'    => ["0 @N1@ SNOTE Text\n1 MIME text/html", "0 @N1@ NOTE Text\n1 _MIME text/html"],
            'shared note pointer'   => ["0 @I1@ INDI\n1 SNOTE @N1@\n1 SNOTE @VOID@", "0 @I1@ INDI\n1 NOTE @N1@\n1 _SNOTE"],
            'text with @'           => ["0 @I1@ INDI\n1 NOTE @me\n2 CONT more", "0 @I1@ INDI\n1 NOTE @me\n2 CONT more"],
            'trailer'               => ['0 TRLR', '0 TRLR'],
        ];
    }

    #[DataProvider('records')]
    public function testDowngrade(string $working_copy, string $expected): void
    {
        self::assertSame($expected, (new Gedcom551Downgrade())->downgrade($working_copy));
    }
}
