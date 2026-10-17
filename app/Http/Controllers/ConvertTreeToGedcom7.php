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

use Fisharebest\Webtrees\Enums\GedcomVersion;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Storage;
use Fisharebest\Webtrees\Gedcom7\Gedcom7TreeConverter;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Log;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomExportService;
use Fisharebest\Webtrees\Tree;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

use function date;
use function e;
use function fclose;
use function response;

/**
 * Convert a tree that stores GEDCOM 5.5.1, after a copy of it in the data folder.
 */
final class ConvertTreeToGedcom7
{
    public function __construct(
        private readonly GedcomExportService $gedcom_export_service,
        private readonly Gedcom7TreeConverter $gedcom7_tree_converter,
    ) {
    }

    public function post(Tree $tree): ResponseInterface
    {
        if (Gedcom7Storage::isGedcom7Tree($tree)) {
            return response();
        }

        if ($tree->hasPendingEdit()) {
            FlashMessages::addMessage(I18N::translate('The family tree “%s” has pending changes.  Accept or reject them first.', e($tree->title())), 'danger');

            return response();
        }

        // A copy, in case something goes wrong
        $filename = $tree->name() . '-gedcom551-' . date('Ymd-His') . '.ged';
        $resource = $this->gedcom_export_service->export(tree: $tree, line_endings: 'LF', version: GedcomVersion::V551);
        Registry::filesystem()->data()->writeStream($filename, $resource);
        fclose($resource);

        try {
            $this->gedcom7_tree_converter->convert($tree);
        } catch (RuntimeException $ex) {
            FlashMessages::addMessage(e($ex->getMessage()), 'danger');

            return response();
        }

        Log::addConfigurationLog('Tree converted to GEDCOM 7.0, with a copy in ' . $filename, $tree);

        /* I18N: %1$s is the name of a family tree, %2$s is the name of a file */
        FlashMessages::addMessage(I18N::translate('The family tree “%1$s” stores GEDCOM 7.0.  A copy as GEDCOM 5.5.1 is in the data folder: %2$s', e($tree->title()), e($filename)), 'success');

        return response();
    }
}
