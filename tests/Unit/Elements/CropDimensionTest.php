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

namespace Fisharebest\Webtrees\Tests\Unit\Elements;

use Fisharebest\Webtrees\Elements\CropDimension;
use Fisharebest\Webtrees\Tests\TestCase;
use Fisharebest\Webtrees\Tree;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CropDimension::class)]
class CropDimensionTest extends TestCase
{
    public function testNumbers(): void
    {
        $element = new CropDimension('label');
        $tree    = self::createStub(Tree::class);

        self::assertSame('120', $element->canonical(' 120px '));
        self::assertSame('', $element->canonical(''));
        self::assertStringContainsString('type="number"', $element->edit('id', 'name', '120', $tree));
        self::assertStringContainsString('min="0"', $element->edit('id', 'name', '120', $tree));
        self::assertStringContainsString('value="120"', $element->edit('id', 'name', ' 120px', $tree));
        self::assertStringNotContainsString('<script', $element->edit('id', 'name', '<script>', $tree));
    }
}
