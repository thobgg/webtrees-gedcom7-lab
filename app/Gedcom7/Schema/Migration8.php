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
use Fisharebest\Webtrees\Enums\CalendarEscape;
use Fisharebest\Webtrees\Gedcom7\Gedcom7TreeConverter;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Schema\MigrationInterface;

use function array_column;
use function array_keys;

/**
 * Upgrade the webtrees7 schema (WT7_SCHEMA_VERSION) from version 8 to version 9.
 *
 * Dates are GEDCOM 7.0 in the working copy, and the dates table uses the GEDCOM 7.0 calendar names.
 */
final readonly class Migration8 implements MigrationInterface
{
    private const array CALENDARS = [
        '@#DGREGORIAN@' => 'GREGORIAN',
        '@#DJULIAN@'    => 'JULIAN',
        '@#DHEBREW@'    => 'HEBREW',
        '@#DFRENCH R@'  => 'FRENCH_R',
        '@#DHIJRI@'     => '_HIJRI',
        '@#DROMAN@'     => '_ROMAN',
        '@#DJALALI@'    => '_JALALI',
    ];

    public function upgrade(): void
    {
        $calendars = array_column(CalendarEscape::cases(), 'value');

        EnumColumn::change('dates', 'd_type', [...array_keys(self::CALENDARS), ...$calendars]);

        foreach (self::CALENDARS as $old => $new) {
            DB::table('dates')->where('d_type', '=', $old)->update(['d_type' => $new]);
        }

        EnumColumn::change('dates', 'd_type', $calendars);

        Registry::container()->get(Gedcom7TreeConverter::class)->convertTrees(true);
    }
}
