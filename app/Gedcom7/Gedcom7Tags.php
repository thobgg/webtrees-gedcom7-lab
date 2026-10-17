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
use Fisharebest\Webtrees\Elements\AgeAtEvent;
use Fisharebest\Webtrees\Elements\AssociationRole;
use Fisharebest\Webtrees\Elements\CustomElement;
use Fisharebest\Webtrees\Elements\DateLdsOrd;
use Fisharebest\Webtrees\Elements\DateValue;
use Fisharebest\Webtrees\Elements\DateValueExact;
use Fisharebest\Webtrees\Elements\DescriptiveTitle;
use Fisharebest\Webtrees\Elements\EmptyElement;
use Fisharebest\Webtrees\Elements\EventTypeList;
use Fisharebest\Webtrees\Elements\ExternalIdentifier;
use Fisharebest\Webtrees\Elements\ExternalIdentifierType;
use Fisharebest\Webtrees\Elements\LanguageTag;
use Fisharebest\Webtrees\Elements\LdsOrdinanceStatus;
use Fisharebest\Webtrees\Elements\MediaType;
use Fisharebest\Webtrees\Elements\MultimediaFileReference;
use Fisharebest\Webtrees\Elements\NameTranslation;
use Fisharebest\Webtrees\Elements\NoteOrSharedNote;
use Fisharebest\Webtrees\Elements\NoteStructure;
use Fisharebest\Webtrees\Elements\PlaceTranslation;
use Fisharebest\Webtrees\Elements\RelationIsDescriptor;
use Fisharebest\Webtrees\Elements\RestrictionNotice;
use Fisharebest\Webtrees\Elements\SourceMediaType;
use Fisharebest\Webtrees\Elements\TimeValue;
use Fisharebest\Webtrees\Elements\TranslationLanguage;
use Fisharebest\Webtrees\Elements\UniqueIdentifier;
use Fisharebest\Webtrees\Elements\XrefAssociate;
use Fisharebest\Webtrees\Elements\XrefSharedNote;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\I18N;

use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function strrpos;
use function strtr;
use function substr;

