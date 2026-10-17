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

namespace Fisharebest\Webtrees\Tests\Unit\Services;

use Fisharebest\Webtrees\Services\SourceEntryService;
use Fisharebest\Webtrees\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SourceEntryService::class)]
class SourceEntryServiceTest extends TestCase
{
    private const array ENTRY = ['source' => '@S1@', 'page' => 'p. 12', 'date' => '3 MAR 1850', 'text' => "Line one\nLine two", 'media' => '', 'quality' => '3'];

    public function testCitation(): void
    {
        self::assertSame(
            "2 SOUR @S1@\n3 PAGE p. 12\n3 EVEN BAPM\n4 ROLE GODP\n5 PHRASE Godmother\n3 DATA\n4 DATE 3 MAR 1850\n4 TEXT Line one\n5 CONT Line two\n3 QUAY 3",
            (new SourceEntryService())->citation(2, self::ENTRY, 'BAPM', 'GODP', 'Godmother')
        );

        self::assertSame("1 SOUR @S1@\n2 EVEN BAPM", (new SourceEntryService())->citation(1, ['source' => '@S1@', 'page' => '', 'date' => '', 'text' => '', 'media' => '', 'quality' => ''], 'BAPM', '', ''));
    }

    public function testEvent(): void
    {
        $entry = ['source' => '@S1@', 'page' => '12', 'date' => '', 'text' => 'Johannes', 'media' => '@M1@', 'quality' => '3'];

        // Only the individual of the event has the transcript and the media.
        self::assertSame(
            "1 BAPM\n2 DATE 3 MAR 1850\n2 PLAC Kirchheim\n2 ASSO @I2@\n3 ROLE FATH\n3 SOUR @S1@\n4 PAGE 12\n4 EVEN BAPM\n5 ROLE FATH\n4 QUAY 3\n2 ASSO @VOID@\n3 PHRASE Martin Pate\n3 ROLE GODP\n3 SOUR @S1@\n4 PAGE 12\n4 EVEN BAPM\n5 ROLE GODP\n4 QUAY 3\n2 SOUR @S1@\n3 PAGE 12\n3 EVEN BAPM\n4 ROLE CHIL\n3 DATA\n4 TEXT Johannes\n3 OBJE @M1@\n3 QUAY 3",
            (new SourceEntryService())->event('BAPM', '3 MAR 1850', 'Kirchheim', $entry, ['role' => 'CHIL', 'role_phrase' => ''], [
                ['xref' => '@I2@', 'phrase' => '', 'role' => 'FATH', 'role_phrase' => ''],
                ['xref' => '@VOID@', 'phrase' => 'Martin Pate', 'role' => 'GODP', 'role_phrase' => ''],
            ])
        );
    }
}
