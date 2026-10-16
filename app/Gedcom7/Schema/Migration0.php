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
use Fisharebest\Webtrees\Schema\MigrationInterface;
use Illuminate\Database\Schema\Blueprint;

/**
 * Upgrade the webtrees7 schema from version 0 to version 1.
 */
final readonly class Migration0 implements MigrationInterface
{
    // GEDCOM 7.0 is stored next to the existing GEDCOM column, which becomes a working copy for existing code.
    private const array GEDCOM7_COLUMNS = [
        'individuals' => 'i_gedcom7',
        'families'    => 'f_gedcom7',
        'sources'     => 's_gedcom7',
        'media'       => 'm_gedcom7',
        'other'       => 'o_gedcom7',
    ];

    public function upgrade(): void
    {
        foreach (self::GEDCOM7_COLUMNS as $table => $column) {
            if (!DB::schema()->hasColumn(table: $table, column: $column)) {
                DB::schema()->table(table: $table, callback: static function (Blueprint $table) use ($column): void {
                    // Null until the tree has been converted to GEDCOM 7.0.
                    $table->longText(column: $column)->nullable();
                });
            }
        }

        // GEDCOM 7.0 adds SEX X.
        EnumColumn::change('individuals', 'i_sex', ['U', 'M', 'F', 'X']);
    }
}
