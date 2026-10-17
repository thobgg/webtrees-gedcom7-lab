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

use Fisharebest\Webtrees\Elements\AbstractXrefElement;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Storage;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomEditService;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Tests\TestCase;
use Fisharebest\Webtrees\Tree;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(AbstractXrefElement::class)]
class Gedcom7VoidPointersTest extends TestCase
{
    private Tree $tree;

    protected function setUp(): void
    {
        parent::setUp();
        self::createDatabase();
        (new Gedcom())->registerTags(Registry::elementFactory(), true);

        $this->tree = (new TreeService(new GedcomImportService()))->create('test', 'Test');
        $this->tree->setPreference(Gedcom7Storage::TREE_PREFERENCE, '1');
    }

    /**
     * @return array<array{0:string}>
     */
    public static function pointers(): array
    {
        return [
            ['INDI:ASSO'],
            ['INDI:ALIA'],
            ['INDI:ANCI'],
            ['INDI:ADOP:FAMC'],
            ['INDI:BIRT:SOUR'],
            ['INDI:OBJE'],
            ['INDI:SNOTE'],
            ['SOUR:REPO'],
            ['FAM:SUBM'],
        ];
    }

    #[DataProvider('pointers')]
    public function testPointerToNothingIsSelected(string $tag): void
    {
        $html = Registry::elementFactory()->make($tag)->edit('id', 'values[]', '@VOID@', $this->tree);

        self::assertStringContainsString('<option value="@VOID@" selected="selected">', $html);
    }

    #[DataProvider('pointers')]
    public function testPointerToNothingIsOffered(string $tag): void
    {
        $html = Registry::elementFactory()->make($tag)->edit('id', 'values[]', '', $this->tree);

        self::assertMatchesRegularExpression('/<option value="@VOID@"\s*>/', $html);
    }

    public function testNotOfferedInGedcom551Trees(): void
    {
        $this->tree->setPreference(Gedcom7Storage::TREE_PREFERENCE, '0');

        $html = Registry::elementFactory()->make('INDI:ASSO')->edit('id', 'values[]', '', $this->tree);

        self::assertStringNotContainsString('@VOID@', $html);
    }

    public function testSavePointerToNothing(): void
    {
        $gedcom = Registry::container()->get(GedcomEditService::class)->editLinesToGedcom(
            'INDI',
            ['1', '2', '2', '1', '1', '2'],
            ['BIRT', 'ASSO', 'SOUR', 'ALIA', 'ADOP', 'FAMC'],
            ['', '@VOID@', '@VOID@', '@VOID@', '', '@VOID@'],
        );

        self::assertSame("\n1 BIRT\n2 ASSO @VOID@\n2 SOUR @VOID@\n1 ALIA @VOID@\n1 ADOP\n2 FAMC @VOID@", $gedcom);
    }
}
