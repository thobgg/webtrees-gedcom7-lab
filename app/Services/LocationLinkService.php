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

namespace Fisharebest\Webtrees\Services;

use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Converter;
use Fisharebest\Webtrees\Location;
use Fisharebest\Webtrees\Tree;

use function count;
use function implode;
use function in_array;
use function mb_strtolower;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function str_contains;
use function str_starts_with;
use function strtoupper;
use function trim;

use const PREG_SET_ORDER;

/**
 * The GEDCOM-L location records (_LOC) of a tree, and the places that belong to them.
 *
 * A place is linked to a location record by "3 _LOC @L1@".  Without a link, the location record is found by its GOV
 * identifier, or by its name: the names of the record and its parents ("Hof Nr. 1, Bienenbüttel") are the place, or
 * the first parts of the place ("Hof Nr. 1, Bienenbüttel, Uelzen, Niedersachsen, Deutschland").  Only a unique match
 * counts.
 */
class LocationLinkService
{
    // The levels of parent locations that we follow - more than any place name has, and it protects against cycles
    private const int MAX_DEPTH = 10;

    /**
     * @var array<int,array{names:array<string,string>,full:array<string,string>,by_name:array<string,list<string>>,by_gov:array<string,list<string>>,coordinates:array<string,array{0:float,1:float}|null>}>
     */
    private array $index = [];

    public function __construct(private readonly GedcomService $gedcom_service)
    {
    }

    /**
     * The location record of a place, if there is exactly one.
     *
     * @param string      $place The place, as it is written at the event
     * @param string|null $gov   The GOV identifier of the place, if it has one
     *
     * @return string|null The XREF of the location record
     */
    public function locationFor(Tree $tree, string $place, string|null $gov = null): string|null
    {
        $index = $this->index($tree);

        if ($gov !== null) {
            $xrefs = $index['by_gov'][strtoupper(trim($gov))] ?? [];

            if (count($xrefs) === 1) {
                return $xrefs[0];
            }
        }

        $key   = mb_strtolower(trim($place));
        $xrefs = $index['by_name'][$key] ?? [];

        if (count($xrefs) === 1) {
            return $xrefs[0];
        }

        if ($xrefs !== [] || $key === '') {
            return null;
        }

        // The location record is the first part of the place, e.g. a farm whose record ends at the village
        $found = [];

        foreach ($index['by_name'] as $name => $candidates) {
            if ($name !== '' && str_starts_with($key, $name . ', ')) {
                foreach ($candidates as $xref) {
                    $found[] = $xref;
                }
            }
        }

        return count($found) === 1 ? $found[0] : null;
    }

    /**
     * The coordinates (MAP) of a location record.
     *
     * @return array{0:float,1:float}|null latitude, longitude
     */
    public function coordinates(Tree $tree, string $xref): array|null
    {
        return $this->index($tree)['coordinates'][$xref] ?? null;
    }

    /**
     * The coordinates of a place, from its location record.
     *
     * @return array{0:float,1:float}|null latitude, longitude
     */
    public function coordinatesForPlace(Tree $tree, string $place, string|null $gov = null): array|null
    {
        $xref = $this->locationFor($tree, $place, $gov);

        return $xref === null ? null : $this->coordinates($tree, $xref);
    }

    /**
     * The coordinates of the place of an event: the linked location record, or the record with the same name.
     *
     * @return array{0:float,1:float}|null latitude, longitude
     */
    public function coordinatesOfFact(Fact $fact): array|null
    {
        $tree = $fact->record()->tree();
        $xref = $this->locationOfFact($fact);

        if ($xref !== null) {
            return $this->coordinates($tree, $xref);
        }

        if (preg_match('/\n2 PLAC (.+)((?:\n[3-9].*)*)/', $fact->gedcom(), $match) !== 1) {
            return null;
        }

        return $this->coordinatesForPlace($tree, $match[1], self::govOfPlace($match[2]));
    }

    /**
     * The location record that the place of an event links to, e.g. "3 _LOC @L1@".
     */
    public function locationOfFact(Fact $fact): string|null
    {
        return preg_match('/\n3 _LOC @(' . Gedcom::REGEX_XREF . ')@/', $fact->gedcom(), $match) === 1 ? $match[1] : null;
    }

