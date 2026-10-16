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

namespace Fisharebest\Webtrees\Elements;

use function preg_match;
use function strtoupper;
use function trim;

/**
 * GEDCOM 7.0 UID.  New values are UUIDs, but values from GEDCOM 5.5.1 (such as the _UID of PAF)
 * and other programs are kept unchanged.
 */
class UniqueIdentifier extends Uid
{
    /**
     * Convert a value to a canonical form.
     */
    public function canonical(string $value): string
    {
        $value = trim($value);

        // A UUID, e.g. "f096b664-5e40-40e2-bb72-c1664a46fe45"
        if (preg_match('/^[0-9A-Fa-f]{8}-?[0-9A-Fa-f]{4}-?[0-9A-Fa-f]{4}-?[0-9A-Fa-f]{4}-?[0-9A-Fa-f]{12}$/', $value) === 1) {
            return parent::canonical($value);
        }

        // A PAF _UID, which is a UUID with a checksum
        if (preg_match('/^[0-9A-Fa-f]{36}$/', $value) === 1) {
            return strtoupper($value);
        }

        return $value;
    }
}
