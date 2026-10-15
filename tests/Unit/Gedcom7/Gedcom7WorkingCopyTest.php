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

use Fisharebest\Webtrees\Gedcom7\Gedcom7Converter;
use Fisharebest\Webtrees\Gedcom7\Gedcom7WorkingCopy;
use Fisharebest\Webtrees\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

use function file_get_contents;
use function preg_split;
use function str_starts_with;
use function trim;

#[CoversClass(Gedcom7WorkingCopy::class)]
class Gedcom7WorkingCopyTest extends TestCase
{
    /**
     * @return array<string,array{0:string,1:string}>
     */
    public static function rules(): array
    {
        return [
            'shared note record'      => ["0 @N1@ SNOTE Text\n1 CONT More", "0 @N1@ NOTE Text\n1 CONT More"],
            'shared note pointer'     => ["0 @I1@ INDI\n1 SNOTE @N1@", "0 @I1@ INDI\n1 NOTE @N1@"],
            'inline note'             => ["0 @I1@ INDI\n1 NOTE Text", "0 @I1@ INDI\n1 NOTE Text"],
            'julian calendar'         => ["0 @I1@ INDI\n1 BIRT\n2 DATE JULIAN 1 JAN 1700", "0 @I1@ INDI\n1 BIRT\n2 DATE @#DJULIAN@ 1 JAN 1700"],
            'french calendar'         => ["0 @I1@ INDI\n1 BIRT\n2 DATE FRENCH_R 1 VEND 10", "0 @I1@ INDI\n1 BIRT\n2 DATE @#DFRENCH R@ 1 VEND 10"],
            'calendar in range'       => ["0 @I1@ INDI\n1 BIRT\n2 DATE BET JULIAN 1700 AND JULIAN 1710", "0 @I1@ INDI\n1 BIRT\n2 DATE BET @#DJULIAN@ 1700 AND @#DJULIAN@ 1710"],
            'epoch'                   => ["0 @I1@ INDI\n1 BIRT\n2 DATE 44 BCE", "0 @I1@ INDI\n1 BIRT\n2 DATE 44 B.C."],
            'date phrase'             => ["0 @I1@ INDI\n1 BIRT\n2 DATE 1850\n3 PHRASE about Easter", "0 @I1@ INDI\n1 BIRT\n2 DATE INT 1850 (about Easter)"],
            'date that is a phrase'   => ["0 @I1@ INDI\n1 BIRT\n2 DATE\n3 PHRASE about Easter", "0 @I1@ INDI\n1 BIRT\n2 DATE (about Easter)"],
            'phrase of a period'      => ["0 @I1@ INDI\n1 BIRT\n2 DATE FROM 1700 TO 1800\n3 PHRASE 18th century", "0 @I1@ INDI\n1 BIRT\n2 DATE FROM 1700 TO 1800\n3 PHRASE 18th century"],
            'age word'                => ["0 @I1@ INDI\n1 DEAT\n2 AGE < 8y\n3 PHRASE Child", "0 @I1@ INDI\n1 DEAT\n2 AGE CHILD"],
            'age'                     => ["0 @I1@ INDI\n1 DEAT\n2 AGE > 40y 6m", "0 @I1@ INDI\n1 DEAT\n2 AGE > 40y 6m"],
            'role'                    => ["0 @I1@ INDI\n1 ASSO @I2@\n2 ROLE GODP", "0 @I1@ INDI\n1 ASSO @I2@\n2 RELA godparent"],
            'role with phrase'        => ["0 @I1@ INDI\n1 ASSO @I2@\n2 ROLE GODP\n3 PHRASE Godfather", "0 @I1@ INDI\n1 ASSO @I2@\n2 RELA godfather"],
            'other role'              => ["0 @I1@ INDI\n1 ASSO @I2@\n2 ROLE OTHER\n3 PHRASE Best man", "0 @I1@ INDI\n1 ASSO @I2@\n2 RELA best_man"],
            'standard role as phrase' => ["0 @I1@ INDI\n1 ASSO @I2@\n2 ROLE OTHER\n3 PHRASE Godparent\n1 ASSO @I3@\n2 ROLE WITN", "0 @I1@ INDI\n1 ASSO @I2@\n2 ROLE OTHER\n3 PHRASE Godparent\n1 ASSO @I3@\n2 RELA witness"],
            'role of event'           => ["0 @I1@ INDI\n1 BAPM\n2 ASSO @I2@\n3 ROLE CLERGY", "0 @I1@ INDI\n1 BAPM\n2 _ASSO @I2@\n3 RELA Clergy"],
            'godparent'               => ["0 @I1@ INDI\n1 BAPM\n2 ASSO @VOID@\n3 PHRASE Martin Hoppe\n3 ROLE GODP\n3 SOUR @S1@", "0 @I1@ INDI\n1 BAPM\n2 _GODP Martin Hoppe\n3 SOUR @S1@"],
            'witness'                 => ["0 @F1@ FAM\n1 MARR\n2 ASSO @VOID@\n3 PHRASE Horst Garbers\n3 ROLE WITN", "0 @F1@ FAM\n1 MARR\n2 _WITN Horst Garbers"],
            'associate without record' => ["0 @I1@ INDI\n1 BAPM\n2 ASSO @VOID@\n3 PHRASE Pastor Meyer\n3 ROLE CLERGY", "0 @I1@ INDI\n1 BAPM\n2 ASSO @VOID@\n3 PHRASE Pastor Meyer\n3 RELA Clergy"],
            'godparent of individual' => ["0 @I1@ INDI\n1 ASSO @VOID@\n2 PHRASE Martin Hoppe\n2 ROLE GODP", "0 @I1@ INDI\n1 ASSO @VOID@\n2 PHRASE Martin Hoppe\n2 RELA godparent"],
            'role unknown to webtrees' => ["0 @I1@ INDI\n1 ASSO @I2@\n2 ROLE FATH", "0 @I1@ INDI\n1 ASSO @I2@\n2 RELA Father"],
            'phrase unknown to webtrees' => ["0 @I1@ INDI\n1 ASSO @I2@\n2 ROLE OTHER\n3 PHRASE Pate", "0 @I1@ INDI\n1 ASSO @I2@\n2 RELA Pate"],
            'name type phrase'        => ["0 @I1@ INDI\n1 NAME Peter\n2 TYPE OTHER\n3 PHRASE Name type phrase", "0 @I1@ INDI\n1 NAME Peter\n2 TYPE Name type phrase"],
            'name type of webtrees'   => ["0 @I1@ INDI\n1 NAME Peter\n2 TYPE OTHER\n3 PHRASE Estate", "0 @I1@ INDI\n1 NAME Peter\n2 TYPE ESTATE"],
            'lds status'              => ["0 @F1@ FAM\n1 SLGS\n2 TEMP SLAKE\n2 STAT DNS_CAN\n3 DATE 27 MAR 2022", "0 @F1@ FAM\n1 SLGS\n2 TEMP SLAKE\n2 STAT DNS/CAN\n3 DATE 27 MAR 2022"],
            'void shared note'        => ["0 @I1@ INDI\n1 SNOTE @VOID@", "0 @I1@ INDI\n1 SNOTE @VOID@"],
            'restriction of source'   => ["0 @S1@ SOUR\n1 TITL Title\n1 _RESN CONFIDENTIAL", "0 @S1@ SOUR\n1 TITL Title\n1 RESN CONFIDENTIAL"],
            'restriction of note'     => ["0 @N1@ SNOTE Text\n1 _RESN PRIVACY", "0 @N1@ NOTE Text\n1 RESN PRIVACY"],
            'real extension'          => ["0 @I1@ INDI\n1 _PRIM Y\n1 _TODO Find", "0 @I1@ INDI\n1 _PRIM Y\n1 _TODO Find"],
            'romanized name'          => ["0 @I1@ INDI\n1 NAME 山田 /太郎/\n2 TRAN Yamada /Taro/\n3 LANG ja-Latn", "0 @I1@ INDI\n1 NAME 山田 /太郎/\n2 ROMN Yamada /Taro/\n3 TYPE ROMAJI"],
            'romanized place'         => ["0 @I1@ INDI\n1 BIRT\n2 PLAC Москва\n3 TRAN Moskva\n4 LANG und-Latn", "0 @I1@ INDI\n1 BIRT\n2 PLAC Москва\n3 ROMN Moskva"],
            'other translation'       => ["0 @I1@ INDI\n1 NAME Peter\n2 TRAN Pierre\n3 LANG fr", "0 @I1@ INDI\n1 NAME Peter\n2 TRAN Pierre\n3 LANG French"],
            'unique identifier'       => ["0 @I1@ INDI\n1 UID f096b664-5e40-40e2-bb72-c1664a46fe45", "0 @I1@ INDI\n1 _UID f096b664-5e40-40e2-bb72-c1664a46fe45"],
            'gov identifier'          => ["0 @I1@ INDI\n1 BIRT\n2 PLAC Celle\n3 EXID CELLE1JO42QO\n4 TYPE https://gov.genealogy.net/", "0 @I1@ INDI\n1 BIRT\n2 PLAC Celle\n3 _GOV CELLE1JO42QO"],
            'ancestral file number'   => ["0 @I1@ INDI\n1 EXID 12345\n2 TYPE https://gedcom.io/terms/v7/AFN", "0 @I1@ INDI\n1 AFN 12345"],
            'record file number'      => ["0 @I1@ INDI\n1 EXID 123\n2 TYPE https://gedcom.io/terms/v7/RFN#Archive%20X", "0 @I1@ INDI\n1 RFN Archive X:123"],
            'familysearch'            => ["0 @I1@ INDI\n1 EXID KWCB-QZ2\n2 TYPE https://gedcom.io/exid-type/FamilySearch-PersonId", "0 @I1@ INDI\n1 _FSFTID KWCB-QZ2"],
            'other identifier'        => ["0 @I1@ INDI\n1 EXID 42\n2 TYPE https://example.com/", "0 @I1@ INDI\n1 EXID 42\n2 TYPE https://example.com/"],
            'file and format'         => ["0 @M1@ OBJE\n1 FILE media/my%20photo.jpg\n2 FORM image/jpeg\n3 MEDI PHOTO", "0 @M1@ OBJE\n1 FILE media/my photo.jpg\n2 FORM jpg\n3 TYPE PHOTO"],
            'url'                     => ["0 @M1@ OBJE\n1 FILE https://example.com/a%20b.png\n2 FORM image/png", "0 @M1@ OBJE\n1 FILE https://example.com/a%20b.png\n2 FORM png"],
            'relative path like url'  => ["0 @M1@ OBJE\n1 FILE https%3a//not.a.url\n2 FORM image/bmp", "0 @M1@ OBJE\n1 FILE https%3a//not.a.url\n2 FORM bmp"],
            'pedigree'                => ["0 @I1@ INDI\n1 FAMC @F1@\n2 PEDI OTHER\n3 PHRASE Rada", "0 @I1@ INDI\n1 FAMC @F1@\n2 PEDI RADA"],
            'role in source'          => ["0 @I1@ INDI\n1 SOUR @S1@\n2 EVEN CHR\n3 ROLE OTHER\n4 PHRASE Patin", "0 @I1@ INDI\n1 SOUR @S1@\n2 EVEN CHR\n3 ROLE (Patin)"],
            'standard role in source' => ["0 @I1@ INDI\n1 SOUR @S1@\n2 EVEN CHR\n3 ROLE WITN", "0 @I1@ INDI\n1 SOUR @S1@\n2 EVEN CHR\n3 ROLE WITN"],
            'name type'               => ["0 @I1@ INDI\n1 NAME Peter\n2 TYPE MARRIED", "0 @I1@ INDI\n1 NAME Peter\n2 TYPE MARRIED"],
            'language'                => ["0 @U1@ SUBM\n1 NAME Me\n1 LANG de", "0 @U1@ SUBM\n1 NAME Me\n1 LANG German"],
            'escaped @'               => ["0 @I1@ INDI\n1 NOTE @@me\n2 CONT @@@@ and a@@b", "0 @I1@ INDI\n1 NOTE @me\n2 CONT @@@ and a@@b"],
            'extension'               => ["0 @I1@ INDI\n1 _TODO Find birth\n2 DATE JULIAN 1700", "0 @I1@ INDI\n1 _TODO Find birth\n2 DATE JULIAN 1700"],
            'extension record'        => ["0 @L1@ _LOC\n1 NAME Celle\n1 SNOTE @N1@", "0 @L1@ _LOC\n1 NAME Celle\n1 SNOTE @N1@"],
        ];
    }

