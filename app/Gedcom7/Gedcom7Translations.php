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

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;

use function explode;
use function implode;
use function preg_match;
use function preg_match_all;
use function strtolower;
use function trim;

use const PREG_SET_ORDER;

/**
 * Translations of GEDCOM 7.0, e.g. "1 TRAN …" + "2 LANG de", in the language of the viewer.
 */
final class Gedcom7Translations
{
    /**
     * Is this the language of the viewer?  e.g. "de" and "de-AT" for German.
     */
    public static function isViewerLanguage(string $language): bool
    {
        return strtolower(explode('-', trim($language))[0]) === explode('-', I18N::languageTag())[0];
    }

    /**
     * The language of the texts that do not have their own, e.g. "1 LANG de" in the header.  Empty if not known.
     */
    public static function fileLanguage(Tree $tree): string
    {
        return Registry::headerFactory()->make('HEAD', $tree)?->facts(['LANG'])->first()?->value() ?? '';
    }

    /**
     * The translation of a structure into the language of the viewer, or null if there is none.
     *
     * @param string $gedcom The structure, e.g. a shared note record
     * @param int    $level  The level of the structure, e.g. 0 for a record
     */
    public static function text(string $gedcom, int $level): string|null
    {
        $sub    = $level + 1;
        $subsub = $level + 2;

        preg_match_all('/\n' . $sub . ' TRAN ?(.*)((?:\n(?:[' . $subsub . '-9]) .*)*)/', $gedcom, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            if (preg_match('/\n' . $subsub . ' LANG (.+)/', $match[2], $language) === 1 && self::isViewerLanguage($language[1])) {
                preg_match_all('/\n' . $subsub . ' CONT ?(.*)/', $match[2], $lines);

                return implode("\n", [$match[1], ...$lines[1]]);
            }
        }

        return null;
    }
}
