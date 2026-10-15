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
use Fisharebest\Webtrees\Gedcom7\Gedcom7Node;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Payload;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Schema;
use Fisharebest\Webtrees\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Gedcom7Converter::class)]
#[CoversClass(Gedcom7Node::class)]
#[CoversClass(Gedcom7Payload::class)]
#[CoversClass(Gedcom7Schema::class)]
class Gedcom7ConverterTest extends TestCase
{
    private function convert(string $gedcom, Gedcom7Converter|null $converter = null): string
    {
        return ($converter ?? new Gedcom7Converter())->convertRecord($gedcom);
    }

    public function testStandardRecordIsUnchanged(): void
    {
        $gedcom = "0 @I1@ INDI\n1 NAME John /Smith/\n2 GIVN John\n2 SURN Smith\n1 SEX M\n1 BIRT\n2 DATE 1 JAN 1850\n2 PLAC London\n1 FAMC @F1@";

        self::assertSame($gedcom, $this->convert($gedcom));
    }

    public function testTrailer(): void
    {
        self::assertSame('0 TRLR', $this->convert('0 TRLR'));
    }

    public function testConcAndCont(): void
    {
        self::assertSame(
            "0 @I1@ INDI\n1 NOTE First line\n2 CONT second line",
            $this->convert("0 @I1@ INDI\n1 NOTE First \n2 CONC line\n2 CONT second\n2 CONC  line")
        );
    }

    public function testLeadingAtSignsAreEscaped(): void
    {
        self::assertSame(
            "0 @I1@ INDI\n1 NOTE me@example.com\n2 CONT @@me on social media",
            $this->convert("0 @I1@ INDI\n1 NOTE me@example.com\n2 CONT @me on social media")
        );
    }

    public function testBannedCharactersAreReplaced(): void
    {
        self::assertSame("0 @I1@ INDI\n1 NOTE a\u{FFFD}b", $this->convert("0 @I1@ INDI\n1 NOTE a\x07b"));
    }

    public function testNoteRecordsAreSharedNotes(): void
    {
        self::assertSame("0 @N1@ SNOTE Some text\n1 CHAN\n2 DATE 1 JAN 2020", $this->convert("0 @N1@ NOTE Some text\n1 CHAN\n2 DATE 1 JAN 2020"));
        self::assertSame("0 @I1@ INDI\n1 NAME John //\n2 SNOTE @N1@\n1 SNOTE @N1@", $this->convert("0 @I1@ INDI\n1 NAME John //\n2 NOTE @N1@\n1 NOTE @N1@"));
    }

    public function testCrossReferenceIdentifiers(): void
    {
        $converter = new Gedcom7Converter('', ['i1', 'I1', 'f1', 'X1', 'VOID']);

        self::assertSame("0 @I1_1@ INDI\n1 FAMC @F1@\n1 FAMS @VOID@\n1 ALIA @VOID_1@", $this->convert("0 @i1@ INDI\n1 FAMC @f1@\n1 FAMS @missing@\n1 ALIA @VOID@", $converter));
        self::assertSame('I1', $converter->xref('I1'));
    }

    public function testCrossReferenceIdentifiersWithoutAList(): void
    {
        $converter = new Gedcom7Converter();

        self::assertSame('I1', $converter->xref('I1'));
        self::assertMatchesRegularExpression('/^I_1_[0-9A-F]{8}$/', $converter->xref('i.1'));
    }

    public function testInlineSourcesBecomeRecords(): void
    {
        $converter = new Gedcom7Converter();

        self::assertSame(
            "0 @I1@ INDI\n1 DEAT\n2 DATE 1910\n2 SOUR @_S7_1@\n3 PAGE 2\n1 BURI\n2 SOUR @_S7_1@",
            $this->convert("0 @I1@ INDI\n1 DEAT\n2 DATE 1910\n2 SOUR Letter from Alice\n3 TEXT My father died in 1910.\n3 PAGE 2\n1 BURI\n2 SOUR Letter from Alice\n3 TEXT My father died in 1910.", $converter)
        );
        self::assertSame(["0 @_S7_1@ SOUR\n1 TITL Letter from Alice\n1 TEXT My father died in 1910."], $converter->generatedRecords());
        self::assertSame([], $converter->generatedRecords());
    }

