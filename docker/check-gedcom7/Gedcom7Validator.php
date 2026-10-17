<?php

/**
 * Check GEDCOM 7.0 against g7validation.json of FamilySearch, independent of the webtrees code.
 * See https://github.com/FamilySearch/GEDCOM-registries and https://gedcom.io/specifications/FamilySearchGEDCOMv7.html
 */

declare(strict_types=1);

namespace Webtrees7Check;

use function array_key_exists;
use function array_keys;
use function array_slice;
use function count;
use function explode;
use function implode;
use function in_array;
use function is_string;
use function json_decode;
use function preg_match;
use function preg_quote;
use function str_contains;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

final class Gedcom7Validator
{
    private const string V7 = 'https://gedcom.io/terms/v7/';

    private const string XREF = '@[A-Z0-9_]+@';

    private const string EXT_TAG = '_[A-Za-z0-9_]+';

    /** @var array<string,mixed> */
    private array $schema;

    private string $date_regex;

    private string $period_regex;

    private string $exact_regex;

    /** @var list<string> */
    private array $errors = [];

    /** @var array<string,int> extension tags, and how often they are used */
    public array $extension_tags = [];

    /**
     * @param array<string,string> $xrefs Record type of each XREF, to check pointers.  Empty: pointers are not checked.
     * @param bool                 $file  Payloads are escaped as in a file ("@@"), not as webtrees stores them.
     */
    public function __construct(string $json, public array $xrefs = [], private readonly bool $file = false)
    {
        $this->schema = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        $dates = [];

        foreach ($this->schema['calendar'] as $calendar => $definition) {
            $months = implode('|', array_keys($definition['months'])) . '|' . self::EXT_TAG;
            $epochs = implode('|', [...$definition['epochs'], self::EXT_TAG]);
            // The default calendar is GREGORIAN.
            $prefix  = $calendar === 'GREGORIAN' ? '(?:GREGORIAN )?' : preg_quote($calendar) . ' ';
            $dates[] = $prefix . '(?:(?:\d+ )?(?:' . $months . ') )?\d+(?: (?:' . $epochs . '))?';
        }

        // Extension calendars
        $dates[] = self::EXT_TAG . ' (?:(?:\d+ )?[A-Za-z0-9_]+ )?\d+(?: ' . self::EXT_TAG . ')?';

        $date  = '(?:' . implode('|', $dates) . ')';
        $month = implode('|', array_keys($this->schema['calendar']['GREGORIAN']['months']));

        $this->period_regex = '/^(?:|TO ' . $date . '|FROM ' . $date . '(?: TO ' . $date . ')?)$/';
        $this->date_regex   = '/^(?:|' . $date . '|TO ' . $date . '|FROM ' . $date . '(?: TO ' . $date . ')?|BET ' . $date . ' AND ' . $date . '|(?:AFT|BEF|ABT|CAL|EST) ' . $date . ')$/';
        $this->exact_regex  = '/^\d\d? (?:' . $month . ') \d+$/';
    }

    /**
     * @return list<string> errors
     */
    public function validate(string $record): array
    {
        $this->errors = [];

        $nodes = $this->parse($record);

        if ($nodes === []) {
            return $this->errors;
        }

        $root = $nodes[0];

        if (count($nodes) !== 1) {
            $this->errors[] = 'not one record';
        }

        $type = $this->childType('', $root->tag);

        if (str_starts_with($root->tag, '_')) {
            $this->extension_tags[$root->tag] = ($this->extension_tags[$root->tag] ?? 0) + 1;
        } elseif ($type === null) {
            $this->errors[] = '0 ' . $root->tag . ': not a GEDCOM 7.0 record';
        } else {
            // Other records need an XREF only if something points to them.
            if (in_array($root->tag, ['HEAD', 'TRLR'], true) && $root->xref !== '') {
                $this->errors[] = '0 ' . $root->tag . ': XREF not allowed';
            }

            $this->structure($root, $type, $root->tag);
        }

        if ($root->xref !== '' && (preg_match('/^' . self::XREF . '$/', $root->xref) !== 1 || $root->xref === '@VOID@')) {
            $this->errors[] = 'invalid XREF ' . $root->xref;
        }

        return $this->errors;
    }

