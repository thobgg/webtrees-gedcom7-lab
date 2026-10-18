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

namespace Fisharebest\Webtrees\Tests\Unit\Factories;

use Fisharebest\Webtrees\Elements\CustomElement;
use Fisharebest\Webtrees\Elements\UnknownElement;
use Fisharebest\Webtrees\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Fisharebest\Webtrees\Factories\ElementFactory;

#[CoversClass(ElementFactory::class)]
class ElementFactoryTest extends TestCase
{
    public function testClass(): void
    {
        self::assertTrue(class_exists(ElementFactory::class));
    }

    public function testWildcards(): void
    {
        $factory = new ElementFactory();
        $place   = new CustomElement('Place');
        $label   = new CustomElement('Label');

        $factory->registerTags(['INDI:*:PLAC' => $place, 'HEAD:_SCHEMA:INDI:_FA*:LABL' => $label]);

        self::assertSame($place, $factory->make('INDI:BIRT:PLAC'));
        self::assertSame($label, $factory->make('HEAD:_SCHEMA:INDI:_FATHER:LABL'));
        // Not in GEDCOM 7.0, so there is no element for its structure type
        self::assertInstanceOf(UnknownElement::class, $factory->make('INDI:BIRT:PLAC:ROMN'));
        self::assertInstanceOf(UnknownElement::class, $factory->make('HEAD:_SCHEMA:INDI:_FA:LABL'));
        self::assertInstanceOf(UnknownElement::class, $factory->make('HEAD:_SCHEMA:INDI:_MOTHER:LABL'));
    }
}
