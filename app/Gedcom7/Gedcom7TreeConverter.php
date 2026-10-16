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

namespace Fisharebest\Webtrees\Gedcom7;

use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Encodings\UTF8;
use Fisharebest\Webtrees\Enums\AccessLevel;
use Fisharebest\Webtrees\Enums\GedcomVersion;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Log;
use Fisharebest\Webtrees\Services\GedcomExportService;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Tree;
use RuntimeException;

use function count;
use function fclose;
use function preg_replace;
use function preg_split;
use function stream_get_contents;
use function trim;

/**
 * Convert a tree to GEDCOM 7.0: export it as GEDCOM 7.0 and import it again.
 */
final readonly class Gedcom7TreeConverter
{
    public function __construct(
        private GedcomExportService $gedcom_export_service,
        private GedcomImportService $gedcom_import_service,
    ) {
    }

    /**
     * @return array<string,string> The renamed XREFs, old => new
     */
    public function convert(Tree $tree): array
    {
        if ($tree->hasPendingEdit()) {
            throw new RuntimeException('The tree has pending changes.  Accept or reject them first.');
        }

        // A tree that stores GEDCOM 7.0 is built again from its records, unless some are not GEDCOM 7.0.
        $records = Gedcom7Storage::isGedcom7Tree($tree) ? $this->storedGedcom7($tree) : null;
        $renamed = [];

        if ($records === null) {
            $renamed = $this->renamedXrefs($tree);

            $stream = $this->gedcom_export_service->export($tree, false, UTF8::NAME, AccessLevel::Hidden, 'LF', null, null, null, GedcomVersion::V70);
            $gedcom = (string) stream_get_contents($stream);
            fclose($stream);

            $gedcom  = (string) preg_replace('/^\xEF\xBB\xBF/', '', $gedcom);
            $records = preg_split('/\n(?=0 )/', trim($gedcom));
        }

        DB::transaction(function () use ($tree, $records, $renamed): void {
            $this->deleteGenealogyData($tree);

            foreach ($records as $record) {
                $this->gedcom_import_service->importRecord($record, $tree, false);
            }

            $this->renameXrefs($tree, $renamed);
        });

        Log::addEditLog('Converted to GEDCOM 7.0 (' . count($renamed) . ' XREFs renamed)', $tree);

        return $renamed;
    }

    /**
     * Convert all trees, e.g. after an upgrade.  Trees with pending changes are left for the command tree-gedcom7.
     *
     * @param bool $again Also convert trees that already store GEDCOM 7.0, e.g. to update their index tables
     */
    public function convertTrees(bool $again): void
    {
        // Migrations run before the language is chosen.  The import needs one, e.g. for the soundex codes of places.
        I18N::init('en-US');

        $trees = DB::table('gedcom')
            ->where('gedcom_id', '>', 0)
            ->where('imported', '=', 1)
            ->get()
            ->map(Tree::fromDB(...));

        foreach ($trees as $tree) {
            if (!$again && Gedcom7Storage::isGedcom7Tree($tree)) {
                continue;
            }

            if ($tree->hasPendingEdit()) {
                Log::addErrorLog('GEDCOM 7.0: tree ' . $tree->name() . ' has pending changes, and was not converted');
                continue;
            }

            $this->convert($tree);
        }
    }

    /**
     * The GEDCOM 7.0 of a tree that stores it, header first - or null if a record is not GEDCOM 7.0.
     *
     * @return list<string>|null
     */
    private function storedGedcom7(Tree $tree): array|null
    {
        $header  = DB::table('other')->where('o_file', '=', $tree->id())->where('o_id', '=', 'HEAD')->value('o_gedcom');
        $records = DB::table('individuals')->where('i_file', '=', $tree->id())->pluck('i_gedcom')
            ->merge(DB::table('families')->where('f_file', '=', $tree->id())->pluck('f_gedcom'))
            ->merge(DB::table('sources')->where('s_file', '=', $tree->id())->pluck('s_gedcom'))
            ->merge(DB::table('media')->where('m_file', '=', $tree->id())->pluck('m_gedcom'))
            ->merge(DB::table('other')->where('o_file', '=', $tree->id())->whereNotIn('o_id', ['HEAD', 'TRLR'])->pluck('o_gedcom'))
            ->prepend($header)
            ->map(static fn (mixed $record): string => (string) $record);

        if ($header === null || $records->contains(static fn (string $record): bool => !Gedcom7Storage::isGedcom7($record))) {
            return null;
        }

        // The "@@" escapes of GEDCOM 7.0
        return [...$records->map(static fn (string $record): string => (new Gedcom7Converter())->convertRecord($record))->all(), '0 TRLR'];
    }

    /**
     * The XREFs that GEDCOM 7.0 does not permit, and their new names.  The export renames them in the same way.
     *
     * @return array<string,string>
     */
    private function renamedXrefs(Tree $tree): array
    {
        $xrefs = DB::table('individuals')->where('i_file', '=', $tree->id())->pluck('i_id')
            ->merge(DB::table('families')->where('f_file', '=', $tree->id())->pluck('f_id'))
            ->merge(DB::table('sources')->where('s_file', '=', $tree->id())->pluck('s_id'))
            ->merge(DB::table('media')->where('m_file', '=', $tree->id())->pluck('m_id'))
            ->merge(DB::table('other')->where('o_file', '=', $tree->id())->pluck('o_id'))
            ->map(static fn (mixed $xref): string => (string) $xref)
            ->all();

        $converter = new Gedcom7Converter('', $xrefs);
        $renamed   = [];

        foreach ($xrefs as $xref) {
            if ($converter->xref($xref) !== $xref) {
                $renamed[$xref] = $converter->xref($xref);
            }
        }

        return $renamed;
    }

    /**
     * The data that the tree-import command deletes, except places, whose IDs Place::id() caches.
     */
    private function deleteGenealogyData(Tree $tree): void
    {
        DB::table('gedcom')->where('gedcom_id', '=', $tree->id())->update(['imported' => 0]);
        DB::table('individuals')->where('i_file', '=', $tree->id())->delete();
        DB::table('families')->where('f_file', '=', $tree->id())->delete();
        DB::table('sources')->where('s_file', '=', $tree->id())->delete();
        DB::table('other')->where('o_file', '=', $tree->id())->delete();
        DB::table('placelinks')->where('pl_file', '=', $tree->id())->delete();
        DB::table('name')->where('n_file', '=', $tree->id())->delete();
        DB::table('dates')->where('d_file', '=', $tree->id())->delete();
        DB::table('link')->where('l_file', '=', $tree->id())->delete();
        DB::table('media_file')->where('m_file', '=', $tree->id())->delete();
        DB::table('media')->where('m_file', '=', $tree->id())->delete();
    }

    /**
     * Settings, favorites, etc. that refer to a renamed XREF.
     *
     * @param array<string,string> $renamed
     */
    private function renameXrefs(Tree $tree, array $renamed): void
    {
        $block_ids = DB::table('block')->where('gedcom_id', '=', $tree->id())->pluck('block_id');

        foreach ($renamed as $old => $new) {
            DB::table('user_gedcom_setting')
                ->where('gedcom_id', '=', $tree->id())
                ->whereIn('setting_name', [UserInterface::PREF_TREE_ACCOUNT_XREF, UserInterface::PREF_TREE_DEFAULT_XREF])
                ->where('setting_value', '=', $old)
                ->update(['setting_value' => $new]);

            DB::table('gedcom_setting')
                ->where('gedcom_id', '=', $tree->id())
                ->where('setting_name', '=', 'PEDIGREE_ROOT_ID')
                ->where('setting_value', '=', $old)
                ->update(['setting_value' => $new]);

            DB::table('default_resn')->where('gedcom_id', '=', $tree->id())->where('xref', '=', $old)->update(['xref' => $new]);
            DB::table('favorite')->where('gedcom_id', '=', $tree->id())->where('xref', '=', $old)->update(['xref' => $new]);
            DB::table('block')->where('gedcom_id', '=', $tree->id())->where('xref', '=', $old)->update(['xref' => $new]);
            DB::table('change')->where('gedcom_id', '=', $tree->id())->where('xref', '=', $old)->update(['xref' => $new]);
            DB::table('hit_counter')->where('gedcom_id', '=', $tree->id())->where('page_parameter', '=', $old)->update(['page_parameter' => $new]);

            DB::table('block_setting')
                ->whereIn('block_id', $block_ids)
                ->where('setting_name', '=', 'pid')
                ->where('setting_value', '=', $old)
                ->update(['setting_value' => $new]);
        }
    }
}
