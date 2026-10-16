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

namespace Fisharebest\Webtrees\Elements;

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Tree;

/**
 * The language of a translation, as a BCP 47 language tag.  GEDCOM 7.0 writes the GEDCOM 5.5.1
 * romanized and phonetic variations as "und-Latn" and "und", and their types as e.g. "ja-Latn".
 */
class TranslationLanguage extends AbstractElement
{
    /**
     * Display the value of this type of element.
     */
    public function value(string $value, Tree $tree): string
    {
        $languages = [
            'und-Latn'         => I18N::translate('Romanized'),
            'und'              => I18N::translate('Phonetic'),
            'und-Latn-pinyin'  => I18N::translate('Romanized') . ' – pinyin',
            'ja-Latn'          => I18N::translate('Romanized') . ' – romaji',
            'zh-Latn-wadegile' => I18N::translate('Romanized') . ' – wadegiles',
            'ko-hang'          => I18N::translate('Phonetic') . ' – hangul',
            'ja-hrkt'          => I18N::translate('Phonetic') . ' – kana',
        ];

        return $languages[$value] ?? parent::value($value, $tree);
    }
}
