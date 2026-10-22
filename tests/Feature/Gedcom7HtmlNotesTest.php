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
use Fisharebest\Webtrees\Elements\NoteStructure;
use Fisharebest\Webtrees\Enums\Role;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Notes;
use Fisharebest\Webtrees\Note;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Tests\TestCase;
use Fisharebest\Webtrees\Tree;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * GEDCOM 7.0 notes with MIME text/html.
 */
#[CoversClass(Gedcom7Notes::class)]
#[CoversClass(NoteStructure::class)]
#[CoversClass(Note::class)]
class Gedcom7HtmlNotesTest extends TestCase
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

    public function testHtmlNotes(): void
    {
        $this->import([
            "0 @N1@ SNOTE <p>Hello <b>World</b></p><script>alert(1)</script>\n1 MIME text/html",
            "0 @N2@ SNOTE Hello <b>World</b>",
        ]);

        $html  = Registry::sharedNoteFactory()->make('N1', $this->tree);
        $plain = Registry::sharedNoteFactory()->make('N2', $this->tree);
        self::assertInstanceOf(Note::class, $html);
        self::assertInstanceOf(Note::class, $plain);

        self::assertSame('text/html', $html->mime());
        self::assertSame('text/plain', $plain->mime());

        // HTML is sanitised, text is escaped
        self::assertSame('<p>Hello <b>World</b></p>', Gedcom7Notes::html($html->getNote(), $html->mime(), $this->tree));
        self::assertStringContainsString('Hello &lt;b&gt;World&lt;/b&gt;', Gedcom7Notes::html($plain->getNote(), $plain->mime(), $this->tree));

        // The name of the note is its first line, without markup
        self::assertSame('Hello World', strip_tags($html->fullName()));

        // Inline notes with a media type, e.g. "2 NOTE <i>text</i>" + "3 MIME text/html"
        $element = new NoteStructure('Note');
        self::assertSame('<i>Text</i>', $element->withMime('text/html')->value('<i>Text</i><script>alert(1)</script>', $this->tree));
        self::assertStringContainsString('&lt;i&gt;Text&lt;/i&gt;', $element->value('<i>Text</i>', $this->tree));
        self::assertStringContainsString('<i>Text</i>', $element->withMime('text/html')->labelValue('<p><i>Text</i></p>', $this->tree));
    }
}
