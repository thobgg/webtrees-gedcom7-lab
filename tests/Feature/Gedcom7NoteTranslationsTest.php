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

use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Translations;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\SharedNote;
use Fisharebest\Webtrees\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function view;

#[CoversClass(Gedcom7Translations::class)]
class Gedcom7NoteTranslationsTest extends TestCase
{
    private const string NOTE = "0 @N1@ SNOTE Ausgewandert nach Amerika\n1 LANG de\n1 TRAN Emigrated\n2 CONT to America\n2 LANG en";

    public function testText(): void
    {
        I18N::init('en-GB');
        self::assertSame("Emigrated\nto America", Gedcom7Translations::text(self::NOTE, 0));

        I18N::init('de');
        self::assertNull(Gedcom7Translations::text(self::NOTE, 0));
        self::assertTrue(Gedcom7Translations::isViewerLanguage('de-AT'));
    }

    public function testSharedNotes(): void
    {
        self::createDatabase();
        (new Gedcom())->registerTags(Registry::elementFactory(), true);

        $import = new GedcomImportService();
        $tree   = (new TreeService($import))->create('test', 'Test');

        DB::table('individuals')->where('i_file', '=', $tree->id())->delete();
        DB::table('other')->where('o_file', '=', $tree->id())->delete();

        foreach (["0 HEAD\n1 GEDC\n2 VERS 7.0", self::NOTE, '0 TRLR'] as $record) {
            $import->importRecord($record, $tree, false);
        }

        $note = Registry::sharedNoteFactory()->make('N1', $tree);
        self::assertInstanceOf(SharedNote::class, $note);

        I18N::init('en-US');
        self::assertSame("Emigrated\nto America", $note->getTranslatedNote());

        I18N::init('de');
        self::assertSame('Ausgewandert nach Amerika', $note->getTranslatedNote());
    }

    public function testNotesOfFacts(): void
    {
        self::createDatabase();
        (new Gedcom())->registerTags(Registry::elementFactory(), true);

        $tree   = (new TreeService(new GedcomImportService()))->create('test', 'Test');
        $gedcom = "2 NOTE Ausgewandert\n3 LANG de\n3 TRAN Emigrated\n4 LANG en";

        I18N::init('en-US');
        $html = view('fact-gedcom-fields', ['gedcom' => $gedcom, 'parent' => 'INDI:EMIG', 'tree' => $tree]);

        // The translation into the language of the viewer comes first, and the original is a translation in its language
        self::assertMatchesRegularExpression('/Emigrated.*Ausgewandert.*Deutsch/s', $html);
    }

    public function testLanguageOfTheFile(): void
    {
        self::createDatabase();
        (new Gedcom())->registerTags(Registry::elementFactory(), true);

        $import = new GedcomImportService();
        $tree   = (new TreeService($import))->create('test', 'Test');

        DB::table('other')->where('o_file', '=', $tree->id())->delete();
        $import->importRecord("0 HEAD\n1 GEDC\n2 VERS 7.0\n1 LANG de", $tree, false);

        self::assertSame('de', Gedcom7Translations::fileLanguage($tree));

        // A note without a language has the language of the file
        I18N::init('en-US');
        $html = view('fact-gedcom-fields', ['gedcom' => "2 NOTE Ausgewandert\n3 TRAN Emigrated\n4 LANG en", 'parent' => 'INDI:EMIG', 'tree' => $tree]);

        self::assertMatchesRegularExpression('/Emigrated.*Ausgewandert.*Deutsch/s', $html);
    }
}
