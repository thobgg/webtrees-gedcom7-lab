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

use function array_filter;
use function array_map;
use function array_values;
use function count;
use function explode;
use function hash;
use function implode;
use function in_array;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function rawurlencode;
use function sort;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strtolower;
use function strtoupper;
use function strtr;
use function substr;
use function trim;
use function ucfirst;

/**
 * Convert GEDCOM 5.5.1 records to GEDCOM 7.0, as https://gedcom.io/migrate/ describes.
 * Nothing is lost: other structures become extensions, and inline sources and media objects become records.
 */
final class Gedcom7Converter
{
    private const string VOID = '@VOID@';

    // RELA values that are the same as a ROLE
    public const array ROLES = [
        'CHILD'      => 'CHIL',
        'CLERGY'     => 'CLERGY',
        'FATHER'     => 'FATH',
        'FRIEND'     => 'FRIEND',
        'GODPARENT'  => 'GODP',
        'HUSBAND'    => 'HUSB',
        'MOTHER'     => 'MOTH',
        'MULTIPLE'   => 'MULTIPLE',
        'NEIGHBOR'   => 'NGHBR',
        'NEIGHBOUR'  => 'NGHBR',
        'OFFICIATOR' => 'OFFICIATOR',
        'PARENT'     => 'PARENT',
        'SPOUSE'     => 'SPOU',
        'WIFE'       => 'WIFE',
        'WITNESS'    => 'WITN',
    ];

    // RELA values that are a special case of a ROLE.  We keep the original in a PHRASE.
    public const array ROLES_WITH_PHRASE = [
        'CIVIL_REGISTRAR'  => 'OFFICIATOR',
        'GODFATHER'        => 'GODP',
        'GODMOTHER'        => 'GODP',
        'MINISTER'         => 'CLERGY',
        'PASTOR'           => 'CLERGY',
        'PRIEST'           => 'CLERGY',
        'RABBI'            => 'CLERGY',
        'REGISTRY_OFFICER' => 'OFFICIATOR',
    ];

    // Enumerated values that were renamed
    private const array ENUMERATION_ALIASES = [
        'ord-STAT' => ['CANCELLED' => 'CANCELED', 'DNS/CAN' => 'DNS_CAN', 'PRE-1970' => 'PRE_1970'],
        'PEDI'     => ['SEALED' => 'SEALING'],
    ];

    // ROMN.TYPE and FONE.TYPE become TRAN.LANG, see https://gedcom.io/migrate/#romn-fone
    public const array TRANSLATIONS = [
        'FONE' => ['HANGUL' => 'ko-hang', 'KANA' => 'ja-hrkt'],
        'ROMN' => ['PINYIN' => 'und-Latn-pinyin', 'ROMAJI' => 'ja-Latn', 'WADEGILES' => 'zh-Latn-wadegile'],
    ];

    // Name pieces may contain a comma-separated list in 5.5.1
    private const array NAME_PIECES = ['NPFX', 'GIVN', 'NICK', 'SPFX', 'SURN', 'NSFX'];

    // Registered values of EXID.TYPE, see https://github.com/FamilySearch/GEDCOM-registries
    public const string EXID_AFN          = 'https://gedcom.io/terms/v7/AFN';
    public const string EXID_RFN          = 'https://gedcom.io/terms/v7/RFN';
    public const string EXID_RIN          = 'https://gedcom.io/terms/v7/RIN';
    public const string EXID_FAMILYSEARCH = 'https://gedcom.io/exid-type/FamilySearch-PersonId';
    public const string EXID_GOV          = 'https://gov.genealogy.net/';

    /** @var array<string,string> Records created during conversion, indexed by XREF */
    private array $generated_records = [];

    /** @var array<string,string> Inline sources that were converted to records */
    private array $inline_sources = [];

    private int $next_xref = 1;

    /** @var array<string,string> New cross-reference identifiers */
    private array $xrefs = [];

