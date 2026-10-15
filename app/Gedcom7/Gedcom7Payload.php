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

use function abs;
use function array_search;
use function count;
use function implode;
use function in_array;
use function is_numeric;
use function parse_url;
use function pathinfo;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function preg_replace_callback;
use function rawurlencode;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function strpos;
use function strtolower;
use function strtoupper;
use function strtr;
use function trim;

use const PATHINFO_EXTENSION;
use const PHP_URL_PATH;
use const PREG_SET_ORDER;

/**
 * Convert the payloads of GEDCOM 5.5.1 structures to GEDCOM 7.0.
 *
 * Where a conversion cannot keep all the information, the methods also return a "phrase",
 * which the caller stores in a PHRASE substructure.  This follows the advice in
 * https://gedcom.io/migrate/
 */
final class Gedcom7Payload
{
    // 5.5.1 calendar escapes, and their 7.0 names
    private const array CALENDARS = [
        'GREGORIAN' => 'GREGORIAN',
        'JULIAN'    => 'JULIAN',
        'HEBREW'    => 'HEBREW',
        'FRENCH R'  => 'FRENCH_R',
        // 5.5.1 permitted these, but did not define them.
        'ROMAN'     => '_ROMAN',
        'UNKNOWN'   => '_UNKNOWN',
        // webtrees also supports these.
        'HIJRI'     => '_HIJRI',
        'JALALI'    => '_JALALI',
    ];

    // The months of the standard calendars
    private const array MONTHS = [
        'GREGORIAN' => ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'],
        'JULIAN'    => ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'],
        'HEBREW'    => ['TSH', 'CSH', 'KSL', 'TVT', 'SHV', 'ADR', 'ADS', 'NSN', 'IYR', 'SVN', 'TMZ', 'AAV', 'ELL'],
        'FRENCH_R'  => ['VEND', 'BRUM', 'FRIM', 'NIVO', 'PLUV', 'VENT', 'GERM', 'FLOR', 'PRAI', 'MESS', 'THER', 'FRUC', 'COMP'],
    ];

    // The 5.5.1 age keywords, see https://gedcom.io/migrate/#age-words
    private const array AGE_WORDS = [
        'CHILD'     => ['< 8y', 'Child'],
        'INFANT'    => ['< 1y', 'Infant'],
        'STILLBORN' => ['0y', 'Stillborn'],
    ];

