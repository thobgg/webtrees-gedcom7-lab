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

namespace Fisharebest\Webtrees\Gedcom7;

use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\MigrationService;
use Fisharebest\Webtrees\Source;
use Fisharebest\Webtrees\Tree;
use Psr\Http\Message\ServerRequestInterface;

use function hash;
use function in_array;
use function preg_match;
use function preg_replace_callback;
use function str_starts_with;
use function strtoupper;
use function substr;

/**
 * Trees that store GEDCOM 7.0, and the webtrees7 schema.
 */
final class Gedcom7Storage
{
    public const string SCHEMA_NAMESPACE = '\Fisharebest\Webtrees\Gedcom7\Schema';
    public const string SCHEMA_SETTING   = 'WT7_SCHEMA_VERSION';
    public const int    SCHEMA_VERSION   = 16;

    // Tree preference: "1" if the tree stores GEDCOM 7.0
    public const string TREE_PREFERENCE = 'GEDCOM7';

    // Table, XREF column and tree column of each record type
    private const array TABLES = [
        Individual::RECORD_TYPE => ['individuals', 'i_id', 'i_file'],
        Family::RECORD_TYPE     => ['families', 'f_id', 'f_file'],
        Source::RECORD_TYPE     => ['sources', 's_id', 's_file'],
        Media::RECORD_TYPE      => ['media', 'm_id', 'm_file'],
    ];

    private const array OTHER_TABLE = ['other', 'o_id', 'o_file'];

    /**
     * Run after the upstream migrations.
     */
    public static function updateSchema(MigrationService $migration_service): void
    {
        $migration_service->updateSchema(self::SCHEMA_NAMESPACE, self::SCHEMA_SETTING, self::SCHEMA_VERSION);
    }

    /**
     * A pending change in a tree that stores GEDCOM 7.0 is GEDCOM 7.0, like the accepted records.
     */
    public static function pendingChange(Tree $tree, string $gedcom): string
    {
        return self::isGedcom7Tree($tree) ? (new self())->edit($gedcom) : $gedcom;
    }

    /**
     * Does the tree store GEDCOM 7.0?
     */
    public static function isGedcom7Tree(Tree $tree): bool
    {
        return $tree->getPreference(self::TREE_PREFERENCE) === '1';
    }

    /**
     * Does the tree of the current request store GEDCOM 7.0?  For elements, which do not know their tree.
     */
    public static function isGedcom7Request(): bool
    {
        if (!Registry::container()->has(ServerRequestInterface::class)) {
            return false;
        }

        $tree = Registry::container()->get(ServerRequestInterface::class)->getAttribute('tree');

        return $tree instanceof Tree && self::isGedcom7Tree($tree);
    }

    /**
     * Is this the header of a GEDCOM 7.x file?
     */
    public static function isGedcom7Header(string $gedcom): bool
    {
        return preg_match('/^0 HEAD\b(?:\n[1-9].*)*?\n1 GEDC(?:\n[2-9].*)*?\n2 VERS 7\./', $gedcom) === 1;
    }

    /**
     * Is a record GEDCOM 7.0, as webtrees stores it?  e.g. not after a merge with a GEDCOM 5.5.1 tree.
     */
    public static function isGedcom7(string $gedcom): bool
    {
        $converter = new Gedcom7Converter();
        $gedcom7   = $converter->convertRecord($gedcom);

        return $converter->generatedRecords() === [] && (new Gedcom7Record())->create($gedcom7) === $gedcom;
    }

    /**
     * A record is being imported from a file.  The header comes first, and tells us the version of the file.
     */
    public function importingGedcom7(Tree $tree, string $gedcom): bool
    {
        if (str_starts_with($gedcom, '0 HEAD')) {
            $tree->setPreference(self::TREE_PREFERENCE, self::isGedcom7Header($gedcom) ? '1' : '0');
        }

        return self::isGedcom7Tree($tree);
    }

    /**
     * A record from a GEDCOM 7.0 file, as webtrees stores it.  Invalid XREFs are renamed.
     */
    public function record(Tree $tree, string $gedcom7): string
    {
        $record  = new Gedcom7Record();
        $gedcom7 = $record->normalize($gedcom7);
        $gedcom7 = self::shortenXrefs($gedcom7);
        $gedcom7 = self::addXref($tree, $gedcom7);

        return $record->create($gedcom7);
    }

    /**
     * 7.0 permits XREFs of any length.  webtrees stores up to 20 characters.
     */
    private static function shortenXrefs(string $gedcom7): string
    {
        $shorten = static fn (array $match): string => $match[1] . substr($match[2], 0, 11) . '_' . strtoupper(hash('crc32b', $match[2])) . $match[3];

        $gedcom7 = (string) preg_replace_callback('/^(0 @)([A-Z0-9_]{21,})(@ )/', $shorten, $gedcom7);

        return (string) preg_replace_callback('/^(\d+ [A-Z0-9_]+ @)([A-Z0-9_]{21,})(@)$/m', $shorten, $gedcom7);
    }

    /**
     * 7.0 permits records without an XREF, if nothing points to them.  webtrees needs one.
     */
    private static function addXref(Tree $tree, string $gedcom7): string
    {
        if (preg_match('/^0 ([A-Z0-9_]+)\b/', $gedcom7, $match) !== 1 || in_array($match[1], ['HEAD', 'TRLR'], true)) {
            return $gedcom7;
        }

        [$table, $id_column, $tree_column] = self::TABLES[$match[1]] ?? self::OTHER_TABLE;

        $base   = '_X7_' . strtoupper(hash('crc32b', $gedcom7));
        $xref   = $base;
        $suffix = 1;

        // Identical records get the same checksum.
        while (DB::table($table)->where($id_column, '=', $xref)->where($tree_column, '=', $tree->id())->exists()) {
            $xref = $base . '_' . $suffix++;
        }

        return '0 @' . $xref . '@ ' . substr($gedcom7, 2);
    }

    /**
     * An edit, which may contain GEDCOM 5.5.1 forms, e.g. "abt 1850" or "1 RELA godmother".
     */
    public function edit(string $gedcom): string
    {
        $converter = new Gedcom7Converter();
        $gedcom7   = $converter->convertRecord($gedcom);

        // e.g. an inline source, which would need a new record.  The tree conversion creates it.
        if ($converter->generatedRecords() !== []) {
            return $gedcom;
        }

        return (new Gedcom7Record())->create($gedcom7);
    }
}
