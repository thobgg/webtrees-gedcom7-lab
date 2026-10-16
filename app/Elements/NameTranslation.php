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

namespace Fisharebest\Webtrees\Elements;

/**
 * NAME_TRANSLATION := TRAN <PersonalName> +1 LANG <Language>
 * A name in another language or script, e.g. romanized (LANG und-Latn) or phonetic (LANG und).
 */
class NameTranslation extends NameRomanizedVariation
{
    protected const array SUBTAGS = [
        'LANG' => '1:1',
        'NPFX' => '0:1',
        'GIVN' => '0:1',
        'SPFX' => '0:1',
        'SURN' => '0:1',
        'NSFX' => '0:1',
        'NICK' => '0:1',
        'NOTE' => '0:M',
        'SOUR' => '0:M',
    ];
}
