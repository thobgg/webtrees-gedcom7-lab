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
use Fisharebest\Webtrees\Enums\Sex;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Problems;
use Fisharebest\Webtrees\Http\ViewResponseTrait;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\SourceEntryService;
use Fisharebest\Webtrees\Source;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function array_fill;
use function array_filter;
use function array_key_first;
use function array_keys;
use function array_map;
use function array_replace;
use function array_values;
use function count;
use function explode;
use function min;
use function redirect;
use function route;
use function strip_tags;
use function trim;

use const ARRAY_FILTER_USE_KEY;
use const PHP_INT_MAX;

/**
 * Record an entry of a source, e.g. a baptism in a parish register, for everyone in it.
 */
final class AddSourceEntry
{
    use ViewResponseTrait;

    public function __construct(
        private readonly SourceEntryService $source_entry_service,
    ) {
    }

    public function get(ServerRequestInterface $request, Tree $tree): ResponseInterface
    {
        $event  = Validator::attributes($request)->isInArray(array_keys(SourceEntryService::TEMPLATES))->string('fact');
        $source = Registry::sourceFactory()->make(Validator::queryParams($request)->string('source', ''), $tree);
        $person = Registry::individualFactory()->make(Validator::queryParams($request)->string('xref', ''), $tree);
        $roles  = SourceEntryService::TEMPLATES[$event];
        $empty  = array_fill(0, count($roles), '');
        $phrase = $empty;

        // The parents of the couple
        if ($event === 'MARR') {
            $phrase = array_replace($phrase, [
                2 => I18N::translate('Father of the groom'),
                3 => I18N::translate('Mother of the groom'),
                4 => I18N::translate('Father of the bride'),
                5 => I18N::translate('Mother of the bride'),
            ]);
        }

        $persons = $empty;

        if ($person instanceof Individual && $person->canEdit()) {
            $persons[$event === 'MARR' && $person->sex() === Sex::Female ? 1 : 0] = '@' . $person->xref() . '@';
        }

        $values = [
            'source'      => $source instanceof Source && $source->canShow() ? '@' . $source->xref() . '@' : '',
            'page'        => '',
            'entry_date'  => '',
            'text'        => '',
            'media'       => '',
            'quality'     => '',
            'date'        => '',
            'place'       => '',
            'role'        => $roles,
            'role_phrase' => $phrase,
            'person'      => $persons,
            'name'        => $empty,
            'sex'         => $empty,
        ];

        $url = $person instanceof Individual ? $person->url() : ($source instanceof Source ? $source->url() : route(TreePage::class, ['tree' => $tree->name()]));

        return $this->form($tree, $event, $values, [], [], $url);
    }

