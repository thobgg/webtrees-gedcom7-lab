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
use Fisharebest\Webtrees\Http\Controllers\CheckGedcom7;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CheckGedcom7::class)]
class CheckGedcom7Test extends TestCase
{
    /**
     * @param list<string> $levels
     * @param list<string> $tags
     * @param list<string> $values
     */
    private function check(string $scope, array $levels, array $tags, array $values): string
    {
        $request = self::createRequest('POST', [], ['levels' => $levels, 'tags' => $tags, 'values' => $values], [], ['record_type' => 'INDI', 'scope' => $scope]);

        return (string) Registry::container()->get(CheckGedcom7::class)->post($request)->getBody();
    }

    public function testFacts(): void
    {
        self::createDatabase();
        (new Gedcom())->registerTags(Registry::elementFactory(), true);

        self::assertStringContainsString('Status change date is missing', $this->check('fact', ['1', '2'], ['BAPL', 'STAT'], ['', 'BIC']));
        self::assertSame('', trim($this->check('fact', ['1', '2', '3'], ['BAPL', 'STAT', 'DATE'], ['', 'BIC', '1 JAN 2000'])));
        self::assertStringContainsString('the value is missing or not valid', $this->check('record', ['1', '2'], ['NO', 'DATE'], ['', 'FROM 1850']));
    }
}
