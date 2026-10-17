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

use Fisharebest\Webtrees\Html;
use Fisharebest\Webtrees\Tree;

use function e;
use function strip_tags;

/**
 * The phrase of an enumeration, e.g. "PEDI OTHER" + "PHRASE Rada".  The other webtrees values are suggested.
 */
class EnumerationPhrase extends AbstractElement
{
    /**
     * @param list<string> $suggestions e.g. ["Patin", "Pfarrer"]
     */
    public function __construct(string $label, private readonly array $suggestions = [])
    {
        parent::__construct($label);
    }

    /**
     * An edit control for this data.
     */
    public function edit(string $id, string $name, string $value, Tree $tree): string
    {
        if ($this->suggestions === []) {
            return parent::edit($id, $name, $value, $tree);
        }

        $attributes = [
            'class'       => 'form-control',
            'dir'         => 'auto',
            'type'        => 'text',
            'id'          => $id,
            'name'        => $name,
            'value'       => $value,
            'list'        => $id . '-suggestions',
            'placeholder' => strip_tags($this->label()),
        ];

        $html = '<input ' . Html::attributes($attributes) . ' />';
        $html .= '<datalist id="' . e($id) . '-suggestions">';

        foreach ($this->suggestions as $suggestion) {
            $html .= '<option value="' . e($suggestion) . '"></option>';
        }

        return $html . '</datalist>';
    }
}
