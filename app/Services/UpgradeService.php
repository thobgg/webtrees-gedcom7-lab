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

namespace Fisharebest\Webtrees\Services;

use Fisharebest\Webtrees\Enums\HttpStatusCode;
use Carbon\CarbonImmutable;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Http\Exceptions\HttpInternalServerErrorException;
use Fisharebest\Webtrees\Html;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Site;
use Fisharebest\Webtrees\Webtrees;
use Illuminate\Support\Collection;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\FilesystemReader;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\ZipArchive\FilesystemZipArchiveProvider;
use League\Flysystem\ZipArchive\ZipArchiveAdapter;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use RuntimeException;
use ZipArchive;

use function explode;
use function fclose;
use function fopen;
use function ftell;
use function fwrite;
use function rewind;
use function strlen;
use function usort;
use function version_compare;

use const PHP_VERSION;

/**
 * Automatic upgrades.
 */
class UpgradeService
{
    // Transfer stream data in blocks of this number of bytes.
    private const int READ_BLOCK_SIZE = 65535;

    public function __construct(
        private readonly ClientInterface $http_client,
        private readonly RequestFactoryInterface $request_factory,
        private readonly TimeoutService $timeout_service,
    ) {
    }

    /**
     * Unpack webtrees.zip.
     */
    public function extractWebtreesZip(string $zip_file, string $target_folder): void
    {
        // The Flysystem ZIP archive adapter is painfully slow, so use the native PHP library.
        $zip = new ZipArchive();

        if ($zip->open($zip_file) === true) {
            $zip->extractTo($target_folder);
            $zip->close();
        } else {
            throw new HttpInternalServerErrorException('Cannot read ZIP file. Is it corrupt?');
        }
    }

    /**
     * Create a list of all the files in a webtrees .ZIP archive
     *
     *
     * @return Collection<int,string>
     * @throws FilesystemException
     */
    public function webtreesZipContents(string $zip_file): Collection
    {
        $zip_provider   = new FilesystemZipArchiveProvider($zip_file, 0755);
        $zip_adapter    = new ZipArchiveAdapter($zip_provider, 'webtrees');
        $zip_filesystem = new Filesystem($zip_adapter);

        $files = $zip_filesystem->listContents('', FilesystemReader::LIST_DEEP)
            ->filter(static fn (StorageAttributes $attributes): bool => $attributes->isFile())
            ->map(static fn (StorageAttributes $attributes): string => $attributes->path());

        return new Collection($files);
    }

    /**
     * Fetch a file from a URL and save it in a filesystem.
     * Use streams so that we can copy files larger than our available memory.
     *
     *
     * @return int The number of bytes downloaded
     * @throws ClientExceptionInterface
     * @throws FilesystemException
     */
    public function downloadFile(string $url, FilesystemOperator $filesystem, string $path): int
    {
        // We store the data in PHP temporary storage.
        $tmp = fopen('php://memory', 'wb+');

        // Read from the URL
        $request  = $this->request_factory->createRequest('GET', $url);
        $response = $this->http_client->sendRequest($request);
        $stream   = $response->getBody();

        // Download the file to temporary storage.
        while (!$stream->eof()) {
            $data = $stream->read(self::READ_BLOCK_SIZE);

            $bytes_written = fwrite($tmp, $data);

            if ($bytes_written !== strlen($data)) {
                throw new RuntimeException('Unable to write to stream.  Perhaps the disk is full?');
            }

            if ($this->timeout_service->isTimeNearlyUp()) {
                $stream->close();
                throw new HttpInternalServerErrorException(I18N::translate('The server’s time limit has been reached.'));
            }
        }

        $stream->close();

        // Copy from temporary storage to the file.
        $bytes = ftell($tmp);
        rewind($tmp);
        $filesystem->writeStream($path, $tmp);
        fclose($tmp);

        return $bytes;
    }

    /**
     * Move (copy and delete) all files from one filesystem to another.
     *
     *
     * @throws FilesystemException
     */
    public function moveFiles(FilesystemOperator $source, FilesystemOperator $destination): void
    {
        $folders = [];

        foreach ($source->listContents('', FilesystemReader::LIST_DEEP) as $attributes) {
            if ($attributes->isFile()) {
                $destination->write($attributes->path(), $source->read($attributes->path()));
                $source->delete($attributes->path());

                if ($this->timeout_service->isTimeNearlyUp()) {
                    throw new HttpInternalServerErrorException(I18N::translate('The server’s time limit has been reached.'));
                }
            } else {
                $folders[] = $attributes->path();
            }
        }

        // Sort folders by longest name, so we have a depth-first list.
        usort($folders, static fn ($first, $second): int => strlen($second) <=> strlen($first));

        // Delete these now empty folders.
        foreach ($folders as $folder) {
            $source->deleteDirectory($folder);
        }
    }

    /**
     * Delete files in $destination that aren't in $source.
     *
     * @param Collection<int,string> $folders_to_clean
     * @param Collection<int,string> $files_to_keep
     */
    public function cleanFiles(FilesystemOperator $filesystem, Collection $folders_to_clean, Collection $files_to_keep): void
    {
        foreach ($folders_to_clean as $folder_to_clean) {
            try {
                foreach ($filesystem->listContents($folder_to_clean, FilesystemReader::LIST_DEEP) as $path) {
                    if ($path['type'] === 'file' && !$files_to_keep->contains($path['path'])) {
                        try {
                            $filesystem->delete($path['path']);
                        } catch (FilesystemException | UnableToDeleteFile) {
                            // Skip to the next file.
                        }
                    }

                    // If we run out of time, then just stop.
                    if ($this->timeout_service->isTimeNearlyUp()) {
                        return;
                    }
                }
            } catch (FilesystemException) {
                // Skip to the next folder.
            }
        }
    }

    public function isUpgradeAvailable(bool $force = false): bool
    {
        // If the latest version is unavailable, we will have an empty string which equates to version 0.

        return version_compare(Webtrees::VERSION, $this->fetchLatestVersion($force)) < 0;
    }

    /**
     * What is the latest version of webtrees.
     */
    public function latestVersion(): string
    {
        $latest_version = $this->fetchLatestVersion(false);

        [$version] = explode('|', $latest_version);

        return $version;
    }

    /**
     * What, if any, error did we have when fetching the latest version of webtrees.
     */
    public function latestVersionError(): string
    {
        return Site::getPreference('LATEST_WT_VERSION_ERROR');
    }

    /**
     * When did we last try to fetch the latest version of webtrees.
     */
    public function latestVersionTimestamp(): CarbonImmutable
    {
        $latest_version_wt_timestamp = (int) Site::getPreference('LATEST_WT_VERSION_TIMESTAMP');

        return Registry::timestampFactory()->fromEpoch($latest_version_wt_timestamp);
    }

    /**
     * Where can we download the latest version of webtrees.
     */
    public function downloadUrl(): string
    {
        $latest_version = $this->fetchLatestVersion(false);

        [, , $url] = explode('|', $latest_version . '||');

        return $url;
    }

    /**
     * webtrees7 has its own releases.  The releases of webtrees are not upgrades for it, so we do not ask webtrees.net.
     */
    private function fetchLatestVersion(bool $force): string
    {
        return '';
    }
}