    /**
     * @param string       $source_system The HEAD:SOUR of the original data, used to convert RIN values
     * @param list<string> $xrefs         The records being exported.  Pointers to other records become @VOID@.
     */
    public function __construct(private readonly string $source_system = '', array $xrefs = [])
    {
        // 7.0 only permits A-Z, 0-9 and _ in cross-reference identifiers.
        $taken   = [];
        $invalid = [];

        foreach ($xrefs as $xref) {
            if (self::isValidXref($xref)) {
                $this->xrefs[$xref] = $xref;
                $taken[$xref]       = true;
            } else {
                $invalid[] = $xref;
            }
        }

        sort($invalid);

        foreach ($invalid as $xref) {
            $base      = strtoupper((string) preg_replace('/[^A-Za-z0-9_]/', '_', $xref));
            $candidate = $base;
            $suffix    = 1;

            while (isset($taken[$candidate]) || !self::isValidXref($candidate)) {
                $candidate = $base . '_' . $suffix++;
            }

            $this->xrefs[$xref]   = $candidate;
            $taken[$candidate]    = true;
        }
    }

    /**
     * Convert a record.  HEAD and TRLR are also records.
     */
    public function convertRecord(string $gedcom): string
    {
        $record = Gedcom7Node::parse($gedcom);

        if ($record->tag === 'TRLR') {
            return '0 TRLR';
        }

        return $this->record($record)->toGedcom();
    }

    /**
     * Records that were created while converting other records, such as inline sources.
     *
     * @return list<string>
     */
    public function generatedRecords(): array
    {
        $records = array_values($this->generated_records);

        $this->generated_records = [];

        return $records;
    }

    /**
     * The new cross-reference identifier of a record.
     */
    public function xref(string $xref): string
    {
        if (isset($this->xrefs[$xref])) {
            return $this->xrefs[$xref];
        }

        if (self::isValidXref($xref)) {
            return $xref;
        }

        // A record that we were not told about.
        return strtoupper((string) preg_replace('/[^A-Za-z0-9_]/', '_', $xref)) . '_' . strtoupper(hash('crc32b', $xref));
    }

    private static function isValidXref(string $xref): bool
    {
        return preg_match('/^[A-Z0-9_]+$/', $xref) === 1 && $xref !== 'VOID';
    }

    /**
     * Pointers to records that are not being exported become @VOID@.
     */
    private function pointer(string $pointer): string
    {
        $xref = substr($pointer, 1, -1);

        if (isset($this->xrefs[$xref])) {
            return '@' . $this->xrefs[$xref] . '@';
        }

        if ($pointer === self::VOID || $this->xrefs !== []) {
            return self::VOID;
        }

        return '@' . $this->xref($xref) . '@';
    }

    private function record(Gedcom7Node $record): Gedcom7Node
    {
        if ($record->xref !== '') {
            $record->xref = $this->xref($record->xref);
        }

        // NOTE records are SNOTE records in 7.0
        if ($record->tag === 'NOTE') {
            $record->tag = 'SNOTE';
        }

        $type = Gedcom7Schema::RECORDS[$record->tag] ?? null;

        // e.g. SUBN, _LOC, _TODO
        if ($type === null || !$this->payload($record, $type, $record)) {
            return $this->extension($record);
        }

        $this->substructures($record, $type);
        $this->required($record, $type);

        return $record;
    }

    private function substructures(Gedcom7Node $node, string $type): void
    {
        $children = [];

        foreach ($node->children as $child) {
            $children[] = $this->substructure($child, $node, $type);
        }

        $node->children = $children;

        if ($type === 'INDI-NAME') {
            $this->namePieces($node);
        }

        // Structures that may only occur once.
        $seen = [];

        foreach ($node->children as $n => $child) {
            $cardinality = Gedcom7Schema::SUBSTRUCTURES[$type][$child->tag][1] ?? '0:M';

            if (str_starts_with($child->tag, '_') || str_ends_with($cardinality, 'M')) {
                continue;
            }

            if (isset($seen[$child->tag])) {
                $node->children[$n] = $this->extension($child);
            }

            $seen[$child->tag] = true;
        }
    }

    private function substructure(Gedcom7Node $node, Gedcom7Node $parent, string $parent_type): Gedcom7Node
    {
        $permitted = Gedcom7Schema::SUBSTRUCTURES[$parent_type] ?? [];

        $this->migrate($node, $permitted);

        $type = $permitted[$node->tag][0] ?? null;

        if ($type === null || !$this->payload($node, $type, $parent)) {
            return $this->extension($node);
        }

        $this->substructures($node, $type);

        if (!$this->required($node, $type)) {
            return $this->extension($node);
        }

        return $node;
    }

