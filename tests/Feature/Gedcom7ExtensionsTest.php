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
use Fisharebest\Webtrees\Gedcom7\Gedcom7Extensions;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Gedcom7Extensions::class)]
class Gedcom7ExtensionsTest extends TestCase
{
    public function testExtensionTags(): void
    {
        self::createDatabase();
        (new Gedcom())->registerTags(Registry::elementFactory(), true);

        $tree = $this->importTree('extensions-gedcom7.ged');
        $tags = Gedcom7Extensions::tags($tree);

        self::assertSame(['_MEMBER', '_SKYPEID', '_UNDOCUMENTED'], array_keys($tags));
        self::assertSame(['count' => 1, 'path' => 'INDI:BIRT:_MEMBER', 'label' => '', 'uri' => 'http://xmlns.com/foaf/0.1/member'], $tags['_MEMBER']);
        self::assertSame('', $tags['_UNDOCUMENTED']['uri']);
        self::assertSame('http://xmlns.com/foaf/0.1/skypeID', $tags['_SKYPEID']['uri']);
    }
}
