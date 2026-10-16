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
use Fisharebest\Webtrees\Elements\UnknownElement;
use Fisharebest\Webtrees\Factories\ElementFactory;
use Fisharebest\Webtrees\Gedcom;

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
use function preg_match;
use function rawurldecode;
use function str_contains;
use function str_starts_with;
use function strlen;
use function strrpos;
use function strtolower;
use function strtoupper;
use function strtr;
use function substr;
use function ucfirst;

/**
 * Create the working copy of a GEDCOM 7.0 record.
 *
 * webtrees7 stores GEDCOM 7.0 as the definitive data.  The existing code reads and writes a working copy,
 * which is GEDCOM 7.0 except for the structures that the existing code interprets.  These are written
 * in their GEDCOM 5.5.1 form, e.g. "1 _UID" instead of "1 UID".
 *
 * Each rule is the reverse of a rule in Gedcom7Converter.  Every working copy is verified by converting
 * it back: Gedcom7Converter(working copy) must give the same GEDCOM 7.0.  If a rule cannot be reversed
 * exactly for a record, it is not applied to that record, so no data can be lost.
 */
final class Gedcom7WorkingCopy
{
    // The rules, see https://gedcom.io/migrate/ for the opposite direction
    public const string RULE_UID  = 'UID';  // UID becomes _UID
    public const string RULE_EXID = 'EXID'; // EXID with a registered TYPE becomes AFN, RFN, RIN, _FSFTID or _GOV
    public const string RULE_ENUM = 'ENUM'; // OTHER + PHRASE becomes the original value
    public const string RULE_LANG = 'LANG'; // BCP 47 language tags become language names
    public const string RULE_EXT  = 'EXT';  // Extensions that Gedcom7Converter made from 5.5.1 structures, e.g. RESN of a source

    public const array RULES = [
        self::RULE_UID,
        self::RULE_EXID,
        self::RULE_ENUM,
        self::RULE_LANG,
        self::RULE_EXT,
    ];

    // Enumerated values that 7.0 renamed, but webtrees uses the 5.5.1 name, see Gedcom7Converter::ENUMERATION_ALIASES
    private const array ENUMERATION_ALIASES = [
        'ord-STAT' => ['DNS_CAN' => 'DNS/CAN', 'PRE_1970' => 'PRE-1970'],
    ];

    // Enumerations where webtrees has its own values, e.g. PEDI RADA or NAME TYPE ESTATE
    private const array ENUMERATIONS_WITH_PHRASE = ['PEDI', 'NAME-TYPE', 'ord-STAT'];

    private static ElementFactory|null $known_tags = null;

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
        $rules = [];

