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

use DomainException;
use Fisharebest\Webtrees\Exceptions\FileUploadException;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\Http\ViewResponseTrait;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\AdminService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

use function basename;
use function e;
use function file_put_contents;
use function redirect;
use function route;
use function str_ends_with;
use function strtolower;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_OK;

final class ImportGedcom
{
    use ViewResponseTrait;

    public function __construct(
        private AdminService $admin_service,
        private StreamFactoryInterface $stream_factory,
        private TreeService $tree_service,
    ) {
    }

    public function get(Tree $tree): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        $data_filesystem = Registry::filesystem()->data();
        $data_folder     = Registry::filesystem()->dataName();

        $default_gedcom_file = $tree->gedcomFilename();
        $gedcom_media_path   = $tree->getPreference('GEDCOM_MEDIA_PATH');
        $gedcom_files        = $this->admin_service->gedcomFiles($data_filesystem);

        $title = I18N::translate('Import a GEDCOM file') . ' — ' . e($tree->title());

        return $this->viewResponse('admin/trees-import', [
            'data_folder'         => $data_folder,
            'default_gedcom_file' => $default_gedcom_file,
            'gedcom_files'        => $gedcom_files,
            'gedcom_media_path'   => $gedcom_media_path,
            'title'               => $title,
            'tree'                => $tree,
        ]);
    }

    public function post(ServerRequestInterface $request, Tree $tree): ResponseInterface
    {
        $keep_media         = Validator::parsedBody($request)->boolean('keep_media', false);
        $word_wrapped_notes = Validator::parsedBody($request)->boolean('WORD_WRAPPED_NOTES', false);
        $gedcom_media_path  = Validator::parsedBody($request)->string('GEDCOM_MEDIA_PATH');
        $encodings          = ['' => ''] + Registry::encodingFactory()->list();
        $encoding           = Validator::parsedBody($request)->isInArrayKeys($encodings)->string('encoding');
        $source             = Validator::parsedBody($request)->isInArray(['client', 'server'])->string('source');

        // Save these choices as defaults
        $tree->setPreference('keep_media', $keep_media ? '1' : '0');
        $tree->setPreference('WORD_WRAPPED_NOTES', $word_wrapped_notes ? '1' : '0');
        $tree->setPreference('GEDCOM_MEDIA_PATH', $gedcom_media_path);

        if ($source === 'client') {
            $client_file = $request->getUploadedFiles()['client_file'] ?? null;

            if ($client_file === null || $client_file->getError() === UPLOAD_ERR_NO_FILE) {
                FlashMessages::addMessage(I18N::translate('No GEDCOM file was received.'), 'danger');

                return redirect(route(self::class, ['tree' => $tree->name()]));
            }

            if ($client_file->getError() !== UPLOAD_ERR_OK) {
                throw new FileUploadException($client_file);
            }

            $client_name = basename((string) $client_file->getClientFilename());

            // A GEDZIP file has a GEDCOM 7.0 file and media files
            if (str_ends_with(strtolower($client_name), '.gdz')) {
                $path = tempnam(sys_get_temp_dir(), 'gdz');
                $client_file->moveTo($path);

                return $this->importGedzip($tree, $path, $client_name);
            }

            $this->tree_service->importGedcomFile($tree, $client_file->getStream(), $client_name, $encoding);
        }

        if ($source === 'server') {
            $server_file = Validator::parsedBody($request)->string('server_file');

            if ($server_file === '') {
                FlashMessages::addMessage(I18N::translate('No GEDCOM file was received.'), 'danger');

                return redirect(route(self::class, ['tree' => $tree->name()]));
            }

            $resource = Registry::filesystem()->data()->readStream($server_file);

            if (str_ends_with(strtolower($server_file), '.gdz')) {
                $path = tempnam(sys_get_temp_dir(), 'gdz');
                file_put_contents($path, $resource);

                return $this->importGedzip($tree, $path, $server_file);
            }

            $stream = $this->stream_factory->createStreamFromResource($resource);
            $this->tree_service->importGedcomFile($tree, $stream, $server_file, $encoding);
        }

        return redirect(route(ManageTrees::class, ['tree' => $tree->name()]));
    }

    private function importGedzip(Tree $tree, string $path, string $filename): ResponseInterface
    {
        try {
            $added = $this->tree_service->importGedzipFile($tree, $path, $filename);
        } catch (DomainException $ex) {
            FlashMessages::addMessage(e($ex->getMessage()), 'danger');

            return redirect(route(self::class, ['tree' => $tree->name()]));
        } finally {
            unlink($path);
        }

        FlashMessages::addMessage(I18N::plural('%s media file was added.', '%s media files were added.', $added, I18N::number($added)), 'info');

        return redirect(route(ManageTrees::class, ['tree' => $tree->name()]));
    }
}
