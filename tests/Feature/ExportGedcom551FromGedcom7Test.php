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

use Fisharebest\Webtrees\Cache;
use Fisharebest\Webtrees\Contracts\CacheFactoryInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Gedcom7\Gedcom551Downgrade;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Storage;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomExportService;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Tests\TestCase;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Cache\Adapter\NullAdapter;

use function fclose;
use function file_get_contents;
use function preg_split;
use function stream_get_contents;
use function trim;

#[CoversClass(Gedcom551Downgrade::class)]
#[CoversClass(GedcomExportService::class)]
class ExportGedcom551FromGedcom7Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::createDatabase();

        // Records are cached during the import.  Don't use them for the export.
        $cache_factory = self::createStub(CacheFactoryInterface::class);
        $cache_factory->method('array')->willReturn(new Cache(new NullAdapter()));
        Registry::cache($cache_factory);
    }

    public function testExportDemoTreeAsGedcom551(): void
    {
        $gedcom_import_service = new GedcomImportService();
        $tree                  = (new TreeService($gedcom_import_service))->create('falkenrath', 'Falkenrath');
        $text                  = (string) file_get_contents(__DIR__ . '/../data/falkenrath-gedcom7.ged');

        // Creating a new tree will create a header and one individual
        DB::table('individuals')->where('i_file', '=', $tree->id())->delete();
        DB::table('other')->where('o_file', '=', $tree->id())->delete();
        DB::table('name')->where('n_file', '=', $tree->id())->delete();

        foreach (preg_split('/\n(?=0 )/', trim($text)) as $record) {
            $gedcom_import_service->importRecord($record, $tree, false);
        }

        self::assertTrue(Gedcom7Storage::isGedcom7Tree($tree));

        $resource = (new GedcomExportService(new Psr17Factory(), new Psr17Factory()))->export(tree: $tree, line_endings: 'LF');
        $gedcom   = (string) stream_get_contents($resource);
        fclose($resource);

        self::assertStringStartsWith("0 HEAD\n", $gedcom);
        self::assertStringContainsString("\n2 VERS 5.5.1\n", $gedcom);
        // GEDCOM-L, as in the GEDCOM 5.5.1 version of the demo tree
        self::assertStringContainsString("\n2 _GODP Martin Hoppe, Bauingenieur, Hannover\n", $gedcom);
        self::assertStringContainsString("\n3 _GOV ", $gedcom);
        self::assertStringContainsString("\n2 _ASSO @", $gedcom);
        self::assertStringContainsString("\n3 ROLE (Pate)\n", $gedcom);
        // No GEDCOM 7.0
        self::assertStringNotContainsString('@VOID@', $gedcom);
        self::assertStringContainsString("\n1 _UID ", $gedcom);
        self::assertDoesNotMatchRegularExpression('/^0 @[^@]+@ (INDI|FAM)(\n[1-9].*)*\n1 UID /m', $gedcom);
        self::assertStringNotContainsString(' SNOTE ', $gedcom);
        self::assertStringNotContainsString(' EXID ', $gedcom);

        // Every structure is GEDCOM 5.5.1, or an extension.
        $downgrade = new Gedcom551Downgrade();

        foreach (preg_split('/\n(?=0 )/', trim($gedcom)) as $record) {
            self::assertSame($record, $downgrade->downgrade($record));
        }
    }
}
