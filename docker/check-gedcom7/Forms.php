<?php

declare(strict_types=1);

namespace Webtrees7Check;

use Fisharebest\Webtrees\Elements\UnknownElement;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Schema;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomEditService;
use Fisharebest\Webtrees\Tree;

use function array_keys;
use function array_slice;
use function count;
use function explode;
use function implode;
use function in_array;
use function ksort;
use function preg_match;
use function sort;
use function str_contains;
use function str_starts_with;
use function strlen;
use function strrpos;
use function substr;
use function substr_count;
use function uasort;

/**
 * Compare the substructures that the edit forms offer with those of the GEDCOM 7.0 schema.
 *
 * The forms are built from the element factory, as GedcomEditService::insertMissingLevels() does for a browser,
 * with all tags shown.  Each path of a form is compared with the schema, by the structure types along the path.
 */
final class Forms extends GedcomEditService
{
    // Substructures of records that webtrees writes, and that the forms remove
    private const array AUTOMATIC = ['CHAN', 'CREA'];

    /** @var array<string,list<string>> Problems, by kind, e.g. "missing" => ["INDI:BIRT: SDATE 0:1"] */
    public array $problems = ['missing' => [], 'extra' => [], 'cardinality' => []];

    /** @var array<string,list<string>> Paths that are not counted as problems, by kind */
    public array $notes = ['automatic' => [], 'repeated' => [], 'extension' => [], 'required' => [], 'hidden' => []];

    /** @var array<string,array<string,list<string>>> Element classes, by structure type, e.g. "DATE" => ["DateValue" => ["INDI:BIRT:DATE", ...]] */
    public array $classes = [];

    /** @var array<string,array<string,array{count:int,example:string}>> Problems, by kind and by structure type and tag, e.g. "NOTE > LANG" */
    public array $patterns = ['missing' => [], 'cardinality' => []];

    /** @var array<int,int> Missing substructures, by level */
    public array $levels = [];

    public int $paths = 0;

    public function run(Tree $tree): void
    {
        Editor::request($tree);

        foreach (Gedcom7Schema::RECORDS as $tag => $type) {
            // The header is written by webtrees.
            if ($tag === 'HEAD') {
                continue;
            }

            $all     = $this->insertMissingLevels($tree, $tag, '0 @X1@ ' . $tag, true);
            $visible = $this->insertMissingLevels($tree, $tag, '0 @X1@ ' . $tag, false);

            $this->compare(self::paths($all), self::paths($visible), $tag, $type);
        }

        foreach ($this->notes as &$paths) {
            sort($paths);
        }

        ksort($this->classes);
        ksort($this->levels);

        foreach ($this->patterns as &$patterns) {
            uasort($patterns, static fn (array $x, array $y): int => $y['count'] <=> $x['count']);
        }
    }

    /**
     * The same problem, wherever the structure type occurs.
     */
    private function pattern(string $kind, string $pattern, string $path): void
    {
        $this->patterns[$kind][$pattern] ??= ['count' => 0, 'example' => $path];
        $this->patterns[$kind][$pattern]['count']++;
    }

    /**
     * @return list<string>
     */
    public function patternLines(string $kind): array
    {
        $lines = [];

        foreach ($this->patterns[$kind] as $pattern => ['count' => $count, 'example' => $example]) {
            $lines[] = $count . 'x ' . $pattern . ', e.g. ' . $example;
        }

        return $lines;
    }

