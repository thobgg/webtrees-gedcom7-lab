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
use Fisharebest\Webtrees\Enums\Role;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Extensions;
use Fisharebest\Webtrees\Http\Controllers\ExtensionTags;
use Fisharebest\Webtrees\Enums\HttpRequestMethod;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Tests\TestCase;
use Fisharebest\Webtrees\Tree;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Gedcom7Extensions::class)]
#[CoversClass(ExtensionTags::class)]
class Gedcom7ExtensionsTest extends TestCase
{
    public function testExtensionTags(): void
    {
        self::createDatabase();
        (new Gedcom())->registerTags(Registry::elementFactory(), true);

        $tree = $this->importTree('extensions-gedcom7.ged');
        $tags = Gedcom7Extensions::tags($tree);

        self::assertSame(['_MEMBER', '_SKYPEID', '_UNDOCUMENTED'], array_keys($tags));
        self::assertSame(['count' => 1, 'path' => 'INDI:BIRT:_MEMBER', 'label' => '', 'uri' => 'http://xmlns.com/foaf/0.1/member'], $tags['_MEMBER']);
        self::assertSame('', $tags['_UNDOCUMENTED']['uri']);
        self::assertSame('http://xmlns.com/foaf/0.1/skypeID', $tags['_SKYPEID']['uri']);

        // The tags that webtrees7 writes have a default URI, unless the header documents them
        self::assertSame(Gedcom7Extensions::URIS['_LOC'], Gedcom7Extensions::uris($tree)['_LOC']);
        self::assertSame('http://xmlns.com/foaf/0.1/member', Gedcom7Extensions::uris($tree)['_MEMBER']);
        self::assertArrayNotHasKey('_UNDOCUMENTED', Gedcom7Extensions::uris($tree));
    }

    public function testEditDocumentation(): void
    {
        $tree = $this->treeWithManager();

        // Document one tag, remove the documentation of another, ignore an invalid tag
        $request = self::createRequest(HttpRequestMethod::POST->value, [], ['uri' => ['_MEMBER' => 'http://xmlns.com/foaf/0.1/member', '_SKYPEID' => '', '_UNDOCUMENTED' => ' https://example.com/undocumented ', 'INDI' => 'https://example.com/']]);
        (new ExtensionTags())->post($request, $tree);

        self::assertSame(
            ['_MEMBER' => 'http://xmlns.com/foaf/0.1/member', '_UNDOCUMENTED' => 'https://example.com/undocumented'],
            Gedcom7Extensions::documentation($tree)
        );
        self::assertSame('https://example.com/undocumented', Gedcom7Extensions::tags($tree)['_UNDOCUMENTED']['uri']);
    }

    public function testInvalidUriIsRefused(): void
    {
        $tree = $this->treeWithManager();

        // A URI without a scheme is refused, and nothing changes
        $request = self::createRequest(HttpRequestMethod::POST->value, [], ['uri' => ['_MEMBER' => 'example.com']]);
        (new ExtensionTags())->post($request, $tree);

        self::assertSame(['_SKYPEID' => 'http://xmlns.com/foaf/0.1/skypeID', '_MEMBER' => 'http://xmlns.com/foaf/0.1/member'], Gedcom7Extensions::documentation($tree));
    }

    public function testWithoutUrisThereIsNoSchema(): void
    {
        $tree = $this->treeWithManager();

        $request = self::createRequest(HttpRequestMethod::POST->value, [], ['uri' => ['_MEMBER' => '', '_SKYPEID' => '']]);
        (new ExtensionTags())->post($request, $tree);

        self::assertSame([], Gedcom7Extensions::documentation($tree));
        self::assertSame(0, Registry::headerFactory()->make('HEAD', $tree)?->facts(['SCHMA'])->count());
    }

    private function treeWithManager(): Tree
    {
        self::createDatabase();
        (new Gedcom())->registerTags(Registry::elementFactory(), true);

        $tree = $this->importTree('extensions-gedcom7.ged');
        $user = (new UserService(new SystemClock()))->create('test', 'test', 'test', '*');
        $tree->setUserPreference($user, UserInterface::PREF_TREE_ROLE, Role::Manager->value);
        $user->setPreference(UserInterface::PREF_AUTO_ACCEPT_EDITS, '1');
        Auth::login($user);

        return $tree;
    }

    protected function tearDown(): void
    {
        Auth::logout();

        parent::tearDown();
    }
}
