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
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function strip_tags;

#[CoversClass(GedcomRecord::class)]
class Gedcom7NameTranslationsTest extends TestCase
{
    public function testTheTranslationIntoTheLanguageOfTheViewerIsShown(): void
    {
        self::createDatabase();
        (new Gedcom())->registerTags(Registry::elementFactory(), true);

        $tree = (new TreeService(new GedcomImportService()))->create('test', 'Test');

        // The tests use English
        $english = Registry::individualFactory()->new('I1', "0 @I1@ INDI\n1 NAME Iwan /Petrow/\n2 TRAN John /Peters/\n3 LANG en-GB\n1 BIRT\n2 DATE 1800", null, $tree);
        $french  = Registry::individualFactory()->new('I2', "0 @I2@ INDI\n1 NAME Iwan /Petrow/\n2 TRAN Jean /Pierre/\n3 LANG fr\n1 BIRT\n2 DATE 1800", null, $tree);

        self::assertSame('John Peters', strip_tags($english->fullName()));
        self::assertSame('Iwan Petrow', strip_tags((string) $english->alternateName()));
        self::assertSame('Iwan Petrow', strip_tags($french->fullName()));
    }
}
