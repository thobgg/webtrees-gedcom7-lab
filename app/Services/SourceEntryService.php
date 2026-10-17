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

use function implode;
use function str_replace;

use const PHP_INT_MAX;

/**
 * An entry in a source, e.g. a baptism in a parish register, recorded once for everyone in it.
 */
class SourceEntryService
{
    // The roles of each type of entry.  The first roles are the principals of the event, e.g. the child of a baptism.
    public const array TEMPLATES = [
        'BAPM' => ['CHIL', 'FATH', 'MOTH', 'GODP', 'GODP', 'CLERGY', 'OTHER', 'OTHER'],
        'MARR' => ['HUSB', 'WIFE', 'FATH', 'MOTH', 'FATH', 'MOTH', 'WITN', 'WITN', 'OFFICIATOR'],
        'BURI' => ['', 'SPOU', 'FATH', 'MOTH', 'CLERGY', 'OTHER', 'OTHER'],
        'CENS' => ['HUSB', 'WIFE', 'CHIL', 'CHIL', 'CHIL', 'CHIL', 'CHIL', 'OTHER', 'OTHER'],
    ];

    // The number of principals: the couple of a marriage, everyone in a census.
    public const array PRINCIPALS = [
        'BAPM' => 1,
        'MARR' => 2,
        'BURI' => 1,
        'CENS' => PHP_INT_MAX,
    ];

    /**
     * The event of an entry, e.g. "INDI:BAPM" or "FAM:MARR".
     */
    public function tag(string $event): string
    {
        return ($event === 'MARR' ? 'FAM:' : 'INDI:') . $event;
    }

    /**
     * A citation of the entry, e.g. "2 SOUR @S1@" + "3 PAGE 12" + "3 EVEN BAPM" + "4 ROLE GODP".
     *
     * @param array{source:string,page:string,date:string,text:string,media:string,quality:string} $entry
     */
    public function citation(int $level, array $entry, string $event, string $role, string $role_phrase): string
    {
        $lines = [$level . ' SOUR ' . $entry['source']];

        if ($entry['page'] !== '') {
            $lines[] = ($level + 1) . ' PAGE ' . $entry['page'];
        }

        $lines[] = ($level + 1) . ' EVEN ' . $event;

        if ($role !== '') {
            $lines[] = ($level + 2) . ' ROLE ' . $role;

            if ($role_phrase !== '') {
                $lines[] = ($level + 3) . ' PHRASE ' . $role_phrase;
            }
        }

        if ($entry['date'] !== '' || $entry['text'] !== '') {
            $lines[] = ($level + 1) . ' DATA';

            if ($entry['date'] !== '') {
                $lines[] = ($level + 2) . ' DATE ' . $entry['date'];
            }

            if ($entry['text'] !== '') {
                $lines[] = ($level + 2) . ' TEXT ' . str_replace("\n", "\n" . ($level + 3) . ' CONT ', $entry['text']);
            }
        }

        if ($entry['media'] !== '') {
            $lines[] = ($level + 1) . ' OBJE ' . $entry['media'];
        }

        if ($entry['quality'] !== '') {
            $lines[] = ($level + 1) . ' QUAY ' . $entry['quality'];
        }

        return implode("\n", $lines);
    }

    /**
     * A shorter citation of the same entry, without the transcript and the media, e.g. for the other participants.
     *
     * @param array{source:string,page:string,date:string,text:string,media:string,quality:string} $entry
     *
     * @return array{source:string,page:string,date:string,text:string,media:string,quality:string}
     */
    public function reference(array $entry): array
    {
        return ['date' => '', 'text' => '', 'media' => ''] + $entry;
    }

    /**
     * The event, e.g. "1 BAPM", with its citation and the other participants as associates.
     *
     * @param array{source:string,page:string,date:string,text:string,media:string,quality:string} $entry
     * @param array{role:string,role_phrase:string}                                                 $individual
     * @param list<array{xref:string,phrase:string,role:string,role_phrase:string}>                 $associates
     */
    public function event(string $event, string $date, string $place, array $entry, array $individual, array $associates): string
    {
        $lines = ['1 ' . $event];

        if ($date !== '') {
            $lines[] = '2 DATE ' . $date;
        }

        if ($place !== '') {
            $lines[] = '2 PLAC ' . $place;
        }

        foreach ($associates as $associate) {
            $lines[] = '2 ASSO ' . $associate['xref'];

            if ($associate['phrase'] !== '') {
                $lines[] = '3 PHRASE ' . $associate['phrase'];
            }

            $lines[] = '3 ROLE ' . $associate['role'];

            if ($associate['role_phrase'] !== '') {
                $lines[] = '4 PHRASE ' . $associate['role_phrase'];
            }

            $lines[] = $this->citation(3, $this->reference($entry), $event, $associate['role'], $associate['role_phrase']);
        }

        $lines[] = $this->citation(2, $entry, $event, $individual['role'], $individual['role_phrase']);

        return implode("\n", $lines);
    }
}
