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

use Fisharebest\Webtrees\Tree;

use function e;
use function rawurlencode;

/**
 * An external identifier of a registered type, as a link to its web page, e.g. a Find a Grave memorial.
 */
class ExternalIdentifierLink extends AbstractElement
{
    /**
     * @param string $url The web page of an identifier, without the identifier, e.g. "https://www.findagrave.com/memorial/"
     */
    public function __construct(string $label, private readonly string $url)
    {
        parent::__construct($label);
    }

    /**
     * Display the value of this type of element.
     */
    public function value(string $value, Tree $tree): string
    {
        $canonical = $this->canonical($value);

        return '<a dir="ltr" href="' . e($this->url . rawurlencode($canonical)) . '" rel="nofollow">' . e($canonical) . '</a>';
    }
}
