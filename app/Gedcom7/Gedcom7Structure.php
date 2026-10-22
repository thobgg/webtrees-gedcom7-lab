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

use Fisharebest\Webtrees\Elements\UnknownElement;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Registry;

use function in_array;
use function str_starts_with;
use function strip_tags;

/**
 * The structures of a fact, and what GEDCOM 7.0 says about each of them.
 *
 * @phpstan-type Structure array{tag:string,path:string,value:string,pointer:bool,status:string,messages:list<string>,children:list<mixed>}
 */
final class Gedcom7Structure
{
    public const string OK        = 'ok';
    public const string EXTENSION = 'extension';
    public const string ERROR     = 'error';

    /**
     * @return Structure
     */
    public static function fact(string $record_type, string $gedcom): array
    {
        $record    = Gedcom7Node::parse('0 @X@ ' . $record_type . "\n" . $gedcom);
        $converted = self::paths(Gedcom7Node::parse((new Gedcom7Converter())->convertRecord('0 @X@ ' . $record_type . "\n" . $gedcom)), $record_type);
        $node      = $record->children[0];
        $type      = Gedcom7Schema::substructures(Gedcom7Schema::records()[$record_type] ?? '')[$node->tag][0] ?? null;

        return self::node($node, $record_type . ':' . $node->tag, $type, $converted);
    }

    /**
     * @param list<string> $converted The structures that GEDCOM 7.0 keeps, e.g. "INDI:BAPL:STAT"
     *
     * @return Structure
     */
    private static function node(Gedcom7Node $node, string $path, string|null $type, array $converted): array
    {
        $element  = Registry::elementFactory()->make($path);
        $label    = strip_tags($element->label());
        $messages = [];

        // An extension, unless the schema knows it, e.g. the _LOC of GEDCOM-L
        if ($type === null && str_starts_with($node->tag, '_')) {
            $status = self::EXTENSION;

            if ($element instanceof UnknownElement) {
                $messages[] = I18N::translate('An extension tag that webtrees does not know.');
            }
        } elseif ($type === null) {
            $status     = self::ERROR;
            $messages[] = I18N::translate('%s is not permitted here.', $label);
        } else {
            $status = self::OK;

            foreach (Gedcom7Schema::substructures($type) as $tag => [, $cardinality]) {
                if (str_starts_with($cardinality, '1') && $node->child($tag) === null) {
                    $status     = self::ERROR;
                    $messages[] = I18N::translate('%s is missing.', strip_tags(Registry::elementFactory()->make($path . ':' . $tag)->label()));
                }
            }

            if ($status === self::OK && !in_array($path, $converted, true)) {
                $status     = self::ERROR;
                $messages[] = I18N::translate('The value is missing or not valid.');
            }
        }

        $children = [];

        foreach ($node->children as $child) {
            $child_type = $type === null ? null : Gedcom7Schema::substructures($type)[$child->tag][0] ?? null;
            $children[] = self::node($child, $path . ':' . $child->tag, $child_type, $converted);
        }

        return [
            'tag'      => $node->tag,
            'path'     => $path,
            'value'    => $node->value,
            'pointer'  => $node->pointer,
            'status'   => $status,
            'messages' => $messages,
            'children' => $children,
        ];
    }

    /**
     * @return list<string>
     */
    private static function paths(Gedcom7Node $node, string $path): array
    {
        $paths = [$path];

        foreach ($node->children as $child) {
            foreach (self::paths($child, $path . ':' . $child->tag) as $child_path) {
                $paths[] = $child_path;
            }
        }

        return $paths;
    }
}
