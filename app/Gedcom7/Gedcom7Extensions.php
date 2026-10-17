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

use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Elements\UnknownElement;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;

use function array_slice;
use function explode;
use function implode;
use function ksort;
use function preg_match;
use function preg_match_all;
use function str_starts_with;

use const PREG_SET_ORDER;

/**
 * Extension tags, e.g. "_LOC", and their documentation in HEAD:SCHMA.
 */
final class Gedcom7Extensions
{
    private const array TABLES = [
        'individuals' => ['i_file', 'i_gedcom'],
        'families'    => ['f_file', 'f_gedcom'],
        'sources'     => ['s_file', 's_gedcom'],
        'media'       => ['m_file', 'm_gedcom'],
        'other'       => ['o_file', 'o_gedcom'],
    ];

    /**
     * The extension tags of a tree, e.g. ["_LOC" => ["count" => 12, "path" => "_LOC", "label" => "Location", "uri" => "..."]].
     *
     * @return array<string,array{count:int,path:string,label:string,uri:string}>
     */
    public static function tags(Tree $tree): array
    {
        $documentation = self::documentation($tree);
        $tags          = [];

        foreach (self::TABLES as $table => [$file, $gedcom]) {
            foreach (DB::table($table)->where($file, '=', $tree->id())->select([$gedcom])->cursor() as $row) {
                $hierarchy = [];

                foreach (explode("\n", $row->$gedcom) as $line) {
                    if (preg_match('/^(\d+) (?:@[^@]+@ )?(\w+)/', $line, $match) !== 1) {
                        continue;
                    }

                    $level             = (int) $match[1];
                    $hierarchy[$level] = $match[2];
                    $hierarchy         = array_slice($hierarchy, 0, $level + 1);

                    if (str_starts_with($match[2], '_')) {
                        $path    = implode(':', $hierarchy);
                        $element = Registry::elementFactory()->make($path);

                        $tags[$match[2]] ??= [
                            'count' => 0,
                            'path'  => $path,
                            'label' => $element instanceof UnknownElement ? '' : $element->label(),
                            'uri'   => $documentation[$match[2]] ?? '',
                        ];

                        $tags[$match[2]]['count']++;
                    }
                }
            }
        }

        ksort($tags);

        return $tags;
    }

    /**
     * The URIs of the extension tags in HEAD:SCHMA, e.g. ["_LOC" => "https://genealogy.net/GEDCOM/"].
     *
     * @return array<string,string>
     */
    public static function documentation(Tree $tree): array
    {
        $header = Registry::headerFactory()->make('HEAD', $tree);
        $uris   = [];

        foreach ($header?->facts(['SCHMA']) ?? [] as $fact) {
            preg_match_all('/\n2 TAG (_\w+) (\S+)/', $fact->gedcom(), $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $uris[$match[1]] = $match[2];
            }
        }

        return $uris;
    }
}
