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

use function strtoupper;

/**
 * GEDCOM 7.0 enumset-ROLE
 * The role of an associate, e.g. a godparent at a baptism.  Other roles are OTHER + PHRASE.
 */
class AssociationRole extends AbstractElement
{
    /**
     * Convert a value to a canonical form.
     */
    public function canonical(string $value): string
    {
        return strtoupper(parent::canonical($value));
    }

    /**
     * A list of controlled values for this element
     *
     * @return array<int|string,string>
     */
    public function values(): array
    {
        return [
            ''           => '',
            'CHIL'       => I18N::translate('Child'),
            'CLERGY'     => I18N::translate('Clergy'),
            'FATH'       => I18N::translate('Father'),
            'FRIEND'     => I18N::translate('Friend'),
            'GODP'       => I18N::translate('Godparent'),
            'HUSB'       => I18N::translate('Husband'),
            'MOTH'       => I18N::translate('Mother'),
            'MULTIPLE'   => I18N::translate('Multiple birth'),
            'NGHBR'      => I18N::translate('Neighbor'),
            'OFFICIATOR' => I18N::translate('Officiator'),
            'PARENT'     => I18N::translate('Parent'),
            'SPOU'       => I18N::translate('Spouse'),
            'WIFE'       => I18N::translate('Wife'),
            'WITN'       => I18N::translate('Witness'),
            'OTHER'      => I18N::translate('Other'),
        ];
    }
}
