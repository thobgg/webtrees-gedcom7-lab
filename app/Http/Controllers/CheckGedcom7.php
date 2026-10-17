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

namespace Fisharebest\Webtrees\Http\Controllers;

use Fisharebest\Webtrees\Gedcom7\Gedcom7Problems;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Schema;
use Fisharebest\Webtrees\Services\GedcomEditService;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function array_keys;
use function response;
use function view;

/**
 * Check an edit form while it is being filled, e.g. an LDS status without a date.
 */
final class CheckGedcom7
{
    public function __construct(
        private readonly GedcomEditService $gedcom_edit_service,
    ) {
    }

    public function post(ServerRequestInterface $request): ResponseInterface
    {
        $record_type = Validator::attributes($request)->isInArray(array_keys(Gedcom7Schema::RECORDS))->string('record_type');
        $scope       = Validator::attributes($request)->isInArray(['fact', 'record'])->string('scope');
        $levels      = Validator::parsedBody($request)->list('levels');
        $tags        = Validator::parsedBody($request)->list('tags');
        $values      = Validator::parsedBody($request)->list('values');

        if ($scope === 'fact') {
            $gedcom   = $this->gedcom_edit_service->editLinesToGedcom($record_type, $levels, $tags, $values, false);
            $problems = Gedcom7Problems::fact($record_type, $gedcom);
        } else {
            $gedcom   = '0 @X@ ' . $record_type . $this->gedcom_edit_service->editLinesToGedcom($record_type, $levels, $tags, $values);
            $problems = Gedcom7Problems::record($gedcom);
        }

        return response(view('edit/gedcom7-problems', ['problems' => $problems]));
    }
}