    #[DataProvider('rules')]
    public function testRules(string $gedcom7, string $expected): void
    {
        $working_copy = new Gedcom7WorkingCopy();

        self::assertSame($expected, $working_copy->create($gedcom7));
        self::assertSame([], $working_copy->skippedRules());
        self::assertSame($gedcom7, (new Gedcom7Converter())->convertRecord($expected));
    }

    public function testRuleThatCannotBeReversedIsSkipped(): void
    {
        // An age that is only a phrase becomes the phrase - but Gedcom7Converter would read "5y" as an age.
        $gedcom7      = "0 @I1@ INDI\n1 DEAT\n2 AGE\n3 PHRASE 5y\n1 BIRT\n2 DATE JULIAN 1700";
        $working_copy = new Gedcom7WorkingCopy();

        self::assertSame("0 @I1@ INDI\n1 DEAT\n2 AGE\n3 PHRASE 5y\n1 BIRT\n2 DATE @#DJULIAN@ 1700", $working_copy->create($gedcom7));
        self::assertSame([Gedcom7WorkingCopy::RULE_AGE], $working_copy->skippedRules());
    }

    public function testNormalize(): void
    {
        $working_copy = new Gedcom7WorkingCopy();

        // 7.0 file paths are already URLs.
        self::assertSame("0 @M1@ OBJE\n1 FILE media/Bront%C3%AB.jpg\n2 FORM image/jpeg", $working_copy->normalize("0 @M1@ OBJE\n1 FILE media/Bront%C3%AB.jpg\n2 FORM image/jpeg"));
        // The gregorian calendar is only shown with other calendars.
        self::assertSame("0 @I1@ INDI\n1 BIRT\n2 DATE 1850", $working_copy->normalize("0 @I1@ INDI\n1 BIRT\n2 DATE GREGORIAN 1850"));
    }

    public function testEveryRecordOfTheDemoTreeIsReversible(): void
    {
        $text         = (string) file_get_contents(__DIR__ . '/../../data/falkenrath-gedcom7.ged');
        $records      = preg_split('/\n(?=0 )/', trim($text));
        $working_copy = new Gedcom7WorkingCopy();

        self::assertCount(1041, $records);

        foreach ($records as $record) {
            if (str_starts_with($record, '0 TRLR')) {
                continue;
            }

            $gedcom7 = $working_copy->normalize($record);

            self::assertSame($gedcom7, $record, 'The demo tree is valid GEDCOM 7.0');
            self::assertSame($gedcom7, (new Gedcom7Converter())->convertRecord($working_copy->create($gedcom7)));
        }
    }
}
