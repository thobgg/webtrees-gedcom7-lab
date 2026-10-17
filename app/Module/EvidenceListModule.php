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

namespace Fisharebest\Webtrees\Module;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Elements\ChildLinkageStatus;
use Fisharebest\Webtrees\Enums\AccessLevel;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function in_array;

/**
 * What is uncertain: challenged links to parents, possibly identical individuals, events that did not occur.
 */
class EvidenceListModule extends AbstractModule implements ModuleListInterface
{
    use ModuleListTrait;

    protected const string ROUTE_URL = '/tree/{tree}/evidence-list';

    protected AccessLevel $access_level = AccessLevel::Member;

    public function boot(): void
    {
        Registry::routeFactory()->routeMap()->add(static::ROUTE_URL, static::class);
    }

    public function title(): string
    {
        /* I18N: Name of a module/list */
        return I18N::translate('Evidence');
    }

    public function description(): string
    {
        /* I18N: Description of the “Evidence” module */
        return I18N::translate('Links to parents that are challenged or disproven, individuals that are possibly the same, and events that did not occur.');
    }

    public function listMenuClass(): string
    {
        return 'menu-list-evidence';
    }

    /**
     * @return array<string>
     */
    public function listUrlAttributes(): array
    {
        return [];
    }

    public function listIsEmpty(Tree $tree): bool
    {
        return $this->records($tree, 'individuals')->isEmpty() && $this->records($tree, 'families')->isEmpty();
    }

    /**
     * @param array<bool|int|string|array<string>|null> $parameters
     */
    public function listUrl(Tree $tree, array $parameters = []): string
    {
        $parameters['tree'] = $tree->name();

        return route(static::class, $parameters);
    }

    public function get(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();
        $user = Validator::attributes($request)->user();

        Auth::checkComponentAccess($this, ModuleListInterface::class, $tree, $user);

        $individuals = $this->records($tree, 'individuals')->map(Registry::individualFactory()->mapper($tree))->filter(GedcomRecord::accessFilter());
        $families    = $this->records($tree, 'families')->map(Registry::familyFactory()->mapper($tree))->filter(GedcomRecord::accessFilter());
        $doubtful    = [ChildLinkageStatus::VALUE_CHALLENGED, ChildLinkageStatus::VALUE_DISPROVEN];

        $links = $individuals
            ->map(static fn (GedcomRecord $record): Collection => $record->facts(['FAMC'])->filter(static fn (Fact $fact): bool => in_array($fact->attribute('STAT'), $doubtful, true)))
            ->flatten();

        $aliases = $individuals->map(static fn (GedcomRecord $record): Collection => $record->facts(['ALIA']))->flatten();

        $non_events = $individuals->concat($families)->map(static fn (GedcomRecord $record): Collection => $record->facts(['NO']))->flatten();

        return $this->viewResponse('modules/evidence-list/page', [
            'aliases'    => $aliases,
            'links'      => $links,
            'non_events' => $non_events,
            'title'      => $this->title(),
            'tree'       => $tree,
        ]);
    }

    /**
     * The records that may have something uncertain.
     *
     * @return Collection<int,object>
     */
    private function records(Tree $tree, string $table): Collection
    {
        [$file, $gedcom] = $table === 'individuals' ? ['i_file', 'i_gedcom'] : ['f_file', 'f_gedcom'];

        return DB::table($table)
            ->where($file, '=', $tree->id())
            ->where(static function ($query) use ($gedcom): void {
                $query
                    ->where($gedcom, 'LIKE', "%\n1 NO %")
                    ->orWhere($gedcom, 'LIKE', "%\n1 ALIA %")
                    ->orWhere($gedcom, 'LIKE', "%\n2 STAT CHALLENGED%")
                    ->orWhere($gedcom, 'LIKE', "%\n2 STAT DISPROVEN%");
            })
            ->get();
    }
}
