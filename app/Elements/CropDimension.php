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

use function preg_replace;

/**
 * GEDCOM 7.0 OBJE:CROP:TOP, LEFT, HEIGHT and WIDTH, in pixels.  Zero is the edge of the image.
 */
class CropDimension extends AbstractElement
{
    protected const string|false PATTERN = '[0-9]*';

    public function canonical(string $value): string
    {
        return (string) preg_replace('/\D/', '', $value);
    }

    public function edit(string $id, string $name, string $value, Tree $tree): string
    {
        $attributes = [
            'class' => 'form-control',
            'type'  => 'number',
            'min'   => '0',
            'step'  => '1',
            'id'    => $id,
            'name'  => $name,
            'value' => $this->canonical($value),
        ];

        return '<input ' . Html::attributes($attributes) . ' />';
    }
}
