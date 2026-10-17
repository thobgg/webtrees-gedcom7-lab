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

use Fisharebest\Webtrees\Gedcom7\Gedcom7Schema;

use function array_key_exists;
use function in_array;
use function strip_tags;
use function view;

/**
 * Edit the values of a GEDCOM 7.0 enumeration.  Other values are OTHER + PHRASE.
 */
trait Gedcom7EnumerationTrait
{
    protected function editEnumeration(string $id, string $name, string $value, string $enumeration): string
    {
        $value   = $this->canonical($value);
        $options = [];

        foreach ($this->values() as $key => $label) {
            if ($key === '' || in_array((string) $key, Gedcom7Schema::ENUMERATIONS[$enumeration], true)) {
                $options[$key] = strip_tags($label);
            }
        }

        // Ensure the current data is in the list.
        if (!array_key_exists($value, $options)) {
            $options = [$value => $value] + $options;
        }

        return view('components/select', [
            'id'       => $id,
            'name'     => $name,
            'options'  => $options,
            'selected' => $value,
        ]);
    }
}