/**
 * GEDCOM 7.0 structures that the upstream tags do not define, or define differently.
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
            'FAM'         => [['SNOTE', '0:M', 'SOUR'], ['ASSO', '0:M', 'SNOTE'], ['EXID', '0:M', 'UID']],
            'FAM:*:ASSO'  => [['PHRASE', '0:1', 'RELA']],
            'INDI:*:ASSO' => [['PHRASE', '0:1', 'RELA']],
            'INDI:ASSO'   => [['PHRASE', '0:1', 'RELA']],
            'INDI:NAME'   => [['TRAN', '0:M', 'FONE']],
            'INDI:*:PLAC' => [['TRAN', '0:M', 'FONE']],
            'FAM:*:PLAC'  => [['TRAN', '0:M', 'FONE']],
            'INDI'        => [['SNOTE', '0:M', 'SOUR'], ['EXID', '0:M', 'UID']],
            'OBJE'        => [['SNOTE', '0:M', 'SOUR'], ['EXID', '0:M', 'UID']],
            'REPO'        => [['SNOTE', '0:M'], ['EXID', '0:M', 'UID']],
            'SOUR'        => [['SNOTE', '0:M'], ['EXID', '0:M', 'UID']],
            'SUBM'        => [['SNOTE', '0:M'], ['EXID', '0:M', 'UID']],
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

        // Translations, e.g. romanized or in Greek.  Their substructures are those of ROMN, with LANG instead of TYPE.
        foreach ($gedcom->gedcom551Tags() as $tag => $element) {
            foreach (['INDI:NAME:ROMN', 'INDI:*:PLAC:ROMN', 'FAM:*:PLAC:ROMN'] as $romn) {
                if (str_starts_with($tag, $romn . ':') && $tag !== $romn . ':TYPE') {
                    $tags[substr($romn, 0, -4) . 'TRAN' . substr($tag, strlen($romn))] = $element;
                }
            }
        }

        // Enumerations with webtrees values, e.g. "2 PEDI OTHER" + "3 PHRASE Rada"
        foreach ([...$gedcom->gedcom551Tags(), ...$gedcom->webtreesTags()] as $tag => $element) {
            $parent = substr($tag, 0, (int) strrpos($tag, ':'));
            $last   = substr($tag, strlen($parent) + 1);

            if (Gedcom7Enumerations::type($parent, $last) !== null || str_ends_with($tag, ':SOUR:EVEN:ROLE')) {
                $tags[$tag . ':PHRASE'] = new CustomElement(I18N::translate('Phrase'));
            }

            // LDS ordinances have the status values of GEDCOM 7.0, e.g. DNS_CAN
            if (Gedcom7Enumerations::type($parent, $last) === 'ord-STAT') {
                $tags[$tag] = new LdsOrdinanceStatus($element->label());
            }

            // Roles in sources are the roles of GEDCOM 7.0
            if (str_ends_with($tag, ':SOUR:EVEN:ROLE')) {
                $tags[$tag] = new AssociationRole($element->label());
            }
        }

        // Notes and texts have a media type and a language, and notes have translations
        foreach ([...$gedcom->gedcom551Tags(), ...$gedcom->webtreesTags()] as $tag => $element) {
            if (str_ends_with($tag, ':NOTE') || str_ends_with($tag, ':TEXT')) {
                $tags[$tag . ':MIME'] = new CustomElement(I18N::translate('Media type'));
                $tags[$tag . ':LANG'] = new LanguageTag(I18N::translate('Language'));
            }

            if (str_ends_with($tag, ':NOTE')) {
                $tags[$tag . ':TRAN']      = new CustomElement(I18N::translate('Translation'));
                $tags[$tag . ':TRAN:MIME'] = new CustomElement(I18N::translate('Media type'));
                $tags[$tag . ':TRAN:LANG'] = new LanguageTag(I18N::translate('Language'));
            }

            // Places have a language
            if (str_ends_with($tag, ':PLAC') && !str_starts_with($tag, 'HEAD:')) {
                $tags[$tag . ':LANG'] = new LanguageTag(I18N::translate('Language'));
            }
        }

        // Unique identifiers keep the values of other programs, e.g. the _UID of PAF
        foreach (['FAM', 'FAM:*', 'INDI', 'INDI:*', 'OBJE', 'REPO', 'SNOTE', 'SOUR', 'SUBM', '_LOC'] as $record) {
            $tags[$record . ':UID'] = new UniqueIdentifier(I18N::translate('Unique identifier'));
        }

        // Places of the events of a source are like the places of other events
        foreach ([...$gedcom->gedcom551Tags(), ...(new Gedcom7())->tags(), ...$tags] as $tag => $element) {
            if (str_starts_with($tag, 'INDI:*:PLAC:')) {
                $tags['SOUR:DATA:EVEN:PLAC:' . substr($tag, strlen('INDI:*:PLAC:'))] ??= $element;
            }
        }

        // Other substructures of GEDCOM 7.0, e.g. CROP of a media link, or the sources of a note
        $known = [...$gedcom->gedcom551Tags(), ...$gedcom->webtreesTags(), ...(new Gedcom7())->tags(), ...$tags];
        $add   = static function (string $tag, ElementInterface $element) use ($known, &$tags): void {
            if (!isset($known[$tag]) && !isset($tags[$tag])) {
                $tags[$tag] = $element;
            }
        };

        foreach ($known as $tag => $element) {
            if (str_contains($tag, ':') && str_ends_with($tag, ':OBJE')) {
                $add($tag . ':TITL', new DescriptiveTitle(I18N::translate('Title')));
                $add($tag . ':CROP', new EmptyElement(I18N::translate('Crop'), ['TOP' => '0:1', 'LEFT' => '0:1', 'HEIGHT' => '0:1', 'WIDTH' => '0:1']));
                $add($tag . ':CROP:TOP', new CustomElement(I18N::translate('Top')));
                $add($tag . ':CROP:LEFT', new CustomElement(I18N::translate('Left')));
                $add($tag . ':CROP:HEIGHT', new CustomElement(I18N::translate('Height')));
                $add($tag . ':CROP:WIDTH', new CustomElement(I18N::translate('Width')));
            }

            if (str_ends_with($tag, ':NOTE')) {
                $add(substr($tag, 0, -4) . 'SNOTE', new XrefSharedNote(I18N::translate('Shared note')));

                foreach ($known as $citation => $citation_element) {
                    if ($citation === 'INDI:SOUR' || str_starts_with($citation, 'INDI:SOUR:')) {
                        $add($tag . substr($citation, 4), $citation_element);
                    }
                }
            }

            if (str_ends_with($tag, ':SOUR:EVEN')) {
                $add($tag . ':PHRASE', new CustomElement(I18N::translate('Phrase')));
            }

            if (str_ends_with($tag, ':STAT:DATE') || str_ends_with($tag, ':SOUR:DATA:DATE')) {
                $add($tag . ':TIME', new TimeValue(I18N::translate('Time')));
            }
        }

        // Phrases of pointers and of the status of a family link
        foreach (['FAM:CHIL', 'FAM:HUSB', 'FAM:WIFE', 'INDI:FAMC:STAT'] as $tag) {
            $add($tag . ':PHRASE', new CustomElement(I18N::translate('Phrase')));
        }

        // Notes are text, and links to shared notes are SNOTE
        foreach ([...$gedcom->gedcom551Tags(), ...$gedcom->webtreesTags(), ...$tags] as $tag => $element) {
            if ($element instanceof NoteStructure && !$element instanceof NoteOrSharedNote) {
                $tags[$tag] = new NoteOrSharedNote($element->label());
            }
        }

        return $tags + [
            'INDI:NAME:TRAN'        => new NameTranslation(I18N::translate('Translation')),
            'INDI:NAME:TRAN:LANG'   => new TranslationLanguage(I18N::translate('Language')),
            'INDI:*:PLAC:TRAN'      => new PlaceTranslation(I18N::translate('Translation')),
            'INDI:*:PLAC:TRAN:LANG' => new TranslationLanguage(I18N::translate('Language')),
            'FAM:*:PLAC:TRAN'       => new PlaceTranslation(I18N::translate('Translation')),
            'FAM:*:PLAC:TRAN:LANG'  => new TranslationLanguage(I18N::translate('Language')),
            'OBJE:FILE:FORM'             => new MediaType(I18N::translate('Format')),
            'OBJE:FILE:FORM:MEDI'        => new SourceMediaType(I18N::translate('Media type')),
            'OBJE:FILE:FORM:MEDI:PHRASE' => new CustomElement(I18N::translate('Phrase')),
            'OBJE:FILE:TRAN'             => new MultimediaFileReference(I18N::translate('Translation')),
            'OBJE:FILE:TRAN:FORM'        => new MediaType(I18N::translate('Format')),
            'SOUR:REPO:CALN:MEDI:PHRASE' => new CustomElement(I18N::translate('Phrase')),
            'SOUR:DATA:EVEN'                => new EventTypeList(I18N::translate('Events')),
            'SOUR:DATA:EVEN:PLAC:TRAN'      => new PlaceTranslation(I18N::translate('Translation')),
            'SOUR:DATA:EVEN:PLAC:TRAN:LANG' => new TranslationLanguage(I18N::translate('Language')),
            'SNOTE:LANG'            => new LanguageTag(I18N::translate('Language')),
            'SNOTE:MIME'            => new CustomElement(I18N::translate('Media type')),
            'SNOTE:TRAN'            => new CustomElement(I18N::translate('Translation')),
            'SNOTE:TRAN:LANG'       => new LanguageTag(I18N::translate('Language')),
            'HEAD:LANG'             => new LanguageTag(I18N::translate('Language')),
            'SUBM:LANG'             => new LanguageTag(I18N::translate('Language')),
            '_LOC:NAME:LANG'        => new LanguageTag(I18N::translate('Language')),
            'NOTE:_RESN'            => new RestrictionNotice(I18N::translate('Restriction')),
            'REPO:_RESN'            => new RestrictionNotice(I18N::translate('Restriction')),
            'SNOTE:_RESN'           => new RestrictionNotice(I18N::translate('Restriction')),
            'SOUR:_RESN'            => new RestrictionNotice(I18N::translate('Restriction')),
            'SUBM:_RESN'            => new RestrictionNotice(I18N::translate('Restriction')),
            'SNOTE:TRAN:MIME'       => new CustomElement(I18N::translate('Media type')),
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
