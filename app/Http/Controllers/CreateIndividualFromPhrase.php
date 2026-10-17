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

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function preg_match_all;
use function response;
use function strlen;
use function substr_replace;

use const PREG_OFFSET_CAPTURE;
use const PREG_SET_ORDER;

/**
 * An associate without a record, e.g. "2 ASSO @VOID@" + "3 PHRASE John Smith", becomes an individual.
 */
final class CreateIndividualFromPhrase
{
    public function post(ServerRequestInterface $request, Tree $tree): ResponseInterface
    {
        $xref    = Validator::attributes($request)->isXref()->string('xref');
        $fact_id = Validator::attributes($request)->string('fact_id');
        $index   = Validator::attributes($request)->integer('index');
        $record  = Registry::gedcomRecordFactory()->make($xref, $tree);
        $record  = Auth::checkRecordAccess($record, true);
        $fact    = $record->facts([], false, null, true)->first(static fn (Fact $fact): bool => $fact->id() === $fact_id);

        if (!$fact instanceof Fact || !$fact->canEdit()) {
            throw new HttpNotFoundException();
        }

        preg_match_all('/\n2 ASSO @VOID@(?:\n3 PHRASE (.*))?/', $fact->gedcom(), $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $match = $matches[$index] ?? null;
        $name  = $match[1][0] ?? '';

        if ($match === null || $name === '') {
            throw new HttpNotFoundException();
        }

        $individual = $tree->createIndividual("0 @@ INDI\n1 NAME " . $name . "\n1 SEX U");
        $gedcom     = substr_replace($fact->gedcom(), "\n2 ASSO @" . $individual->xref() . '@', $match[0][1], strlen($match[0][0]));

        $record->updateFact($fact_id, $gedcom, true);

        return response();
    }
}
