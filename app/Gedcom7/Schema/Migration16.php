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
use Fisharebest\Webtrees\Gedcom7\Gedcom7Schema;
use Fisharebest\Webtrees\Schema\MigrationInterface;

use function array_filter;
use function explode;
use function implode;
use function str_starts_with;

/**
 * Upgrade the webtrees7 schema from version 16 to version 17.
 */
final readonly class Migration16 implements MigrationInterface
{
    // Tree preferences that list facts, and the record that has them
    private const array FACT_LISTS = [
        'CHART_BOX_TAGS'          => 'record-INDI',
        'FAM_FACTS_QUICK'         => 'record-FAM',
        'INDI_FACTS_QUICK'        => 'record-INDI',
        'QUICK_REQUIRED_FACTS'    => 'record-INDI',
        'QUICK_REQUIRED_FAMFACTS' => 'record-FAM',
    ];

    // Site preferences of GEDCOM 5.5.1, and their GEDCOM 7.0 replacements
    private const array RENAMED = [
        'HIDE_NAME_ROMN' => 'HIDE_NAME_TRAN',
        'HIDE_PLAC_ROMN' => 'HIDE_PLAC_TRAN',
    ];

    // Site preferences for tags that GEDCOM 7.0 does not have, or that it always has
    private const array REMOVED = [
        'CUSTOM_FAM_FACT',
        'CUSTOM_FAM_NCHI',
        'CUSTOM_RESI_VALUE',
        'CUSTOM_TIME_TAGS',
        'HIDE_AFN',
        'HIDE_NAME_FONE',
        'HIDE_PLAC_FONE',
        'HIDE_RFN',
        'HIDE_RIN',
    ];

    public function upgrade(): void
    {
        // The facts of GEDCOM 7.0, and extensions, e.g. AFN is not in GEDCOM 7.0
        foreach (self::FACT_LISTS as $setting => $type) {
            $rows = DB::table('gedcom_setting')->where('setting_name', '=', $setting)->get();

            foreach ($rows as $row) {
                $tags = array_filter(
                    explode(',', (string) $row->setting_value),
                    static fn (string $tag): bool => isset(Gedcom7Schema::SUBSTRUCTURES[$type][$tag]) || str_starts_with($tag, '_')
                );

                DB::table('gedcom_setting')
                    ->where('gedcom_id', '=', $row->gedcom_id)
                    ->where('setting_name', '=', $setting)
                    ->update(['setting_value' => implode(',', $tags)]);
            }
        }

        foreach (self::RENAMED as $old => $new) {
            $value = DB::table('site_setting')->where('setting_name', '=', $old)->value('setting_value');

            if ($value !== null) {
                DB::table('site_setting')->updateOrInsert(['setting_name' => $new], ['setting_value' => $value]);
                DB::table('site_setting')->where('setting_name', '=', $old)->delete();
            }
        }

        DB::table('site_setting')->whereIn('setting_name', self::REMOVED)->delete();

        // FAM:FACT is in GEDCOM 7.0
        $value = DB::table('site_setting')->where('setting_name', '=', 'CUSTOM_FAMILY_TAGS')->value('setting_value');

        if ($value !== null) {
            $tags = array_filter(explode(',', (string) $value), static fn (string $tag): bool => $tag !== 'FACT');

            DB::table('site_setting')->where('setting_name', '=', 'CUSTOM_FAMILY_TAGS')->update(['setting_value' => implode(',', $tags)]);
        }
    }
}