    public function testInlineMediaBecomeRecords(): void
    {
        $converter = new Gedcom7Converter();

        self::assertSame("0 @I1@ INDI\n1 OBJE @_M7_1@", $this->convert("0 @I1@ INDI\n1 OBJE\n2 FILE d:\\Media\\John Smith.jpg\n2 FORM jpg\n2 TITL John Smith", $converter));
        self::assertSame(["0 @_M7_1@ OBJE\n1 FILE file:///d:/Media/John%20Smith.jpg\n2 FORM image/jpeg\n2 TITL John Smith"], $converter->generatedRecords());
    }

    public function testMediaRecords(): void
    {
        self::assertSame(
            "0 @M1@ OBJE\n1 FILE photos/john%20smith.jpg\n2 FORM image/jpeg\n3 MEDI PHOTO\n2 TITL John\n1 FILE photos/coat.png\n2 FORM image/png\n3 MEDI OTHER\n4 PHRASE Coat",
            $this->convert("0 @M1@ OBJE\n1 FILE photos/john smith.jpg\n2 FORM jpg\n3 TYPE photo\n2 TITL John\n1 FILE photos/coat.png\n2 FORM png\n3 TYPE COAT")
        );
    }

    public function testMediaWithoutFormat(): void
    {
        self::assertSame("0 @M1@ OBJE\n1 FILE a.pdf\n2 FORM application/pdf", $this->convert("0 @M1@ OBJE\n1 FILE a.pdf"));
        self::assertSame("0 @M1@ OBJE\n1 FILE a.xyz\n2 FORM application/octet-stream\n3 _FORM xyz", $this->convert("0 @M1@ OBJE\n1 FILE a.xyz\n2 FORM xyz"));
    }

    public function testRepositoryWithoutRecord(): void
    {
        self::assertSame("0 @S1@ SOUR\n1 REPO @VOID@\n2 CALN 123\n2 NOTE Town hall", $this->convert("0 @S1@ SOUR\n1 REPO Town hall\n2 CALN 123"));
    }

    public function testAssociations(): void
    {
        self::assertSame(
            "0 @I1@ INDI\n1 ASSO @I2@\n2 ROLE WITN\n1 ASSO @I3@\n2 ROLE GODP\n3 PHRASE Godfather\n1 ASSO @I4@\n2 ROLE OTHER\n3 PHRASE Honorary uncle\n1 ASSO @I5@\n2 ROLE OTHER\n3 PHRASE Best man\n1 ASSO @I6@\n2 ROLE OTHER",
            $this->convert("0 @I1@ INDI\n1 ASSO @I2@\n2 RELA Witness\n1 ASSO @I3@\n2 RELA godfather\n1 ASSO @I4@\n2 RELA Honorary uncle\n1 ASSO @I5@\n2 RELA best_man\n1 ASSO @I6@")
        );
    }

    public function testEventAssociationsFromGedcomL(): void
    {
        self::assertSame("0 @I1@ INDI\n1 CHR\n2 ASSO @I2@\n3 ROLE GODP", $this->convert("0 @I1@ INDI\n1 CHR\n2 _ASSO @I2@\n3 RELA godparent"));
    }

    public function testExternalIdentifiers(): void
    {
        self::assertSame(
            "0 @I1@ INDI\n1 EXID 123456789\n2 TYPE https://gedcom.io/terms/v7/AFN\n1 EXID 9876\n2 TYPE https://gedcom.io/terms/v7/RIN#MySystem\n1 EXID 5431\n2 TYPE https://gedcom.io/terms/v7/RFN#Resource\n1 EXID ABCD-123\n2 TYPE https://gedcom.io/exid-type/FamilySearch-PersonId",
            $this->convert("0 @I1@ INDI\n1 AFN 123456789\n1 RIN 9876\n1 RFN Resource:5431\n1 _FSFTID ABCD-123", new Gedcom7Converter('MySystem'))
        );
    }

    public function testGovIdentifiers(): void
    {
        self::assertSame(
            "0 @I1@ INDI\n1 BIRT\n2 PLAC Celle\n3 EXID CELLE1JO52QO\n4 TYPE https://gov.genealogy.net/",
            $this->convert("0 @I1@ INDI\n1 BIRT\n2 PLAC Celle\n3 _GOV CELLE1JO52QO")
        );
    }

