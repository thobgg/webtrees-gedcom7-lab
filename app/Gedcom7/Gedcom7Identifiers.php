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

use Fisharebest\Webtrees\Contracts\ElementInterface;
use Fisharebest\Webtrees\Elements\FamilySearchFamilyTreeId;
use Fisharebest\Webtrees\Elements\GovIdentifier;
use Fisharebest\Webtrees\I18N;

use function array_pop;
use function count;
use function preg_match;
use function preg_quote;
use function rawurldecode;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * External identifiers are EXID + TYPE in GEDCOM 7.0, e.g. "EXID 123" + "TYPE https://gedcom.io/terms/v7/AFN".
 * GEDCOM 5.5.1 and its extensions have their own tags, e.g. AFN, RFN, RIN, _FSFTID and _GOV.
 */
final class Gedcom7Identifiers
{
    // The GEDCOM 5.5.1 tag of each registered type
    private const array TAGS = [
        Gedcom7Converter::EXID_AFN          => 'AFN',
        Gedcom7Converter::EXID_RFN          => 'RFN',
        Gedcom7Converter::EXID_RIN          => 'RIN',
        Gedcom7Converter::EXID_FAMILYSEARCH => '_FSFTID',
        Gedcom7Converter::EXID_GOV          => '_GOV',
    ];

    /**
     * The name of a type, e.g. "Ancestral file number" for https://gedcom.io/terms/v7/AFN.  Null if it is not registered.
     */
    public static function label(string $type): string|null
    {
        return match (self::TAGS[$type] ?? null) {
            'AFN'     => I18N::translate('Ancestral file number'),
            'RFN'     => I18N::translate('Record file number'),
            'RIN'     => I18N::translate('Record ID number'),
            '_FSFTID' => 'FamilySearch',
            '_GOV'    => 'GOV',
            default   => null,
        };
    }

    /**
     * An element that links an identifier to its website, e.g. FamilySearch.  Null if there is none.
     */
    public static function element(string $type): ElementInterface|null
    {
        return match (self::TAGS[$type] ?? null) {
            '_FSFTID' => new FamilySearchFamilyTreeId(''),
            '_GOV'    => new GovIdentifier(''),
            default   => null,
        };
    }

    /**
     * The record ID number (RIN) of a record, e.g. for the individuals table.
     */
    public static function rin(string $gedcom): string|null
    {
        if (preg_match('/\n1 EXID (.+)\n2 TYPE ' . preg_quote(Gedcom7Converter::EXID_RIN, '/') . '(?:#.*)?$/m', $gedcom, $match) === 1) {
            return $match[1];
        }

        return null;
    }

    /**
     * EXID + TYPE https://gov.genealogy.net/ becomes the GEDCOM-L _GOV, etc., e.g. for GEDCOM 5.5.1.
     */
    public static function downgrade(Gedcom7Node $node): bool
    {
        $type = $node->children[count($node->children) - 1] ?? null;

        if (!$type instanceof Gedcom7Node || $type->tag !== 'TYPE' || $type->children !== []) {
            return false;
        }

        $tag   = self::TAGS[$type->value] ?? null;
        $value = $node->value;

        // "RFN archive:123" has the type https://gedcom.io/terms/v7/RFN#archive
        if ($tag === null && str_starts_with($type->value, Gedcom7Converter::EXID_RFN . '#')) {
            $tag   = 'RFN';
            $value = rawurldecode(substr($type->value, strlen(Gedcom7Converter::EXID_RFN) + 1)) . ':' . $value;
        }

        if ($tag === null) {
            return false;
        }

        array_pop($node->children);
        $node->tag   = $tag;
        $node->value = $value;

        return true;
    }
}