    /**
     * The GOV identifier in the substructures of a place, e.g. "3 EXID CELLE1JO52QO" + "4 TYPE https://gov.genealogy.net/".
     *
     * @param string $gedcom The lines below "2 PLAC"
     */
    public static function govOfPlace(string $gedcom): string|null
    {
        if (preg_match('/\n3 EXID (\S+)\n4 TYPE ' . preg_quote(Gedcom7Converter::EXID_GOV, '/') . '$/m', $gedcom, $match) === 1) {
            return $match[1];
        }

        if (preg_match('/\n3 _GOV (\S+)/', $gedcom, $match) === 1) {
            return $match[1];
        }

        return null;
    }

    /**
     * The name of a location record with the names of its parents, e.g. "Hof Nr. 1, Bienenbüttel".
     */
    public function fullName(Tree $tree, string $xref): string
    {
        return $this->index($tree)['full'][$xref] ?? '';
    }

    /**
     * @return array{names:array<string,string>,full:array<string,string>,by_name:array<string,list<string>>,by_gov:array<string,list<string>>,coordinates:array<string,array{0:float,1:float}|null>}
     */
    private function index(Tree $tree): array
    {
        return $this->index[$tree->id()] ??= $this->build($tree);
    }

    /**
     * @return array{names:array<string,string>,full:array<string,string>,by_name:array<string,list<string>>,by_gov:array<string,list<string>>,coordinates:array<string,array{0:float,1:float}|null>}
     */
    private function build(Tree $tree): array
    {
        $gedcoms = DB::table('other')
            ->where('o_file', '=', $tree->id())
            ->where('o_type', '=', Location::RECORD_TYPE)
            ->pluck('o_gedcom', 'o_id');

        $names       = [];
        $parents     = [];
        $by_gov      = [];
        $coordinates = [];

        foreach ($gedcoms as $xref => $gedcom) {
            $xref   = (string) $xref;
            $gedcom = (string) $gedcom;

            $names[$xref] = preg_match('/\n1 NAME (.+)/', $gedcom, $match) === 1 ? trim($match[1]) : '';

            // The parent in the political hierarchy, or else the first parent
            preg_match_all('/\n1 _LOC @(' . Gedcom::REGEX_XREF . ')@((?:\n[2-9].*)*)/', $gedcom, $matches, PREG_SET_ORDER);

            foreach ($matches as $n => $parent) {
                if ($n === 0 || str_contains($parent[2], "\n2 TYPE POLI")) {
                    $parents[$xref] = $parent[1];
                }
            }

            $gov = self::govOfLocation($gedcom);

            if ($gov !== null) {
                $by_gov[strtoupper($gov)][] = $xref;
            }

            $coordinates[$xref] = $this->coordinatesOfLocation($gedcom);
        }

        $full    = [];
        $by_name = [];

        foreach ($names as $xref => $name) {
            $parts = [];
            $seen  = [];

            for ($next = $xref; $next !== null && !in_array($next, $seen, true) && count($seen) < self::MAX_DEPTH; $next = $parents[$next] ?? null) {
                $seen[] = $next;

                if (($names[$next] ?? '') !== '') {
                    $parts[] = $names[$next];
                }
            }

            $full[$xref] = implode(', ', $parts);

            if ($name !== '') {
                $by_name[mb_strtolower($full[$xref])][] = $xref;
            }
        }

        return [
            'names'       => $names,
            'full'        => $full,
            'by_name'     => $by_name,
            'by_gov'      => $by_gov,
            'coordinates' => $coordinates,
        ];
    }

    /**
     * The GOV identifier of a location record: "1 EXID …" + "2 TYPE https://gov.genealogy.net/", or GEDCOM-L "1 _GOV …".
     */
    private static function govOfLocation(string $gedcom): string|null
    {
        if (preg_match('/\n1 EXID (\S+)\n2 TYPE ' . preg_quote(Gedcom7Converter::EXID_GOV, '/') . '$/m', $gedcom, $match) === 1) {
            return $match[1];
        }

        if (preg_match('/\n1 _GOV (\S+)/', $gedcom, $match) === 1) {
            return $match[1];
        }

        return null;
    }

    /**
     * @return array{0:float,1:float}|null
     */
    private function coordinatesOfLocation(string $gedcom): array|null
    {
        if (preg_match('/\n1 MAP(?:\n[2-9].*)*?\n2 LATI (.+)/', $gedcom, $latitude) !== 1 || preg_match('/\n1 MAP(?:\n[2-9].*)*?\n2 LONG (.+)/', $gedcom, $longitude) !== 1) {
            return null;
        }

        $latitude  = $this->gedcom_service->readLatitude(trim($latitude[1]));
        $longitude = $this->gedcom_service->readLongitude(trim($longitude[1]));

        return $latitude === null || $longitude === null ? null : [$latitude, $longitude];
    }
}
