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

use Fisharebest\Webtrees\Elements\NameType;
use Fisharebest\Webtrees\Elements\PedigreeLinkageType;
use Fisharebest\Webtrees\Elements\RelationIsDescriptor;
use Fisharebest\Webtrees\Elements\SourceMediaType;

use function array_flip;
use function array_keys;
use function array_map;
use function array_shift;
use function explode;
use function implode;
use function in_array;
use function preg_match;
use function str_contains;
use function str_starts_with;
use function strtolower;
use function strtoupper;
use function strtr;
use function substr;

/**
 * Create the working copy of a GEDCOM 7.0 record.
 *
 * webtrees7 stores GEDCOM 7.0 as the definitive data.  The existing code reads and writes a working copy,
 * which is the GEDCOM 7.0 without its "@@" escapes.  The existing code reads the remaining GEDCOM 5.5.1 forms,
 * e.g. "PEDI RADA" instead of "PEDI OTHER" + "PHRASE Rada", from the GEDCOM 7.0 (see Gedcom7Enumerations).
 */
final class Gedcom7WorkingCopy
{
    /**
     * Create the working copy of a record.
     */
    public function create(string $gedcom7): string
    {
        $record = Gedcom7Node::parse($gedcom7);

        self::unescape($record);

        return self::serialize($record, 0);
    }

    /**
     * Normalise a GEDCOM 7.0 record, e.g. from an imported file, as Gedcom7Converter writes it.
     */
    public function normalize(string $gedcom7): string
    {
        return (new Gedcom7Converter())->convertRecord($this->create($gedcom7));
    }

    /**
     * 7.0 escapes a leading "@" as "@@".  webtrees stores the unescaped text.
     */
    private static function unescape(Gedcom7Node $node): void
    {
        foreach ($node->children as $child) {
            self::unescape($child);
        }

        if (!$node->pointer && str_contains($node->value, '@@')) {
            $lines       = explode("\n", $node->value);
            $lines       = array_map(static fn (string $line): string => str_starts_with($line, '@@') ? substr($line, 1) : $line, $lines);
            $node->value = implode("\n", $lines);
        }
    }

    /**
     * Remove the first substructure, if it is a PHRASE.  Gedcom7Converter adds phrases as the first substructure.
     */
    public static function takePhrase(Gedcom7Node $node): string|null
    {
        $first = $node->children[0] ?? null;

        if ($first instanceof Gedcom7Node && $first->tag === 'PHRASE' && $first->children === [] && !str_contains($first->value, "\n")) {
            array_shift($node->children);

            return $first->value;
        }

        return null;
    }

    /**
     * Gedcom7Converter turns a code such as "best_man" into the phrase "Best man".  Find a code that webtrees knows,
     * or else use the phrase.
     *
     * @param list<string>      $values Values that would not become a phrase
     * @param array<string,int> $known  Values that webtrees knows
     */
    public static function code(string $phrase, array $values, array $known): string|null
    {
        $candidates = [strtoupper(strtr($phrase, [' ' => '_'])), strtolower(strtr($phrase, [' ' => '_'])), $phrase];

        foreach ($candidates as $n => $candidate) {
            if (
                ($n === 2 || isset($known[$candidate])) &&
                Gedcom7Converter::humanize($candidate) === $phrase &&
                !in_array(strtoupper($candidate), $values, true) &&
                preg_match('/^_[A-Z0-9_]+$/', strtoupper($candidate)) !== 1
            ) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * The values of an enumeration that webtrees knows, e.g. PEDI RADA.
     *
     * @return array<string,int>
     */
    public static function knownValues(string $type): array
    {
        $element = match ($type) {
            'RELA'      => new RelationIsDescriptor(''),
            'NAME-TYPE' => new NameType(''),
            'PEDI'      => new PedigreeLinkageType(''),
            'MEDI'      => new SourceMediaType(''),
            default     => null,
        };

        return $element === null ? [] : array_flip(array_map(static fn (int|string $value): string => (string) $value, array_keys($element->values())));
    }

    /**
     * Create the working copy.  This is GEDCOM as webtrees stores it: CONT lines, but no "@@" escapes.
     */
    public static function serialize(Gedcom7Node $node, int $level = 0): string
    {
        $lines  = $node->pointer ? [$node->value] : explode("\n", $node->value);
        $first  = array_shift($lines);
        $gedcom = $level . ($node->xref === '' ? '' : ' @' . $node->xref . '@') . ' ' . $node->tag . ($first === '' ? '' : ' ' . $first);

        foreach ($lines as $line) {
            $gedcom .= "\n" . ($level + 1) . ' CONT' . ($line === '' ? '' : ' ' . $line);
        }

        foreach ($node->children as $child) {
            $gedcom .= "\n" . self::serialize($child, $level + 1);
        }

        return $gedcom;
    }
}