    // The 5.5.1 language names, and their BCP 47 language tags
    private const array LANGUAGES = [
        'AFRIKAANS'     => 'af',
        'ALBANIAN'      => 'sq',
        'AMHARIC'       => 'am',
        'ANGLO-SAXON'   => 'ang',
        'ARABIC'        => 'ar',
        'ARMENIAN'      => 'hy',
        'ASSAMESE'      => 'as',
        'BELORUSIAN'    => 'be',
        'BENGALI'       => 'bn',
        'BRAJ'          => 'bra',
        'BULGARIAN'     => 'bg',
        'BURMESE'       => 'my',
        'CANTONESE'     => 'yue',
        'CATALAN'       => 'ca',
        'CATALAN_SPN'   => 'ca-ES',
        'CHURCH-SLAVIC' => 'cu',
        'CZECH'         => 'cs',
        'DANISH'        => 'da',
        'DOGRI'         => 'doi',
        'DUTCH'         => 'nl',
        'ENGLISH'       => 'en',
        'ESPERANTO'     => 'eo',
        'ESTONIAN'      => 'et',
        'FAROESE'       => 'fo',
        'FINNISH'       => 'fi',
        'FRENCH'        => 'fr',
        'GEORGIAN'      => 'ka',
        'GERMAN'        => 'de',
        'GREEK'         => 'el',
        'GUJARATI'      => 'gu',
        'HAWAIIAN'      => 'haw',
        'HEBREW'        => 'he',
        'HINDI'         => 'hi',
        'HUNGARIAN'     => 'hu',
        'ICELANDIC'     => 'is',
        'INDONESIAN'    => 'id',
        'ITALIAN'       => 'it',
        'JAPANESE'      => 'ja',
        'KANNADA'       => 'kn',
        'KHMER'         => 'km',
        'KONKANI'       => 'kok',
        'KOREAN'        => 'ko',
        'LAHNDA'        => 'lah',
        'LAO'           => 'lo',
        'LATVIAN'       => 'lv',
        'LITHUANIAN'    => 'lt',
        'MACEDONIAN'    => 'mk',
        'MAITHILI'      => 'mai',
        'MALAYALAM'     => 'ml',
        'MANDRIN'       => 'cmn',
        'MANIPURI'      => 'mni',
        'MARATHI'       => 'mr',
        'MEWARI'        => 'mtr',
        'NAVAHO'        => 'nv',
        'NEPALI'        => 'ne',
        'NORWEGIAN'     => 'no',
        'ORIYA'         => 'or',
        'PAHARI'        => 'him',
        'PALI'          => 'pi',
        'PANJABI'       => 'pa',
        'PERSIAN'       => 'fa',
        'POLISH'        => 'pl',
        'PORTUGUESE'    => 'pt',
        'PRAKRIT'       => 'pra',
        'PUSTO'         => 'ps',
        'RAJASTHANI'    => 'raj',
        'ROMANIAN'      => 'ro',
        'RUSSIAN'       => 'ru',
        'SANSKRIT'      => 'sa',
        'SERB'          => 'sr',
        'SERBO_CROA'    => 'hbs',
        'SLOVAK'        => 'sk',
        'SLOVENE'       => 'sl',
        'SPANISH'       => 'es',
        'SWEDISH'       => 'sv',
        'TAGALOG'       => 'tl',
        'TAMIL'         => 'ta',
        'TELUGU'        => 'te',
        'THAI'          => 'th',
        'TIBETAN'       => 'bo',
        'TURKISH'       => 'tr',
        'UKRAINIAN'     => 'uk',
        'URDU'          => 'ur',
        'VIETNAMESE'    => 'vi',
        'WENDIC'        => 'wen',
        'YIDDISH'       => 'yi',
    ];

    // The 5.5.1 multimedia formats, see https://gedcom.io/migrate/#non-pointer-obje-substructures
    private const array MEDIA_TYPES = [
        'bmp'  => 'image/bmp',
        'gif'  => 'image/gif',
        'jpeg' => 'image/jpeg',
        'jpg'  => 'image/jpeg',
        'ole'  => 'application/ole',
        'pcx'  => 'image/vnd.zbrush.pcx',
        'tif'  => 'image/tiff',
        'tiff' => 'image/tiff',
        'wav'  => 'audio/wav',
    ];

