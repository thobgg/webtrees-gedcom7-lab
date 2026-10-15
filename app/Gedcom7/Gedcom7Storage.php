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
use Fisharebest\Webtrees\Log;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\Services\MigrationService;
use Fisharebest\Webtrees\Source;
use Fisharebest\Webtrees\Tree;

use function array_values;
use function hash;
use function in_array;
use function preg_match;
use function preg_replace_callback;
use function str_starts_with;
use function strtoupper;
use function substr;

/**
 * webtrees7 stores GEDCOM 7.0 next to the GEDCOM that the existing code uses.
 *
 * The existing code reads and writes the working copy (see Gedcom7WorkingCopy) in the existing columns.
 * Whenever a record is written, its GEDCOM 7.0 is created from the working copy, so the two always agree.
 * Its schema changes have their own version number, so that they do not clash with the upstream migrations.
 */
final class Gedcom7Storage
{
    public const string SCHEMA_NAMESPACE = '\Fisharebest\Webtrees\Gedcom7\Schema';
    public const string SCHEMA_SETTING   = 'WT7_SCHEMA_VERSION';
    public const int    SCHEMA_VERSION   = 5;

    // Tree preference: "1" if the tree stores GEDCOM 7.0
    public const string TREE_PREFERENCE = 'GEDCOM7';

    // Table, XREF column, tree column and GEDCOM 7.0 column of each record type
    private const array TABLES = [
        Individual::RECORD_TYPE => ['individuals', 'i_id', 'i_file', 'i_gedcom7'],
        Family::RECORD_TYPE     => ['families', 'f_id', 'f_file', 'f_gedcom7'],
        Source::RECORD_TYPE     => ['sources', 's_id', 's_file', 's_gedcom7'],
        Media::RECORD_TYPE      => ['media', 'm_id', 'm_file', 'm_gedcom7'],
    ];

    private const array OTHER_TABLE = ['other', 'o_id', 'o_file', 'o_gedcom7'];

    /**
     * Run after the upstream migrations.
     */
    public static function updateSchema(MigrationService $migration_service): void
    {
        $migration_service->updateSchema(self::SCHEMA_NAMESPACE, self::SCHEMA_SETTING, self::SCHEMA_VERSION);
    }

    /**
     * Does the tree store GEDCOM 7.0?
     */
    public static function isGedcom7Tree(Tree $tree): bool
    {
        return $tree->getPreference(self::TREE_PREFERENCE) === '1';
    }

    /**
     * Is this the header of a GEDCOM 7.x file?
     */
    public static function isGedcom7Header(string $gedcom): bool
    {
        return preg_match('/^0 HEAD\b(?:\n[1-9].*)*?\n1 GEDC(?:\n[2-9].*)*?\n2 VERS 7\./', $gedcom) === 1;
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
     * The working copy of a record from a GEDCOM 7.0 file.
     * Invalid cross-reference identifiers are renamed, consistently in records and pointers.
     */
    public function workingCopy(Tree $tree, string $gedcom7): string
    {
        $working_copy = new Gedcom7WorkingCopy();
        $gedcom7      = $working_copy->normalize($gedcom7);
        $gedcom7      = self::shortenXrefs($gedcom7);
        $gedcom7      = self::addXref($tree, $gedcom7);

        return $working_copy->create($gedcom7);
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
     * Create the GEDCOM 7.0 of every record in a tree, e.g. after the existing code has changed the working copy directly.
     *
     * @return int The number of records that need new records, e.g. for inline sources, and could not be stored
     */
    public function rebuildTree(Tree $tree): int
    {
        $failed = 0;

        foreach ([...array_values(self::TABLES), self::OTHER_TABLE] as [$table, $id_column, $tree_column, $gedcom7_column]) {
            $gedcom_column = substr($gedcom7_column, 0, -1);

            DB::table($table)
                ->where($tree_column, '=', $tree->id())
                ->orderBy($id_column)
                ->select([$id_column, $gedcom_column])
                ->chunk(1000, static function (iterable $rows) use ($tree, $table, $id_column, $tree_column, $gedcom_column, $gedcom7_column, &$failed): void {
                    foreach ($rows as $row) {
                        $converter = new Gedcom7Converter();
                        $gedcom7   = $converter->convertRecord($row->{$gedcom_column});

                        if ($converter->generatedRecords() !== []) {
                            $gedcom7 = null;
                            $failed++;
                        }

                        DB::table($table)
                            ->where($id_column, '=', $row->{$id_column})
                            ->where($tree_column, '=', $tree->id())
                            ->update([$gedcom7_column => $gedcom7]);
                    }
                });
        }

        return $failed;
    }

    /**
     * A record has been written to the database.  Create its GEDCOM 7.0.
     */
    public function store(Tree $tree, string $xref, string $type, string $gedcom): void
    {
        if (!self::isGedcom7Tree($tree)) {
            return;
        }

        $converter = new Gedcom7Converter();
        $gedcom7   = $converter->convertRecord($gedcom);

        // e.g. an inline source, which would need a new record.  Leave it for the tree conversion.
        if ($converter->generatedRecords() !== []) {
            Log::addErrorLog('GEDCOM 7.0: cannot store ' . $type . ' ' . $xref . ', it needs new records');
            $gedcom7 = null;
        }

        [$table, $id_column, $tree_column, $gedcom7_column] = self::TABLES[$type] ?? self::OTHER_TABLE;

        DB::table($table)
            ->where($id_column, '=', $xref)
            ->where($tree_column, '=', $tree->id())
            ->update([$gedcom7_column => $gedcom7]);
    }
}
