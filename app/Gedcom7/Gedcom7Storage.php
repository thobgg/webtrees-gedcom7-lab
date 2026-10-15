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

use Fisharebest\Webtrees\Services\MigrationService;

/**
 * webtrees7 stores GEDCOM 7.0 next to the GEDCOM that the existing code uses.
 * Its schema changes have their own version number, so that they do not clash with the upstream migrations.
 */
final class Gedcom7Storage
{
    public const string SCHEMA_NAMESPACE = '\Fisharebest\Webtrees\Gedcom7\Schema';
    public const string SCHEMA_SETTING   = 'WT7_SCHEMA_VERSION';
    public const int    SCHEMA_VERSION   = 1;

    /**
     * Run after the upstream migrations.
     */
    public static function updateSchema(MigrationService $migration_service): void
    {
        $migration_service->updateSchema(self::SCHEMA_NAMESPACE, self::SCHEMA_SETTING, self::SCHEMA_VERSION);
    }
}
