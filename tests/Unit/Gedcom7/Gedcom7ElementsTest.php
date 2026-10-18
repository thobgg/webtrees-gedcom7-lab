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

use Fisharebest\Webtrees\Elements\EventTypeList;
use Fisharebest\Webtrees\Elements\NamePieceGiven;
use Fisharebest\Webtrees\Elements\RelationIsDescriptor;
use Fisharebest\Webtrees\Elements\UnknownElement;
use Fisharebest\Webtrees\Elements\XrefSource;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Elements;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Gedcom7Elements::class)]
#[CoversClass(EventTypeList::class)]
class Gedcom7ElementsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::createDatabase();
    }

    public function testTags(): void
    {
        (new Gedcom())->registerTags(Registry::elementFactory(), true);

        $factory = Registry::elementFactory();

        // Shared notes have the substructures of note records.
        self::assertNotInstanceOf(UnknownElement::class, $factory->make('SNOTE:SOUR:PAGE'));
        self::assertNotInstanceOf(UnknownElement::class, $factory->make('SNOTE:MIME'));
        // Translations of names have the parts of names.
        self::assertInstanceOf(NamePieceGiven::class, $factory->make('INDI:NAME:TRAN:GIVN'));
        self::assertNotInstanceOf(UnknownElement::class, $factory->make('INDI:BIRT:PLAC:TRAN'));
        // Values of GEDCOM 5.5.1 that webtrees still shows, e.g. roles of associates.
        self::assertInstanceOf(RelationIsDescriptor::class, $factory->make('FAM:MARR:ASSO:RELA'));
        // GEDCOM 5.5.1 is not registered.
        self::assertInstanceOf(UnknownElement::class, $factory->make('INDI:NAME:ROMN'));
        self::assertSame('Date of birth', $factory->make('INDI:BIRT:DATE')->label());
    }

    public function testTagsAreRegistered(): void
    {
        (new Gedcom())->registerTags(Registry::elementFactory(), true);

        self::assertNotInstanceOf(UnknownElement::class, Registry::elementFactory()->make('INDI:NAME:TRAN'));
        self::assertNotInstanceOf(UnknownElement::class, Registry::elementFactory()->make('OBJE:EXID'));
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function structures(): array
    {
        return [
            'pointer phrase'        => ['FAM:CHIL:PHRASE'],
            'family link status'    => ['INDI:FAMC:STAT:PHRASE'],
            'crop of a media link'  => ['SUBM:OBJE:CROP:WIDTH'],
            'title of a media link' => ['FAM:OBJE:TITL'],
            'source of a note'      => ['HEAD:NOTE:SOUR:PAGE'],
            'shared note'           => ['SOUR:REPO:SNOTE'],
            'time of a status'      => ['INDI:SLGC:STAT:DATE:TIME'],
            'phrase of an event'    => ['OBJE:SOUR:EVEN:PHRASE'],
            'place of a source'     => ['SOUR:DATA:EVEN:PLAC:EXID:TYPE'],
            'note of a place'       => ['SOUR:DATA:EVEN:PLAC:NOTE:SOUR'],
        ];
    }

    #[DataProvider('structures')]
    public function testGedcom7Structures(string $tag): void
    {
        (new Gedcom())->registerTags(Registry::elementFactory(), true);

        self::assertNotInstanceOf(UnknownElement::class, Registry::elementFactory()->make($tag));
    }

    public function testSourcesOfNotes(): void
    {
        (new Gedcom())->registerTags(Registry::elementFactory(), true);

        self::assertInstanceOf(XrefSource::class, Registry::elementFactory()->make('INDI:BIRT:NOTE:SOUR'));
    }

    public function testListsOfEvents(): void
    {
        self::assertSame('BIRT, CHR, MARR', (new EventTypeList(''))->canonical('BIRT,CHR, marr'));
    }
}
