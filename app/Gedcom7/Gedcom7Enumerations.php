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

use function array_search;
use function count;
use function in_array;
use function strrchr;
use function strtolower;
use function substr;
use function ucfirst;

/**
 * Values of enumerations that webtrees knows and GEDCOM 7.0 does not, e.g. "PEDI OTHER" + "PHRASE Rada".
 */
final class Gedcom7Enumerations
{
    // Enumerated values that 7.0 renamed, but webtrees uses the 5.5.1 name, see Gedcom7Converter::ENUMERATION_ALIASES
    private const array ALIASES = [
        'ord-STAT' => ['DNS_CAN' => 'DNS/CAN', 'PRE_1970' => 'PRE-1970'],
    ];

    // Events with an LDS ordinance status
    private const array ORDINANCES = ['BAPL', 'CONL', 'ENDL', 'INIL', 'SLGC', 'SLGS'];

    /**
     * The enumeration of a substructure, e.g. "ord-STAT" for BAPL (or INDI:BAPL) + STAT.  Null if it has no webtrees values.
     */
    public static function type(string $parent, string $tag): string|null
    {
        $parent = substr((string) strrchr(':' . $parent, ':'), 1);

        return match (true) {
            $tag === 'PEDI'                                           => 'PEDI',
            $tag === 'TYPE' && $parent === 'NAME'                     => 'NAME-TYPE',
            $tag === 'STAT' && in_array($parent, self::ORDINANCES, true) => 'ord-STAT',
            default                                                   => null,
        };
    }

    /**
     * The webtrees value of an enumeration, e.g. "PEDI OTHER" + "PHRASE Rada" is "RADA".
     */
    public static function value(string $type, string $value, string|null $phrase): string
    {
        if (isset(self::ALIASES[$type][$value])) {
            return self::ALIASES[$type][$value];
        }

        if ($value !== 'OTHER' || $phrase === null) {
            return $value;
        }

        return Gedcom7Record::translatedValue($type, $phrase) ?? Gedcom7Record::code($phrase, Gedcom7Schema::ENUMERATIONS[$type] ?? [], Gedcom7Record::knownValues($type)) ?? $value;
    }

    /**
     * The webtrees values, e.g. "PEDI RADA" for "PEDI OTHER" + "PHRASE Rada", for GEDCOM 5.5.1.
     */
    public static function webtrees(Gedcom7Node $node, string $parent = ''): bool
    {
        $changed = false;
        $type    = self::type($parent, $node->tag);

        if ($type !== null) {
            $phrase = $node->children[0] ?? null;
            $phrase = $phrase instanceof Gedcom7Node && $phrase->tag === 'PHRASE' ? $phrase->value : null;
            $value  = self::value($type, $node->value, $phrase);

            if ($value !== $node->value) {
                if ($node->value === 'OTHER') {
                    Gedcom7Record::takePhrase($node);
                }

                $node->value = $value;
                $changed     = true;
            }
        }

        if ($node->tag === 'EVEN' && $parent === 'SOUR') {
            $changed = self::sourceRole($node) || $changed;
        }

        foreach ($node->children as $child) {
            $changed = self::webtrees($child, $node->tag) || $changed;
        }

        return $changed;
    }

    /**
     * A BCP 47 language tag becomes a GEDCOM 5.5.1 language, e.g. "de" becomes "German".
     */
    public static function language(Gedcom7Node $node): bool
    {
        $name = array_search($node->value, Gedcom7Payload::LANGUAGES, true);

        if ($name === false) {
            return false;
        }

        $node->value = ucfirst(strtolower($name));

        return true;
    }

    /**
     * SOUR:EVEN:ROLE OTHER + PHRASE "Pate" becomes the 5.5.1 "(Pate)".
     */
    private static function sourceRole(Gedcom7Node $event): bool
    {
        $changed = false;

        foreach ($event->children as $role) {
            $phrase = $role->children[0] ?? null;

            if (
                $role->tag === 'ROLE' && $role->value === 'OTHER' && count($role->children) === 1 &&
                $phrase instanceof Gedcom7Node && $phrase->tag === 'PHRASE' && Gedcom7Converter::humanize($phrase->value) === $phrase->value
            ) {
                $role->value    = '(' . $phrase->value . ')';
                $role->children = [];
                $changed        = true;
            }
        }

        return $changed;
    }
}
