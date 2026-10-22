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
use Fisharebest\Webtrees\Enums\Role;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Module\FixPlaceLocations;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\DataFixService;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\GedcomService;
use Fisharebest\Webtrees\Services\LocationLinkService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Tests\TestCase;
use Fisharebest\Webtrees\Tree;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Places and the GEDCOM-L location records (_LOC) that belong to them.
 */
#[CoversClass(LocationLinkService::class)]
#[CoversClass(FixPlaceLocations::class)]
class Gedcom7PlaceLocationsTest extends TestCase
{
    private Tree $tree;

    private GedcomImportService $import_service;

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

        $this->import_service = new GedcomImportService();
        $this->tree           = (new TreeService($this->import_service))->create('test', 'Test');

        $user = (new UserService(new SystemClock()))->create('test', 'test', 'test', '*');
        $this->tree->setUserPreference($user, UserInterface::PREF_TREE_ROLE, Role::Manager->value);
        $user->setPreference(UserInterface::PREF_AUTO_ACCEPT_EDITS, '1');
        Auth::login($user);

        DB::table('other')->where('o_file', '=', $this->tree->id())->delete();
    }

    /**
     * @param list<string> $records
     */
    private function import(array $records): void
    {
        foreach (["0 HEAD\n1 GEDC\n2 VERS 7.0", ...$records, '0 TRLR'] as $record) {
            $this->import_service->importRecord($record, $this->tree, false);
        }
    }

    public function testPlacesAndLocationRecords(): void
    {
        $this->import([
            "0 @L1@ _LOC\n1 NAME Bienenbüttel\n1 EXID BIETE1JO53FD\n2 TYPE https://gov.genealogy.net/\n1 MAP\n2 LATI N53.1417\n2 LONG E10.4867",
            "0 @L2@ _LOC\n1 NAME Hof Nr. 1\n1 _LOC @L1@\n2 TYPE POLI\n1 MAP\n2 LATI N53.1431\n2 LONG E10.4902",
            "0 @L3@ _LOC\n1 NAME Hof Nr. 3\n1 _LOC @L1@\n2 TYPE POLI",
            "0 @L4@ _LOC\n1 NAME Celle",
            "0 @L5@ _LOC\n1 NAME Celle",
            "0 @I1@ INDI\n1 NAME Test /Person/\n1 BIRT\n2 PLAC Hof Nr. 1, Bienenbüttel, Uelzen, Niedersachsen, Deutschland\n1 CHR\n2 PLAC Hof Nr. 3, Bienenbüttel\n3 _LOC @L3@\n1 DEAT\n2 PLAC Bienenbüttel, Uelzen, Niedersachsen, Deutschland\n3 EXID BIETE1JO53FD\n4 TYPE https://gov.genealogy.net/\n1 BURI\n2 PLAC Celle\n1 RESI\n2 PLAC Nirgendwo",
            "0 @I2@ INDI\n1 NAME Other /Person/\n1 BIRT\n2 DATE 1900",
        ]);

        $service = new LocationLinkService(new GedcomService());

        // Names with the names of the parents
        self::assertSame('Hof Nr. 1, Bienenbüttel', $service->fullName($this->tree, 'L2'));
        self::assertSame('Bienenbüttel', $service->fullName($this->tree, 'L1'));

        // Exact name, first parts of the place, GOV identifier, ambiguous name, unknown name
        self::assertSame('L2', $service->locationFor($this->tree, 'Hof Nr. 1, Bienenbüttel'));
        self::assertSame('L2', $service->locationFor($this->tree, 'Hof Nr. 1, Bienenbüttel, Uelzen, Niedersachsen, Deutschland'));
        self::assertSame('L1', $service->locationFor($this->tree, 'Somewhere else', 'BIETE1JO53FD'));
        self::assertNull($service->locationFor($this->tree, 'Celle'));
        self::assertNull($service->locationFor($this->tree, 'Nirgendwo'));
        self::assertNull($service->locationFor($this->tree, ''));

        // Coordinates
        self::assertSame([53.1431, 10.4902], $service->coordinates($this->tree, 'L2'));
        self::assertNull($service->coordinates($this->tree, 'L3'));
        self::assertSame([53.1417, 10.4867], $service->coordinatesForPlace($this->tree, 'Bienenbüttel'));

        // The data fix links the places that match exactly one location record
        $module     = new FixPlaceLocations(new DataFixService(), $service);
        $individual = Registry::individualFactory()->make('I1', $this->tree);
        $other      = Registry::individualFactory()->make('I2', $this->tree);
        self::assertInstanceOf(Individual::class, $individual);
        self::assertInstanceOf(Individual::class, $other);

        self::assertSame(['I1'], $module->recordsToFix($this->tree, [])->map(static fn (object $row): string => $row->xref)->all());
        self::assertTrue($module->doesRecordNeedUpdate($individual, []));
        self::assertFalse($module->doesRecordNeedUpdate($other, []));
        self::assertStringContainsString('<ins>', $module->previewUpdate($individual, []));

        $module->updateRecord($individual, []);
        $gedcom = Registry::individualFactory()->make('I1', $this->tree)?->gedcom() ?? '';

        self::assertStringContainsString("\n2 PLAC Hof Nr. 1, Bienenbüttel, Uelzen, Niedersachsen, Deutschland\n3 _LOC @L2@", $gedcom);
        self::assertStringContainsString("\n4 TYPE https://gov.genealogy.net/\n3 _LOC @L1@", $gedcom);
        self::assertStringContainsString("\n2 PLAC Celle\n1 RESI\n2 PLAC Nirgendwo", $gedcom);
        self::assertSame(1, preg_match_all('/3 _LOC @L3@/', $gedcom));

        // The map tab uses the coordinates of the linked record, or of the record with the same name
        $individual = Registry::individualFactory()->make('I1', $this->tree);
        self::assertInstanceOf(Individual::class, $individual);
        $birth = $individual->facts(['BIRT'])->first();
        $death = $individual->facts(['DEAT'])->first();
        self::assertSame([53.1431, 10.4902], $service->coordinatesOfFact($birth));
        self::assertSame([53.1417, 10.4867], $service->coordinatesOfFact($death));
    }
}