    private function structure(Node $node, string $type, string $path): void
    {
        $this->payload($node, $type, $path);

        $permitted = $this->schema['substructure'][$type] ?? [];
        $counts    = [];

        foreach ($node->children as $child) {
            $tag = $child->tag;

            if (str_starts_with($tag, '_')) {
                // Extensions may contain anything.
                $this->extension_tags[$tag] = ($this->extension_tags[$tag] ?? 0) + 1;
                continue;
            }

            $child_type = $this->childType($type, $tag);

            if ($child_type === null) {
                $this->errors[] = $path . ':' . $tag . ': not permitted here';
                continue;
            }

            $counts[$tag] = ($counts[$tag] ?? 0) + 1;

            $this->structure($child, $child_type, $path . ':' . $tag);
        }

        foreach ($permitted as $tag => $definition) {
            if (str_contains($tag, '://')) {
                continue;
            }

            [$min, $max] = explode(':', substr($definition['cardinality'], 1, -1));

            if (($counts[$tag] ?? 0) < (int) $min) {
                $this->errors[] = $path . ':' . $tag . ': missing';
            }

            if ($max === '1' && ($counts[$tag] ?? 0) > 1) {
                $this->errors[] = $path . ':' . $tag . ': more than one';
            }
        }
    }

    private function childType(string $type, string $tag): string|null
    {
        $child = $this->schema['substructure'][$type][$tag]['type'] ?? null;

        return is_string($child) && str_starts_with($child, self::V7) ? $child : null;
    }

    private function payload(Node $node, string $type, string $path): void
    {
        $definition = $this->schema['payload'][$type] ?? null;
        $payload    = $node->payload;
        $kind       = $definition['type'] ?? null;

        if ($this->file && str_starts_with($payload, '@') && $kind !== 'pointer') {
            if (!str_starts_with($payload, '@@')) {
                $this->errors[] = $path . ': "@" at the start of the payload must be escaped as "@@"';
            }

            $payload = substr($payload, 1);
        }

        if ($node->cont && $kind !== 'http://www.w3.org/2001/XMLSchema#string') {
            $this->errors[] = $path . ': CONT is only for text';
        }

        $error = match ($kind) {
            null                                                   => $payload === '' ? null : 'no payload permitted',
            'Y|<NULL>'                                             => $payload === '' || $payload === 'Y' ? null : 'payload must be Y or empty',
            'pointer'                                              => $this->pointer($payload, $definition['to']),
            'http://www.w3.org/2001/XMLSchema#string'              => $payload === '' ? 'empty text' : null,
            'https://gedcom.io/terms/v7/type-List#Text'            => null,
            'http://www.w3.org/2001/XMLSchema#nonNegativeInteger'  => preg_match('/^\d+$/', $payload) === 1 ? null : 'not an integer',
            'https://gedcom.io/terms/v7/type-Enum'                 => $this->enumeration($payload, $definition['set']),
            'https://gedcom.io/terms/v7/type-List#Enum'            => $this->enumerations($payload, $definition['set']),
            'https://gedcom.io/terms/v7/type-Date'                 => preg_match($this->date_regex, $payload) === 1 ? null : 'not a DateValue',
            'https://gedcom.io/terms/v7/type-Date#period'          => preg_match($this->period_regex, $payload) === 1 ? null : 'not a DatePeriod',
            'https://gedcom.io/terms/v7/type-Date#exact'           => preg_match($this->exact_regex, $payload) === 1 ? null : 'not a DateExact',
            'https://gedcom.io/terms/v7/type-Age'                  => preg_match('/^(?:[<>] )?(?:\d+y(?: \d+m)?(?: \d+w)?(?: \d+d)?|\d+m(?: \d+w)?(?: \d+d)?|\d+w(?: \d+d)?|\d+d)?$/', $payload) === 1 && $payload !== '< ' ? null : 'not an Age',
            'http://www.w3.org/2001/XMLSchema#Language'            => preg_match('/^[a-zA-Z]{2,8}(?:-[a-zA-Z0-9]{1,8})*$/', $payload) === 1 ? null : 'not a language tag',
            'http://www.w3.org/ns/dcat#mediaType'                  => preg_match('~^[A-Za-z0-9!#$&^_.+-]+/[A-Za-z0-9!#$&^_.+-]+(?:\s*;.*)?$~', $payload) === 1 ? null : 'not a media type',
            'https://gedcom.io/terms/v7/type-FilePath'             => preg_match('/^[^\s\\\\]+$/u', $payload) === 1 ? null : 'not a URI reference',
            'http://www.w3.org/2001/XMLSchema#anyURI'              => preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:\S+$/', $payload) === 1 ? null : 'not a URI',
            'https://gedcom.io/terms/v7/type-Name'                 => preg_match('~^[^/]*(?:/[^/]*/[^/]*)?$~', $payload) === 1 ? null : 'not a Name',
            'https://gedcom.io/terms/v7/type-Latitude'             => preg_match('/^[NS](?:90|[0-8]?\d)(?:\.\d+)?$/', $payload) === 1 ? null : 'not a latitude',
            'https://gedcom.io/terms/v7/type-Longitude'            => preg_match('/^[EW](?:180|1[0-7]\d|\d?\d)(?:\.\d+)?$/', $payload) === 1 ? null : 'not a longitude',
            'https://gedcom.io/terms/v7/type-TagDef'               => preg_match('/^' . self::EXT_TAG . ' [A-Za-z][A-Za-z0-9+.-]*:\S+$/', $payload) === 1 ? null : 'not a tag definition',
            'https://gedcom.io/terms/v7/type-Time'                 => preg_match('/^(?:[01]?\d|2[0-3]):[0-5]\d(?::[0-5]\d(?:\.\d+)?)?Z?$/', $payload) === 1 ? null : 'not a Time',
            default                                                => 'unknown payload type ' . $kind,
        };

        if ($error !== null) {
            $this->errors[] = $path . ' ' . $payload . ': ' . $error;
        }
    }

