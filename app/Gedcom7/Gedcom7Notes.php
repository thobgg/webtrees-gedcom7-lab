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

use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\HtmlService;
use Fisharebest\Webtrees\Tree;

use function preg_match;
use function strtolower;
use function trim;

/**
 * The text of a note, as GEDCOM 7.0 describes it: plain text, or HTML (MIME text/html).
 */
final class Gedcom7Notes
{
    public const string HTML  = 'text/html';
    public const string PLAIN = 'text/plain';

    /**
     * The media type of a note, e.g. "0 @N1@ SNOTE text" + "1 MIME text/html", or of an inline note.
     *
     * @param int $level The level of the MIME line, e.g. 1 for a shared note
     */
    public static function mime(string $gedcom, int $level = 1): string
    {
        if (preg_match('/\n' . $level . ' MIME (.+)/', $gedcom, $match) === 1) {
            return strtolower(trim($match[1]));
        }

        return self::PLAIN;
    }

    /**
     * The HTML of a note: sanitised HTML for text/html, otherwise markdown or plain text with links, as the tree prefers.
     */
    public static function html(string $text, string $mime, Tree $tree): string
    {
        if (strtolower(trim($mime)) === self::HTML) {
            return Registry::container()->get(HtmlService::class)->sanitize($text);
        }

        if ($tree->getPreference('FORMAT_TEXT') === 'markdown') {
            return Registry::markdownFactory()->markdown($text, $tree);
        }

        return Registry::markdownFactory()->autolink($text, $tree);
    }
}
