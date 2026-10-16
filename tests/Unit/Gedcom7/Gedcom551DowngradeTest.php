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
            'julian calendar'       => ["0 @I1@ INDI\n1 BIRT\n2 DATE JULIAN 1 JAN 1700", "0 @I1@ INDI\n1 BIRT\n2 DATE @#DJULIAN@ 1 JAN 1700"],
            'french calendar'       => ["0 @I1@ INDI\n1 BIRT\n2 DATE FRENCH_R 1 VEND 10", "0 @I1@ INDI\n1 BIRT\n2 DATE @#DFRENCH R@ 1 VEND 10"],
            'hijri calendar'        => ["0 @I1@ INDI\n1 BIRT\n2 DATE _HIJRI 1 MUHAR 1300", "0 @I1@ INDI\n1 BIRT\n2 DATE @#DHIJRI@ 1 MUHAR 1300"],
            'calendar in range'     => ["0 @I1@ INDI\n1 BIRT\n2 DATE BET JULIAN 1700 AND JULIAN 1710", "0 @I1@ INDI\n1 BIRT\n2 DATE BET @#DJULIAN@ 1700 AND @#DJULIAN@ 1710"],
            'epoch'                 => ["0 @I1@ INDI\n1 BIRT\n2 DATE 44 BCE", "0 @I1@ INDI\n1 BIRT\n2 DATE 44 B.C."],
            'date phrase'           => ["0 @I1@ INDI\n1 BIRT\n2 DATE 1850\n3 PHRASE about Easter", "0 @I1@ INDI\n1 BIRT\n2 DATE INT 1850 (about Easter)"],
            'date that is a phrase' => ["0 @I1@ INDI\n1 BIRT\n2 DATE\n3 PHRASE about Easter", "0 @I1@ INDI\n1 BIRT\n2 DATE (about Easter)"],
            'age word'              => ["0 @I1@ INDI\n1 DEAT\n2 AGE < 8y\n3 PHRASE Child", "0 @I1@ INDI\n1 DEAT\n2 AGE CHILD"],
            'age that is a phrase'  => ["0 @I1@ INDI\n1 DEAT\n2 AGE\n3 PHRASE about 30", "0 @I1@ INDI\n1 DEAT\n2 AGE about 30"],
            'age with phrase'       => ["0 @F1@ FAM\n1 MARR\n2 HUSB\n3 AGE 30y\n4 PHRASE thirty", "0 @F1@ FAM\n1 MARR\n2 HUSB\n3 AGE 30y\n4 _PHRASE thirty"],
            'role'                  => ["0 @I1@ INDI\n1 ASSO @I2@\n2 ROLE GODP", "0 @I1@ INDI\n1 ASSO @I2@\n2 RELA godparent"],
            'role with phrase'      => ["0 @I1@ INDI\n1 ASSO @I2@\n2 ROLE GODP\n3 PHRASE Godfather", "0 @I1@ INDI\n1 ASSO @I2@\n2 RELA godfather"],
            'other role'            => ["0 @I1@ INDI\n1 ASSO @I2@\n2 ROLE OTHER\n3 PHRASE Best man", "0 @I1@ INDI\n1 ASSO @I2@\n2 RELA best_man"],
            'unknown phrase'        => ["0 @I1@ INDI\n1 ASSO @I2@\n2 ROLE OTHER\n3 PHRASE Pate", "0 @I1@ INDI\n1 ASSO @I2@\n2 RELA Pate"],
            'standard role phrase'  => ["0 @I1@ INDI\n1 ASSO @I2@\n2 ROLE OTHER\n3 PHRASE Godparent", "0 @I1@ INDI\n1 ASSO @I2@\n2 _ROLE OTHER\n3 PHRASE Godparent"],
            'role of event'         => ["0 @I1@ INDI\n1 BAPM\n2 ASSO @I2@\n3 ROLE CLERGY\n3 NOTE Text", "0 @I1@ INDI\n1 BAPM\n2 _ASSO @I2@\n3 RELA Clergy\n3 NOTE Text"],
            'godparent'             => ["0 @I1@ INDI\n1 BAPM\n2 ASSO @VOID@\n3 PHRASE Martin Hoppe\n3 ROLE GODP\n3 SOUR @S1@", "0 @I1@ INDI\n1 BAPM\n2 _GODP Martin Hoppe\n3 SOUR @S1@"],
            'witness'               => ["0 @F1@ FAM\n1 MARR\n2 ASSO @VOID@\n3 PHRASE Horst Garbers\n3 ROLE WITN", "0 @F1@ FAM\n1 MARR\n2 _WITN Horst Garbers"],
            'associate no record'   => ["0 @I1@ INDI\n1 BAPM\n2 ASSO @VOID@\n3 PHRASE Pastor Meyer\n3 ROLE CLERGY", "0 @I1@ INDI\n1 BAPM\n2 _ASSO\n3 PHRASE Pastor Meyer\n3 RELA Clergy"],
            'identifier'            => ["0 @I1@ INDI\n1 EXID 42\n2 TYPE https://example.com/", "0 @I1@ INDI\n1 _EXID 42\n2 TYPE https://example.com/"],
            'phrase'                => ["0 @I1@ INDI\n1 BIRT\n2 DATE FROM 1700 TO 1800\n3 PHRASE 18th century", "0 @I1@ INDI\n1 BIRT\n2 DATE FROM 1700 TO 1800\n3 _PHRASE 18th century"],
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
