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

namespace Fisharebest\Webtrees\Tests\Feature;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Clock\SystemClock;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Enums\HttpStatusCode;
use Fisharebest\Webtrees\Enums\Role;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Http\Controllers\AddSourceEntry;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\SourceEntryService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Source;
use Fisharebest\Webtrees\Tests\TestCase;
use Fisharebest\Webtrees\Tree;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;

use function array_column;
use function array_map;
use function preg_replace;

#[CoversClass(AddSourceEntry::class)]
#[CoversClass(SourceEntryService::class)]
class AddSourceEntryTest extends TestCase
{
    private Tree $tree;

    protected function tearDown(): void
    {
        Auth::logout();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::createDatabase();
        (new Gedcom())->registerTags(Registry::elementFactory(), true);

        $gedcom_import_service = new GedcomImportService();
        $this->tree            = (new TreeService($gedcom_import_service))->create('test', 'Test');

        $user = (new UserService(new SystemClock()))->create('test', 'test', 'test', '*');
        $user->setPreference(UserInterface::PREF_AUTO_ACCEPT_EDITS, '1');
        $this->tree->setUserPreference($user, UserInterface::PREF_TREE_ROLE, Role::Manager->value);
        Auth::login($user);

        DB::table('individuals')->where('i_file', '=', $this->tree->id())->delete();
        DB::table('sources')->where('s_file', '=', $this->tree->id())->delete();
        DB::table('other')->where('o_file', '=', $this->tree->id())->delete();
        DB::table('name')->where('n_file', '=', $this->tree->id())->delete();

        $records = [
            "0 HEAD\n1 GEDC\n2 VERS 7.0",
            "0 @I1@ INDI\n1 NAME John /Smith/\n1 SEX M",
            "0 @I2@ INDI\n1 NAME Mary /Brown/\n1 SEX F",
            "0 @S1@ SOUR\n1 TITL Parish register",
            '0 TRLR',
        ];

        foreach ($records as $record) {
            $gedcom_import_service->importRecord($record, $this->tree, false);
        }
    }

    /**
     * @param array<string,string|list<string>> $params
     */
    private function post(string $event, array $params): int
    {
        $request = self::createRequest('POST', [], $params + [
            'source'      => '@S1@',
            'page'        => 'p. 12',
            'date'        => '1 JAN 1850',
            'place'       => '',
        ], [], ['tree' => $this->tree, 'fact' => $event]);

        return Registry::container()->get(AddSourceEntry::class)->post($request, $this->tree)->getStatusCode();
    }

    private function individual(string $xref): Individual
    {
        $individual = Registry::individualFactory()->make($xref, $this->tree);
        self::assertInstanceOf(Individual::class, $individual);

        return $individual;
    }

    /**
     * The record without the last change and the creation.
     */
    private static function data(string $gedcom): string
    {
        return preg_replace('/\n1 (CHAN|CREA)(\n[2-9].*)*/', '', $gedcom);
    }

    public function testMarriage(): void
    {
        $status = $this->post('MARR', [
            'role'        => ['HUSB', 'WIFE', 'WITN'],
            'role_phrase' => ['', '', ''],
            'person'      => ['@I1@', '@I2@', '@VOID@'],
            'name'        => ['', '', 'Peter Miller'],
            'sex'         => ['', '', ''],
        ]);

        self::assertSame(HttpStatusCode::Found->value, $status);

        $family = $this->individual('I1')->spouseFamilies()->first();
        self::assertInstanceOf(Family::class, $family);
        self::assertSame('I2', $family->wife()?->xref());
        self::assertSame(
            '0 @' . $family->xref() . "@ FAM\n1 HUSB @I1@\n1 WIFE @I2@\n1 MARR\n2 DATE 1 JAN 1850\n2 ASSO @VOID@\n3 PHRASE Peter Miller\n3 ROLE WITN\n3 SOUR @S1@\n4 PAGE p. 12\n4 EVEN MARR\n5 ROLE WITN\n2 SOUR @S1@\n3 PAGE p. 12\n3 EVEN MARR",
            self::data($family->gedcom())
        );
    }

