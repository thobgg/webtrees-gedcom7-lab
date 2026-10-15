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

use Fisharebest\Webtrees\Gedcom;

use function array_flip;
use function array_keys;
use function count;
use function explode;
use function implode;
use function str_starts_with;

/**
 * Export GEDCOM 5.5.1 from a tree that stores GEDCOM 7.0.
 *
 * The working copy is GEDCOM 5.5.1 wherever the existing code needs it, but it keeps other GEDCOM 7.0
 * structures, such as EXID, NO or PHRASE.  These become extension structures, by adding an underscore
 * to their tag.  Pointers to @VOID@ become extension structures without a pointer.
 */
final class Gedcom551Downgrade
{
    /** @var array<string,int>|null The structures of GEDCOM 5.5.1, e.g. "INDI:*:DATE" */
    private static array|null $paths = null;

    public function __construct()
    {
        self::$paths ??= array_flip(array_keys((new Gedcom())->gedcom551Tags()));
    }

    /**
     * Downgrade a record of the working copy.
     */
    public function downgrade(string $gedcom): string
    {
        if ($gedcom === '0 TRLR') {
            return $gedcom;
        }

        $record  = Gedcom7Node::parse($gedcom);
        $changed = $this->node($record, $record->tag);

        return $changed ? Gedcom7WorkingCopy::serialize($record) : $gedcom;
    }

    private function node(Gedcom7Node $node, string $path): bool
    {
        if (str_starts_with($node->tag, '_')) {
            return false;
        }

        if (!$this->isGedcom551($path) || $node->value === '@VOID@') {
            $node->tag = '_' . $node->tag;

            if ($node->value === '@VOID@') {
                $node->value   = '';
                $node->pointer = false;
            }

            return true;
        }

        $changed = false;

        foreach ($node->children as $child) {
            $changed = $this->node($child, $path . ':' . $child->tag) || $changed;
        }

        return $changed;
    }

    /**
     * Is this a GEDCOM 5.5.1 structure?  Events and attributes are written as "*", e.g. "INDI:*:DATE".
     */
    public function isGedcom551(string $path): bool
    {
        if (isset(self::$paths[$path])) {
            return true;
        }

        $parts = explode(':', $path);

        for ($n = 1; $n < count($parts) && $n <= 2; $n++) {
            $wildcard      = $parts;
            $wildcard[$n]  = '*';

            if (isset(self::$paths[implode(':', $wildcard)])) {
                return true;
            }
        }

        return false;
    }
}
