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

use Fisharebest\Webtrees\Gedcom7\Gedcom7Identifiers;
use Fisharebest\Webtrees\Html;
use Fisharebest\Webtrees\Tree;

use function e;
use function strip_tags;

/**
 * EXID:TYPE fields
 */
class ExternalIdentifierType extends AbstractElement
{
    /**
     * An edit control for this data.  The registered types are suggested, e.g. "GOV" for https://gov.genealogy.net/
     */
    public function edit(string $id, string $name, string $value, Tree $tree): string
    {
        $attributes = [
            'class'       => 'form-control',
            'dir'         => 'ltr',
            'type'        => 'text',
            'id'          => $id,
            'name'        => $name,
            'value'       => $value,
            'list'        => $id . '-types',
            'placeholder' => strip_tags($this->label()),
        ];

        $html = '<input ' . Html::attributes($attributes) . ' />';
        $html .= '<datalist id="' . e($id) . '-types">';

        foreach (Gedcom7Identifiers::types() as $type => $label) {
            $html .= '<option value="' . e($type) . '">' . e($label) . '</option>';
        }

        return $html . '</datalist>';
    }

    /**
     * Display the value of this type of element.
     */
    public function value(string $value, Tree $tree): string
    {
        $label = Gedcom7Identifiers::label($value);

        if ($label === null) {
            return parent::value($value, $tree);
        }

        return '<span title="' . e($value) . '">' . e($label) . '</span>';
    }
}
