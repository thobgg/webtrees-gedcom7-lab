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

use Fisharebest\Webtrees\Mime;

use function array_diff;
use function array_flip;
use function array_keys;
use function array_map;
use function array_pop;
use function array_search;
use function array_shift;
use function count;
use function explode;
use function implode;
use function in_array;
use function pathinfo;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use function rawurldecode;
use function str_contains;
use function str_starts_with;
use function strtolower;
use function strtoupper;
use function strtr;
use function substr;
use function ucfirst;

use const PATHINFO_EXTENSION;

/**
 * Create the working copy of a GEDCOM 7.0 record.
 *
 * webtrees7 stores GEDCOM 7.0 as the definitive data.  The existing code reads and writes a working copy,
 * which is GEDCOM 7.0 except for the structures that the existing code interprets.  These are written
 * in their GEDCOM 5.5.1 form, e.g. "@#DJULIAN@ 1700" instead of "JULIAN 1700".
 *
 * Each rule is the reverse of a rule in Gedcom7Converter.  Every working copy is verified by converting
 * it back: Gedcom7Converter(working copy) must give the same GEDCOM 7.0.  If a rule cannot be reversed
 * exactly for a record, it is not applied to that record, so no data can be lost.
 */
final class Gedcom7WorkingCopy
{
    // The rules, see https://gedcom.io/migrate/ for the opposite direction
    public const string RULE_NOTE = 'NOTE'; // SNOTE records and pointers become NOTE
    public const string RULE_DATE = 'DATE'; // calendar names, BCE and date phrases
    public const string RULE_AGE  = 'AGE';  // "< 8y" + PHRASE Child becomes CHILD
    public const string RULE_ROLE = 'ROLE'; // ASSO:ROLE becomes ASSO:RELA
    public const string RULE_TRAN = 'TRAN'; // NAME:TRAN and PLAC:TRAN become ROMN/FONE
    public const string RULE_UID  = 'UID';  // UID becomes _UID
    public const string RULE_FILE = 'FILE'; // relative URLs become file names
    public const string RULE_FORM = 'FORM'; // media types become file extensions, MEDI becomes TYPE
    public const string RULE_ENUM = 'ENUM'; // OTHER + PHRASE becomes the original value
    public const string RULE_LANG = 'LANG'; // BCP 47 language tags become language names

    public const array RULES = [
        self::RULE_NOTE,
        self::RULE_DATE,
        self::RULE_AGE,
        self::RULE_ROLE,
        self::RULE_TRAN,
        self::RULE_UID,
        self::RULE_FILE,
        self::RULE_FORM,
        self::RULE_ENUM,
        self::RULE_LANG,
    ];

    // Rules that are needed to normalise GEDCOM 7.0, and are therefore always applied
    private const array BASE_RULES = [self::RULE_FILE];

    // Enumerations where webtrees has its own values, e.g. PEDI RADA or NAME TYPE ESTATE
    private const array ENUMERATIONS_WITH_PHRASE = ['PEDI', 'NAME-TYPE', 'MEDI'];

    /** @var list<string> Rules that could not be applied to the last record */
    private array $skipped_rules = [];

    /**
     * Create the working copy of a record.
     */
    public function create(string $gedcom7): string
    {
        $this->skipped_rules = [];

        $target  = $this->normalize($gedcom7);
        $working = $this->apply($gedcom7, self::RULES);

        if ($this->toGedcom7($working) === $target) {
            return $working;
        }

        // Find the rules that can be applied to this record.
        $rules = self::BASE_RULES;

        foreach (array_diff(self::RULES, self::BASE_RULES) as $rule) {
            if ($this->toGedcom7($this->apply($gedcom7, [...$rules, $rule])) === $target) {
                $rules[] = $rule;
            } else {
                $this->skipped_rules[] = $rule;
            }
        }

        return $this->apply($gedcom7, $rules);
    }

    /**
     * Normalise a GEDCOM 7.0 record, e.g. from an imported file, as Gedcom7Converter writes it.
     * File paths are already URLs in GEDCOM 7.0, so they are decoded before Gedcom7Converter encodes them.
     */
    public function normalize(string $gedcom7): string
    {
        return $this->toGedcom7($this->apply($gedcom7, self::BASE_RULES));
    }

    /**
     * The rules that could not be applied to the last record.
     *
     * @return list<string>
     */
    public function skippedRules(): array
    {
        return $this->skipped_rules;
    }

    /**
     * Apply some rules to a record, without verifying them.
     *
     * @param list<string> $rules
     */
    public function apply(string $gedcom7, array $rules): string
    {
        $record = Gedcom7Node::parse($gedcom7);

        $this->node($record, Gedcom7Schema::RECORDS[$record->tag] ?? null, $rules);

        return self::serialize($record, 0);
    }

    private function toGedcom7(string $gedcom): string
    {
        return (new Gedcom7Converter())->convertRecord($gedcom);
    }

