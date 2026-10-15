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

use function array_keys;
use function array_map;
use function array_shift;
use function explode;
use function preg_match;
use function preg_replace;
use function preg_split;
use function str_starts_with;
use function strtr;

/**
 * A GEDCOM structure: a tag, an optional cross-reference identifier, a payload and substructures.
 *
 * Multi-line payloads are stored with "\n".  CONT and CONC lines are not substructures.
 */
final class Gedcom7Node
{
    /**
     * @param list<Gedcom7Node> $children
     */
    public function __construct(
        public string $tag,
        public string $value = '',
        public string $xref = '',
        public bool $pointer = false,
        public array $children = [],
    ) {
    }

    /**
     * Parse a GEDCOM record, with any level numbers, CONT and CONC lines.
     */
    public static function parse(string $gedcom): self
    {
        $root  = new self('');
        $stack = [-1 => $root];
        $last  = $root;

        $lines = preg_split('/\r\n|\r|\n/', $gedcom);

        foreach ($lines === false ? [] : $lines as $line) {
            if ($line === '') {
                continue;
            }

            if (preg_match('/^(\d+) (?:@([^@ ]+)@ )?(\S+)(?: (.*))?$/s', $line, $match) !== 1) {
                // Not a valid line?  Keep the text.
                $last->value .= "\n" . $line;
                continue;
            }

            $level = (int) $match[1];
            $tag   = $match[3];
            $value = $match[4] ?? '';

            if ($tag === 'CONT' && $last !== $root) {
                $last->value .= "\n" . $value;
                continue;
            }

            if ($tag === 'CONC' && $last !== $root) {
                $last->value .= $value;
                continue;
            }

            $node = new self($tag, $value, $level === 0 ? $match[2] : '');

            // Attach to the nearest structure with a lower level.
            $parent_level = $level - 1;

            while (!isset($stack[$parent_level])) {
                $parent_level--;
            }

            $stack[$parent_level]->children[] = $node;

            foreach (array_keys($stack) as $key) {
                if ($key >= $level) {
                    unset($stack[$key]);
                }
            }

            $stack[$level] = $node;
            $last          = $node;
        }

        $record = $root->children[0] ?? new self('');
        $record->findPointers();

        return $record;
    }

    public function child(string $tag): self|null
    {
        foreach ($this->children as $child) {
            if ($child->tag === $tag) {
                return $child;
            }
        }

        return null;
    }

    /**
     * Remove substructures with the given tag, and return them.
     *
     * @return list<Gedcom7Node>
     */
    public function removeChildren(string $tag): array
    {
        $removed  = [];
        $children = [];

        foreach ($this->children as $child) {
            if ($child->tag === $tag) {
                $removed[] = $child;
            } else {
                $children[] = $child;
            }
        }

        $this->children = $children;

        return $removed;
    }

    /**
     * Create GEDCOM 7 lines.  Text is split into CONT lines and leading "@" signs are escaped.
     */
    public function toGedcom(int $level = 0): string
    {
        if ($this->pointer) {
            $lines = [$this->value];
        } else {
            // Banned characters: C0 (except TAB, LF, CR), DEL, C1 and non-characters.
            $text  = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\xC2[\x80-\x9F]|\xEF\xBF[\xBE\xBF]/', "\u{FFFD}", $this->value);
            $lines = explode("\n", strtr($text, ["\r\n" => "\n", "\r" => "\n"]));
            $lines = array_map(self::escape(...), $lines);
        }

        $first  = array_shift($lines);
        $gedcom = $level . ($this->xref === '' ? '' : ' @' . $this->xref . '@') . ' ' . $this->tag . ($first === '' ? '' : ' ' . $first);

        foreach ($lines as $line) {
            $gedcom .= "\n" . ($level + 1) . ' CONT' . ($line === '' ? '' : ' ' . $line);
        }

        foreach ($this->children as $child) {
            $gedcom .= "\n" . $child->toGedcom($level + 1);
        }

        return $gedcom;
    }

    private static function escape(string $line): string
    {
        return str_starts_with($line, '@') ? '@' . $line : $line;
    }

    private function findPointers(): void
    {
        $this->pointer = preg_match('/^@[^@#\s][^@\s]*@$/', $this->value) === 1;

        foreach ($this->children as $child) {
            $child->findPointers();
        }
    }
}
