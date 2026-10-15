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
use Fisharebest\Webtrees\Gedcom7\Gedcom7Storage;
use Fisharebest\Webtrees\Gedcom7\Gedcom7TreeConverter;
use Fisharebest\Webtrees\Log;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Schema\MigrationInterface;
use Fisharebest\Webtrees\Tree;

/**
 * Upgrade the webtrees7 schema (WT7_SCHEMA_VERSION) from version 1 to version 2.
 *
 * Convert the existing trees to GEDCOM 7.0.  Trees with pending changes cannot be converted.
 * They are converted by the command tree-gedcom7, or when they are imported again.
 * If this takes too long, the next request continues with the trees that are not yet converted.
 */
final readonly class Migration1 implements MigrationInterface
{
    public function upgrade(): void
    {
        $trees = DB::table('gedcom')
            ->where('gedcom_id', '>', 0)
            ->where('imported', '=', 1)
            ->get()
            ->map(Tree::fromDB(...));

        foreach ($trees as $tree) {
            if (Gedcom7Storage::isGedcom7Tree($tree)) {
                continue;
            }

            if ($tree->hasPendingEdit()) {
                Log::addErrorLog('GEDCOM 7.0: tree ' . $tree->name() . ' has pending changes, and was not converted');
                continue;
            }

            Registry::container()->get(Gedcom7TreeConverter::class)->convert($tree);
        }
    }
}
