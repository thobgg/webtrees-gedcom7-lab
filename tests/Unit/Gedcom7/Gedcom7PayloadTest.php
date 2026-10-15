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

use Fisharebest\Webtrees\Gedcom7\Gedcom7Payload;
use Fisharebest\Webtrees\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Gedcom7Payload::class)]
class Gedcom7PayloadTest extends TestCase
{
    public function testDatesThatDoNotChange(): void
    {
        foreach (['', '1850', 'JAN 1850', '1 JAN 1850', 'ABT 1850', 'CAL 1850', 'EST 1850', 'AFT 1850', 'BEF 1850', 'BET 1850 AND 1860', 'FROM 1850', 'TO 1850', 'FROM 1850 TO 1860'] as $date) {
            self::assertSame([$date, ''], Gedcom7Payload::date($date), $date);
        }
    }

    public function testDatesAreUpperCase(): void
    {
        self::assertSame(['ABT 1 JAN 1850', ''], Gedcom7Payload::date('abt 1 jan 1850'));
    }

    public function testCalendarEscapes(): void
    {
        self::assertSame(['JULIAN 1 JAN 1700', ''], Gedcom7Payload::date('@#DJULIAN@ 1 JAN 1700'));
        self::assertSame(['FRENCH_R 1 VEND 10', ''], Gedcom7Payload::date('@#DFRENCH R@ 1 VEND 10'));
        self::assertSame(['HEBREW 1 TSH 5600', ''], Gedcom7Payload::date('@#DHEBREW@ 1 TSH 5600'));
        self::assertSame(['_ROMAN 71', ''], Gedcom7Payload::date('@#DROMAN@ 71'));
        self::assertSame(['_HIJRI 1 MUHAR 1300', ''], Gedcom7Payload::date('@#DHIJRI@ 1 MUHAR 1300'));
    }

    public function testGregorianCalendarIsOnlyShownWithOtherCalendars(): void
    {
        self::assertSame(['1 JAN 1850', ''], Gedcom7Payload::date('@#DGREGORIAN@ 1 JAN 1850'));
        self::assertSame(['FROM GREGORIAN 1670 TO JULIAN 1800', ''], Gedcom7Payload::date('FROM 1670 TO @#DJULIAN@ 1800'));
    }

    public function testEpochs(): void
    {
        self::assertSame(['44 BCE', ''], Gedcom7Payload::date('44 B.C.'));
        self::assertSame(['BET 100 BCE AND 50 BCE', ''], Gedcom7Payload::date('BET 100 B.C. AND 50 B.C.'));
    }

    public function testExtensionMonthsAndEpochs(): void
    {
        self::assertSame(['FRENCH_R 2 _JOUR 8', ''], Gedcom7Payload::date('FRENCH_R 2 _JOUR 8'));
        self::assertSame(['_CALENDAR 8 _MONTH 190 _EPOCH', ''], Gedcom7Payload::date('_CALENDAR 8 _MONTH 190 _EPOCH'));
        self::assertNull(Gedcom7Payload::date('8 JAN 190 _EPOCH', 'DateExact'));
    }

    public function testDatePhrases(): void
    {
        self::assertSame(['1850', 'about Easter'], Gedcom7Payload::date('INT 1850 (about Easter)'));
        self::assertSame(['', 'about Easter'], Gedcom7Payload::date('(about Easter)'));
    }

    public function testDualYears(): void
    {
        self::assertSame(['30 JAN 1649', '30 JAN 1648/9'], Gedcom7Payload::date('30 JAN 1648/9'));
        self::assertSame(['30 JAN 1700', '30 JAN 1699/00'], Gedcom7Payload::date('30 JAN 1699/00'));
        // Not a dual year
        self::assertSame(['', '1648/2'], Gedcom7Payload::date('1648/2'));
    }

    public function testDateRangesAreInOrder(): void
    {
        self::assertSame(['BET 1880 AND 1900', ''], Gedcom7Payload::date('BET 1900 AND 1880'));
        self::assertSame(['BET 1 JAN 1880 AND 2 JAN 1880', ''], Gedcom7Payload::date('BET 2 JAN 1880 AND 1 JAN 1880'));
    }

    public function testInvalidDatesBecomePhrases(): void
    {
        self::assertSame(['', 'Easter 1850'], Gedcom7Payload::date('Easter 1850'));
        self::assertSame(['', '31 FOO 1850'], Gedcom7Payload::date('31 FOO 1850'));
    }

    public function testDatePeriods(): void
    {
        self::assertSame(['FROM 1850 TO 1860', ''], Gedcom7Payload::date('FROM 1850 TO 1860', 'DatePeriod'));
        self::assertSame(['', '1850'], Gedcom7Payload::date('1850', 'DatePeriod'));
    }