    /**
     * Structures that were removed or replaced in 7.0.
     *
     * @param array<string,array{0:string,1:string}> $permitted
     */
    private function migrate(Gedcom7Node $node, array $permitted): void
    {
        switch ($node->tag) {
            case 'NOTE':
                // Pointers to shared notes
                if ($node->pointer && isset($permitted['SNOTE'])) {
                    $node->tag = 'SNOTE';
                }
                break;

            case 'SOUR':
                if (!$node->pointer && self::permitsPointer($permitted, 'SOUR')) {
                    $this->inlineSource($node);
                }
                break;

            case 'OBJE':
                if (!$node->pointer && self::permitsPointer($permitted, 'OBJE')) {
                    $this->inlineMedia($node);
                }
                break;

            case 'REPO':
                // A repository without a record
                if (!$node->pointer && self::permitsPointer($permitted, 'REPO')) {
                    if (trim($node->value) !== '') {
                        $node->children[] = new Gedcom7Node('NOTE', $node->value);
                    }

                    $node->value   = self::VOID;
                    $node->pointer = true;
                }
                break;

            case 'RELA':
                if (isset($permitted['ROLE'])) {
                    $this->relationship($node);
                }
                break;

            case '_ASSO':
                // GEDCOM-L puts associates on events
                if ($node->pointer && isset($permitted['ASSO'])) {
                    $node->tag = 'ASSO';
                }
                break;

            case '_GODP':
            case '_WITN':
                // GEDCOM-L godparents and witnesses without a record become associates without a record.
                if (!$node->pointer && trim($node->value) !== '' && isset($permitted['ASSO'])) {
                    $node->children = [
                        new Gedcom7Node('PHRASE', $node->value),
                        new Gedcom7Node('ROLE', $node->tag === '_GODP' ? 'GODP' : 'WITN'),
                        ...$node->children,
                    ];
                    $node->tag     = 'ASSO';
                    $node->value   = self::VOID;
                    $node->pointer = true;
                }
                break;

            case 'AFN':
                $this->externalIdentifier($node, $permitted, $node->value, self::EXID_AFN);
                break;

            case 'RFN':
                if (str_contains($node->value, ':')) {
                    [$resource, $value] = explode(':', $node->value, 2);
                    $this->externalIdentifier($node, $permitted, $value, self::EXID_RFN . '#' . rawurlencode($resource));
                } else {
                    $this->externalIdentifier($node, $permitted, $node->value, self::EXID_RFN);
                }
                break;

            case 'RIN':
                $type = $this->source_system === '' ? self::EXID_RIN : self::EXID_RIN . '#' . rawurlencode($this->source_system);
                $this->externalIdentifier($node, $permitted, $node->value, $type);
                break;

            case '_FSFTID':
                $this->externalIdentifier($node, $permitted, $node->value, self::EXID_FAMILYSEARCH);
                break;

            case '_GOV':
                $this->externalIdentifier($node, $permitted, $node->value, self::EXID_GOV);
                break;

            case '_UID':
                // The value is unchanged, e.g. a PAF-style UID with a checksum, so that other applications recognise the record
                if (isset($permitted['UID'])) {
                    $node->tag = 'UID';
                }
                break;

            case 'ROMN':
            case 'FONE':
                if (isset($permitted['TRAN'])) {
                    $this->translation($node);
                }
                break;

            case 'WAC':
                if (isset($permitted['INIL'])) {
                    $node->tag = 'INIL';
                }
                break;

            case 'EMAI':
                if (isset($permitted['EMAIL'])) {
                    $node->tag = 'EMAIL';
                }
                break;

            case 'TYPE':
                // OBJE:FILE:FORM:TYPE is OBJE:FILE:FORM:MEDI
                if (isset($permitted['MEDI']) && !isset($permitted['TYPE'])) {
                    $node->tag = 'MEDI';
                }
                break;
        }
    }

    /**
     * Is a pointer permitted in this context, e.g. SOUR is a citation and not HEAD:SOUR?
     *
     * @param array<string,array{0:string,1:string}> $permitted
     */
    private static function permitsPointer(array $permitted, string $tag): bool
    {
        return isset($permitted[$tag]) && str_starts_with(Gedcom7Schema::PAYLOADS[$permitted[$tag][0]] ?? '', '@');
    }

