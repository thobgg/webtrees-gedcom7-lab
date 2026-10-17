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
use Fisharebest\Webtrees\Encodings\UTF8;
use Fisharebest\Webtrees\Enums\AccessLevel;
use Fisharebest\Webtrees\Enums\GedcomVersion;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Converter;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Schema;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomExportService;
use Fisharebest\Webtrees\Tests\TestCase;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Cache\Adapter\NullAdapter;

use function array_pop;
use function array_slice;
use function count;
use function explode;
use function fclose;
use function is_string;
use function preg_match;
use function str_ends_with;
use function str_starts_with;
use function stream_get_contents;
use function substr;

#[CoversClass(GedcomExportService::class)]
#[CoversClass(Gedcom7Converter::class)]
class ExportGedcom7Test extends TestCase
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

    public function testExportDemoTreeAsGedcom7(): void
    {
        $tree = $this->importTree('demo.ged');

        $gedcom_export_service = new GedcomExportService(new Psr17Factory(), new Psr17Factory());

        // Even if we ask for ANSEL, we get UTF-8.
        $resource = $gedcom_export_service->export(tree: $tree, sort_by_xref: true, encoding: 'ANSEL', line_endings: 'LF', version: GedcomVersion::V70);
        $gedcom   = stream_get_contents($resource);
        fclose($resource);

        self::assertIsString($gedcom);
        self::assertStringStartsWith(UTF8::BYTE_ORDER_MARK . "0 HEAD\n1 GEDC\n2 VERS 7.0\n1 SOUR webtrees\n", $gedcom);
        self::assertStringEndsWith("\n0 TRLR\n", $gedcom);
        self::assertStringNotContainsString(' CONC ', $gedcom);
        self::assertStringNotContainsString("\n1 CHAR ", $gedcom);

        $this->assertValidGedcom7(substr($gedcom, 3));
    }

    public function testExportKeepsTheDocumentationOfExtensions(): void
    {
        $tree = $this->importTree('extensions-gedcom7.ged');

        $gedcom_export_service = new GedcomExportService(new Psr17Factory(), new Psr17Factory());

        $resource = $gedcom_export_service->export(tree: $tree, sort_by_xref: true, line_endings: 'LF', version: GedcomVersion::V70);
        $gedcom   = stream_get_contents($resource);
        fclose($resource);

        self::assertIsString($gedcom);
        self::assertStringContainsString("\n1 SCHMA\n2 TAG _SKYPEID http://xmlns.com/foaf/0.1/skypeID\n2 TAG _MEMBER http://xmlns.com/foaf/0.1/member\n", $gedcom);
        self::assertStringContainsString("\n1 _SKYPEID john.smith\n", $gedcom);
    }

    public function testExportWithPrivacyAsGedcom7(): void
    {
        $tree = $this->importTree('demo.ged');

        $gedcom_export_service = new GedcomExportService(new Psr17Factory(), new Psr17Factory());

        $resource = $gedcom_export_service->export(tree: $tree, access_level: AccessLevel::Public, line_endings: 'LF', version: GedcomVersion::V70);
        $gedcom   = stream_get_contents($resource);
        fclose($resource);

        self::assertIsString($gedcom);
        $this->assertValidGedcom7(substr($gedcom, 3));
    }

    public function testExportGedcom551IsUnchanged(): void
    {
        $tree = $this->importTree('demo.ged');

        $gedcom_export_service = new GedcomExportService(new Psr17Factory(), new Psr17Factory());

        $resource = $gedcom_export_service->export($tree, true);
        $gedcom   = stream_get_contents($resource);
        fclose($resource);

        self::assertIsString($gedcom);
        self::assertStringStartsWith("0 HEAD\r\n1 SOUR webtrees\r\n", $gedcom);
        self::assertStringContainsString("\r\n1 GEDC\r\n2 VERS 5.5.1\r\n2 FORM LINEAGE-LINKED\r\n1 CHAR UTF-8\r\n", $gedcom);
    }

    /**
     * Check the structure of a GEDCOM 7.0 file: line syntax, standard structures
     * in permitted places, cardinality, and pointers to records of the right type.
     */
    private function assertValidGedcom7(string $gedcom): void
    {
        $lines = explode("\n", $gedcom);
        self::assertSame('', array_pop($lines));

        $records = [];

        foreach ($lines as $line) {
            self::assertMatchesRegularExpression('/^(0|[1-9]\d*) (@[A-Z0-9_]+@ )?([A-Z][A-Z0-9_]*|_[A-Z0-9_]+)( (?!@[^@])[^\n]+| @[A-Z0-9_]+@)?$/', $line);

            if (preg_match('/^0 @([A-Z0-9_]+)@ (\S+)/', $line, $match) === 1) {
                self::assertArrayNotHasKey($match[1], $records, 'Duplicate XREF');
                $records[$match[1]] = $match[2];
            }
        }

        self::assertSame('0 HEAD', $lines[0]);
        self::assertSame('0 TRLR', $lines[count($lines) - 1]);

        // Stack of structure types.  null for extensions.
        $types  = [];
        $counts = [];

        foreach ($lines as $line) {
            preg_match('/^(\d+) (?:@[A-Z0-9_]+@ )?(\S+)(?: (.*))?$/', $line, $match);
            $level = (int) $match[1];
            $tag   = $match[2];
            $value = $match[3] ?? '';

            if ($tag === 'CONT') {
                continue;
            }

            $types  = array_slice($types, 0, $level);
            $counts = array_slice($counts, 0, $level + 1);

            if ($level === 0) {
                $type = $tag === 'TRLR' || str_starts_with($tag, '_') ? null : Gedcom7Schema::RECORDS[$tag] ?? null;
                self::assertTrue($tag === 'TRLR' || str_starts_with($tag, '_') || is_string($type), 'Unknown record ' . $tag);
            } elseif ($types[$level - 1] === null || str_starts_with($tag, '_')) {
                $type = null;
            } else {
                $parent = $types[$level - 1];
                self::assertArrayHasKey($tag, Gedcom7Schema::SUBSTRUCTURES[$parent] ?? [], $tag . ' is not permitted in ' . $parent);
                [$type, $cardinality] = Gedcom7Schema::SUBSTRUCTURES[$parent][$tag];

                $counts[$level][$type] = ($counts[$level][$type] ?? 0) + 1;

                if (str_ends_with($cardinality, ':1')) {
                    self::assertSame(1, $counts[$level][$type], $tag . ' may only occur once in ' . $parent);
                }

                $payload = Gedcom7Schema::PAYLOADS[$type];

                if (str_starts_with($payload, '@')) {
                    if ($value !== '@VOID@') {
                        self::assertSame(substr($payload, 1), $records[substr($value, 1, -1)] ?? null, 'Pointer ' . $line);
                    }
                } else {
                    self::assertDoesNotMatchRegularExpression('/^@[A-Z0-9_]+@$/', $value, 'Unexpected pointer ' . $line);
                }
            }

            $types[$level] = $type;
        }
    }
}