    /**
     * @param list<string> $rules
     */
    private function node(Gedcom7Node $node, string|null $type, array $rules): void
    {
        self::unescape($node);

        foreach ($node->children as $child) {
            $child_type = $type === null ? null : (Gedcom7Schema::SUBSTRUCTURES[$type][$child->tag][0] ?? null);

            $this->node($child, $child_type, $rules);
        }

        if ($type === null) {
            // An extension structure.  Gedcom7Converter keeps these unchanged.
            return;
        }

        $payload = Gedcom7Schema::PAYLOADS[$type] ?? '';

        if (in_array(self::RULE_NOTE, $rules, true) && ($type === 'record-SNOTE' || $type === 'SNOTE')) {
            $node->tag = 'NOTE';
        }

        if (in_array(self::RULE_DATE, $rules, true) && ($payload === 'Date' || $payload === 'DatePeriod')) {
            self::date($node);
        }

        if (in_array(self::RULE_AGE, $rules, true) && $payload === 'Age') {
            self::age($node);
        }

        if (in_array(self::RULE_ROLE, $rules, true) && $type === 'ASSO') {
            self::role($node);
        }

        if (in_array(self::RULE_TRAN, $rules, true) && ($type === 'NAME-TRAN' || $type === 'PLAC-TRAN')) {
            self::translation($node);
        }

        if (in_array(self::RULE_UID, $rules, true) && $type === 'UID') {
            $node->tag = '_UID';
        }

        if (in_array(self::RULE_FILE, $rules, true) && $type === 'FILE') {
            self::file($node);
        }

        if (in_array(self::RULE_FORM, $rules, true) && $type === 'FILE') {
            self::form($node);
        }

        if (in_array(self::RULE_ENUM, $rules, true) && in_array($type, self::ENUMERATIONS_WITH_PHRASE, true)) {
            self::enumeration($node, $type);
        }

        if (in_array(self::RULE_LANG, $rules, true) && $payload === 'Language') {
            self::language($node);
        }
    }

    /**
     * 7.0 escapes a leading "@" as "@@".  webtrees stores the unescaped text.
     */
    private static function unescape(Gedcom7Node $node): void
    {
        if (!$node->pointer && str_contains($node->value, '@@')) {
            $lines       = explode("\n", $node->value);
            $lines       = array_map(static fn (string $line): string => str_starts_with($line, '@@') ? substr($line, 1) : $line, $lines);
            $node->value = implode("\n", $lines);
        }
    }

    /**
     * Remove the first substructure, if it is a PHRASE.  Gedcom7Converter adds phrases as the first substructure.
     */
    private static function takePhrase(Gedcom7Node $node): string|null
    {
        $first = $node->children[0] ?? null;

        if ($first instanceof Gedcom7Node && $first->tag === 'PHRASE' && $first->children === [] && !str_contains($first->value, "\n")) {
            array_shift($node->children);

            return $first->value;
        }

        return null;
    }

    /**
     * "JULIAN 1700" becomes "@#DJULIAN@ 1700", "100 BCE" becomes "100 B.C.", a PHRASE becomes "INT … (…)".
     */
    private static function date(Gedcom7Node $node): void
    {
        $calendars = array_flip(Gedcom7Payload::CALENDARS);

        $node->value = (string) preg_replace_callback(
            '/(?<=^|FROM |TO |BET |AND |AFT |BEF |ABT |CAL |EST )(GREGORIAN|JULIAN|HEBREW|FRENCH_R|_[A-Z0-9_]+) /',
            static fn (array $match): string => '@#D' . ($calendars[$match[1]] ?? strtr(substr($match[1], 1), ['_' => ' '])) . '@ ',
            $node->value
        );

        $node->value = (string) preg_replace('/(\d) BCE(?= |$)/', '$1 B.C.', $node->value);

        // 5.5.1 only has phrases for dates that are not ranges, periods or approximations.
        if (preg_match('/^(FROM|TO|BET|AFT|BEF|ABT|CAL|EST) /', $node->value) === 1) {
            return;
        }

        $phrase = self::takePhrase($node);

        if ($phrase !== null) {
            $node->value = $node->value === '' ? '(' . $phrase . ')' : 'INT ' . $node->value . ' (' . $phrase . ')';
        }
    }

    /**
     * "< 8y" + PHRASE "Child" becomes "CHILD".  An age that is only a phrase becomes the phrase.
     */
    private static function age(Gedcom7Node $node): void
    {
        $first = $node->children[0] ?? null;

        if (!$first instanceof Gedcom7Node || $first->tag !== 'PHRASE') {
            return;
        }

        foreach (Gedcom7Payload::AGE_WORDS as $word => [$age, $phrase]) {
            if ($node->value === $age && $first->value === $phrase) {
                self::takePhrase($node);
                $node->value = $word;

                return;
            }
        }

        if ($node->value === '') {
            $node->value = (string) self::takePhrase($node);
        }
    }

