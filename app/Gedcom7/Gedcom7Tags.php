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
use Fisharebest\Webtrees\Elements\AgeAtEvent;
use Fisharebest\Webtrees\Elements\AssociationRole;
use Fisharebest\Webtrees\Elements\CustomElement;
use Fisharebest\Webtrees\Elements\DateLdsOrd;
use Fisharebest\Webtrees\Elements\DateValue;
use Fisharebest\Webtrees\Elements\DateValueExact;
use Fisharebest\Webtrees\Elements\ExternalIdentifier;
use Fisharebest\Webtrees\Elements\ExternalIdentifierType;
use Fisharebest\Webtrees\Elements\NameRomanizedVariation;
use Fisharebest\Webtrees\Elements\PafUid;
use Fisharebest\Webtrees\Elements\PlaceRomanizedVariation;
use Fisharebest\Webtrees\Elements\RelationIsDescriptor;
use Fisharebest\Webtrees\Elements\XrefAssociate;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\I18N;

use function str_ends_with;
use function str_starts_with;
use function strlen;
use function strtr;
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
     * Shared notes, for the records that permit them.
     *
     * @return array<string,array<int,array<int,string>>>
     */
    public function subTags(): array
    {
        return [
            'FAM'         => [['SNOTE', '0:M', 'SOUR'], ['ASSO', '0:M', 'SNOTE']],
            'FAM:*:ASSO'  => [['PHRASE', '0:1', 'RELA']],
            'INDI:*:ASSO' => [['PHRASE', '0:1', 'RELA']],
            'INDI:ASSO'   => [['PHRASE', '0:1', 'RELA']],
            'INDI'        => [['SNOTE', '0:M', 'SOUR']],
            'OBJE'        => [['SNOTE', '0:M', 'SOUR']],
            'REPO'        => [['SNOTE', '0:M']],
            'SOUR'        => [['SNOTE', '0:M']],
            'SUBM'        => [['SNOTE', '0:M']],
        ];
    }

    /**
     * @return array<string,ElementInterface>
     */
    public function tags(): array
    {
        $tags   = [];
        $gedcom = new Gedcom();

        // Shared notes have the substructures of note records, e.g. sources and restrictions
        foreach ([...$gedcom->gedcom551Tags(), ...$gedcom->webtreesTags()] as $tag => $element) {
            if (str_starts_with($tag, 'NOTE:')) {
                $tags['SNOTE:' . substr($tag, strlen('NOTE:'))] = $element;
            }
        }

        // Associates of events have the substructures of the GEDCOM-L _ASSO
        foreach ($gedcom->webtreesTags() as $tag => $element) {
            foreach (['INDI:*:_ASSO', 'FAM:*:_ASSO'] as $asso) {
                if ($tag === $asso || str_starts_with($tag, $asso . ':')) {
                    $tags[strtr($tag, ['_ASSO' => 'ASSO'])] = $element;
                }
            }
        }

        // Date phrases, e.g. "2 DATE 1850" + "3 PHRASE about Easter"
        foreach ([...$gedcom->gedcom551Tags(), ...$gedcom->webtreesTags()] as $tag => $element) {
            if (str_ends_with($tag, ':DATE') && ($element instanceof DateValue && !$element instanceof DateValueExact || $element instanceof DateLdsOrd)) {
                $tags[$tag . ':PHRASE'] = new CustomElement(I18N::translate('Phrase'));
            }
        }

        // Age phrases, e.g. "2 AGE < 8y" + "3 PHRASE Child"
        foreach ([...$gedcom->gedcom551Tags(), ...$gedcom->webtreesTags()] as $tag => $element) {
            if ($element instanceof AgeAtEvent) {
                $tags[$tag . ':PHRASE'] = new CustomElement(I18N::translate('Phrase'));
            }
        }

        // Translations that are not romanizations, e.g. a name in Greek.  Their substructures are those of ROMN.
        foreach ($gedcom->gedcom551Tags() as $tag => $element) {
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
            'SNOTE:LANG'            => new CustomElement(I18N::translate('Language')),
            'SNOTE:MIME'            => new CustomElement(I18N::translate('Media type')),
            'SNOTE:TRAN'            => new CustomElement(I18N::translate('Translation')),
            'SNOTE:TRAN:LANG'       => new CustomElement(I18N::translate('Language')),
            'SNOTE:TRAN:MIME'       => new CustomElement(I18N::translate('Media type')),
            'SNOTE:_UID'            => new PafUid(I18N::translate('Unique identifier')),
            'FAM:*:ASSO:PHRASE'     => new CustomElement(I18N::translate('Name')),
            'FAM:*:ASSO:ROLE'       => new AssociationRole(I18N::translate('Role')),
            'FAM:*:ASSO:RELA'       => new RelationIsDescriptor(I18N::translate('Relationship')),
            'FAM:ASSO'              => new XrefAssociate(I18N::translate('Associate')),
            'FAM:ASSO:PHRASE'       => new CustomElement(I18N::translate('Name')),
            'FAM:ASSO:ROLE'         => new AssociationRole(I18N::translate('Role')),
            'FAM:ASSO:RELA'         => new RelationIsDescriptor(I18N::translate('Relationship')),
            'INDI:*:ASSO:PHRASE'    => new CustomElement(I18N::translate('Name')),
            'INDI:*:ASSO:ROLE'      => new AssociationRole(I18N::translate('Role')),
            'INDI:ASSO:PHRASE'      => new CustomElement(I18N::translate('Name')),
            'INDI:ASSO:ROLE'        => new AssociationRole(I18N::translate('Role')),
            'OBJE:EXID'             => new ExternalIdentifier(I18N::translate('External identifier')),
            'OBJE:EXID:TYPE'        => new ExternalIdentifierType(I18N::translate('Type')),
        ];
    }
}
