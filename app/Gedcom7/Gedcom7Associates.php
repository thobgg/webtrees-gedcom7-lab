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
use function array_slice;
use function str_contains;
use function strcasecmp;
use function strtolower;
use function strtoupper;
use function strtr;
use function ucfirst;

/**
 * Associates (ASSO) have a ROLE in GEDCOM 7.0, and a relationship (RELA) in GEDCOM 5.5.1.
 * webtrees knows more relationships than GEDCOM 7.0 has roles.  These are written as OTHER + PHRASE, e.g. "Servant".
 */
final class Gedcom7Associates
{
    /**
     * The relationship for a role, e.g. "GODP" + "Godmother" is "godmother".  Null if there is none.
     */
    public static function relationship(string $role, string|null $phrase): string|null
    {
        $known = Gedcom7WorkingCopy::knownValues('RELA');

        if ($phrase === null) {
            $key = array_search($role, Gedcom7Converter::ROLES, true);

            return $key === false ? null : self::code($key, $known);
        }

        foreach (Gedcom7Converter::ROLES_WITH_PHRASE as $key => $base) {
            if ($base === $role && strcasecmp(Gedcom7Converter::humanize($key), $phrase) === 0) {
                return self::code($key, $known);
            }
        }

        if ($role !== 'OTHER') {
            return null;
        }

        $value = Gedcom7WorkingCopy::code($phrase, [], $known);
        $key   = strtoupper(strtr((string) $value, [' ' => '_']));

        // e.g. "Godparent" would become ROLE GODP.
        if (isset(Gedcom7Converter::ROLES[$key]) || isset(Gedcom7Converter::ROLES_WITH_PHRASE[$key])) {
            return null;
        }

        return $value;
    }

    /**
     * ASSO:ROLE becomes ASSO:RELA, where there is a relationship.
     */
    public static function roles(Gedcom7Node $asso): void
    {
        foreach ($asso->children as $role) {
            if ($role->tag !== 'ROLE') {
                continue;
            }

            $phrase = $role->children[0] ?? null;
            $phrase = $phrase instanceof Gedcom7Node && $phrase->tag === 'PHRASE' && $phrase->children === [] && !str_contains($phrase->value, "\n") ? $phrase : null;
            $value  = self::relationship($role->value, $phrase?->value);

            if ($value !== null) {
                $role->tag      = 'RELA';
                $role->value    = $value;
                $role->children = $phrase === null ? $role->children : array_slice($role->children, 1);
            }
        }
    }

    /**
     * ASSO @VOID@ + PHRASE + ROLE GODP becomes the GEDCOM-L _GODP, and ROLE WITN becomes _WITN.
     * The ROLE may already have become RELA.
     */
    public static function godparents(Gedcom7Node $event): void
    {
        foreach ($event->children as $asso) {
            [$phrase, $role] = [$asso->children[0] ?? null, $asso->children[1] ?? null];

            if (
                $asso->tag !== 'ASSO' || !$asso->pointer || $asso->value !== '@VOID@' ||
                !$phrase instanceof Gedcom7Node || $phrase->tag !== 'PHRASE' || $phrase->children !== [] || str_contains($phrase->value, "\n") ||
                !$role instanceof Gedcom7Node || $role->children !== []
            ) {
                continue;
            }

            $tag = match ($role->tag . ' ' . $role->value) {
                'ROLE GODP', 'RELA godparent' => '_GODP',
                'ROLE WITN', 'RELA witness'   => '_WITN',
                default                       => null,
            };

            if ($tag !== null) {
                $asso->tag      = $tag;
                $asso->value    = $phrase->value;
                $asso->pointer  = false;
                $asso->children = array_slice($asso->children, 2);
            }
        }
    }

    /**
     * webtrees edits relationships, not roles.  e.g. "2 ASSO @I1@\n3 ROLE OTHER\n4 PHRASE Servant" is edited as "3 RELA servant".
     * When the edit is accepted, the relationship becomes a role again.
     */
    public static function forEditing(string $gedcom, int $level): string
    {
        $node = Gedcom7Node::parse($gedcom);

        if (!self::rolesForEditing($node)) {
            return $gedcom;
        }

        return Gedcom7WorkingCopy::serialize($node, $level);
    }

    private static function rolesForEditing(Gedcom7Node $node): bool
    {
        $changed = false;

        foreach ($node->children as $child) {
            if ($child->tag === 'ASSO') {
                $before = Gedcom7WorkingCopy::serialize($child);
                self::roles($child);
                $changed = $changed || Gedcom7WorkingCopy::serialize($child) !== $before;
            } else {
                $changed = self::rolesForEditing($child) || $changed;
            }
        }

        return $changed;
    }

    /**
     * @param array<string,int> $known
     */
    private static function code(string $key, array $known): string
    {
        $code = strtolower($key);

        return isset($known[$code]) ? $code : ucfirst($code);
    }
}