    public function post(ServerRequestInterface $request, Tree $tree): ResponseInterface
    {
        $event   = Validator::attributes($request)->isInArray(array_keys(SourceEntryService::TEMPLATES))->string('fact');
        $factory = Registry::elementFactory();
        $tag     = $this->source_entry_service->tag($event);
        $url     = Validator::parsedBody($request)->isLocalUrl()->string('url', route(TreePage::class, ['tree' => $tree->name()]));

        $values = [
            'source'      => Validator::parsedBody($request)->string('source', ''),
            'page'        => $factory->make('INDI:SOUR:PAGE')->canonical(Validator::parsedBody($request)->string('page', '')),
            'entry_date'  => $factory->make('INDI:SOUR:DATA:DATE')->canonical(Validator::parsedBody($request)->string('entry_date', '')),
            'text'        => $factory->make('INDI:SOUR:DATA:TEXT')->canonical(Validator::parsedBody($request)->string('text', '')),
            'media'       => Validator::parsedBody($request)->string('media', ''),
            'quality'     => Validator::parsedBody($request)->string('quality', ''),
            'date'        => $factory->make($tag . ':DATE')->canonical(Validator::parsedBody($request)->string('date', '')),
            'place'       => $factory->make($tag . ':PLAC')->canonical(Validator::parsedBody($request)->string('place', '')),
            'role'        => Validator::parsedBody($request)->list('role'),
            'role_phrase' => Validator::parsedBody($request)->list('role_phrase'),
            'person'      => Validator::parsedBody($request)->list('person'),
            'name'        => Validator::parsedBody($request)->list('name'),
            'sex'         => Validator::parsedBody($request)->list('sex'),
        ];

        $entry = [
            'source'  => $values['source'],
            'page'    => $values['page'],
            'date'    => $values['entry_date'],
            'text'    => $values['text'],
            'media'   => $values['media'],
            'quality' => $values['quality'],
        ];

        $rows       = $this->rows($values['role'], $values['role_phrase'], $values['person'], $values['name'], $values['sex']);
        $principals = SourceEntryService::PRINCIPALS[$event];
        $errors     = [];

        // The principals need an individual, e.g. the child of a baptism.
        for ($n = 0; $n < min($principals, count($values['role'])); $n++) {
            $row = $rows[$n] ?? null;

            if ($row === null && $principals < PHP_INT_MAX || $row !== null && $row['xref'] === '@VOID@') {
                $role     = $values['role'][$n] ?? '';
                $label    = $role === '' ? I18N::translate('Individual') : strip_tags($factory->make('INDI:BAPM:ASSO:ROLE')->value($role, $tree));
                $errors[] = I18N::translate('%s is missing.', $label);
            }
        }

        if ($rows === []) {
            $errors[] = I18N::translate('%s is missing.', I18N::translate('Individual'));
        }

        if (Registry::sourceFactory()->make(trim($entry['source'], '@'), $tree) === null) {
            $errors[] = I18N::translate('%s is missing.', I18N::translate('Source'));
        }

        // New individuals do not have an XREF yet.
        $preview = array_map(static fn (array $row): array => ['xref' => $row['xref'] === '' ? '@VOID@' : $row['xref']] + $row, $rows);

        $problems = $errors === [] ? Gedcom7Problems::fact(explode(':', $tag)[0], $this->events($event, $values, $entry, $preview, $principals)[0]) : [];

        if ($errors !== [] || $problems !== [] && !Validator::parsedBody($request)->boolean('ignore_problems', false)) {
            return $this->form($tree, $event, $values, $errors, $problems, $url);
        }

        // Create the new individuals.
        $individuals = [];

        foreach ($rows as $n => $row) {
            if ($row['xref'] === '') {
                $individuals[$n] = $tree->createIndividual("0 @@ INDI\n1 NAME " . $row['phrase'] . "\n1 SEX " . $row['sex']);
                $row['xref']     = '@' . $individuals[$n]->xref() . '@';
                $row['phrase']   = '';
                $rows[$n]        = $row;
            } elseif ($row['xref'] !== '@VOID@') {
                $individuals[$n] = Registry::individualFactory()->make(trim($row['xref'], '@'), $tree);
            }
        }

        $events = $this->events($event, $values, $entry, $rows, $principals);

        if ($event === 'MARR') {
            $husband = Auth::checkIndividualAccess($individuals[0] ?? null, true);
            $wife    = Auth::checkIndividualAccess($individuals[1] ?? null, true);
            $family  = $this->couple($tree, $husband, $wife);
            $family->createFact($events[0], true);

            return redirect($family->url());
        }

        // Each principal has the event, e.g. everyone in a census.
        foreach ($events as $n => $gedcom) {
            Auth::checkIndividualAccess($individuals[$n] ?? null, true)->createFact($gedcom, true);
        }

        $individual = Auth::checkIndividualAccess($individuals[array_key_first($events)] ?? null, true);

        // The parents' family has the citation too.  GEDCOM 7.0 has no citations of FAMC.
        $father = null;
        $mother = null;

        foreach ($rows as $n => $row) {
            if ($row['role'] === 'FATH' && ($individuals[$n] ?? null) instanceof Individual) {
                $father = $individuals[$n];
            }

            if ($row['role'] === 'MOTH' && ($individuals[$n] ?? null) instanceof Individual) {
                $mother = $individuals[$n];
            }
        }

        $family = $this->family($tree, $individual, $father, $mother);

        if ($family instanceof Family) {
            $family->createFact($this->source_entry_service->citation(1, $this->source_entry_service->reference($entry), $event, '', ''), true);
        }

        return redirect($individual->url());
    }

    /**
     * The events of the principals, with the other participants as associates.
     *
     * @param array<string,string|list<string>>                                                    $values
     * @param array{source:string,page:string,date:string,text:string,media:string,quality:string} $entry
     * @param array<int,array{xref:string,phrase:string,sex:string,role:string,role_phrase:string}> $rows
     *
     * @return array<int,string>
     */
    private function events(string $event, array $values, array $entry, array $rows, int $principals): array
    {
        $date       = (string) $values['date'];
        $place      = (string) $values['place'];
        $associates = array_values(array_filter($rows, static fn (int $n): bool => $n >= $principals, ARRAY_FILTER_USE_KEY));

        // The couple of a marriage share the event.
        if ($event === 'MARR') {
            return [$this->source_entry_service->event($event, $date, $place, $entry, ['role' => '', 'role_phrase' => ''], $associates)];
        }

        $events = [];

        foreach ($rows as $n => $row) {
            if ($n < $principals) {
                $events[$n] = $this->source_entry_service->event($event, $date, $place, $events === [] ? $entry : $this->source_entry_service->reference($entry), $row, $associates);
            }
        }

        return $events === [] ? [''] : $events;
    }

