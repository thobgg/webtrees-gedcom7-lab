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

use Fisharebest\Webtrees\Gedcom7\Gedcom7TreeConverter;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Schema\MigrationInterface;

/**
 * Upgrade the webtrees7 schema (WT7_SCHEMA_VERSION) from version 13 to version 14.
 *
 * External identifiers (EXID) and unique identifiers (UID) are GEDCOM 7.0 in the working copy.
 * Convert all trees again, to update their working copy.  Their GEDCOM 7.0 does not change.
 */
final readonly class Migration13 implements MigrationInterface
{
    public function upgrade(): void
    {
        Registry::container()->get(Gedcom7TreeConverter::class)->convertTrees(true);
    }
}
