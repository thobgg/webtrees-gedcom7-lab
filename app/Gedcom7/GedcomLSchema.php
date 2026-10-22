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

/**
 * The location records of GEDCOM-L, as data in the format of Gedcom7Schema.
 *
 * GEDCOM-L (the German-speaking developers of genealogy applications) defines shared location records (_LOC) in its
 * addendum to GEDCOM 5.5.1, revision 2 of 2020-12-01, page 13: https://genealogy.net/GEDCOM/
 * GEDCOM 7.0 has no location records; they are planned for a later major version (SPLAC).  Until then, webtrees7
 * treats the structures of GEDCOM-L like those of the standard: the same import, export, edit forms and checks.
 *
 * Where the addendum uses the structures of 5.5.1 (dates, sources, notes, media, coordinates, change dates), the
 * records use the structure types of 7.0, e.g. _LOC:NAME:DATE is a 7.0 DATE.  The GOV identifier (_GOV) is an EXID
 * with the registered type https://gov.genealogy.net/, as it is for places; Gedcom551Downgrade writes _GOV again.
 * The unique identifier (_UID) is a UID.  Hand-written: this file is not generated.
 */
final class GedcomLSchema
{
    /** Structure type of each record, by tag */
    public const array RECORDS = [
        '_LOC' => 'gedcoml-LOC',
    ];

    /** Payload type of each structure type, see Gedcom7Schema::PAYLOADS */
    public const array PAYLOADS = [
        'gedcoml-LOC'          => '',
        'gedcoml-LOC-NAME'     => 'Text',
        'gedcoml-NAME-ABBR'    => 'Text',
        'gedcoml-ABBR-TYPE'    => 'Text',
        'gedcoml-LOC-TYPE'     => 'Text',
        'gedcoml-GOVTYPE'      => 'Text',
        'gedcoml-POST'         => 'Text',
        'gedcoml-MAIDENHEAD'   => 'Text',
        'gedcoml-AIDN'         => 'Text',
        'gedcoml-AIDN-TYPE'    => 'Text',
        'gedcoml-DMGD'         => 'Text',
        'gedcoml-DMGD-TYPE'    => 'Text',
        'gedcoml-LOC-LOC'      => '@_LOC',
        'gedcoml-LOC-LOC-TYPE' => 'Enum',
        'gedcoml-LOC-EVEN'     => 'Text',
        'gedcoml-PLAC-LOC'     => '@_LOC',
    ];

    /**
     * Permitted substructures: structure type => [tag => [structure type, cardinality]].
     * The standard structures that GEDCOM-L extends (PLAC) list only the additional substructures.
     */
    public const array SUBSTRUCTURES = [
        'gedcoml-LOC' => [
            'NAME'        => ['gedcoml-LOC-NAME', '1:M'],
            'TYPE'        => ['gedcoml-LOC-TYPE', '0:M'],
            '_POST'       => ['gedcoml-POST', '0:M'],
            'EXID'        => ['EXID', '0:M'],
            'MAP'         => ['MAP', '0:1'],
            '_MAIDENHEAD' => ['gedcoml-MAIDENHEAD', '0:1'],
            'RELI'        => ['RELI', '0:1'],
            'EVEN'        => ['gedcoml-LOC-EVEN', '0:M'],
            '_LOC'        => ['gedcoml-LOC-LOC', '0:M'],
            '_DMGD'       => ['gedcoml-DMGD', '0:M'],
            '_AIDN'       => ['gedcoml-AIDN', '0:M'],
            'OBJE'        => ['OBJE', '0:M'],
            'NOTE'        => ['NOTE', '0:M'],
            'SNOTE'       => ['SNOTE', '0:M'],
            'SOUR'        => ['SOUR', '0:M'],
            'RESN'        => ['RESN', '0:1'],
            'UID'         => ['UID', '0:M'],
            'CHAN'        => ['CHAN', '0:1'],
            'CREA'        => ['CREA', '0:1'],
        ],
        'gedcoml-LOC-NAME' => [
            'ABBR' => ['gedcoml-NAME-ABBR', '0:M'],
            'DATE' => ['DATE', '0:1'],
            'LANG' => ['LANG', '0:1'],
            'SOUR' => ['SOUR', '0:M'],
        ],
        'gedcoml-NAME-ABBR' => [
            'TYPE' => ['gedcoml-ABBR-TYPE', '0:1'],
        ],
        'gedcoml-LOC-TYPE' => [
            '_GOVTYPE' => ['gedcoml-GOVTYPE', '0:1'],
            'DATE'     => ['DATE', '0:1'],
            'SOUR'     => ['SOUR', '0:M'],
        ],
        'gedcoml-POST' => [
            'DATE' => ['DATE', '0:1'],
            'SOUR' => ['SOUR', '0:M'],
        ],
        'gedcoml-AIDN' => [
            'TYPE' => ['gedcoml-AIDN-TYPE', '1:1'],
            'DATE' => ['DATE', '0:1'],
            'SOUR' => ['SOUR', '0:M'],
        ],
        'gedcoml-DMGD' => [
            'TYPE' => ['gedcoml-DMGD-TYPE', '1:1'],
            'DATE' => ['DATE', '0:1'],
            'SOUR' => ['SOUR', '0:M'],
        ],
        'gedcoml-LOC-LOC' => [
            'TYPE' => ['gedcoml-LOC-LOC-TYPE', '1:1'],
            'DATE' => ['DATE', '0:1'],
            'SOUR' => ['SOUR', '0:M'],
        ],
        // The event details of 7.0, without an age
        'gedcoml-LOC-EVEN' => [
            'ADDR'  => ['ADDR', '0:1'],
            'AGNC'  => ['AGNC', '0:1'],
            'ASSO'  => ['ASSO', '0:M'],
            'CAUS'  => ['CAUS', '0:1'],
            'DATE'  => ['DATE', '0:1'],
            'EMAIL' => ['EMAIL', '0:M'],
            'FAX'   => ['FAX', '0:M'],
            'NOTE'  => ['NOTE', '0:M'],
            'OBJE'  => ['OBJE', '0:M'],
            'PHON'  => ['PHON', '0:M'],
            'PLAC'  => ['PLAC', '0:1'],
            'RELI'  => ['RELI', '0:1'],
            'RESN'  => ['RESN', '0:1'],
            'SDATE' => ['SDATE', '0:1'],
            'SNOTE' => ['SNOTE', '0:M'],
            'SOUR'  => ['SOUR', '0:M'],
            'TYPE'  => ['TYPE', '0:1'],
            'UID'   => ['UID', '0:M'],
            'WWW'   => ['WWW', '0:M'],
        ],
        // The place of an event links to a location record
        'PLAC' => [
            '_LOC'        => ['gedcoml-PLAC-LOC', '0:1'],
            '_POST'       => ['gedcoml-POST', '0:M'],
            '_MAIDENHEAD' => ['gedcoml-MAIDENHEAD', '0:1'],
        ],
    ];

    /** The values of each enumeration */
    public const array ENUMERATIONS = [
        'gedcoml-LOC-LOC-TYPE' => ['POLI', 'RELI', 'GEOG', 'CULT'],
    ];
}
