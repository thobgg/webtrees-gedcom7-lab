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
use Fisharebest\Webtrees\Registry;

/**
 * The chronicle theme.
 */
class ChronicleTheme extends AbstractModule implements ModuleThemeInterface, ModuleGlobalInterface
{
    use ModuleThemeTrait;
    use ModuleGlobalTrait;

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
     * Follow the light/dark setting of the browser.
     */
    public function headContent(): string
    {
        if (Registry::container()->get(ModuleThemeInterface::class)->name() !== $this->name()) {
            return '';
        }

        return '<script>(() => { const q = matchMedia("(prefers-color-scheme: dark)"); const f = () => document.documentElement.dataset.bsTheme = q.matches ? "dark" : "light"; f(); q.addEventListener("change", f); })();</script>';
    }
}
