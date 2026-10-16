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

use Fisharebest\Webtrees\Elements\NamePieceGiven;
use Fisharebest\Webtrees\Elements\RelationIsDescriptor;
use Fisharebest\Webtrees\Elements\UnknownElement;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Tags;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Gedcom7Tags::class)]
class Gedcom7TagsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::createDatabase();
    }

    public function testTags(): void
    {
        $tags = (new Gedcom7Tags())->tags();

        // Shared notes have the substructures of note records.
        self::assertArrayHasKey('SNOTE:SOUR:PAGE', $tags);
        self::assertArrayHasKey('SNOTE:RESN', $tags);
        self::assertArrayHasKey('SNOTE:MIME', $tags);
        // Translations have the substructures of romanizations.
        self::assertInstanceOf(NamePieceGiven::class, $tags['INDI:NAME:TRAN:GIVN']);
        self::assertArrayHasKey('INDI:*:PLAC:TRAN', $tags);
        self::assertInstanceOf(RelationIsDescriptor::class, $tags['FAM:*:ASSO:RELA']);
    }

    public function testTagsAreRegistered(): void
    {
        (new Gedcom())->registerTags(Registry::elementFactory(), true);

        self::assertNotInstanceOf(UnknownElement::class, Registry::elementFactory()->make('INDI:NAME:TRAN'));
        self::assertNotInstanceOf(UnknownElement::class, Registry::elementFactory()->make('OBJE:EXID'));
    }
}
