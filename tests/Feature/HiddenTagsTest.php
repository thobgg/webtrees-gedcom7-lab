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
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomEditService;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Site;
use Fisharebest\Webtrees\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(GedcomEditService::class)]
class HiddenTagsTest extends TestCase
{
    public function testHiddenEventsHaveFields(): void
    {
        self::createDatabase();
        (new Gedcom())->registerTags(Registry::elementFactory(), true);
        Site::setPreference('HIDE_CHR', '1');

        $tree       = (new TreeService(new GedcomImportService()))->create('test', 'Test');
        $individual = Registry::individualFactory()->new('I1', "0 @I1@ INDI\n1 NAME John /Smith/\n1 CHR\n2 DATE 1850", null, $tree);
        $service    = new GedcomEditService();

        // A hidden event is not offered, but an existing one can be edited.
        self::assertStringNotContainsString("\n1 CHR\n", $service->insertMissingRecordSubtags(Registry::individualFactory()->new('I2', "0 @I2@ INDI", null, $tree), false));
        self::assertStringContainsString("\n2 PLAC", $service->insertMissingFactSubtags($individual->facts(['CHR'])->first(), false));
    }
}