        foreach (self::RULES as $rule) {
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
     */
    public function normalize(string $gedcom7): string
    {
        return $this->toGedcom7($this->apply($gedcom7, []));
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

        // GEDCOM 5.5.1 paths, for RULE_EXT
        $path = $record->tag === 'SNOTE' ? 'NOTE' : $record->tag;

        $this->node($record, Gedcom7Schema::RECORDS[$record->tag] ?? null, $rules, $path);

        return self::serialize($record, 0);
    }

    private function toGedcom7(string $gedcom): string
    {
        return (new Gedcom7Converter())->convertRecord($gedcom);
    }

    /**
     * @param list<string> $rules
     */
    private function node(Gedcom7Node $node, string|null $type, array $rules, string $path): void
    {
        self::unescape($node);

        foreach ($node->children as $child) {
            $child_type = $type === null ? null : (Gedcom7Schema::SUBSTRUCTURES[$type][$child->tag][0] ?? null);

            $this->node($child, $child_type, $rules, $path . ':' . $child->tag);
        }

        if ($type === null) {
            // An extension structure.  Gedcom7Converter keeps these unchanged.
            if (in_array(self::RULE_EXT, $rules, true)) {
                self::extension($node, $path);
            }

            return;
        }

        $payload = Gedcom7Schema::PAYLOADS[$type] ?? '';

        if (in_array(self::RULE_UID, $rules, true) && $type === 'UID') {
            $node->tag = '_UID';
        }

        if (in_array(self::RULE_EXID, $rules, true) && $type === 'EXID') {
            self::externalIdentifier($node);
        }

        if (in_array(self::RULE_ENUM, $rules, true) && in_array($type, self::ENUMERATIONS_WITH_PHRASE, true)) {
            self::enumeration($node, $type);
        }

        if (in_array(self::RULE_ENUM, $rules, true) && $type === 'SOUR-EVEN') {
            self::sourceRole($node);
        }

        if (in_array(self::RULE_LANG, $rules, true) && $payload === 'Language') {
            self::language($node);
        }
    }

    /**
     * Gedcom7Converter keeps a 5.5.1 structure that 7.0 does not permit, by adding an underscore to its tag,
     * e.g. "1 RESN" of a source becomes "1 _RESN".  The existing code needs the original tag, e.g. for privacy.
     */
    private static function extension(Gedcom7Node $node, string $path): void
    {
        $parent = strrpos($path, ':');

        if ($parent === false || !str_starts_with($node->tag, '_') || strlen($node->tag) < 2) {
            return;
        }

        $tag = substr($node->tag, 1);

        if (!self::knownTags()->make(substr($path, 0, $parent) . ':' . $tag) instanceof UnknownElement) {
            $node->tag = $tag;
        }
    }

    /**
     * The tags that webtrees itself defines: GEDCOM 5.5.1 and its own extensions, but not those of other applications.
     */
    private static function knownTags(): ElementFactory
    {
        if (self::$known_tags === null) {
            self::$known_tags = new ElementFactory();
            (new Gedcom())->registerTags(self::$known_tags, false);
        }

        return self::$known_tags;
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
     * EXID + TYPE https://gov.genealogy.net/ becomes the GEDCOM-L _GOV, etc.
     */
    private static function externalIdentifier(Gedcom7Node $node): void
    {
        $type = $node->children[count($node->children) - 1] ?? null;

        if (!$type instanceof Gedcom7Node || $type->tag !== 'TYPE' || $type->children !== []) {
            return;
        }

        $tag = match ($type->value) {
            Gedcom7Converter::EXID_AFN          => 'AFN',
            Gedcom7Converter::EXID_RFN          => 'RFN',
            Gedcom7Converter::EXID_RIN          => 'RIN',
            Gedcom7Converter::EXID_FAMILYSEARCH => '_FSFTID',
            Gedcom7Converter::EXID_GOV          => '_GOV',
            default                             => null,
        };

        $value = $node->value;

        // "RFN archive:123" has the type https://gedcom.io/terms/v7/RFN#archive
        if ($tag === null && str_starts_with($type->value, Gedcom7Converter::EXID_RFN . '#')) {
            $tag   = 'RFN';
            $value = rawurldecode(substr($type->value, strlen(Gedcom7Converter::EXID_RFN) + 1)) . ':' . $value;
        }

        if ($tag !== null) {
            array_pop($node->children);
            $node->tag   = $tag;
            $node->value = $value;
        }
    }

    /**
     * PEDI OTHER + PHRASE "Rada" becomes PEDI RADA.  ord-STAT DNS_CAN becomes DNS/CAN.
     */
    private static function enumeration(Gedcom7Node $node, string $type): void
    {
        if (isset(self::ENUMERATION_ALIASES[$type][$node->value])) {
            $node->value = self::ENUMERATION_ALIASES[$type][$node->value];

            return;
        }

        $first = $node->children[0] ?? null;

        if ($node->value !== 'OTHER' || !$first instanceof Gedcom7Node || $first->tag !== 'PHRASE') {
            return;
        }

        $code = self::code($first->value, Gedcom7Schema::ENUMERATIONS[$type] ?? [], self::knownValues($type));

        if ($code !== null) {
            self::takePhrase($node);
            $node->value = $code;
        }
    }

    /**
     * SOUR:EVEN:ROLE OTHER + PHRASE "Pate" becomes the 5.5.1 "(Pate)".
     */
    private static function sourceRole(Gedcom7Node $event): void
    {
        foreach ($event->children as $role) {
            $phrase = $role->children[0] ?? null;

            if (
                $role->tag === 'ROLE' && $role->value === 'OTHER' && count($role->children) === 1 &&
                $phrase instanceof Gedcom7Node && $phrase->tag === 'PHRASE' && Gedcom7Converter::humanize($phrase->value) === $phrase->value
            ) {
                $role->value    = '(' . $phrase->value . ')';
                $role->children = [];
            }
        }
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
