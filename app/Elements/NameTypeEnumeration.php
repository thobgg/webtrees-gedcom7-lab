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

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Tree;

/**
 * GEDCOM 7.0 enumset-NAME-TYPE.  The other webtrees name types are OTHER + PHRASE, e.g. "Estate".
 */
class NameTypeEnumeration extends NameType
{
    use Gedcom7EnumerationTrait;

    protected const array SUBTAGS = [
        'PHRASE' => '0:1',
    ];

    /**
     * An edit control for this data.
     */
    public function edit(string $id, string $name, string $value, Tree $tree): string
    {
        return $this->editEnumeration($id, $name, $value, 'NAME-TYPE');
    }

    /**
     * A list of controlled values for this element
     *
     * @return array<int|string,string>
     */
    public function values(): array
    {
        return parent::values() + [
            /* I18N: A name used in a profession, e.g. a pen name or a stage name */
            'PROFESSIONAL' => I18N::translate('professional name'),
            'OTHER'        => I18N::translate('Other'),
        ];
    }
}
