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

use Fisharebest\Webtrees\Mime;

use function array_keys;
use function array_map;
use function pathinfo;
use function preg_match;
use function rawurldecode;
use function str_starts_with;
use function strtolower;

use const PATHINFO_EXTENSION;

/**
 * Media files have a URL, a media type (FORM) and a medium (MEDI) in GEDCOM 7.0.
 * In GEDCOM 5.5.1, they have a file name, a file extension (FORM) and a media type (TYPE).
 */
final class Gedcom7Media
{
    private const string SCHEME = '/^[A-Za-z][A-Za-z0-9+.-]*:/';

    /**
     * A relative URL, e.g. "my%20photo.jpg", is the file "my photo.jpg" in the media folder.
     */
    public static function fileName(string $url): string
    {
        if (preg_match(self::SCHEME, $url) === 1 || str_starts_with($url, '/')) {
            return $url;
        }

        $file = rawurldecode($url);

        // e.g. "https%3A//example.com" is a relative path, not a URL.
        return preg_match(self::SCHEME, $file) === 1 ? $url : $file;
    }

    /**
     * The webtrees media type of a medium, e.g. "OTHER" + "Certificate" is "CERTIFICATE".
     */
    public static function type(string $medium, string|null $phrase): string
    {
        if ($medium !== 'OTHER' || $phrase === null) {
            return $medium;
        }

        return self::code($phrase) ?? $phrase;
    }

    /**
     * A media file in GEDCOM 5.5.1: "my%20photo.jpg" + FORM "image/jpeg" + MEDI "PHOTO"
     * becomes "my photo.jpg" + FORM "jpg" + TYPE "PHOTO".
     */
    public static function downgrade(Gedcom7Node $file): void
    {
        $file->value = self::fileName($file->value);

        foreach ($file->children as $form) {
            if ($form->tag !== 'FORM') {
                continue;
            }

            $extension = strtolower(pathinfo($file->value, PATHINFO_EXTENSION));
            $formats   = $form->removeChildren('_FORM');

            if ($formats !== [] && $form->value === Mime::DEFAULT_TYPE) {
                // A format that Gedcom7Converter did not recognise.
                $form->value = $formats[0]->value;
            } elseif ($extension !== '' && Gedcom7Payload::mediaType($extension) === $form->value) {
                $form->value = $extension;
            } else {
                $extensions = [...array_keys(Gedcom7Payload::MEDIA_TYPES), ...array_map(strtolower(...), array_keys(Mime::TYPES))];

                foreach ($extensions as $candidate) {
                    if (Gedcom7Payload::mediaType($candidate) === $form->value) {
                        $form->value = $candidate;
                        break;
                    }
                }

                $form->children = [...$form->children, ...$formats];
            }

            foreach ($form->children as $medium) {
                if ($medium->tag === 'MEDI') {
                    $medium->tag = 'TYPE';
                    self::medium($medium);
                }
            }
        }
    }

    /**
     * A medium "OTHER" + PHRASE "Certificate" becomes "CERTIFICATE", e.g. for GEDCOM 5.5.1.
     */
    public static function medium(Gedcom7Node $medium): void
    {
        $phrase = $medium->children[0] ?? null;

        if ($medium->value === 'OTHER' && $phrase instanceof Gedcom7Node && $phrase->tag === 'PHRASE') {
            $code = self::code($phrase->value);

            if ($code !== null) {
                Gedcom7Record::takePhrase($medium);
                $medium->value = $code;
            }
        }
    }

    private static function code(string $phrase): string|null
    {
        return Gedcom7Record::code($phrase, Gedcom7Schema::ENUMERATIONS['MEDI'], Gedcom7Record::knownValues('MEDI'));
    }
}