    /**
     * Check and convert the payload of a structure.
     */
    private function payload(Gedcom7Node $node, string $type, Gedcom7Node $parent): bool
    {
        $payload = Gedcom7Schema::PAYLOADS[$type] ?? '';

        if (str_starts_with($payload, '@')) {
            if (!$node->pointer) {
                return false;
            }

            $node->value = $this->pointer($node->value);

            return true;
        }

        // Text that looks like a pointer, e.g. "1 NOTE @mention@"
        if ($node->pointer && $payload !== 'Text') {
            return false;
        }

        $node->pointer = false;

        switch ($payload) {
            case '':
                return $node->value === '' || $this->moveToNote($node, $type, '');

            case 'Y':
                if (in_array(strtoupper(trim($node->value)), ['', 'Y'], true)) {
                    $node->value = strtoupper(trim($node->value));

                    return true;
                }

                return $this->moveToNote($node, $type, 'Y');

            case 'Name':
                $node->value = strtr($node->value, ["\t" => ' ']);

                return true;

            case 'Integer':
                $node->value = trim($node->value);

                return preg_match('/^\d+$/', $node->value) === 1;

            case 'Enum':
                return $this->enumeration($node, $type);

            case 'ListEnum':
                return $this->enumerationList($node, $type);

            case 'Date':
            case 'DatePeriod':
            case 'DateExact':
                $date = Gedcom7Payload::date($node->value, $payload);

                if ($date === null) {
                    return false;
                }

                [$node->value, $phrase] = $date;

                return $phrase === '' || $this->phrase($node, $type, $phrase);

            case 'Age':
                [$node->value, $phrase] = Gedcom7Payload::age($node->value);

                return $phrase === '' || $this->phrase($node, $type, $phrase);

            case 'Time':
                return $this->replace($node, Gedcom7Payload::time($node->value));

            case 'Language':
                return $this->replace($node, Gedcom7Payload::language($node->value));

            case 'Latitude':
                return $this->replace($node, Gedcom7Payload::latitude($node->value));

            case 'Longitude':
                return $this->replace($node, Gedcom7Payload::longitude($node->value));

            case 'FilePath':
                $node->value = Gedcom7Payload::filePath($node->value);

                return true;

            case 'MediaType':
                $format      = trim($node->value);
                $node->value = Gedcom7Payload::mediaType($format, $parent->value);

                // Keep formats that we do not recognise.
                if ($format !== '' && $node->value === Mime::DEFAULT_TYPE && strtolower($format) !== Mime::DEFAULT_TYPE) {
                    $node->children[] = new Gedcom7Node('_FORM', $format);
                }

                return true;

            default:
                // Text, ListText, URI, TagDef
                return true;
        }
    }

    private function replace(Gedcom7Node $node, string|null $value): bool
    {
        if ($value === null) {
            return false;
        }

        $node->value = $value;

        return true;
    }

    /**
     * A payload that is not permitted by 7.0, such as "1 BIRT yes", is kept as a note.
     */
    private function moveToNote(Gedcom7Node $node, string $type, string $value): bool
    {
        if (!isset(Gedcom7Schema::SUBSTRUCTURES[$type]['NOTE'])) {
            return false;
        }

        $node->children[] = new Gedcom7Node('NOTE', $node->value);
        $node->value      = $value;

        return true;
    }

    /**
     * Add a PHRASE substructure, if permitted.
     */
    private function phrase(Gedcom7Node $node, string $type, string $phrase): bool
    {
        if (!isset(Gedcom7Schema::SUBSTRUCTURES[$type]['PHRASE'])) {
            return false;
        }

        if ($node->child('PHRASE') === null) {
            $node->children = [new Gedcom7Node('PHRASE', $phrase), ...$node->children];
        }

        return true;
    }

    private function enumeration(Gedcom7Node $node, string $type): bool
    {
        $values = Gedcom7Schema::ENUMERATIONS[$type] ?? [];
        $value  = trim($node->value);
        $upper  = strtoupper($value);
        $upper  = self::ENUMERATION_ALIASES[$type][$upper] ?? $upper;

        // See https://gedcom.io/migrate/#sex-values
        if ($type === 'SEX') {
            $node->value = in_array(substr($upper, 0, 1), ['M', 'F', 'X'], true) ? substr($upper, 0, 1) : 'U';

            return true;
        }

        // 5.5.1 SOUR:EVEN:ROLE could be "(text)"
        if (preg_match('/^\((.+)\)$/', $value, $match) === 1) {
            $value = $match[1];
            $upper = '';
        }

        if (in_array($upper, $values, true) || preg_match('/^_[A-Z0-9_]+$/', $upper) === 1) {
            $node->value = $upper;

            return true;
        }

        if ($value === '') {
            return false;
        }

        foreach (['OTHER', 'EVEN'] as $other) {
            if (in_array($other, $values, true) && isset(Gedcom7Schema::SUBSTRUCTURES[$type]['PHRASE'])) {
                $node->value = $other;

                return $this->phrase($node, $type, self::humanize($value));
            }
        }

        // An extension value
        $extension = self::extensionValue($upper);

        if ($type === 'QUAY' || $extension === '_') {
            return false;
        }

        $node->value = $extension;

        return true;
    }

