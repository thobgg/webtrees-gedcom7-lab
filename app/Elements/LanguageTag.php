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

use Fisharebest\Webtrees\Gedcom7\Gedcom7Payload;
use Fisharebest\Webtrees\I18N;

use function array_flip;
use function uasort;

/**
 * A GEDCOM 7.0 language, as a BCP 47 language tag, e.g. "de".  GEDCOM 5.5.1 languages, e.g. "German", are converted.
 */
class LanguageTag extends AbstractElement
{
    /**
     * Convert a value to a canonical form.
     */
    public function canonical(string $value): string
    {
        $value = parent::canonical($value);

        return Gedcom7Payload::language($value) ?? $value;
    }

    /**
     * A list of controlled values for this element
     *
     * @return array<int|string,string>
     */
    public function values(): array
    {
        $names  = (new LanguageId(''))->values();
        $values = [];

        foreach (array_flip(Gedcom7Payload::LANGUAGES) as $tag => $name) {
            $values[$tag] = $names[$name] ?? $tag;
        }

        uasort($values, I18N::compare(...));

        return ['' => ''] + $values;
    }
}