    /**
     * ASSO:ROLE GODP becomes ASSO:RELA godparent.
     */
    private static function role(Gedcom7Node $asso): void
    {
        foreach ($asso->children as $role) {
            if ($role->tag !== 'ROLE') {
                continue;
            }

            $phrase = self::takePhrase($role);
            $value  = null;

            if ($phrase === null) {
                $key   = array_search($role->value, Gedcom7Converter::ROLES, true);
                $value = $key === false ? null : strtolower($key);
            } else {
                foreach (Gedcom7Converter::ROLES_WITH_PHRASE as $key => $base) {
                    if ($base === $role->value && Gedcom7Converter::humanize($key) === $phrase) {
                        $value = strtolower($key);
                    }
                }

                if ($value === null && $role->value === 'OTHER') {
                    $code = strtolower(self::code($phrase, []) ?? $phrase);
                    $key  = strtoupper(strtr($code, [' ' => '_']));

                    // e.g. "Godparent" would become ROLE GODP.
                    if (Gedcom7Converter::humanize($code) === $phrase && !isset(Gedcom7Converter::ROLES[$key]) && !isset(Gedcom7Converter::ROLES_WITH_PHRASE[$key])) {
                        $value = $code;
                    }
                }
            }

            if ($value === null) {
                // Cannot be represented as RELA.
                if ($phrase !== null) {
                    $role->children = [new Gedcom7Node('PHRASE', $phrase), ...$role->children];
                }

                continue;
            }

            $role->tag   = 'RELA';
            $role->value = $value;
        }
    }

    /**
     * TRAN + LANG und-Latn becomes ROMN, TRAN + LANG und becomes FONE.
     */
    private static function translation(Gedcom7Node $node): void
    {
        $last = $node->children[count($node->children) - 1] ?? null;

        if (!$last instanceof Gedcom7Node || $last->tag !== 'LANG') {
            return;
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
            return;
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
    }

    /**
     * A relative URL, e.g. "my%20photo.jpg", becomes a file name, e.g. "my photo.jpg".
     */
    private static function file(Gedcom7Node $node): void
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $node->value) !== 1 && !str_starts_with($node->value, '/')) {
            $file = rawurldecode($node->value);

            // e.g. "https%3A//example.com" is a relative path, not a URL.
            if (Gedcom7Payload::filePath($file) === $node->value) {
                $node->value = $file;
            }
        }
    }

    /**
     * FILE:FORM image/jpeg becomes FILE:FORM jpg, and FILE:FORM:MEDI becomes FILE:FORM:TYPE.
     */
    private static function form(Gedcom7Node $file): void
    {
        foreach ($file->children as $form) {
            if ($form->tag !== 'FORM') {
                continue;
            }

            $extension = strtolower(pathinfo($file->value, PATHINFO_EXTENSION));
            $formats   = $form->removeChildren('_FORM');

            if ($formats !== [] && $form->value === Mime::DEFAULT_TYPE) {
                // A format that Gedcom7Converter did not recognise.
                $form->value = $formats[0]->value;
            } elseif ($extension !== '' && Gedcom7Payload::mediaType($extension) === $form->value) {
                $form->value = $extension;
            } else {
                $extensions = [...array_keys(Gedcom7Payload::MEDIA_TYPES), ...array_map(strtolower(...), array_keys(Mime::TYPES))];

                foreach ($extensions as $candidate) {
                    if (Gedcom7Payload::mediaType($candidate) === $form->value) {
                        $form->value = $candidate;
                        break;
                    }
                }

                $form->children = [...$form->children, ...$formats];
            }

            foreach ($form->children as $child) {
                if ($child->tag === 'MEDI') {
                    $child->tag = 'TYPE';
                }
            }
        }
    }

    /**
     * PEDI OTHER + PHRASE "Rada" becomes PEDI RADA.
     */
    private static function enumeration(Gedcom7Node $node, string $type): void
    {
        if ($node->value !== 'OTHER') {
            return;
        }

        $first = $node->children[0] ?? null;

        if (!$first instanceof Gedcom7Node || $first->tag !== 'PHRASE') {
            return;
        }

        $code = self::code($first->value, Gedcom7Schema::ENUMERATIONS[$type] ?? []);

        if ($code !== null) {
            self::takePhrase($node);
            $node->value = $code;
        }
    }

    /**
     * Gedcom7Converter turns a code such as "best_man" into the phrase "Best man".  Find the code.
     *
     * @param list<string> $values Values that would not become a phrase
     */
    private static function code(string $phrase, array $values): string|null
    {
        $candidates = [strtoupper(strtr($phrase, [' ' => '_'])), strtolower(strtr($phrase, [' ' => '_'])), $phrase];

        foreach ($candidates as $candidate) {
            if (
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
     * "de" becomes "German".
     */
    private static function language(Gedcom7Node $node): void
    {
        $name = array_search($node->value, Gedcom7Payload::LANGUAGES, true);

        if ($name !== false) {
            $node->value = ucfirst(strtolower($name));
        }
    }

    /**
     * Create the working copy.  This is GEDCOM as webtrees stores it: CONT lines, but no "@@" escapes.
     */
    private static function serialize(Gedcom7Node $node, int $level): string
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