    private function enumerationList(Gedcom7Node $node, string $type): bool
    {
        $values = Gedcom7Schema::ENUMERATIONS[$type] ?? [];
        $items  = array_filter(array_map(trim(...), explode(',', strtoupper($node->value))), static fn (string $item): bool => $item !== '');

        if ($items === []) {
            return false;
        }

        foreach ($items as $n => $item) {
            if (!in_array($item, $values, true) && preg_match('/^_[A-Z0-9_]+$/', $item) !== 1) {
                // e.g. webtrees uses "RESN NONE"
                if ($type !== 'RESN') {
                    return false;
                }

                $items[$n] = self::extensionValue($item);

                if ($items[$n] === '_') {
                    return false;
                }
            }
        }

        $node->value = implode(', ', $items);

        return true;
    }

    /**
     * 5.5.1 ASSO:RELA is 7.0 ASSO:ROLE, see https://gedcom.io/migrate/#rela
     */
    private function relationship(Gedcom7Node $node): void
    {
        $value = trim($node->value);
        $key   = strtoupper(strtr($value, [' ' => '_']));

        $node->tag   = 'ROLE';
        $node->value = self::ROLES[$key] ?? self::ROLES_WITH_PHRASE[$key] ?? 'OTHER';

        if ($value !== '' && !isset(self::ROLES[$key])) {
            $node->children = [new Gedcom7Node('PHRASE', self::humanize($value)), ...$node->children];
        }
    }

    /**
     * @param array<string,array{0:string,1:string}> $permitted
     */
    private function externalIdentifier(Gedcom7Node $node, array $permitted, string $value, string $type): void
    {
        if (isset($permitted['EXID']) && !$node->pointer) {
            $node->tag        = 'EXID';
            $node->value      = trim($value);
            $node->children[] = new Gedcom7Node('TYPE', $type);
        }
    }

    /**
     * 5.5.1 ROMN and FONE are 7.0 TRAN, see https://gedcom.io/migrate/#romn-fone
     */
    private function translation(Gedcom7Node $node): void
    {
        $types    = $node->removeChildren('TYPE');
        $type     = strtoupper(trim($types[0]->value ?? ''));
        $language = self::TRANSLATIONS[$node->tag][$type] ?? ($node->tag === 'ROMN' ? 'und-Latn' : 'und');

        if ($type !== '' && !isset(self::TRANSLATIONS[$node->tag][$type])) {
            $node->children[] = new Gedcom7Node('_TYPE', $types[0]->value ?? '');
        }

        $node->tag        = 'TRAN';
        $node->children[] = new Gedcom7Node('LANG', $language);
    }

    /**
     * 5.5.1 "SOUR text" becomes a pointer to a new source record.
     * See https://gedcom.io/migrate/#non-pointer-sour-substructures
     */
    private function inlineSource(Gedcom7Node $node): void
    {
        $title = $node->value;
        $texts = array_map(static fn (Gedcom7Node $text): string => $text->value, $node->removeChildren('TEXT'));
        $text  = implode("\n", $texts);
        $key   = $title . "\n\n" . $text;

        if (!isset($this->inline_sources[$key])) {
            $xref   = $this->newXref('S');
            $record = new Gedcom7Node('SOUR', '', $xref);

            if ($title !== '') {
                $record->children[] = new Gedcom7Node('TITL', $title);
            }

            if ($text !== '') {
                $record->children[] = new Gedcom7Node('TEXT', $text);
            }

            $this->inline_sources[$key]     = $xref;
            $this->generated_records[$xref] = $this->record($record)->toGedcom();
        }

        $node->value   = '@' . $this->inline_sources[$key] . '@';
        $node->pointer = true;
    }

