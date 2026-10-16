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
use function array_pop;
use function array_search;
use function count;
use function explode;
use function implode;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use function str_ends_with;
use function str_starts_with;
use function strtr;
use function substr;
use function substr_count;

/**
 * Export GEDCOM 5.5.1 from a tree that stores GEDCOM 7.0.
 *
 * Dates, ages, media files, translations, identifiers, enumerations and languages become GEDCOM 5.5.1.
 * Other GEDCOM 7.0 structures, such as NO or PHRASE, become extension structures, by adding an underscore
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
     * Downgrade a record, as webtrees stores it.
     */
    public function downgrade(string $gedcom): string
    {
        if ($gedcom === '0 TRLR') {
            return $gedcom;
        }

        $record  = Gedcom7Node::parse($gedcom);
        $changed = Gedcom7Enumerations::webtrees($record);
        $changed = $this->node($record, $record->tag) || $changed;

        return $changed ? Gedcom7Record::serialize($record) : $gedcom;
    }

    private function node(Gedcom7Node $node, string $path): bool
    {
        if (str_starts_with($node->tag, '_')) {
            return $path === '_LOC' && self::location($node);
        }

        // Shared notes are NOTE records in 5.5.1
        if ($node->tag === 'SNOTE' && $node->value !== '@VOID@') {
            $node->tag = 'NOTE';
            $path      = substr($path, 0, -5) . 'NOTE';

            $this->node($node, $path);

            return true;
        }

        // Associates have a relationship, not a role
        if ($node->tag === 'ASSO') {
            $before = Gedcom7Record::serialize($node);
            Gedcom7Associates::roles($node);

            // webtrees and GEDCOM-L use _ASSO for the associates of an event, with the substructures of ASSO
            if (substr_count($path, ':') === 2 && $node->value !== '@VOID@') {
                $node->tag = '_ASSO';

                foreach ($node->children as $child) {
                    $this->node($child, 'INDI:ASSO:' . $child->tag);
                }

                return true;
            }

            $changed = Gedcom7Record::serialize($node) !== $before;
        } else {
            $changed = false;
        }

        // Godparents and witnesses without a record
        if (substr_count($path, ':') === 1) {
            $before = Gedcom7Record::serialize($node);
            Gedcom7Associates::godparents($node);
            $changed = $changed || Gedcom7Record::serialize($node) !== $before;
        }

        // Media files have a file name, a file extension and a media type
        if ($path === 'OBJE:FILE' || $path === 'SOUR:REPO:CALN:MEDI') {
            $before = Gedcom7Record::serialize($node);

            if ($path === 'OBJE:FILE') {
                Gedcom7Media::downgrade($node);
            } else {
                Gedcom7Media::medium($node);
            }

            $changed = $changed || Gedcom7Record::serialize($node) !== $before;
        }

        // External identifiers, e.g. AFN or the GEDCOM-L _GOV
        if ($node->tag === 'EXID' && Gedcom7Identifiers::downgrade($node)) {
            if (str_starts_with($node->tag, '_')) {
                return true;
            }

            $path    = substr($path, 0, -4) . $node->tag;
            $changed = true;
        }

        // Romanized and phonetic names and places
        if ($node->tag === 'TRAN' && (str_ends_with($path, ':NAME:TRAN') || str_ends_with($path, ':PLAC:TRAN')) && self::translation($node)) {
            $path    = substr($path, 0, -4) . $node->tag;
            $changed = true;
        }

        if (!$this->isGedcom551($path) || $node->value === '@VOID@') {
            $node->tag = '_' . $node->tag;

            if ($node->value === '@VOID@') {
                $node->value   = '';
                $node->pointer = false;
            }

            return true;
        }

        $changed = $node->tag === 'DATE' && self::date($node) || $changed;
        $changed = $node->tag === 'AGE' && self::age($node) || $changed;
        $changed = $node->tag === 'LANG' && Gedcom7Enumerations::language($node) || $changed;

        foreach ($node->children as $child) {
            $changed = $this->node($child, $path . ':' . $child->tag) || $changed;
        }

        return $changed;
    }

    /**
     * "JULIAN 1700" becomes "@#DJULIAN@ 1700", "100 BCE" becomes "100 B.C.", a PHRASE becomes "INT … (…)".
     */
    private static function date(Gedcom7Node $node): bool
    {
        $calendars = array_flip(Gedcom7Payload::CALENDARS);
        $original  = $node->value;

        $node->value = (string) preg_replace_callback(
            '/(?<=^|FROM |TO |BET |AND |AFT |BEF |ABT |CAL |EST )(GREGORIAN|JULIAN|HEBREW|FRENCH_R|_[A-Z0-9_]+) /',
            static fn (array $match): string => '@#D' . ($calendars[$match[1]] ?? strtr(substr($match[1], 1), ['_' => ' '])) . '@ ',
            $node->value
        );

        $node->value = (string) preg_replace('/(\d) BCE(?= |$)/', '$1 B.C.', $node->value);

        // 5.5.1 only has phrases for dates that are not ranges, periods or approximations.
        if (preg_match('/^(FROM|TO|BET|AFT|BEF|ABT|CAL|EST) /', $node->value) === 1) {
            return $node->value !== $original;
        }

        $phrase = Gedcom7Record::takePhrase($node);

        if ($phrase !== null) {
            $node->value = $node->value === '' ? '(' . $phrase . ')' : 'INT ' . $node->value . ' (' . $phrase . ')';
        }

        return $node->value !== $original || $phrase !== null;
    }

    /**
     * "< 8y" + PHRASE "Child" becomes "CHILD".  An age that is only a phrase becomes the phrase.
     */
    private static function age(Gedcom7Node $node): bool
    {
        $first = $node->children[0] ?? null;

        if (!$first instanceof Gedcom7Node || $first->tag !== 'PHRASE') {
            return false;
        }

        foreach (Gedcom7Payload::AGE_WORDS as $word => [$age, $phrase]) {
            if ($node->value === $age && $first->value === $phrase) {
                Gedcom7Record::takePhrase($node);
                $node->value = $word;

                return true;
            }
        }

        if ($node->value === '') {
            $node->value = (string) Gedcom7Record::takePhrase($node);

            return true;
        }

        return false;
    }

    /**
     * TRAN + LANG und-Latn becomes ROMN, TRAN + LANG und becomes FONE.
     */
    private static function translation(Gedcom7Node $node): bool
    {
        $last = $node->children[count($node->children) - 1] ?? null;

        if (!$last instanceof Gedcom7Node || $last->tag !== 'LANG') {
            return false;
        }

        $tag  = null;
        $type = null;

        foreach (Gedcom7Converter::TRANSLATIONS as $translation => $languages) {
            $key = array_search($last->value, $languages, true);

            if ($key !== false) {
                [$tag, $type] = [$translation, $key];
            }
        }

        if ($last->value === 'und-Latn') {
            $tag = 'ROMN';
        } elseif ($last->value === 'und') {
            $tag = 'FONE';
        }

        if ($tag === null) {
            return false;
        }

        array_pop($node->children);

        $node->tag = $tag;

        // Gedcom7Converter keeps types that it does not recognise as _TYPE.
        if ($type === null) {
            $types = $node->removeChildren('_TYPE');
            $type  = $types[0]->value ?? null;
        }

        if ($type !== null) {
            $node->children[] = new Gedcom7Node('TYPE', $type);
        }

        return true;
    }

    /**
     * GEDCOM-L location records have the identifiers of GEDCOM-L, e.g. _UID and _GOV.
     */
    private static function location(Gedcom7Node $location): bool
    {
        $changed = false;

        foreach ($location->children as $child) {
            if ($child->tag === 'UID') {
                $child->tag = '_UID';
                $changed    = true;
            } elseif ($child->tag === 'EXID') {
                $changed = Gedcom7Identifiers::downgrade($child) || $changed;
            }
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
