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

namespace Fisharebest\Webtrees\Gedcom7;

use Fisharebest\Webtrees\Contracts\CustomTagInterface;
use Fisharebest\Webtrees\Contracts\ElementInterface;
use Fisharebest\Webtrees\CustomTags\Gedcom7;
use Fisharebest\Webtrees\Elements\CustomElement;
use Fisharebest\Webtrees\Elements\ExternalIdentifier;
use Fisharebest\Webtrees\Elements\ExternalIdentifierType;
use Fisharebest\Webtrees\Elements\NameRomanizedVariation;
use Fisharebest\Webtrees\Elements\PlaceRomanizedVariation;
use Fisharebest\Webtrees\Elements\RelationIsDescriptor;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\I18N;

use function str_starts_with;
use function strlen;
use function substr;

/**
 * GEDCOM 7.0 structures that webtrees7 keeps in the working copy, and that the upstream tags do not define.
 */
final class Gedcom7Tags implements CustomTagInterface
{
    public function name(): string
    {
        return 'webtrees7';
    }

    /**
     * @return array<string,ElementInterface>
     */
    public function tags(): array
    {
        $tags = [];

        // In the working copy, SNOTE records are NOTE records.
        foreach ((new Gedcom7())->tags() as $tag => $element) {
            if (str_starts_with($tag, 'SNOTE:')) {
                $tags['NOTE:' . substr($tag, strlen('SNOTE:'))] = $element;
            }
        }

        // Translations that are not romanizations, e.g. a name in Greek.  Their substructures are those of ROMN.
        foreach ((new Gedcom())->gedcom551Tags() as $tag => $element) {
            foreach (['INDI:NAME:ROMN', 'INDI:*:PLAC:ROMN', 'FAM:*:PLAC:ROMN'] as $romn) {
                if (str_starts_with($tag, $romn . ':')) {
                    $tags[substr($romn, 0, -4) . 'TRAN' . substr($tag, strlen($romn))] = $element;
                }
            }
        }

        return $tags + [
            'INDI:NAME:TRAN'        => new NameRomanizedVariation(I18N::translate('Translation')),
            'INDI:NAME:TRAN:LANG'   => new CustomElement(I18N::translate('Language')),
            'INDI:*:PLAC:TRAN'      => new PlaceRomanizedVariation(I18N::translate('Translation')),
            'INDI:*:PLAC:TRAN:LANG' => new CustomElement(I18N::translate('Language')),
            'FAM:*:PLAC:TRAN'       => new PlaceRomanizedVariation(I18N::translate('Translation')),
            'FAM:*:PLAC:TRAN:LANG'  => new CustomElement(I18N::translate('Language')),
            'NOTE:LANG'             => new CustomElement(I18N::translate('Language')),
            'NOTE:MIME'             => new CustomElement(I18N::translate('Media type')),
            'NOTE:TRAN'             => new CustomElement(I18N::translate('Translation')),
            'NOTE:TRAN:LANG'        => new CustomElement(I18N::translate('Language')),
            'NOTE:TRAN:MIME'        => new CustomElement(I18N::translate('Media type')),
            'FAM:*:ASSO:RELA'       => new RelationIsDescriptor(I18N::translate('Relationship')),
            'OBJE:EXID'             => new ExternalIdentifier(I18N::translate('External identifier')),
            'OBJE:EXID:TYPE'        => new ExternalIdentifierType(I18N::translate('Type')),
        ];
    }
}