    /**
     * Convert a DateValue, DatePeriod or DateExact.
     *
     * @param string $value
     * @param string $type  Date, DatePeriod or DateExact
     *
     * @return array{0:string,1:string}|null [payload, phrase] or null if the date cannot be converted
     */
    public static function date(string $value, string $type = 'Date'): array|null
    {
        $original = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

        if ($original === '') {
            return ['', ''];
        }

        $text   = $original;
        $phrase = '';

        // Date phrases: "INT 1850 (about Easter)", "(about Easter)"
        if (preg_match('/^(?:INT )?(.*?) ?\((.*)\)$/i', $text, $match) === 1) {
            [, $text, $phrase] = $match;
        }

        $text = strtoupper((string) preg_replace('/^INT /i', '', $text));

        // Calendar escapes: "@#DJULIAN@" becomes "JULIAN"
        $text = (string) preg_replace_callback(
            '/@#D([A-Z ]+?)@ ?/',
            static fn (array $match): string => (self::CALENDARS[$match[1]] ?? '_' . strtr($match[1], [' ' => '_'])) . ' ',
            $text
        );

        // Epochs: "B.C." becomes "BCE"
        $text = (string) preg_replace('/(\d) ?(?:B\.C\.?|BCE?)(?= |$)/', '$1 BCE', $text);

        // Dual years: "1648/9" becomes "1649", see https://gedcom.io/migrate/#dual-dating
        $dual  = false;
        $valid = true;
        $text  = (string) preg_replace_callback('~(\d+)/(\d+)~', static function (array $match) use (&$dual, &$valid): string {
            $dual = true;
            $next = (string) ((int) $match[1] + 1);

            if (strlen($match[2]) <= strlen($next) && str_ends_with($next, $match[2])) {
                return $next;
            }

            $valid = false;

            return $match[0];
        }, $text);

        $parsed = $valid ? self::parseDateValue(trim($text)) : null;

        if ($parsed !== null) {
            [$format, $dates] = $parsed;

            $allowed = match ($type) {
                'DatePeriod' => in_array($format, ['', 'FROM %s', 'TO %s', 'FROM %s TO %s'], true),
                'DateExact'  => $format === '%s' && $dates[0]['day'] !== null && in_array($dates[0]['calendar'], [null, 'GREGORIAN'], true) && !$dates[0]['bce'],
                default      => true,
            };

            if ($allowed) {
                if ($format === 'BET %s AND %s' && self::compareDates($dates[0], $dates[1]) > 0) {
                    $dates = [$dates[1], $dates[0]];
                }

                $converted = self::formatDateValue($format, $dates, $type !== 'DateExact');

                if ($dual) {
                    $phrase = $original;
                }

                if ($type === 'DateExact' && $phrase !== '') {
                    return null;
                }

                return [$converted, $phrase];
            }
        }

        if ($type === 'DateExact') {
            return null;
        }

        return ['', $original];
    }

    /**
     * Convert an age.
     *
     * @return array{0:string,1:string} [payload, phrase]
     */
    public static function age(string $value): array
    {
        $original = trim($value);

        if ($original === '') {
            return ['', ''];
        }

        $upper = strtoupper($original);

        if (isset(self::AGE_WORDS[$upper])) {
            return self::AGE_WORDS[$upper];
        }

        $text = strtolower((string) preg_replace('/\s+/', ' ', $original));

        if (preg_match('/^([<>]?) ?((?:\d+ ?[ymwd] ?)+)$/', $text, $match) === 1) {
            preg_match_all('/(\d+) ?([ymwd])/', $match[2], $parts, PREG_SET_ORDER);

            $order    = 'ymwd';
            $position = -1;
            $duration = [];

            foreach ($parts as [, $number, $unit]) {
                $next = (int) strpos($order, $unit);

                if ($next <= $position) {
                    return ['', $original];
                }

                $position   = $next;
                $duration[] = $number . $unit;
            }

            return [($match[1] === '' ? '' : $match[1] . ' ') . implode(' ', $duration), ''];
        }

        return ['', $original];
    }

    /**
     * Convert a 5.5.1 language name to a BCP 47 language tag.
     */
    public static function language(string $value): string|null
    {
        $value = trim($value);
        $upper = strtoupper($value);

        if (isset(self::LANGUAGES[$upper])) {
            return self::LANGUAGES[$upper];
        }

        // Already a language tag?
        if (preg_match('/^[a-zA-Z]{2,3}(-[a-zA-Z0-9]{1,8})*$/', $value) === 1) {
            return $value;
        }

        return null;
    }

    /**
     * Convert a 5.5.1 multimedia format (e.g. "jpg") to a media type (e.g. "image/jpeg").
     * If there is no format, use the extension of the file.
     */
    public static function mediaType(string $format, string $file = ''): string
    {
        $format = strtolower(trim($format));

        if (str_contains($format, '/')) {
            return $format;
        }

        if ($format === '') {
            $format = strtolower(pathinfo((string) parse_url($file, PHP_URL_PATH), PATHINFO_EXTENSION));
        }

        return self::MEDIA_TYPES[$format] ?? Mime::TYPES[strtoupper($format)] ?? Mime::DEFAULT_TYPE;
    }

