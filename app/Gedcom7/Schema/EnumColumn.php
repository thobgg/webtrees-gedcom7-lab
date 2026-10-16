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
use Illuminate\Database\Schema\Blueprint;

use function array_map;
use function implode;

/**
 * Change the values that an enum column permits.
 */
final class EnumColumn
{
    /**
     * @param list<string> $values
     */
    public static function change(string $table, string $column, array $values): void
    {
        $prefixed = DB::prefix(identifier: $table);
        $check    = '"' . $column . '" IN (' . implode(', ', array_map(static fn (string $value): string => "'" . $value . "'", $values)) . ')';

        switch (DB::driverName()) {
            case DB::POSTGRESQL:
                // Laravel cannot change an enum column on PostgreSQL.
                DB::exec(sql: 'ALTER TABLE "' . $prefixed . '" DROP CONSTRAINT IF EXISTS "' . $prefixed . '_' . $column . '_check"');
                DB::exec(sql: 'ALTER TABLE "' . $prefixed . '" ADD CONSTRAINT "' . $prefixed . '_' . $column . '_check" CHECK (' . $check . ')');
                break;

            case DB::SQL_SERVER:
                // The check constraint has a generated name.
                $constraints = DB::query()
                    ->from(table: 'sys.check_constraints')
                    ->whereRaw(sql: 'parent_object_id = OBJECT_ID(?)', bindings: [$prefixed])
                    ->where(column: 'definition', operator: 'LIKE', value: '%' . $column . '%')
                    ->pluck(column: 'name');

                foreach ($constraints as $constraint) {
                    DB::exec(sql: 'ALTER TABLE "' . $prefixed . '" DROP CONSTRAINT "' . $constraint . '"');
                }

                DB::exec(sql: 'ALTER TABLE "' . $prefixed . '" ADD CONSTRAINT "' . $prefixed . '_' . $column . '_check" CHECK (' . $check . ')');
                break;

            default:
                DB::schema()->table(table: $table, callback: static function (Blueprint $table) use ($column, $values): void {
                    $table->enum(column: $column, allowed: $values)->change();
                });
                break;
        }
    }
}
