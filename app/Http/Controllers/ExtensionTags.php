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

use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Extensions;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Schema;
use Fisharebest\Webtrees\Http\ViewResponseTrait;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function e;
use function implode;
use function preg_match;
use function redirect;
use function route;
use function trim;

/**
 * The extension tags of a tree, e.g. "_LOC", and their documentation.
 */
final class ExtensionTags
{
    use ViewResponseTrait;

    public function get(Tree $tree): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        return $this->viewResponse('admin/trees-extensions', [
            'tags'  => Gedcom7Extensions::tags($tree),
            'title' => I18N::translate('Extension tags') . ' — ' . e($tree->title()),
            'tree'  => $tree,
        ]);
    }

    /**
     * The URIs of the extension tags, in HEAD:SCHMA.
     */
    public function post(ServerRequestInterface $request, Tree $tree): ResponseInterface
    {
        $lines = [];

        foreach (Validator::parsedBody($request)->array('uri') as $tag => $uri) {
            $tag = (string) $tag;
            $uri = trim($uri);

            if (preg_match('/^_[A-Z0-9_]+$/', $tag) !== 1 || $uri === '') {
                continue;
            }

            // A URI has a scheme, e.g. https:
            if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:\S+$/', $uri) !== 1) {
                FlashMessages::addMessage(I18N::translate('The URI of %s is not valid.', '<code>' . e($tag) . '</code>'), 'danger');

                return redirect(route(self::class, ['tree' => $tree->name()]));
            }

            $lines[] = '2 TAG ' . $tag . ' ' . $uri;
        }

        $gedcom = $lines === [] ? '' : "1 SCHMA\n" . implode("\n", $lines);
        $header = Registry::headerFactory()->make('HEAD', $tree);

        if ($header === null) {
            // A tree without a header, e.g. created before the import of a file
            if ($gedcom !== '') {
                DB::table('other')->insert([
                    'o_id'     => 'HEAD',
                    'o_file'   => $tree->id(),
                    'o_type'   => 'HEAD',
                    'o_gedcom' => "0 HEAD\n1 GEDC\n2 VERS " . Gedcom7Schema::VERSION . "\n" . $gedcom,
                ]);
            }
        } else {
            $schema = $header->facts(['SCHMA'], false, null, true)->first();

            if ($schema instanceof Fact && $gedcom === '') {
                $header->deleteFact($schema->id(), false);
            } elseif ($schema instanceof Fact) {
                $header->updateFact($schema->id(), $gedcom, false);
            } elseif ($gedcom !== '') {
                $header->createFact($gedcom, false);
            }
        }

        FlashMessages::addMessage(I18N::translate('The preferences for the family tree “%s” have been updated.', e($tree->title())), 'success');

        return redirect(route(self::class, ['tree' => $tree->name()]));
    }
}
