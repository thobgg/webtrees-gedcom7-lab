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

namespace Fisharebest\Webtrees\Factories;

use Fisharebest\Webtrees\Contracts\ElementFactoryInterface;
use Fisharebest\Webtrees\Contracts\ElementInterface;
use Fisharebest\Webtrees\Elements\UnknownElement;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Definitions;

use function array_key_exists;
use function count;
use function explode;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function substr;

/**
 * Make a GEDCOM element.
 */
class ElementFactory implements ElementFactoryInterface
{
    /** @var array<string,ElementInterface> */
    private array $elements = [];

    /** @var array<int,list<array{0:list<string>,1:ElementInterface}>>|null The wildcard tags, by number of levels */
    private array|null $wildcards = null;

    /** @var array<string,ElementInterface|null>|null The elements of the GEDCOM 7.0 structure types, for paths that have none */
    private array|null $types = null;

    /**
     * Create a GEDCOM element that corresponds to a GEDCOM tag.
     * Finds the correct element for all valid tags.
     * Finds a likely element for custom tags.
     *
     * @param string $tag Colon delimited hierarchy, e.g. 'INDI:BIRT:PLAC'
     */
    public function make(string $tag): ElementInterface
    {
        return $this->elements[$tag] ?? $this->findElementByWildcard($tag) ?? $this->findElementByType($tag) ?? new UnknownElement($tag);
    }

    /**
     * Take the substructures of all the elements from GEDCOM 7.0.  Register after all the other tags.
     */
    public function registerSchema(): void
    {
        $this->elements  = Gedcom7Definitions::schemaSubTags($this->elements);
        $this->wildcards = null;
        $this->types     = [];
    }

    /**
     * Register GEDCOM tags.
     *
     * @param array<string,ElementInterface> $elements
     */
    public function registerTags(array $elements): void
    {
        $this->elements  = $elements + $this->elements;
        $this->wildcards = null;
    }

    /**
     * Register more subtags.
     *
     * @param array<string,array<int,array<int,string>>> $subtags
     */
    public function registerSubTags(array $subtags): void
    {
        foreach ($subtags as $tag => $children) {
            foreach ($children as $child) {
                $this->make($tag)->subtag(...$child);
            }
        }
    }

    private function findElementByType(string $tag): ElementInterface|null
    {
        if ($this->types === null) {
            return null;
        }

        $type = Gedcom7Definitions::type($tag, false);

        if ($type === null) {
            return null;
        }

        if (!array_key_exists($type, $this->types)) {
            $this->types[$type] = Gedcom7Definitions::element($type);
        }

        return $this->types[$type];
    }

    private function findElementByWildcard(string $tag): ElementInterface|null
    {
        // Matching every wildcard against every tag is slow.  Group them by the number of levels.
        if ($this->wildcards === null) {
            $this->wildcards = [];

            foreach ($this->elements as $tags => $element) {
                if (str_contains($tags, '*')) {
                    $parts = explode(':', $tags);

                    $this->wildcards[count($parts)][] = [$parts, $element];
                }
            }
        }

        $parts = explode(':', $tag);

        foreach ($this->wildcards[count($parts)] ?? [] as [$wildcard, $element]) {
            foreach ($wildcard as $n => $part) {
                // e.g. "*" or "_FA*"
                if ($part !== $parts[$n] && $part !== '*' && (!str_ends_with($part, '*') || !str_starts_with($parts[$n], substr($part, 0, -1)) || $parts[$n] === substr($part, 0, -1))) {
                    continue 2;
                }
            }

            return $element;
        }

        return null;
    }
}