    public function testBurialWithoutARoleForTheDeceased(): void
    {
        $this->post('BURI', [
            'role'        => ['', 'CLERGY'],
            'role_phrase' => ['', ''],
            'person'      => ['@I1@', '@VOID@'],
            'name'        => ['', 'Pastor Schmidt'],
            'sex'         => ['', ''],
        ]);

        self::assertStringEndsWith(
            "\n1 BURI\n2 DATE 1 JAN 1850\n2 ASSO @VOID@\n3 PHRASE Pastor Schmidt\n3 ROLE CLERGY\n3 SOUR @S1@\n4 PAGE p. 12\n4 EVEN BURI\n5 ROLE CLERGY\n2 SOUR @S1@\n3 PAGE p. 12\n3 EVEN BURI",
            self::data($this->individual('I1')->gedcom())
        );
    }

    public function testCensusForEveryone(): void
    {
        $this->post('CENS', [
            'role'        => ['HUSB', 'WIFE', 'CHIL'],
            'role_phrase' => ['', '', ''],
            'person'      => ['@I1@', '@I2@', ''],
            'name'        => ['', '', 'Anna /Smith/'],
            'sex'         => ['', '', 'F'],
        ]);

        self::assertStringEndsWith("\n1 CENS\n2 DATE 1 JAN 1850\n2 SOUR @S1@\n3 PAGE p. 12\n3 EVEN CENS\n4 ROLE HUSB", self::data($this->individual('I1')->gedcom()));
        self::assertStringEndsWith("\n1 CENS\n2 DATE 1 JAN 1850\n2 SOUR @S1@\n3 PAGE p. 12\n3 EVEN CENS\n4 ROLE WIFE", self::data($this->individual('I2')->gedcom()));
        $xref = (string) DB::table('individuals')->where('i_file', '=', $this->tree->id())->whereNotIn('i_id', ['I1', 'I2'])->value('i_id');

        self::assertSame(
            '0 @' . $xref . "@ INDI\n1 NAME Anna /Smith/\n1 SEX F\n1 CENS\n2 DATE 1 JAN 1850\n2 SOUR @S1@\n3 PAGE p. 12\n3 EVEN CENS\n4 ROLE CHIL",
            self::data($this->individual($xref)->gedcom())
        );
    }

    public function testEntriesOfTheSource(): void
    {
        $this->post('CENS', [
            'role'        => ['HUSB', 'WIFE'],
            'role_phrase' => ['', ''],
            'person'      => ['@I1@', '@I2@'],
            'name'        => ['', ''],
            'sex'         => ['', ''],
        ]);
        $this->post('BURI', [
            'role'        => ['', 'CLERGY'],
            'role_phrase' => ['', 'Pastor'],
            'person'      => ['@I1@', '@VOID@'],
            'name'        => ['', 'Peter Miller'],
            'sex'         => ['', ''],
        ]);

        $source  = Registry::sourceFactory()->make('S1', $this->tree);
        self::assertInstanceOf(Source::class, $source);

        $entries = (new SourceEntryService())->entries($source, new Collection([$this->individual('I1'), $this->individual('I2')]));

        self::assertCount(2, $entries);
        self::assertSame(['p. 12', 'p. 12'], array_column($entries, 'page'));
        self::assertEqualsCanonicalizing(['CENS', 'BURI'], array_column($entries, 'event'));
        self::assertEqualsCanonicalizing(['INDI:CENS', 'INDI:BURI'], array_column($entries, 'tag'));

        foreach ($entries as $entry) {
            $participants = array_map(static fn (array $p): string => ($p['record']?->xref() ?? $p['name']) . '/' . $p['role'] . '/' . $p['role_phrase'], $entry['participants']);

            if ($entry['event'] === 'CENS') {
                self::assertSame(['I1/HUSB/', 'I2/WIFE/'], $participants);
            } else {
                self::assertSame(['I1//', 'Peter Miller/CLERGY/Pastor'], $participants);
            }
        }
    }

    public function testEntriesOfCitationsWithoutAnEvent(): void
    {
        $this->individual('I2')->createFact("1 BIRT\n2 DATE 1 JAN 1900\n2 SOUR @S1@\n3 PAGE Birth certificate", true);

        $source = Registry::sourceFactory()->make('S1', $this->tree);
        self::assertInstanceOf(Source::class, $source);

        $entries = (new SourceEntryService())->entries($source, new Collection([$this->individual('I2')]));

        self::assertSame(['Birth certificate'], array_column($entries, 'page'));
        self::assertSame(['BIRT'], array_column($entries, 'event'));
        self::assertSame(['INDI:BIRT'], array_column($entries, 'tag'));
    }
}