    private function pointer(string $payload, string $to): string|null
    {
        if ($payload === '@VOID@') {
            return null;
        }

        if (preg_match('/^' . self::XREF . '$/', $payload) !== 1) {
            return 'not a pointer';
        }

        if ($this->xrefs === []) {
            return null;
        }

        if (!array_key_exists($payload, $this->xrefs)) {
            return 'points to a missing record';
        }

        // An extension tag may be an alias of a standard record, see HEAD:SCHMA.
        if (str_starts_with($this->xrefs[$payload], '_')) {
            return null;
        }

        $type = $this->childType('', $this->xrefs[$payload]);

        return $type === $to ? null : 'points to a ' . $this->xrefs[$payload] . ' record';
    }

    private function enumeration(string $payload, string $set): string|null
    {
        if (array_key_exists($payload, $this->schema['set'][$set] ?? []) || preg_match('/^' . self::EXT_TAG . '$/', $payload) === 1) {
            return null;
        }

        return 'not a value of ' . substr($set, strlen(self::V7));
    }

    private function enumerations(string $payload, string $set): string|null
    {
        foreach (explode(',', $payload) as $value) {
            $error = $this->enumeration(trim($value), $set);

            if ($error !== null) {
                return $error;
            }
        }

        return null;
    }

    /**
     * @return list<Node> top-level nodes
     */
    private function parse(string $record): array
    {
        $roots = [];
        $stack = [];

        foreach (explode("\n", $record) as $number => $line) {
            if (preg_match('/^(0|[1-9]\d*) (?:(@[^@ ]+@) )?([A-Za-z0-9_]+)(?: (.*))?$/', $line, $match) !== 1) {
                $this->errors[] = 'line ' . ($number + 1) . ': not a GEDCOM line: ' . $line;
                continue;
            }

            $level   = (int) $match[1];
            $xref    = $match[2];
            $tag     = $match[3];
            $payload = $match[4] ?? '';

            if ($xref !== '' && $level > 0) {
                $this->errors[] = 'line ' . ($number + 1) . ': XREF on a substructure';
            }

            if ($tag === 'CONC') {
                $this->errors[] = 'line ' . ($number + 1) . ': CONC';
                continue;
            }

            if ($tag === 'CONT') {
                if (!isset($stack[$level - 1]) || count($stack) !== $level) {
                    $this->errors[] = 'line ' . ($number + 1) . ': CONT without a parent';
                    continue;
                }

                if ($this->file && str_starts_with($payload, '@@')) {
                    $payload = substr($payload, 1);
                }

                $stack[$level - 1]->payload .= "\n" . $payload;
                $stack[$level - 1]->cont = true;
                continue;
            }

            if ($level > count($stack)) {
                $this->errors[] = 'line ' . ($number + 1) . ': level ' . $level . ' after level ' . (count($stack) - 1);
                continue;
            }

            $node  = new Node($level, $xref, $tag, $payload);
            $stack = array_slice($stack, 0, $level);

            if ($level === 0) {
                $roots[] = $node;
            } else {
                $stack[$level - 1]->children[] = $node;
            }

            $stack[] = $node;
        }

        return $roots;
    }
}

final class Node
{
    public bool $cont = false;

    /** @var list<Node> */
    public array $children = [];

    public function __construct(
        public readonly int $level,
        public readonly string $xref,
        public readonly string $tag,
        public string $payload,
    ) {
    }
}