    /**
     * @param array<string,string|list<string>> $values
     * @param list<string>                      $errors   What we need, e.g. the child of a baptism
     * @param list<string>                      $problems What GEDCOM 7.0 does not permit
     */
    private function form(Tree $tree, string $event, array $values, array $errors, array $problems, string $url): ResponseInterface
    {
        $tag   = $this->source_entry_service->tag($event);
        $title = I18N::translate('Add from a source') . ' – ' . Registry::elementFactory()->make($tag)->label();

        return $this->viewResponse('edit/source-entry', [
            'errors'     => $errors,
            'event'      => $event,
            'principals' => SourceEntryService::PRINCIPALS[$event],
            'problems'   => $problems,
            'tag'        => $tag,
            'title'      => $title,
            'tree'       => $tree,
            'url'        => $url,
            'values'     => $values,
        ]);
    }

    /**
     * The participants who were entered: an individual, a new individual (no XREF) or a name without a record (@VOID@).
     *
     * @param list<string> $roles
     * @param list<string> $role_phrases
     * @param list<string> $persons
     * @param list<string> $names
     * @param list<string> $sexes
     *
     * @return array<int,array{xref:string,phrase:string,sex:string,role:string,role_phrase:string}>
     */
    private function rows(array $roles, array $role_phrases, array $persons, array $names, array $sexes): array
    {
        $factory = Registry::elementFactory();
        $rows    = [];

        foreach ($roles as $n => $role) {
            $xref = $persons[$n] ?? '';
            $name = $factory->make('INDI:NAME')->canonical($names[$n] ?? '');

            if ($xref === '' && $name === '' || $xref === '@VOID@' && $name === '') {
                continue;
            }

            $sex = $factory->make('INDI:SEX')->canonical($sexes[$n] ?? '');
            $sex = match (true) {
                $role === 'FATH'                    => Sex::Male->value,
                $role === 'MOTH'                    => Sex::Female->value,
                $sex !== ''                         => $sex,
                $role === 'HUSB'                    => Sex::Male->value,
                $role === 'WIFE'                    => Sex::Female->value,
                default                             => Sex::Unknown->value,
            };

            $rows[$n] = [
                'xref'        => $xref,
                'phrase'      => $xref === '' || $xref === '@VOID@' ? $name : '',
                'sex'         => $sex,
                'role'        => $factory->make('INDI:BAPM:ASSO:ROLE')->canonical($role),
                'role_phrase' => $role_phrases[$n] ?? '',
            ];
        }

        return $rows;
    }

    /**
     * The family of a couple.  Use an existing family, if there is one.
     */
    private function couple(Tree $tree, Individual $husband, Individual $wife): Family
    {
        foreach ($husband->spouseFamilies() as $family) {
            if ($family->spouse($husband)?->xref() === $wife->xref()) {
                return $family;
            }
        }

        $family = $tree->createFamily("0 @@ FAM\n1 HUSB @" . $husband->xref() . "@\n1 WIFE @" . $wife->xref() . '@');

        $husband->createFact('1 FAMS @' . $family->xref() . '@', true);
        $wife->createFact('1 FAMS @' . $family->xref() . '@', true);

        return $family;
    }

    /**
     * The family of the parents.  Use an existing family, if there is one.
     */
    private function family(Tree $tree, Individual $child, Individual|null $father, Individual|null $mother): Family|null
    {
        if ($father === null && $mother === null) {
            return null;
        }

        // Is the child already in this family?
        foreach ($child->childFamilies() as $family) {
            if (($father === null || $family->husband()?->xref() === $father->xref()) && ($mother === null || $family->wife()?->xref() === $mother->xref())) {
                return $family;
            }
        }

        // Do the parents already have a family?
        $parent = $father ?? $mother;
        $other  = $father === null ? null : $mother;

        foreach ($parent->spouseFamilies() as $family) {
            if ($other === null || $family->spouse($parent)?->xref() === $other->xref()) {
                $family->createFact('1 CHIL @' . $child->xref() . '@', true);
                $child->createFact('1 FAMC @' . $family->xref() . '@', true);

                return $family;
            }
        }

        $gedcom = '0 @@ FAM';

        foreach (['HUSB' => $father, 'WIFE' => $mother] as $tag => $spouse) {
            if ($spouse instanceof Individual) {
                $gedcom .= "\n1 " . $tag . ' @' . $spouse->xref() . '@';
            }
        }

        $family = $tree->createFamily($gedcom . "\n1 CHIL @" . $child->xref() . '@');

        foreach ([$father, $mother] as $spouse) {
            $spouse?->createFact('1 FAMS @' . $family->xref() . '@', true);
        }

        $child->createFact('1 FAMC @' . $family->xref() . '@', true);

        return $family;
    }
}