    public function testUniqueIdentifiers(): void
    {
        self::assertSame("0 @I1@ INDI\n1 UID 0123abcd-ef01-2345-6789-0123456789ab", $this->convert("0 @I1@ INDI\n1 _UID 0123ABCDEF01234567890123456789AB1234"));
        self::assertSame("0 @I1@ INDI\n1 UID 0123abcd-ef01-2345-6789-0123456789ab", $this->convert("0 @I1@ INDI\n1 _UID 0123ABCDEF01234567890123456789AB"));
        self::assertSame("0 @I1@ INDI\n1 UID something-else", $this->convert("0 @I1@ INDI\n1 _UID something-else"));
    }

    public function testTranslations(): void
    {
        self::assertSame(
            "0 @I1@ INDI\n1 NAME /橘/ 逸勢\n2 TRAN /Tachibana/ no Hayanari\n3 LANG ja-Latn\n2 TRAN /たちばな/ の はやなり\n3 LANG ja-hrkt",
            $this->convert("0 @I1@ INDI\n1 NAME /橘/ 逸勢\n2 ROMN /Tachibana/ no Hayanari\n3 TYPE romaji\n2 FONE /たちばな/ の はやなり\n3 TYPE kana")
        );
        self::assertSame(
            "0 @I1@ INDI\n1 NAME Ivan //\n2 TRAN Iwan\n3 _TYPE ISO 9\n3 LANG und-Latn",
            $this->convert("0 @I1@ INDI\n1 NAME Ivan //\n2 ROMN Iwan\n3 TYPE ISO 9")
        );
    }

    public function testEnumerations(): void
    {
        self::assertSame(
            "0 @I1@ INDI\n1 NAME A /B/\n2 TYPE BIRTH\n1 NAME C /D/\n2 TYPE OTHER\n3 PHRASE Religious\n1 SEX X\n1 RESN LOCKED, _NONE\n1 FAMC @F1@\n2 PEDI OTHER\n3 PHRASE Rada\n2 STAT CHALLENGED\n1 FAMC @F2@\n2 PEDI SEALING",
            $this->convert("0 @I1@ INDI\n1 NAME A /B/\n2 TYPE birth\n1 NAME C /D/\n2 TYPE RELIGIOUS\n1 SEX x\n1 RESN locked, none\n1 FAMC @F1@\n2 PEDI RADA\n2 STAT challenged\n1 FAMC @F2@\n2 PEDI sealed")
        );
    }

    public function testSexValues(): void
    {
        self::assertSame("0 @I1@ INDI\n1 SEX M", $this->convert("0 @I1@ INDI\n1 SEX Male"));
        self::assertSame("0 @I1@ INDI\n1 SEX U", $this->convert("0 @I1@ INDI\n1 SEX ?"));
    }

    public function testLdsOrdinanceStatus(): void
    {
        self::assertSame("0 @I1@ INDI\n1 BAPL\n2 STAT DNS_CAN\n3 DATE 1 JAN 2000", $this->convert("0 @I1@ INDI\n1 BAPL\n2 STAT DNS/CAN\n3 DATE 1 JAN 2000"));
        // A status needs a date in 7.0
        self::assertSame("0 @I1@ INDI\n1 BAPL\n2 _STAT PRE_1970", $this->convert("0 @I1@ INDI\n1 BAPL\n2 STAT PRE-1970"));
    }

    public function testSourceCitationEvents(): void
    {
        self::assertSame(
            "0 @I1@ INDI\n1 BIRT\n2 SOUR @S1@\n3 EVEN BIRT\n4 ROLE OTHER\n5 PHRASE Midwife\n3 QUAY 3\n2 SOUR @S2@\n3 EVEN EVEN\n4 PHRASE Births",
            $this->convert("0 @I1@ INDI\n1 BIRT\n2 SOUR @S1@\n3 EVEN BIRT\n4 ROLE (Midwife)\n3 QUAY 3\n2 SOUR @S2@\n3 EVEN Births")
        );
    }

    public function testDatesAndAges(): void
    {
        self::assertSame(
            "0 @I1@ INDI\n1 BIRT\n2 DATE 1850\n3 PHRASE about Easter\n1 DEAT\n2 DATE JULIAN 30 JAN 1648\n2 AGE < 8y\n3 PHRASE Child",
            $this->convert("0 @I1@ INDI\n1 BIRT\n2 DATE INT 1850 (about Easter)\n1 DEAT\n2 DATE @#DJULIAN@ 30 JAN 1648\n2 AGE CHILD")
        );
    }