    public function testExactDates(): void
    {
        self::assertSame(['1 JAN 2020', ''], Gedcom7Payload::date('1 JAN 2020', 'DateExact'));
        self::assertNull(Gedcom7Payload::date('JAN 2020', 'DateExact'));
        self::assertNull(Gedcom7Payload::date('@#DJULIAN@ 1 JAN 2020', 'DateExact'));
    }

    public function testAges(): void
    {
        self::assertSame(['', ''], Gedcom7Payload::age(''));
        self::assertSame(['5y', ''], Gedcom7Payload::age('5y'));
        self::assertSame(['< 5y 3m 2d', ''], Gedcom7Payload::age('<5Y 3M 2D'));
        self::assertSame(['> 1y', ''], Gedcom7Payload::age('> 1y'));
        self::assertSame(['< 8y', 'Child'], Gedcom7Payload::age('CHILD'));
        self::assertSame(['< 1y', 'Infant'], Gedcom7Payload::age('infant'));
        self::assertSame(['0y', 'Stillborn'], Gedcom7Payload::age('STILLBORN'));
        self::assertSame(['', 'about 50'], Gedcom7Payload::age('about 50'));
        self::assertSame(['', '3m 2y'], Gedcom7Payload::age('3m 2y'));
    }

    public function testLanguages(): void
    {
        self::assertSame('de', Gedcom7Payload::language('German'));
        self::assertSame('de', Gedcom7Payload::language('GERMAN'));
        self::assertSame('ang', Gedcom7Payload::language('Anglo-Saxon'));
        self::assertSame('en-GB', Gedcom7Payload::language('en-GB'));
        self::assertNull(Gedcom7Payload::language('Klingon language'));
    }

    public function testMediaTypes(): void
    {
        self::assertSame('image/jpeg', Gedcom7Payload::mediaType('jpg'));
        self::assertSame('image/jpeg', Gedcom7Payload::mediaType('JPEG'));
        self::assertSame('image/vnd.zbrush.pcx', Gedcom7Payload::mediaType('pcx'));
        self::assertSame('application/pdf', Gedcom7Payload::mediaType('', 'folder/file.pdf'));
        self::assertSame('image/png', Gedcom7Payload::mediaType('', 'https://example.com/image.png?size=2'));
        self::assertSame('text/html', Gedcom7Payload::mediaType('text/html'));
        self::assertSame('application/octet-stream', Gedcom7Payload::mediaType('xyz'));
    }

    public function testFilePaths(): void
    {
        self::assertSame('photos/john%20smith.jpg', Gedcom7Payload::filePath('photos\\john smith.jpg'));
        self::assertSame('photos/50%25%3F%23.jpg', Gedcom7Payload::filePath('photos/50%?#.jpg'));
        self::assertSame('photos/Müller.jpg', Gedcom7Payload::filePath('photos/Müller.jpg'));
        self::assertSame('media/Bront%C3%AB.jpg', Gedcom7Payload::filePath('media/Bront%C3%AB.jpg'));
        self::assertSame('file:///C:/Media/1896-02-04.jpg', Gedcom7Payload::filePath('C:\\Media\\1896-02-04.jpg'));
        self::assertSame('file://server/share/a.jpg', Gedcom7Payload::filePath('\\\\server\\share\\a.jpg'));
        self::assertSame('file:///home/user/a%20b.jpg', Gedcom7Payload::filePath('/home/user/a b.jpg'));
        self::assertSame('https://example.com/a%20b.jpg?x=1#y', Gedcom7Payload::filePath('https://example.com/a b.jpg?x=1#y'));
        self::assertSame('https://example.com/a%20b.jpg', Gedcom7Payload::filePath('https://example.com/a%20b.jpg'));
    }

    public function testTimes(): void
    {
        self::assertSame('12:30', Gedcom7Payload::time('12:30'));
        self::assertSame('09:05:00.5Z', Gedcom7Payload::time('09:05:00.5z'));
        self::assertNull(Gedcom7Payload::time('25:00'));
        self::assertNull(Gedcom7Payload::time('noon'));
    }

    public function testCoordinates(): void
    {
        self::assertSame('N52.6226', Gedcom7Payload::latitude('N52.6226'));
        self::assertSame('S52.6226', Gedcom7Payload::latitude('-52.6226'));
        self::assertSame('E10.0815', Gedcom7Payload::longitude('10,0815'));
        self::assertSame('W120.5', Gedcom7Payload::longitude('W120.5'));
        self::assertNull(Gedcom7Payload::latitude('N95'));
        self::assertNull(Gedcom7Payload::longitude('E190'));
    }
}
