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

namespace Fisharebest\Webtrees\Gedcom7\Schema;

use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Record;
use Fisharebest\Webtrees\Schema\MigrationInterface;
use Illuminate\Database\Schema\Blueprint;
use RuntimeException;

use function implode;

/**
 * Upgrade the webtrees7 schema from version 15 to version 16.
 */
final readonly class Migration15 implements MigrationInterface
{
    // Table, XREF column, tree column, GEDCOM column, GEDCOM 7.0 column
    private const array TABLES = [
        ['individuals', 'i_id', 'i_file', 'i_gedcom', 'i_gedcom7'],
        ['families', 'f_id', 'f_file', 'f_gedcom', 'f_gedcom7'],
        ['sources', 's_id', 's_file', 's_gedcom', 's_gedcom7'],
        ['media', 'm_id', 'm_file', 'm_gedcom', 'm_gedcom7'],
        ['other', 'o_id', 'o_file', 'o_gedcom', 'o_gedcom7'],
    ];

    public function upgrade(): void
    {
        // The existing columns store GEDCOM 7.0.  Remove the GEDCOM 7.0 columns, if every record agrees with them.
        $record    = new Gedcom7Record();
        $different = [];

        foreach (self::TABLES as [$table, $id_column, $tree_column, $gedcom_column, $gedcom7_column]) {
            if (!DB::schema()->hasColumn($table, $gedcom7_column)) {
                continue;
            }

            DB::table($table)
                ->whereNotNull($gedcom7_column)
                ->orderBy($tree_column)
                ->orderBy($id_column)
                ->select([$id_column, $tree_column, $gedcom_column, $gedcom7_column])
                ->chunk(1000, static function (iterable $rows) use ($record, $id_column, $tree_column, $gedcom_column, $gedcom7_column, &$different): void {
                    foreach ($rows as $row) {
                        if ($record->create((string) $row->{$gedcom7_column}) !== $row->{$gedcom_column}) {
                            $different[] = $row->{$tree_column} . ':' . $row->{$id_column};
                        }
                    }
                });
        }

        if ($different !== []) {
            throw new RuntimeException('GEDCOM 7.0: these records do not agree with their GEDCOM 7.0 (tree:xref): ' . implode(', ', $different));
        }

        foreach (self::TABLES as [$table, , , , $gedcom7_column]) {
            if (DB::schema()->hasColumn($table, $gedcom7_column)) {
                DB::schema()->table($table, static function (Blueprint $table) use ($gedcom7_column): void {
                    $table->dropColumn($gedcom7_column);
                });
            }
        }
    }
}