    public function testChangeDateMustBeExact(): void
    {
        self::assertSame("0 @I1@ INDI\n1 _CHAN\n2 _DATE JAN 2020", $this->convert("0 @I1@ INDI\n1 CHAN\n2 DATE JAN 2020"));
        self::assertSame("0 @I1@ INDI\n1 CHAN\n2 DATE 1 JAN 2020\n3 TIME 12:00:00\n2 _WT_USER admin", $this->convert("0 @I1@ INDI\n1 CHAN\n2 DATE 1 JAN 2020\n3 TIME 12:00:00\n2 _WT_USER admin"));
    }

    public function testLanguages(): void
    {
        self::assertSame("0 @S1@ SOUR\n1 TEXT Text\n2 LANG de", $this->convert("0 @S1@ SOUR\n1 TEXT Text\n2 LANG German"));
        self::assertSame("0 @S1@ SOUR\n1 TEXT Text\n2 _LANG Klingon language", $this->convert("0 @S1@ SOUR\n1 TEXT Text\n2 LANG Klingon language"));
        // TEXT is not permitted in SOUR:DATA
        self::assertSame("0 @S1@ SOUR\n1 DATA\n2 _TEXT Text", $this->convert("0 @S1@ SOUR\n1 DATA\n2 TEXT Text"));
    }

    public function testNamePieces(): void
    {
        self::assertSame(
            "0 @I1@ INDI\n1 NAME Juan /de la Cruz/\n2 SPFX de la\n2 SURN Cruz\n1 NAME Velislava /Marinova/\n2 SURN Marinova\n2 SURN Marinov\n2 SURN Marinovi",
            $this->convert("0 @I1@ INDI\n1 NAME Juan /de la Cruz/\n2 SPFX de, la\n2 SURN Cruz\n1 NAME Velislava /Marinova/\n2 SURN Marinova, Marinov, Marinovi")
        );
    }

    public function testEventsWithText(): void
    {
        self::assertSame("0 @I1@ INDI\n1 DEAT Y\n2 NOTE in the war", $this->convert("0 @I1@ INDI\n1 DEAT in the war"));
        self::assertSame("0 @I1@ INDI\n1 DEAT Y", $this->convert("0 @I1@ INDI\n1 DEAT y"));
    }

    public function testRequiredSubstructures(): void
    {
        self::assertSame("0 @I1@ INDI\n1 EVEN Something\n2 TYPE", $this->convert("0 @I1@ INDI\n1 EVEN Something"));
        self::assertSame("0 @I1@ INDI\n1 BIRT\n2 PLAC Celle\n3 _MAP\n4 LATI N52.6", $this->convert("0 @I1@ INDI\n1 BIRT\n2 PLAC Celle\n3 MAP\n4 LATI N52.6"));
    }

    public function testRemovedStructuresBecomeExtensions(): void
    {
        self::assertSame("0 @U1@ _SUBN\n1 SUBM @U2@", $this->convert("0 @U1@ SUBN\n1 SUBM @U2@"));
        self::assertSame("0 @I1@ INDI\n1 _ANCE something", $this->convert("0 @I1@ INDI\n1 ANCE something"));
    }

    public function testStructuresThatMayOnlyOccurOnce(): void
    {
        self::assertSame("0 @I1@ INDI\n1 BIRT\n2 DATE 1850\n2 _DATE 1851", $this->convert("0 @I1@ INDI\n1 BIRT\n2 DATE 1850\n2 DATE 1851"));
    }

    public function testExtensionsAreKept(): void
    {
        self::assertSame(
            "0 @L1@ _LOC\n1 NAME Celle\n1 _GOV CELLE1JO52QO\n0 @I1@ INDI",
            $this->convert("0 @L1@ _LOC\n1 NAME Celle\n1 _GOV CELLE1JO52QO") . "\n0 @I1@ INDI"
        );
        self::assertSame("0 @I1@ INDI\n1 _MARNM Mary /Jones/\n2 _X @F1@", $this->convert("0 @I1@ INDI\n1 _MARNM Mary /Jones/\n2 _X @F1@"));
    }

    public function testHeader(): void
    {
        self::assertSame(
            "0 HEAD\n1 GEDC\n2 VERS 7.0\n1 SOUR webtrees\n2 NAME webtrees\n1 LANG de\n1 PLAC\n2 FORM City, County\n1 SUBM @U1@",
            $this->convert("0 HEAD\n1 GEDC\n2 VERS 7.0\n1 SOUR webtrees\n2 NAME webtrees\n1 LANG German\n1 PLAC\n2 FORM City, County\n1 SUBM @U1@")
        );
    }
}
