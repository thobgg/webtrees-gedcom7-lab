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

use Fisharebest\Webtrees\Tree;

use function explode;
use function implode;

/**
 * GEDCOM 7.0 lists of events, e.g. "BIRT, CHR, MARR".  GEDCOM 5.5.1 writes them without spaces.
 */
class EventTypeList extends EventsRecorded
{
    /**
     * Convert a value to a canonical form.
     */
    public function canonical(string $value): string
    {
        return implode(', ', explode(',', parent::canonical($value)));
    }

    /**
     * Display the value of this type of element.
     */
    public function value(string $value, Tree $tree): string
    {
        return (new EventsRecorded(''))->value($value, $tree);
    }
}