    /**
     * 5.5.1 "OBJE" without a pointer becomes a pointer to a new media record.
     * See https://gedcom.io/migrate/#non-pointer-obje-substructures
     */
    private function inlineMedia(Gedcom7Node $node): void
    {
        $xref   = $this->newXref('M');
        $record = new Gedcom7Node('OBJE', '', $xref);
        $forms  = $node->removeChildren('FORM');
        $titles = $node->removeChildren('TITL');

        foreach ($node->removeChildren('FILE') as $file) {
            if ($file->child('FORM') === null && $forms !== []) {
                $file->children[] = $forms[0];
            }

            if ($file->child('TITL') === null && $titles !== []) {
                $file->children[] = $titles[0];
            }

            $record->children[] = $file;
        }

        // NOTE, _PRIM, etc.
        foreach ($node->children as $child) {
            $record->children[] = $child;
        }

        $this->generated_records[$xref] = $this->record($record)->toGedcom();

        $node->value    = '@' . $xref . '@';
        $node->pointer  = true;
        $node->children = [];
    }

    /**
     * 7.0 requires some substructures.  Create them, where we can.
     */
    private function required(Gedcom7Node $node, string $type): bool
    {
        foreach (Gedcom7Schema::SUBSTRUCTURES[$type] ?? [] as $tag => [$substructure, $cardinality]) {
            if (str_starts_with($cardinality, '1') && $node->child($tag) === null) {
                $child = match ($substructure) {
                    'ROLE'  => new Gedcom7Node('ROLE', 'OTHER'),
                    'FORM'  => new Gedcom7Node('FORM', Gedcom7Payload::mediaType('', $node->value)),
                    'TYPE'  => new Gedcom7Node('TYPE'),
                    'NAME'  => new Gedcom7Node('NAME'),
                    'LANG'  => new Gedcom7Node('LANG', 'und'),
                    default => null,
                };

                if ($child === null) {
                    return false;
                }

                $node->children[] = $child;
            }
        }

        return true;
    }

    /**
     * 5.5.1 allowed comma-separated name pieces.  See https://gedcom.io/migrate/#name-piece-values
     */
    private function namePieces(Gedcom7Node $name): void
    {
        $children = [];

        foreach ($name->children as $child) {
            if (in_array($child->tag, self::NAME_PIECES, true) && str_contains($child->value, ',')) {
                $pieces = array_values(array_filter(array_map(trim(...), explode(',', $child->value)), static fn (string $piece): bool => $piece !== ''));
                $found  = count(array_filter($pieces, static fn (string $piece): bool => preg_match('/(?<![\p{L}\p{N}])' . preg_quote($piece, '/') . '(?![\p{L}\p{N}])/u', $name->value) === 1));
                $spaces = count(array_filter($pieces, static fn (string $piece): bool => str_contains($piece, ' ')));

                if ($spaces > 0 || $found === 1) {
                    foreach ($pieces as $piece) {
                        $children[] = new Gedcom7Node($child->tag, $piece, '', false, $child->children);
                    }

                    continue;
                }

                $child->value = implode(' ', $pieces);
            }

            $children[] = $child;
        }

        $name->children = $children;
    }

    /**
     * Keep a structure that is not valid in 7.0, by making it an extension.
     */
    private function extension(Gedcom7Node $node): Gedcom7Node
    {
        if (!str_starts_with($node->tag, '_')) {
            $node->tag = '_' . $node->tag;
        }

        $this->extensionSubstructures($node);

        return $node;
    }

    private function extensionSubstructures(Gedcom7Node $node): void
    {
        if ($node->pointer) {
            $node->value = $this->pointer($node->value);
        }

        // Languages of extensions, e.g. the GEDCOM-L _LOC:NAME:LANG
        if ($node->tag === 'LANG') {
            $node->value = Gedcom7Payload::language($node->value) ?? $node->value;
        }

        foreach ($node->children as $child) {
            $this->extensionSubstructures($child);
        }
    }

    private function newXref(string $prefix): string
    {
        do {
            $xref = '_' . $prefix . '7_' . $this->next_xref++;
        } while (isset($this->xrefs[$xref]) || in_array($xref, $this->xrefs, true));

        if ($this->xrefs !== []) {
            $this->xrefs[$xref] = $xref;
        }

        return $xref;
    }

    /**
     * An enumerated value that is not standard, e.g. "RESN none" becomes "RESN _NONE".
     */
    private static function extensionValue(string $value): string
    {
        return '_' . trim((string) preg_replace('/[^A-Z0-9_]+/', '_', strtoupper($value)), '_');
    }

    /**
     * webtrees stores some values as codes, e.g. "best_man" or "RADA".
     */
    public static function humanize(string $value): string
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $value) === 1) {
            return ucfirst(strtolower(strtr($value, ['_' => ' '])));
        }

        return $value;
    }
}
