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

namespace Fisharebest\Webtrees\Module;

use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Services\DataFixService;
use Fisharebest\Webtrees\Services\LocationLinkService;
use Fisharebest\Webtrees\Tree;
use Illuminate\Support\Collection;

use function preg_replace_callback;
use function str_contains;
use function trim;

/**
 * Link the places of events to the GEDCOM-L location records (_LOC) with the same name or GOV identifier.
 */
class FixPlaceLocations extends AbstractModule implements ModuleDataFixInterface
{
    use ModuleDataFixTrait;

    public function __construct(
        private readonly DataFixService $data_fix_service,
        private readonly LocationLinkService $location_link_service,
    ) {
    }

    public function title(): string
    {
        /* I18N: Name of a module */
        return I18N::translate('Link places to location records');
    }

    public function description(): string
    {
        /* I18N: Description of a “Data fix” module */
        return I18N::translate('Add a link from the place of an event to the location record with the same name or GOV identifier.  Only places that match exactly one location record are linked.');
    }

    /**
     * @param array<string,string> $params
     *
     * @return Collection<int,string>|null
     */
    protected function familiesToFix(Tree $tree, array $params): Collection|null
    {
        return $this->familiesToFixQuery($tree, $params)
            ->where('f_gedcom', 'LIKE', "%\n2 PLAC %")
            ->pluck('f_id');
    }

    /**
     * @param array<string,string> $params
     *
     * @return Collection<int,string>|null
     */
    protected function individualsToFix(Tree $tree, array $params): Collection|null
    {
        return $this->individualsToFixQuery($tree, $params)
            ->where('i_gedcom', 'LIKE', "%\n2 PLAC %")
            ->pluck('i_id');
    }

    /**
     * @param array<string,string> $params
     */
    public function doesRecordNeedUpdate(GedcomRecord $record, array $params): bool
    {
        return $this->updateGedcom($record) !== $record->gedcom();
    }

    /**
     * @param array<string,string> $params
     */
    public function previewUpdate(GedcomRecord $record, array $params): string
    {
        return $this->data_fix_service->gedcomDiff($record->tree(), $record->gedcom(), $this->updateGedcom($record));
    }

    /**
     * @param array<string,string> $params
     */
    public function updateRecord(GedcomRecord $record, array $params): void
    {
        $record->updateRecord($this->updateGedcom($record), false);
    }

    /**
     * Each place without a link gets one, if exactly one location record matches.
     */
    private function updateGedcom(GedcomRecord $record): string
    {
        $tree = $record->tree();

        return (string) preg_replace_callback(
            '/\n2 PLAC (.+)((?:\n[3-9].*)*)/',
            function (array $match) use ($tree): string {
                if (str_contains($match[2], "\n3 _LOC @")) {
                    return $match[0];
                }

                $xref = $this->location_link_service->locationFor($tree, trim($match[1]), LocationLinkService::govOfPlace($match[2]));

                return $xref === null ? $match[0] : $match[0] . "\n3 _LOC @" . $xref . '@';
            },
            $record->gedcom()
        );
    }
}
