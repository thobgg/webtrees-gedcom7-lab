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
use Fisharebest\Webtrees\Gedcom7\Gedcom7Storage;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Tags;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function array_keys;

#[CoversClass(Gedcom7Tags::class)]
class Gedcom7SourcesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::createDatabase();
        (new Gedcom())->registerTags(Registry::elementFactory(), true);
    }

    public function testCitations(): void
    {
        $factory = Registry::elementFactory();

        self::assertSame('0:M', $factory->make('INDI:BIRT:SOUR:DATA')->subtags()['TEXT']);
        self::assertSame('0:1', $factory->make('INDI:BIRT:SOUR:DATA:TEXT')->subtags()['LANG']);
        self::assertSame(['PHRASE', 'ROLE'], array_keys($factory->make('INDI:BIRT:SOUR:EVEN')->subtags()));
        self::assertSame('0:1', $factory->make('SOUR:TEXT')->subtags()['LANG']);
    }

    public function testRestrictionOfNewRecords(): void
    {
        $tree = (new TreeService(new GedcomImportService()))->create('test', 'Test');

        self::assertSame('RESN', Gedcom7Storage::restrictionTag($tree));

        $tree->setPreference(Gedcom7Storage::TREE_PREFERENCE, '1');

        self::assertSame('_RESN', Gedcom7Storage::restrictionTag($tree));
    }
}
