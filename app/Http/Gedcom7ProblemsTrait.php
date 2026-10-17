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

namespace Fisharebest\Webtrees\Http;

use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Problems;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Storage;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function array_map;
use function preg_split;
use function trim;

/**
 * Show the form for a new individual again, with what GEDCOM 7.0 does not permit.
 * Used by controllers that use ViewResponseTrait and have a GedcomEditService.
 */
trait Gedcom7ProblemsTrait
{
    /**
     * @param array<string,string> $gedcom The facts of each part of the form, "i" for the individual and "f" for the family
     */
    private function newIndividualProblems(ServerRequestInterface $request, Tree $tree, array $gedcom, string $title, string $url): ResponseInterface|null
    {
        if (!Gedcom7Storage::isGedcom7Tree($tree) || Validator::parsedBody($request)->boolean('ignore_problems', false)) {
            return null;
        }

        $problems = [];
        $facts    = [];

        foreach ($gedcom as $prefix => $lines) {
            $lines  = trim($lines);
            $record = $prefix === 'f' ? Registry::familyFactory()->new('', '0 @@ FAM', null, $tree) : Registry::individualFactory()->new('', '0 @@ INDI', null, $tree);

            foreach (Gedcom7Problems::fact($prefix === 'f' ? Family::RECORD_TYPE : Individual::RECORD_TYPE, $lines) as $problem) {
                $problems[] = $problem;
            }

            $facts[$prefix] = $lines === '' ? [] : array_map(static fn (string $fact): Fact => new Fact($fact, $record, ''), preg_split('/\n(?=1)/', $lines));
        }

        if ($problems === []) {
            return null;
        }

        return $this->viewResponse('edit/new-individual', [
            'facts'               => $facts,
            'gedcom_edit_service' => $this->gedcom_edit_service,
            'post_url'            => (string) $request->getUri(),
            'problems'            => $problems,
            'title'               => $title,
            'tree'                => $tree,
            'url'                 => $url,
        ]);
    }
}
