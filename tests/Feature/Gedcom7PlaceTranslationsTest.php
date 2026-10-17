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

use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

use function strip_tags;
use function strpos;
use function view;

#[CoversNothing]
class Gedcom7PlaceTranslationsTest extends TestCase
{
    public function testTranslationsInTheLanguageOfTheViewerComeFirst(): void
    {
        self::createDatabase();
        (new Gedcom())->registerTags(Registry::elementFactory(), true);

        $tree       = (new TreeService(new GedcomImportService()))->create('test', 'Test');
        $individual = Registry::individualFactory()->new('I1', "0 @I1@ INDI\n1 BIRT\n2 PLAC München\n3 TRAN Monaco di Baviera\n4 LANG it\n3 TRAN Munich\n4 LANG en", null, $tree);
        $fact       = new Fact("1 BIRT\n2 PLAC München\n3 TRAN Monaco di Baviera\n4 LANG it\n3 TRAN Munich\n4 LANG en", $individual, 'birth');
        $html       = strip_tags(view('fact-place', ['fact' => $fact, 'record' => $individual, 'cal_link' => 'false', 'time' => false]));

        // The tests use English
        self::assertNotFalse(strpos($html, 'Munich'));
        self::assertNotFalse(strpos($html, 'Monaco di Baviera'));
        self::assertLessThan(strpos($html, 'Monaco di Baviera'), strpos($html, 'Munich'));
    }
}
