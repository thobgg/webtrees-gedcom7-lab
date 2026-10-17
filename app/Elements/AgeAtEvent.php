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

use Fisharebest\Webtrees\Gedcom7\Gedcom7Payload;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Storage;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Tree;

use function in_array;
use function preg_replace_callback_array;
use function strtolower;
use function strtoupper;

/**
 * AGE_AT_EVENT := {Size=1:12}
 * [ < | > | <NULL>]
 * [ YYy MMm DDDd | YYy | MMm | DDDd |
 * YYy MMm | YYy DDDd | MMm DDDd |
 * CHILD | INFANT | STILLBORN ]
 * ]
 * Where:
 * >         = greater than indicated age
 * <         = less than indicated age
 * y         = a label indicating years
 * m         = a label indicating months
 * d         = a label indicating days
 * YY        = number of full years
 * MM        = number of months
 * DDD       = number of days
 * CHILD     = age < 8 years
 * INFANT    = age <1year
 * STILLBORN = died just prior, at, or near birth, 0 years
 */
class AgeAtEvent extends AbstractElement
{
    protected const int MAXIMUM_LENGTH = 12;

    protected const array KEYWORDS = ['CHILD', 'INFANT', 'STILLBORN'];

    protected const array SUBTAGS = [
        'PHRASE' => '0:1',
    ];

    /**
     * Should we collapse the children of this element when editing?  e.g. an empty phrase.
     */
    public function collapseChildren(): bool
    {
        return true;
    }

    /**
     * Convert a value to a canonical form.
     */
    public function canonical(string $value): string
    {
        // Keywords are upper case.  Ages are lower case
        $canonical = parent::canonical($value);
        $upper     = strtoupper($canonical);

        if (in_array($upper, self::KEYWORDS, true)) {
            return $upper;
        }

        // GEDCOM 7.0 trees have GEDCOM 7.0 ages, e.g. "63" is "63y"
        if (Gedcom7Storage::isGedcom7Request()) {
            [$age, $phrase] = $this->withPhrase($canonical);

            if ($phrase === '') {
                return $age;
            }
        }

        return strtolower($canonical);
    }

    /**
     * GEDCOM 7.0 keeps the words of an age as a phrase, e.g. "CHILD" is "< 8y" + "Child".
     *
     * @return array{0:string,1:string}
     */
    public function withPhrase(string $value): array
    {
        return Gedcom7Payload::age($value);
    }

    /**
     * Display the value of this type of element.
     */
    public function value(string $value, Tree $tree): string
    {
        $canonical = $this->canonical($value);

        switch ($canonical) {
            case 'CHILD':
                return I18N::translate('child');

            case 'INFANT':
                return I18N::translate('infant');

            case 'STILLBORN':
                return I18N::translate('stillborn');
        }

        return preg_replace_callback_array([
            '/\b(\d+)y\b/' => fn (array $match) => I18N::plural('%s year', '%s years', (int) $match[1], I18N::number((float) $match[1])),
            '/\b(\d+)m\b/' => fn (array $match) => I18N::plural('%s month', '%s months', (int) $match[1], I18N::number((float) $match[1])),
            '/\b(\d+)w\b/' => fn (array $match) => I18N::plural('%s week', '%s weeks', (int) $match[1], I18N::number((float) $match[1])),
            '/\b(\d+)d\b/' => fn (array $match) => I18N::plural('%s day', '%s days', (int) $match[1], I18N::number((float) $match[1])),
        ], e($canonical));
    }

    /**
     * Display an age with its phrase, e.g. "< 8y" + "Child" or "" + "about 30".
     */
    public function valueWithPhrase(string $value, string $phrase, Tree $tree): string
    {
        foreach (Gedcom7Payload::AGE_WORDS as $word => [$age, $age_phrase]) {
            if ($value === $age && $phrase === $age_phrase) {
                return $this->value($word, $tree);
            }
        }

        if ($phrase === '') {
            return $this->value($value, $tree);
        }

        if ($value === '') {
            return e($phrase);
        }

        return $this->value($value, $tree) . ' (' . e($phrase) . ')';
    }
}
