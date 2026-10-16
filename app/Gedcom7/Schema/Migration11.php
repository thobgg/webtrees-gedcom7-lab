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
use Fisharebest\Webtrees\Gedcom7\Gedcom7TreeConverter;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Schema\MigrationInterface;
use Illuminate\Database\Schema\Blueprint;

/**
 * Upgrade the webtrees7 schema from version 11 to version 12.
 */
final readonly class Migration11 implements MigrationInterface
{
    public function upgrade(): void
    {
        // The media_file table stores media types, e.g. "image/jpeg".  SQL Server cannot change a column that is indexed.
        DB::schema()->table('media_file', static function (Blueprint $table): void {
            $table->dropIndex(['m_file', 'multimedia_format']);
        });

        DB::schema()->table('media_file', static function (Blueprint $table): void {
            $table->string('multimedia_format', 255)->change();
        });

        DB::schema()->table('media_file', static function (Blueprint $table): void {
            $table->index(['m_file', 'multimedia_format']);
        });

        Registry::container()->get(Gedcom7TreeConverter::class)->convertTrees(true);
    }
}