    /**
     * Compare one record, and all its substructures, with the schema.
     *
     * @param array<string,true> $paths   Every path of the form, with all tags shown
     * @param array<string,true> $visible The paths of the form, as first shown
     * @param list<string>       $types   The structure types above, e.g. to stop at INDI:BIRT:SOUR:NOTE:SOUR
     */
    private function compare(array $paths, array $visible, string $path, string $type, array $types = []): void
    {
        $this->paths++;
        $types[] = $type;

        $factory = Registry::elementFactory();
        $element = $factory->make($path);
        $offered = $element->subtags();

        $this->classes[$type][self::shortClass($element::class)][] = $path;

        foreach (Gedcom7Schema::SUBSTRUCTURES[$type] ?? [] as $subtag => [$subtype, $cardinality]) {
            $subpath = $path . ':' . $subtag;

            if (!isset($paths[$subpath])) {
                if (!str_contains($path, ':') && in_array($subtag, self::AUTOMATIC, true)) {
                    $this->notes['automatic'][] = $subpath;
                } elseif (in_array($subtype, $types, true)) {
                    // e.g. INDI:BIRT:SOUR:NOTE:SOUR
                    $this->notes['repeated'][] = $subpath;
                } else {
                    $why = $factory->make($subpath) instanceof UnknownElement ? 'no element' : 'not a subtag';

                    $this->problems['missing'][] = $subpath . ' ' . $cardinality . ' (' . $why . ')';
                    $this->pattern('missing', $type . ' > ' . $subtag . ' ' . $cardinality . ' (' . $why . ')', $subpath);

                    $level                = substr_count($subpath, ':');
                    $this->levels[$level] = ($this->levels[$level] ?? 0) + 1;
                }

                continue;
            }

            [$min, $max]   = explode(':', $cardinality);
            [$fmin, $fmax] = explode(':', $offered[$subtag] ?? '0:0');

            if ($max !== $fmax) {
                $this->problems['cardinality'][] = $subpath . ' ' . $cardinality . ', form ' . $offered[$subtag];
                $this->pattern('cardinality', '* > ' . $subtag . ' ' . $cardinality . ', form ' . $fmin . ':' . $fmax, $subpath);
            } elseif ($min !== $fmin) {
                $this->notes['required'][] = $subpath . ' ' . $cardinality . ', form ' . $offered[$subtag];
            }

            if (!isset($visible[$subpath])) {
                $this->notes['hidden'][] = $subpath;
            }

            $this->compare($paths, $visible, $subpath, $subtype, $types);
        }

        // What the form offers, but the schema does not
        foreach (self::children($paths, $path) as $subtag) {
            if (isset(Gedcom7Schema::SUBSTRUCTURES[$type][$subtag])) {
                continue;
            }

            if (str_starts_with($subtag, '_')) {
                $this->notes['extension'][] = $path . ':' . $subtag;
            } else {
                $this->problems['extra'][] = $path . ':' . $subtag . ' ' . ($offered[$subtag] ?? '?');
            }
        }
    }

    /**
     * The tags directly below a path.
     *
     * @param array<string,true> $paths
     *
     * @return list<string>
     */
    private static function children(array $paths, string $path): array
    {
        $children = [];

        foreach (array_keys($paths) as $child) {
            if (str_starts_with($child, $path . ':') && !str_contains(substr($child, strlen($path) + 1), ':')) {
                $children[] = substr($child, strlen($path) + 1);
            }
        }

        return $children;
    }

    /**
     * The paths of the lines of a record, e.g. "INDI:BIRT:DATE".
     *
     * @return array<string,true>
     */
    private static function paths(string $gedcom): array
    {
        $paths = [];
        $stack = [];

        foreach (explode("\n", $gedcom) as $line) {
            if (preg_match('/^(\d+) (?:@[^@]+@ )?(\w+)/', $line, $match) === 1) {
                $stack = [...array_slice($stack, 0, (int) $match[1]), $match[2]];

                $paths[implode(':', $stack)] = true;
            }
        }

        return $paths;
    }

    private static function shortClass(string $class): string
    {
        return substr($class, (int) strrpos($class, '\\') + 1);
    }

    /**
     * Structure types whose paths use different element classes, e.g. a date that is not a DateValue.
     *
     * @return list<string>
     */
    public function mixedClasses(): array
    {
        $lines = [];

        foreach ($this->classes as $type => $classes) {
            if (count($classes) > 1 || isset($classes['UnknownElement'])) {
                foreach ($classes as $class => $paths) {
                    $lines[] = $type . ': ' . $class . ' ' . count($paths) . ', e.g. ' . $paths[0];
                }
            }
        }

        return $lines;
    }
}
