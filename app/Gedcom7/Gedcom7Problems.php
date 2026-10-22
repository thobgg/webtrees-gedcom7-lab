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

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Registry;

use function in_array;
use function str_starts_with;
use function strip_tags;

/**
 * What GEDCOM 7.0 does not permit in a new or edited fact, e.g. an LDS status without a date.
 */
final class Gedcom7Problems
{
    /**
     * @return list<string>
     */
    public static function fact(string $record_type, string $gedcom): array
    {
        return $gedcom === '' ? [] : self::check('0 @X@ ' . $record_type . "\n" . $gedcom, false);
    }

    /**
     * @return list<string>
     */
    public static function record(string $gedcom): array
    {
        return self::check($gedcom, true);
    }

    /**
     * @return list<string>
     */
    private static function check(string $gedcom, bool $check_record): array
    {
        $record = Gedcom7Node::parse($gedcom);
        $type   = Gedcom7Schema::records()[$record->tag] ?? null;

        if ($type === null) {
            return [];
        }

        $converted = self::paths(Gedcom7Node::parse((new Gedcom7Converter())->convertRecord($gedcom)), $record->tag);
        $problems  = [];

        if ($check_record) {
            self::node($record, $record->tag, $type, $converted, $problems);
        } else {
            foreach ($record->children as $node) {
                self::node($node, $record->tag . ':' . $node->tag, Gedcom7Schema::substructures($type)[$node->tag][0] ?? null, $converted, $problems);
            }
        }

        return $problems;
    }

    /**
     * @param list<string> $converted The structures that GEDCOM 7.0 keeps, e.g. "INDI:BAPL:STAT"
     * @param list<string> $problems
     */
    private static function node(Gedcom7Node $node, string $path, string|null $type, array $converted, array &$problems): void
    {
        // An extension, unless the schema knows it, e.g. the _LOC of GEDCOM-L
        if ($type === null && str_starts_with($node->tag, '_')) {
            return;
        }

        $label = strip_tags(Registry::elementFactory()->make($path)->label());

        if ($type === null) {
            $problems[] = I18N::translate('%s is not permitted here.', $label);

            return;
        }

        $missing = false;

        foreach (Gedcom7Schema::substructures($type) as $tag => [, $cardinality]) {
            if (str_starts_with($cardinality, '1') && $node->child($tag) === null) {
                $problems[] = I18N::translate('%1$s: %2$s is missing.', $label, strip_tags(Registry::elementFactory()->make($path . ':' . $tag)->label()));
                $missing    = true;
            }
        }

        // e.g. an enumeration without a value
        if (!$missing && !in_array($path, $converted, true)) {
            $problems[] = I18N::translate('%s: the value is missing or not valid.', $label);

            return;
        }

        foreach ($node->children as $child) {
            self::node($child, $path . ':' . $child->tag, Gedcom7Schema::substructures($type)[$child->tag][0] ?? null, $converted, $problems);
        }
    }

    /**
     * @return list<string>
     */
    private static function paths(Gedcom7Node $node, string $path): array
    {
        $paths = [$path];

        foreach ($node->children as $child) {
            foreach (self::paths($child, $path . ':' . $child->tag) as $child_path) {
                $paths[] = $child_path;
            }
        }

        return $paths;
    }
}