    /**
     * Convert a file name or URL to a GEDCOM 7.0 FilePath, which is a URL.
     *
     * Relative paths remain relative, with forward slashes.  Absolute paths become "file:" URLs.
     */
    public static function filePath(string $path): string
    {
        // Windows: "C:\folder\file.jpg"
        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1) {
            return 'file:///' . self::encodePath(strtr($path, ['\\' => '/']), true);
        }

        // Windows network share: "\\server\share\file.jpg"
        if (str_starts_with($path, '\\\\')) {
            return 'file:' . self::encodePath(strtr($path, ['\\' => '/']), true);
        }

        // Already a URL: "https://example.com/file.jpg"
        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $path) === 1) {
            return self::encodePath($path, false);
        }

        // Unix: "/home/user/file.jpg"
        if (str_starts_with($path, '/')) {
            return 'file://' . self::encodePath($path, true);
        }

        return self::encodePath(strtr($path, ['\\' => '/']), true);
    }

    /**
     * Times are "hh:mm" or "hh:mm:ss" or "hh:mm:ss.fs", optionally with "Z" for UTC.
     */
    public static function time(string $value): string|null
    {
        $value = strtoupper(trim($value));

        if (preg_match('/^([01]?\d|2[0-3]):[0-5]\d(:[0-5]\d(\.\d+)?)?Z?$/', $value) === 1) {
            return $value;
        }

        return null;
    }

    /**
     * Latitudes are "N12.345" or "S12.345".  We also accept "12.345" and "-12.345".
     */
    public static function latitude(string $value): string|null
    {
        $value = self::coordinate($value, 'N', 'S');

        return preg_match('/^[NS](90|[0-8]?\d)(\.\d+)?$/', $value) === 1 ? $value : null;
    }

    /**
     * Longitudes are "E12.345" or "W12.345".  We also accept "12.345" and "-12.345".
     */
    public static function longitude(string $value): string|null
    {
        $value = self::coordinate($value, 'E', 'W');

        return preg_match('/^[EW](180|1[0-7]\d|0?\d?\d)(\.\d+)?$/', $value) === 1 ? $value : null;
    }

    private static function coordinate(string $value, string $positive, string $negative): string
    {
        $value = strtoupper(trim(strtr($value, [',' => '.', ' ' => ''])));

        if (is_numeric($value)) {
            $hemisphere = str_starts_with($value, '-') ? $negative : $positive;
            $value      = $hemisphere . (string) abs((float) $value);
        }

        return $value;
    }

    /**
     * Percent-encode the characters that are not permitted in a URL.
     * Non-ASCII characters are permitted.  Local file names may contain "%", "?" and "#".
     */
    private static function encodePath(string $path, bool $local): string
    {
        if ($local) {
            $pattern = '/[^A-Za-z0-9\-._~!$&\'()*+,;=:@\/\x80-\xFF]/';
        } else {
            $pattern = '/%(?![0-9A-Fa-f]{2})|[^A-Za-z0-9\-._~!$&\'()*+,;=:@\/%?#\x80-\xFF]/';
        }

        return (string) preg_replace_callback($pattern, static fn (array $match): string => rawurlencode($match[0]), $path);
    }

    /**
     * @return array{0:string,1:list<array{calendar:string|null,day:int|null,month:string|null,year:int,bce:bool}>}|null
     */
    private static function parseDateValue(string $text): array|null
    {
        if ($text === '') {
            return ['', []];
        }

        $patterns = [
            '/^FROM (.+) TO (.+)$/'          => 'FROM %s TO %s',
            '/^FROM (.+)$/'                  => 'FROM %s',
            '/^TO (.+)$/'                    => 'TO %s',
            '/^BET (.+) AND (.+)$/'          => 'BET %s AND %s',
            '/^(AFT|BEF|ABT|CAL|EST) (.+)$/' => '%s %s',
            '/^(.+)$/'                       => '%s',
        ];

        foreach ($patterns as $pattern => $format) {
            if (preg_match($pattern, $text, $match) === 1) {
                if ($format === '%s %s') {
                    $format = $match[1] . ' %s';
                    $match  = [$match[0], $match[2]];
                }

                $dates = [];

                for ($n = 1; $n < count($match); $n++) {
                    $date = self::parseDate($match[$n]);

                    if ($date === null) {
                        return null;
                    }

                    $dates[] = $date;
                }

                return [$format, $dates];
            }
        }

        return null;
    }

    /**
     * @return array{calendar:string|null,day:int|null,month:string|null,year:int,bce:bool}|null
     */
    private static function parseDate(string $text): array|null
    {
        $pattern = '/^(?:(GREGORIAN|JULIAN|HEBREW|FRENCH_R|_[A-Z0-9_]+) )?(?:(?:(\d+) )?([A-Z][A-Z0-9_]*|_[A-Z0-9_]+) )?(\d+)(?: (BCE))?$/';

        if (preg_match($pattern, $text, $match) !== 1) {
            return null;
        }

        $calendar = $match[1] === '' ? null : $match[1];
        $day      = $match[2] === '' ? null : (int) $match[2];
        $month    = $match[3] === '' ? null : $match[3];
        $bce      = ($match[5] ?? '') !== '';
        $months   = self::MONTHS[$calendar ?? 'GREGORIAN'] ?? null;

        if ($month !== null && $months !== null && !in_array($month, $months, true)) {
            return null;
        }

        if ($day !== null && ($day < 1 || $day > 31)) {
            return null;
        }

        if ($bce && !in_array($calendar, [null, 'GREGORIAN', 'JULIAN'], true)) {
            return null;
        }

        return [
            'calendar' => $calendar,
            'day'      => $day,
            'month'    => $month,
            'year'     => (int) $match[4],
            'bce'      => $bce,
        ];
    }

    /**
     * @param array{calendar:string|null,day:int|null,month:string|null,year:int,bce:bool} $date1
     * @param array{calendar:string|null,day:int|null,month:string|null,year:int,bce:bool} $date2
     */
    private static function compareDates(array $date1, array $date2): int
    {
        $calendar = $date1['calendar'] ?? 'GREGORIAN';

        if ($calendar !== ($date2['calendar'] ?? 'GREGORIAN') || !isset(self::MONTHS[$calendar])) {
            return 0;
        }

        $year1 = $date1['bce'] ? -$date1['year'] : $date1['year'];
        $year2 = $date2['bce'] ? -$date2['year'] : $date2['year'];

        if ($year1 !== $year2) {
            return $year1 <=> $year2;
        }

        if ($date1['month'] === null || $date2['month'] === null) {
            return 0;
        }

        $months = self::MONTHS[$calendar];
        $month1 = (int) array_search($date1['month'], $months, true);
        $month2 = (int) array_search($date2['month'], $months, true);

        if ($month1 !== $month2) {
            return $month1 <=> $month2;
        }

        if ($date1['day'] === null || $date2['day'] === null) {
            return 0;
        }

        return $date1['day'] <=> $date2['day'];
    }

    /**
     * Calendars are only shown if one of the dates is not gregorian.
     *
     * @param list<array{calendar:string|null,day:int|null,month:string|null,year:int,bce:bool}> $dates
     */
    private static function formatDateValue(string $format, array $dates, bool $show_calendars): string
    {
        $gregorian = true;

        foreach ($dates as $date) {
            $gregorian = $gregorian && in_array($date['calendar'], [null, 'GREGORIAN'], true);
        }

        $strings = [];

        foreach ($dates as $date) {
            $parts = [];

            if ($show_calendars && !$gregorian) {
                $parts[] = $date['calendar'] ?? 'GREGORIAN';
            }

            if ($date['day'] !== null) {
                $parts[] = (string) $date['day'];
            }

            if ($date['month'] !== null) {
                $parts[] = $date['month'];
            }

            $parts[] = (string) $date['year'];

            if ($date['bce']) {
                $parts[] = 'BCE';
            }

            $strings[] = implode(' ', $parts);
        }

        return $format === '' ? '' : sprintf($format, ...$strings);
    }
}
