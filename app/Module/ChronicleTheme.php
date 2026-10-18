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

namespace Fisharebest\Webtrees\Module;

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Menu;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;

use function view;

/**
 * The chronicle theme.
 */
class ChronicleTheme extends AbstractModule implements ModuleThemeInterface, ModuleGlobalInterface
{
    use ModuleThemeTrait;
    use ModuleGlobalTrait;

    // Text, male, female and unknown on the fan chart, which is an image: the colors of the boxes, a little stronger
    public const array FAN_CHART_COLORS = ['2a241d', 'd2e1ee', 'efd8e2', 'e6ded0'];

    public function title(): string
    {
        /* I18N: Name of a theme. */
        return I18N::translate('chronicle');
    }

    /**
     * A list of CSS files to include for this page.
     *
     * @return array<string>
     */
    public function stylesheets(): array
    {
        return [
            asset('css/chronicle.min.css'),
        ];
    }

    public function bootstrapColorScheme(): string
    {
        return 'auto';
    }

    /**
     * Generate a list of items for the user menu.
     *
     * @return array<Menu>
     */
    public function userMenu(Tree|null $tree): array
    {
        return array_filter([
            $this->menuPendingChanges($tree),
            $this->menuMyPages($tree),
            $this->menuThemes(),
            $this->menuColorMode(),
            $this->menuLanguages(),
            $this->menuLogin(),
            $this->menuLogout(),
        ]);
    }

    /**
     * Light, dark or follow the browser. The choice is stored in the browser.
     */
    public function menuColorMode(): Menu
    {
        $submenus = [
            /* I18N: Colour scheme - follow the setting of the browser */
            new Menu(I18N::translate('automatic'), '#', 'menu-color-mode-auto', ['data-wt-color-mode' => 'auto']),
            /* I18N: Colour scheme */
            new Menu(I18N::translate('light'), '#', 'menu-color-mode-light', ['data-wt-color-mode' => 'light']),
            /* I18N: Colour scheme */
            new Menu(I18N::translate('dark'), '#', 'menu-color-mode-dark', ['data-wt-color-mode' => 'dark']),
        ];

        /* I18N: A menu to choose light or dark colours */
        return new Menu(I18N::translate('Appearance'), '#', 'menu-color-mode', [], $submenus);
    }

    /**
     * Set the colour scheme before the page is drawn.
     */
    public function headContent(): string
    {
        if (Registry::container()->get(ModuleThemeInterface::class)->name() !== $this->name()) {
            return '';
        }

        return view('modules/chronicle-theme/color-mode');
    }
}
