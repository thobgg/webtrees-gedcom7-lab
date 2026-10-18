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

use Fisharebest\Webtrees\Contracts\ElementInterface;
use Fisharebest\Webtrees\Elements\AddressCity;
use Fisharebest\Webtrees\Elements\AddressCountry;
use Fisharebest\Webtrees\Elements\AddressEmail;
use Fisharebest\Webtrees\Elements\AddressFax;
use Fisharebest\Webtrees\Elements\AddressLine;
use Fisharebest\Webtrees\Elements\AddressLine1;
use Fisharebest\Webtrees\Elements\AddressLine2;
use Fisharebest\Webtrees\Elements\AddressLine3;
use Fisharebest\Webtrees\Elements\AddressPostalCode;
use Fisharebest\Webtrees\Elements\AddressState;
use Fisharebest\Webtrees\Elements\AddressWebPage;
use Fisharebest\Webtrees\Elements\AdoptedByWhichParent;
use Fisharebest\Webtrees\Elements\Adoption;
use Fisharebest\Webtrees\Elements\AdultChristening;
use Fisharebest\Webtrees\Elements\AgeAtEvent;
use Fisharebest\Webtrees\Elements\Annulment;
use Fisharebest\Webtrees\Elements\ApprovedSystemId;
use Fisharebest\Webtrees\Elements\AssociationRole;
use Fisharebest\Webtrees\Elements\AssociationStructure;
use Fisharebest\Webtrees\Elements\Baptism;
use Fisharebest\Webtrees\Elements\BarMitzvah;
use Fisharebest\Webtrees\Elements\BasMitzvah;
use Fisharebest\Webtrees\Elements\Birth;
use Fisharebest\Webtrees\Elements\Blessing;
use Fisharebest\Webtrees\Elements\Burial;
use Fisharebest\Webtrees\Elements\CasteName;
use Fisharebest\Webtrees\Elements\CauseOfEvent;
use Fisharebest\Webtrees\Elements\Census;
use Fisharebest\Webtrees\Elements\CertaintyAssessment;
use Fisharebest\Webtrees\Elements\Change;
use Fisharebest\Webtrees\Elements\ChangeDate;
use Fisharebest\Webtrees\Elements\ChildFamilyLink;
use Fisharebest\Webtrees\Elements\ChildLinkageStatus;
use Fisharebest\Webtrees\Elements\Christening;
use Fisharebest\Webtrees\Elements\Confirmation;
use Fisharebest\Webtrees\Elements\Coordinates;
use Fisharebest\Webtrees\Elements\CopyrightFile;
use Fisharebest\Webtrees\Elements\CountOfChildren;
use Fisharebest\Webtrees\Elements\CountOfChildrenFam;
use Fisharebest\Webtrees\Elements\CountOfMarriages;
use Fisharebest\Webtrees\Elements\Creation;
use Fisharebest\Webtrees\Elements\Cremation;
use Fisharebest\Webtrees\Elements\CustomElement;
use Fisharebest\Webtrees\Elements\DatePeriod;
use Fisharebest\Webtrees\Elements\DateValue;
use Fisharebest\Webtrees\Elements\Death;
use Fisharebest\Webtrees\Elements\DescriptiveTitle;
use Fisharebest\Webtrees\Elements\Divorce;
use Fisharebest\Webtrees\Elements\DivorceFiled;
use Fisharebest\Webtrees\Elements\Emigration;
use Fisharebest\Webtrees\Elements\EmptyElement;
use Fisharebest\Webtrees\Elements\Engagement;
use Fisharebest\Webtrees\Elements\EventOrFactClassification;
use Fisharebest\Webtrees\Elements\EventTypeCitedFrom;
use Fisharebest\Webtrees\Elements\EventTypeList;
use Fisharebest\Webtrees\Elements\ExternalIdentifier;
use Fisharebest\Webtrees\Elements\ExternalIdentifierType;
use Fisharebest\Webtrees\Elements\FamilyCensus;
use Fisharebest\Webtrees\Elements\FamilyEvent;
use Fisharebest\Webtrees\Elements\FamilyFact;
use Fisharebest\Webtrees\Elements\FamilyNonEvent;
use Fisharebest\Webtrees\Elements\FamilyRecord;
use Fisharebest\Webtrees\Elements\FirstCommunion;
use Fisharebest\Webtrees\Elements\GedcomElement;
use Fisharebest\Webtrees\Elements\Graduation;
use Fisharebest\Webtrees\Elements\HeaderRecord;
use Fisharebest\Webtrees\Elements\Immigration;
use Fisharebest\Webtrees\Elements\IndividualEvent;
use Fisharebest\Webtrees\Elements\IndividualFact;
use Fisharebest\Webtrees\Elements\IndividualRecord;
use Fisharebest\Webtrees\Elements\LanguageTag;
use Fisharebest\Webtrees\Elements\LdsBaptism;
use Fisharebest\Webtrees\Elements\LdsChildSealing;
use Fisharebest\Webtrees\Elements\LdsConfirmation;
use Fisharebest\Webtrees\Elements\LdsEndowment;
use Fisharebest\Webtrees\Elements\LdsInitiatory;
use Fisharebest\Webtrees\Elements\LdsOrdinanceStatus;
use Fisharebest\Webtrees\Elements\LdsSpouseSealing;
use Fisharebest\Webtrees\Elements\Marriage;
use Fisharebest\Webtrees\Elements\MarriageBanns;
use Fisharebest\Webtrees\Elements\MarriageContract;
use Fisharebest\Webtrees\Elements\MarriageLicence;
use Fisharebest\Webtrees\Elements\MarriageSettlement;
use Fisharebest\Webtrees\Elements\MediaRecord;
use Fisharebest\Webtrees\Elements\MediaType;
use Fisharebest\Webtrees\Elements\MultimediaFileReference;
use Fisharebest\Webtrees\Elements\NameOfBusiness;
use Fisharebest\Webtrees\Elements\NameOfProduct;
use Fisharebest\Webtrees\Elements\NameOfSourceData;
use Fisharebest\Webtrees\Elements\NamePersonal;
use Fisharebest\Webtrees\Elements\NamePieceGiven;
use Fisharebest\Webtrees\Elements\NamePieceNickname;
use Fisharebest\Webtrees\Elements\NamePiecePrefix;
use Fisharebest\Webtrees\Elements\NamePieceSuffix;
use Fisharebest\Webtrees\Elements\NamePieceSurname;
use Fisharebest\Webtrees\Elements\NamePieceSurnamePrefix;
use Fisharebest\Webtrees\Elements\NameTranslation;
use Fisharebest\Webtrees\Elements\NameTypeEnumeration;
use Fisharebest\Webtrees\Elements\NationalIdNumber;
use Fisharebest\Webtrees\Elements\NationalOrTribalOrigin;
use Fisharebest\Webtrees\Elements\Naturalization;
use Fisharebest\Webtrees\Elements\NobilityTypeTitle;
use Fisharebest\Webtrees\Elements\NoteOrSharedNote;
use Fisharebest\Webtrees\Elements\NoteRecord;
use Fisharebest\Webtrees\Elements\Occupation;
use Fisharebest\Webtrees\Elements\Ordination;
use Fisharebest\Webtrees\Elements\PedigreeLinkageTypeEnumeration;
use Fisharebest\Webtrees\Elements\PhoneNumber;
use Fisharebest\Webtrees\Elements\PhysicalDescription;
use Fisharebest\Webtrees\Elements\PlaceHierarchy;
use Fisharebest\Webtrees\Elements\PlaceLatitude;
use Fisharebest\Webtrees\Elements\PlaceLongtitude;
use Fisharebest\Webtrees\Elements\PlaceName;
use Fisharebest\Webtrees\Elements\PlaceTranslation;
use Fisharebest\Webtrees\Elements\Possessions;
use Fisharebest\Webtrees\Elements\Probate;
use Fisharebest\Webtrees\Elements\ReceivingSystemName;
use Fisharebest\Webtrees\Elements\ReligiousAffiliation;
use Fisharebest\Webtrees\Elements\RepositoryRecord;
use Fisharebest\Webtrees\Elements\Residence;
use Fisharebest\Webtrees\Elements\ResponsibleAgency;
use Fisharebest\Webtrees\Elements\RestrictionNotice;
use Fisharebest\Webtrees\Elements\Retirement;
use Fisharebest\Webtrees\Elements\ScholasticAchievement;
use Fisharebest\Webtrees\Elements\SexXValue;
use Fisharebest\Webtrees\Elements\SocialSecurityNumber;
use Fisharebest\Webtrees\Elements\SourceCallNumber;
use Fisharebest\Webtrees\Elements\SourceData;
use Fisharebest\Webtrees\Elements\SourceFiledByEntry;
use Fisharebest\Webtrees\Elements\SourceMediaTypeEnumeration;
use Fisharebest\Webtrees\Elements\SourceOriginator;
use Fisharebest\Webtrees\Elements\SourcePublicationFacts;
use Fisharebest\Webtrees\Elements\SourceRecord;
use Fisharebest\Webtrees\Elements\SubmitterRecord;
use Fisharebest\Webtrees\Elements\TempleCode;
use Fisharebest\Webtrees\Elements\TextFromSource;
use Fisharebest\Webtrees\Elements\TextMediaType;
use Fisharebest\Webtrees\Elements\TimeValue;
use Fisharebest\Webtrees\Elements\TransmissionDate;
use Fisharebest\Webtrees\Elements\UniqueIdentifier;
use Fisharebest\Webtrees\Elements\UserReferenceNumber;
use Fisharebest\Webtrees\Elements\VersionNumber;
use Fisharebest\Webtrees\Elements\WhereWithinSource;
use Fisharebest\Webtrees\Elements\Will;
use Fisharebest\Webtrees\Elements\XrefFamily;
use Fisharebest\Webtrees\Elements\XrefIndividual;
use Fisharebest\Webtrees\Elements\XrefMedia;
use Fisharebest\Webtrees\Elements\XrefRepository;
use Fisharebest\Webtrees\Elements\XrefSharedNote;
use Fisharebest\Webtrees\Elements\XrefSource;
use Fisharebest\Webtrees\Elements\XrefSubmitter;
use Fisharebest\Webtrees\I18N;

use function array_key_exists;
use function array_key_first;
use function array_keys;
use function array_slice;
use function count;
use function explode;
use function in_array;
use function preg_match;
use function spl_object_id;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function uasort;

/**
 * The structures of GEDCOM 7.0 define the substructures of every element.  webtrees defines how they look.
 */
final class Gedcom7Definitions
{
    // Substructures of records that webtrees writes
    private const array NOT_OFFERED = ['CHAN', 'CREA'];

    // The substructures that the edit forms show at once, by structure type.  The others need "all tags".
    private const array SHOWN = [
        'ADOP'           => ['ADDR', 'AGE', 'AGNC', 'ASSO', 'DATE', 'FAMC', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR'],
        'ADOP-FAMC'      => ['ADOP'],
        'AGE'            => ['PHRASE'],
        'ALIA'           => ['PHRASE'],
        'ANUL'           => ['ASSO', 'DATE', 'HUSB', 'NOTE', 'OBJE', 'PLAC', 'RELI', 'RESN', 'SOUR', 'WIFE'],
        'ASSO'           => ['NOTE', 'PHRASE', 'ROLE', 'SOUR'],
        'BAPL'           => ['DATE', 'NOTE', 'PLAC', 'SOUR', 'STAT', 'TEMP'],
        'BAPM'           => ['ADDR', 'AGE', 'ASSO', 'DATE', 'NOTE', 'OBJE', 'PLAC', 'RELI', 'RESN', 'SOUR'],
        'BARM'           => ['ADDR', 'AGE', 'ASSO', 'DATE', 'NOTE', 'OBJE', 'PLAC', 'RELI', 'RESN', 'SOUR'],
        'BASM'           => ['ADDR', 'AGE', 'ASSO', 'DATE', 'NOTE', 'OBJE', 'PLAC', 'RELI', 'RESN', 'SOUR'],
        'BIRT'           => ['ADDR', 'ASSO', 'DATE', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR'],
        'BLES'           => ['ADDR', 'AGE', 'DATE', 'NOTE', 'OBJE', 'PLAC', 'RELI', 'RESN', 'SOUR'],
        'BURI'           => ['ADDR', 'ASSO', 'DATE', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR'],
        'CALN'           => ['MEDI'],
        'CAST'           => ['ADDR', 'DATE', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR'],
        'CHAN'           => ['DATE'],
        'CHR'            => ['ADDR', 'AGE', 'ASSO', 'DATE', 'NOTE', 'OBJE', 'PLAC', 'RELI', 'RESN', 'SOUR'],
        'CHRA'           => ['ADDR', 'AGE', 'ASSO', 'DATE', 'NOTE', 'OBJE', 'PLAC', 'RELI', 'RESN', 'SOUR'],
        'CONF'           => ['ADDR', 'AGE', 'ASSO', 'DATE', 'NOTE', 'OBJE', 'PLAC', 'RELI', 'RESN', 'SOUR'],
        'CONL'           => ['DATE', 'NOTE', 'PLAC', 'SOUR', 'STAT', 'TEMP'],
        'CREM'           => ['ADDR', 'ASSO', 'DATE', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR'],
        'CROP'           => ['HEIGHT', 'LEFT', 'TOP', 'WIDTH'],
        'DATA'           => ['AGNC', 'EVEN', 'NOTE'],
        'DATA-EVEN'      => ['DATE', 'PLAC'],
        'DATA-EVEN-DATE' => ['PHRASE'],
        'DATE'           => ['PHRASE'],
        'DATE-exact'     => ['TIME'],
        'DEAT'           => ['ADDR', 'AGE', 'ASSO', 'CAUS', 'DATE', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR'],
        'DIV'            => ['ADDR', 'ASSO', 'DATE', 'HUSB', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR', 'WIFE'],
        'DIVF'           => ['ADDR', 'ASSO', 'DATE', 'HUSB', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR', 'WIFE'],
        'DSCR'           => ['ADDR', 'DATE', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR'],
        'EDUC'           => ['ADDR', 'AGE', 'AGNC', 'ASSO', 'DATE', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR'],
        'EMIG'           => ['ADDR', 'AGE', 'ASSO', 'DATE', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR'],
        'ENDL'           => ['DATE', 'NOTE', 'PLAC', 'SOUR', 'STAT', 'TEMP'],
        'ENGA'           => ['ADDR', 'ASSO', 'DATE', 'HUSB', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR', 'WIFE'],
        'EXID'           => ['TYPE'],
        'FAM-CENS'       => ['ADDR', 'ASSO', 'DATE', 'HUSB', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR', 'WIFE'],
        'FAM-EVEN'       => ['ADDR', 'AGNC', 'ASSO', 'CAUS', 'DATE', 'HUSB', 'NOTE', 'OBJE', 'PLAC', 'RELI', 'RESN', 'SOUR', 'TYPE', 'WIFE'],
        'FAM-FACT'       => ['ADDR', 'DATE', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR', 'TYPE'],
        'FAM-RESI'       => ['ADDR', 'DATE', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR'],
        'FCOM'           => ['ADDR', 'AGE', 'DATE', 'NOTE', 'OBJE', 'PLAC', 'RELI', 'RESN', 'SOUR'],
        'FILE'           => ['FORM', 'TITL'],
        'FILE-TRAN'      => ['FORM'],
        'FORM'           => ['MEDI'],
        'GRAD'           => ['ADDR', 'AGE', 'AGNC', 'ASSO', 'DATE', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR'],
        'HUSB'           => ['AGE'],
        'IDNO'           => ['AGNC', 'DATE', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR', 'TYPE'],
        'IMMI'           => ['ADDR', 'AGE', 'ASSO', 'DATE', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR'],
        'INDI-CENS'      => ['ADDR', 'AGE', 'ASSO', 'DATE', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR'],
        'INDI-EVEN'      => ['ADDR', 'AGE', 'AGNC', 'ASSO', 'CAUS', 'DATE', 'NOTE', 'OBJE', 'PLAC', 'RELI', 'RESN', 'SOUR', 'TYPE'],
        'INDI-FACT'      => ['DATE', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR', 'TYPE'],
        'INDI-FAMC'      => ['NOTE', 'PEDI', 'STAT'],
        'INDI-NAME'      => ['GIVN', 'NICK', 'NOTE', 'NPFX', 'NSFX', 'SOUR', 'SPFX', 'SURN', 'TRAN', 'TYPE'],
        'INDI-NCHI'      => ['DATE', 'NOTE', 'OBJE', 'RESN', 'SOUR'],
        'INDI-RELI'      => ['ADDR', 'DATE', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR'],
        'INDI-RESI'      => ['ADDR', 'DATE', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR'],
        'INDI-TITL'      => ['ADDR', 'ASSO', 'DATE', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR'],
        'INIL'           => ['DATE', 'NOTE', 'PLAC', 'SOUR', 'STAT', 'TEMP'],
        'MAP'            => ['LATI', 'LONG'],
        'MARB'           => ['ADDR', 'ASSO', 'DATE', 'HUSB', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR', 'WIFE'],
        'MARC'           => ['ADDR', 'ASSO', 'DATE', 'HUSB', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR', 'WIFE'],
        'MARL'           => ['ADDR', 'ASSO', 'DATE', 'HUSB', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR', 'WIFE'],
        'MARR'           => ['ADDR', 'ASSO', 'DATE', 'HUSB', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR', 'TYPE', 'WIFE'],
        'MARS'           => ['ADDR', 'ASSO', 'DATE', 'HUSB', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR', 'WIFE'],
        'MEDI'           => ['PHRASE'],
        'NAME-TRAN'      => ['GIVN', 'LANG', 'NICK', 'NPFX', 'NSFX', 'SPFX', 'SURN'],
        'NAME-TYPE'      => ['PHRASE'],
        'NATI'           => ['ADDR', 'DATE', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR'],
        'NATU'           => ['ADDR', 'AGE', 'ASSO', 'DATE', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR'],
        'NMR'            => ['DATE', 'NOTE', 'OBJE', 'RESN', 'SOUR'],
        'NO'             => ['DATE', 'NOTE', 'SOUR'],
        'NO-DATE'        => ['PHRASE'],
        'OCCU'           => ['AGNC', 'ASSO', 'DATE', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR'],
        'ORDN'           => ['ADDR', 'AGE', 'ASSO', 'DATE', 'NOTE', 'OBJE', 'PLAC', 'RELI', 'RESN', 'SOUR'],
        'PEDI'           => ['PHRASE'],
        'PLAC'           => ['EXID', 'FORM', 'MAP', 'NOTE', 'TRAN'],
        'PLAC-TRAN'      => ['LANG'],
        'PROB'           => ['ADDR', 'AGE', 'ASSO', 'DATE', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR'],
        'PROP'           => ['ADDR', 'ASSO', 'DATE', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR'],
        'REFN'           => ['TYPE'],
        'REPO'           => ['CALN', 'NOTE'],
        'RETI'           => ['ADDR', 'AGE', 'AGNC', 'ASSO', 'DATE', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR'],
        'ROLE'           => ['PHRASE'],
        'SLGC'           => ['DATE', 'NOTE', 'PLAC', 'SOUR', 'STAT', 'TEMP'],
        'SLGS'           => ['DATE', 'NOTE', 'PLAC', 'SOUR', 'STAT', 'TEMP'],
        'SOUR'           => ['DATA', 'EVEN', 'NOTE', 'OBJE', 'PAGE', 'QUAY'],
        'SOUR-DATA'      => ['DATE', 'TEXT'],
        'SOUR-EVEN'      => ['PHRASE', 'ROLE'],
        'SSN'            => ['ADDR', 'DATE', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR'],
        'TEXT'           => ['LANG'],
        'WIFE'           => ['AGE'],
        'WILL'           => ['ADDR', 'ASSO', 'DATE', 'NOTE', 'OBJE', 'PLAC', 'RESN', 'SOUR'],
        'record-FAM'     => ['ANUL', 'ASSO', 'CENS', 'CHAN', 'CHIL', 'DIV', 'DIVF', 'ENGA', 'EVEN', 'EXID', 'HUSB', 'MARB', 'MARC', 'MARL', 'MARR', 'MARS', 'NCHI', 'NO', 'NOTE', 'OBJE', 'REFN', 'RESI', 'RESN', 'SLGS', 'SNOTE', 'SOUR', 'SUBM', 'UID', 'WIFE'],
        'record-INDI'    => ['ADOP', 'ALIA', 'ANCI', 'ASSO', 'BAPL', 'BAPM', 'BARM', 'BASM', 'BIRT', 'BLES', 'BURI', 'CAST', 'CENS', 'CHAN', 'CHR', 'CHRA', 'CONF', 'CONL', 'CREM', 'DEAT', 'DESI', 'DSCR', 'EDUC', 'EMIG', 'ENDL', 'EVEN', 'EXID', 'FACT', 'FAMC', 'FAMS', 'FCOM', 'GRAD', 'IDNO', 'IMMI', 'NAME', 'NATI', 'NATU', 'NCHI', 'NMR', 'NO', 'NOTE', 'OBJE', 'OCCU', 'ORDN', 'PROB', 'PROP', 'REFN', 'RELI', 'RESI', 'RESN', 'RETI', 'SEX', 'SLGC', 'SNOTE', 'SOUR', 'SSN', 'SUBM', 'TITL', 'UID', 'WILL'],
        'record-OBJE'    => ['CHAN', 'EXID', 'FILE', 'NOTE', 'REFN', 'RESN', 'SNOTE', 'SOUR'],
        'record-REPO'    => ['ADDR', 'CHAN', 'EMAIL', 'EXID', 'NAME', 'NOTE', 'PHON', 'REFN', 'SNOTE', 'WWW'],
        'record-SNOTE'   => ['CHAN', 'REFN', 'SOUR'],
        'record-SOUR'    => ['ABBR', 'AUTH', 'CHAN', 'DATA', 'EXID', 'NOTE', 'OBJE', 'PUBL', 'REFN', 'REPO', 'SNOTE', 'TEXT', 'TITL'],
        'record-SUBM'    => ['ADDR', 'CHAN', 'EMAIL', 'EXID', 'LANG', 'NAME', 'NOTE', 'PHON', 'SNOTE', 'WWW'],
    ];

    /** @var array<string,array{0:string|null,1:bool}> The structure type of each path, and whether it occurs above */
    private static array $types = [];

    /**
     * The structure type of a path, e.g. "INDI:BIRT:DATE" is "DATE".
     *
     * @param bool $repeat Also for a structure type that occurs above, e.g. "INDI:BIRT:SOUR:NOTE:SOUR"
     */
    public static function type(string $path, bool $repeat = true): string|null
    {
        if (!array_key_exists($path, self::$types)) {
            $tags     = explode(':', $path);
            $type     = Gedcom7Schema::RECORDS[$tags[0]] ?? null;
            $types    = [$type];
            $repeated = false;

            foreach (array_slice($tags, 1) as $tag) {
                $type = Gedcom7Schema::SUBSTRUCTURES[$type ?? ''][$tag][0] ?? null;

                if ($type === null) {
                    break;
                }

                $repeated = in_array($type, $types, true);
                $types[]  = $type;
            }

            self::$types[$path] = [$type, $repeated];
        }

        [$type, $repeated] = self::$types[$path];

        return $repeat || !$repeated ? $type : null;
    }

    /**
     * The paths of the schema that match a path with wildcards, e.g. "INDI:*:ADDR".
     *
     * @return list<string>
     */
    private static function expand(string $path): array
    {
        if (!str_contains($path, '*')) {
            return [$path];
        }

        $paths = [''];

        foreach (explode(':', $path) as $tag) {
            $next = [];

            foreach ($paths as $parent) {
                if ($tag !== '*') {
                    $next[] = $parent === '' ? $tag : $parent . ':' . $tag;
                } elseif ($parent !== '') {
                    foreach (array_keys(Gedcom7Schema::SUBSTRUCTURES[self::type($parent) ?? ''] ?? []) as $subtag) {
                        $next[] = $parent . ':' . $subtag;
                    }
                }
            }

            $paths = $next;
        }

        return $paths;
    }

    /**
     * The tags of GEDCOM 7.0 and extensions, without those of GEDCOM 5.5.1, e.g. for the tags of other applications.
     *
     * @param array<string,ElementInterface> $elements
     *
     * @return array<string,ElementInterface>
     */
    public static function gedcom7Tags(array $elements): array
    {
        $tags = [];

        foreach ($elements as $path => $element) {
            if (preg_match('/(?:^|:)_/', $path) === 1) {
                $tags[$path] = $element;
                continue;
            }

            foreach (self::expand($path) as $concrete) {
                if (self::type($concrete) !== null) {
                    $tags[$path] = $element;
                    break;
                }
            }
        }

        return $tags;
    }

    /**
     * Give each element the substructures of its structure type.  Run after all the other tags.
     *
     * @param array<string,ElementInterface> $elements
     *
     * @return array<string,ElementInterface>
     */
    public static function schemaSubTags(array $elements): array
    {
        // The paths of the schema that each wildcard defines, as ElementFactory::make() finds them
        $wildcards = [];
        $found     = [];

        foreach ($elements as $path => $element) {
            if (str_contains($path, '*')) {
                foreach (self::expand($path) as $concrete) {
                    if (!isset($elements[$concrete]) && !isset($found[$concrete])) {
                        $found[$concrete]   = true;
                        $wildcards[$path][] = $concrete;
                    }
                }
            }
        }

        // Elements can be shared, e.g. NOTE:SOUR and SNOTE:SOUR
        $done      = [];
        $originals = [];

        foreach ($elements as $path => $element) {
            // The header is written by webtrees.
            if ($path === 'HEAD' || str_starts_with($path, 'HEAD:')) {
                continue;
            }

            $paths = [];

            foreach (str_contains($path, '*') ? $wildcards[$path] ?? [] : [$path] as $concrete) {
                $type = self::type($concrete);

                if ($type !== null) {
                    $paths[$type][] = $concrete;
                }
            }

            // e.g. a wildcard for the dates of events that also matches the date of a non-event
            uasort($paths, static fn (array $x, array $y): int => count($y) <=> count($x));

            $id               = spl_object_id($element);
            $originals[$id] ??= clone $element;

            foreach ($paths as $type => $concretes) {
                if ($type !== array_key_first($paths)) {
                    foreach ($concretes as $concrete) {
                        $elements[$concrete] = clone $originals[$id];
                        self::subTags($elements[$concrete], $type, !str_contains($concrete, ':'));
                    }
                } elseif (($done[$id] ?? $type) !== $type) {
                    $elements[$path] = clone $originals[$id];
                    self::subTags($elements[$path], $type, !str_contains($path, ':'));
                } elseif (!isset($done[$id])) {
                    $done[$id] = $type;
                    self::subTags($element, $type, !str_contains($path, ':'));
                }
            }
        }

        return $elements;
    }

    /**
     * The substructures of the schema, in the order of webtrees.
     */
    private static function subTags(ElementInterface $element, string $type, bool $record): void
    {
        $permitted = Gedcom7Schema::SUBSTRUCTURES[$type] ?? [];

        foreach ($element->subtags() as $subtag => $repeat) {
            if (!isset($permitted[$subtag]) && !str_starts_with($subtag, '_')) {
                $element->subtag($subtag, '0:0');
            }
        }

        foreach ($permitted as $subtag => [, $cardinality]) {
            // e.g. CREA is written by webtrees
            if ($record && in_array($subtag, self::NOT_OFFERED, true) && !isset($element->subtags()[$subtag])) {
                continue;
            }

            $element->subtag($subtag, $cardinality . (self::shown($type, $subtag) ? '' : ':?'));
        }
    }

    private static function shown(string $type, string $subtag): bool
    {
        return in_array($subtag, self::SHOWN[$type] ?? [], true);
    }

    /**
     * An element for a path that webtrees does not define, e.g. "INDI:BIRT:SOUR:NOTE:LANG".
     *
     * @param bool $schema With the substructures of GEDCOM 7.0
     */
    public static function element(string $type, bool $schema = true): ElementInterface|null
    {
        $element = match ($type) {
            'ABBR'           => new SourceFiledByEntry(I18N::translate('Abbreviation')),
            'ADDR'           => new AddressLine(I18N::translate('Address')),
            'ADOP'           => new Adoption(I18N::translate('Adoption')),
            'ADOP-FAMC'      => new XrefFamily(I18N::translate('Adoptive parents')),
            'ADR1'           => new AddressLine1(I18N::translate('Address line 1')),
            'ADR2'           => new AddressLine2(I18N::translate('Address line 2')),
            'ADR3'           => new AddressLine3(I18N::translate('Address line 3')),
            'AGE'            => new AgeAtEvent(I18N::translate('Age')),
            'AGNC'           => new ResponsibleAgency(I18N::translate('Agency')),
            'ALIA'           => new XrefIndividual(I18N::translate('Alias')),
            'ANCI'           => new XrefSubmitter(I18N::translate('Ancestors interest')),
            'ANUL'           => new Annulment(I18N::translate('Annulment')),
            'ASSO'           => new AssociationStructure(I18N::translate('Associate')),
            'AUTH'           => new SourceOriginator(I18N::translate('Author')),
            'BAPL'           => new LdsBaptism(I18N::translate('LDS baptism')),
            'BAPM'           => new Baptism(I18N::translate('Baptism')),
            'BARM'           => new BarMitzvah(I18N::translate('Bar mitzvah')),
            'BASM'           => new BasMitzvah(I18N::translate('Bat mitzvah')),
            'BIRT'           => new Birth(I18N::translate('Birth')),
            'BLES'           => new Blessing(I18N::translate('Blessing')),
            'BURI'           => new Burial(I18N::translate('Burial')),
            'CALN'           => new SourceCallNumber(I18N::translate('Call number')),
            'CAST'           => new CasteName(I18N::translate('Caste')),
            'CAUS'           => new CauseOfEvent(I18N::translate('Cause')),
            'CHAN'           => new Change(I18N::translate('Last change')),
            'CHIL'           => new XrefIndividual(I18N::translate('Child')),
            'CHR'            => new Christening(I18N::translate('Christening')),
            'CHRA'           => new AdultChristening(I18N::translate('Adult christening')),
            'CITY'           => new AddressCity(I18N::translate('City')),
            'CONF'           => new Confirmation(I18N::translate('Confirmation')),
            'CONL'           => new LdsConfirmation(I18N::translate('LDS confirmation')),
            'COPR'           => new CopyrightFile(I18N::translate('Copyright')),
            'CORP'           => new NameOfBusiness(I18N::translate('Corporation')),
            'CREA'           => new Creation(I18N::translate('Created at')),
            'CREM'           => new Cremation(I18N::translate('Cremation')),
            'CROP'           => new EmptyElement(I18N::translate('Crop')),
            'CTRY'           => new AddressCountry(I18N::translate('Country')),
            'DATA'           => new EmptyElement(I18N::translate('Data')),
            'DATA-EVEN'      => new EventTypeList(I18N::translate('Events')),
            'DATA-EVEN-DATE' => new DateValue(I18N::translate('Date range')),
            'DATE'           => new DateValue(I18N::translate('Date')),
            'DATE-exact'     => new ChangeDate(I18N::translate('Date')),
            'DEAT'           => new Death(I18N::translate('Death')),
            'DESI'           => new XrefSubmitter(I18N::translate('Descendants interest')),
            'DEST'           => new ReceivingSystemName(I18N::translate('Destination')),
            'DIV'            => new Divorce(I18N::translate('Divorce')),
            'DIVF'           => new DivorceFiled(I18N::translate('Divorce filed')),
            'DSCR'           => new PhysicalDescription(I18N::translate('Description')),
            'EDUC'           => new ScholasticAchievement(I18N::translate('Education')),
            'EMAIL'          => new AddressEmail(I18N::translate('Email address')),
            'EMIG'           => new Emigration(I18N::translate('Emigration')),
            'ENDL'           => new LdsEndowment(I18N::translate('LDS endowment')),
            'ENGA'           => new Engagement(I18N::translate('Engagement')),
            'EXID'           => new ExternalIdentifier(I18N::translate('External identifier')),
            'EXID-TYPE'      => new ExternalIdentifierType(I18N::translate('Type')),
            'FAM-CENS'       => new FamilyCensus(I18N::translate('Family census')),
            'FAM-EVEN'       => new FamilyEvent(I18N::translate('Event')),
            'FAM-FACT'       => new FamilyFact(I18N::translate('Fact')),
            'FAM-HUSB'       => new XrefIndividual(I18N::translate('Husband')),
            'FAM-NCHI'       => new CountOfChildrenFam(I18N::translate('Number of children')),
            'FAM-RESI'       => new Residence(I18N::translate('Family residence')),
            'FAM-WIFE'       => new XrefIndividual(I18N::translate('Wife')),
            'FAMC'           => new XrefFamily(I18N::translate('Family')),
            'FAMC-ADOP'      => new AdoptedByWhichParent(I18N::translate('Adoption')),
            'FAMC-STAT'      => new ChildLinkageStatus(I18N::translate('Status')),
            'FAMS'           => new XrefFamily(I18N::translate('Family as a spouse')),
            'FAX'            => new AddressFax(I18N::translate('Fax')),
            'FCOM'           => new FirstCommunion(I18N::translate('First communion')),
            'FILE'           => new MultimediaFileReference(I18N::translate('Filename')),
            'FILE-TRAN'      => new MultimediaFileReference(I18N::translate('Translation')),
            'FORM'           => new MediaType(I18N::translate('Format')),
            'GEDC'           => new GedcomElement(I18N::translate('GEDCOM')),
            'GEDC-VERS'      => new VersionNumber(I18N::translate('Version')),
            'GIVN'           => new NamePieceGiven(I18N::translate('Given names')),
            'GRAD'           => new Graduation(I18N::translate('Graduation')),
            'HEAD'           => new HeaderRecord(I18N::translate('Header')),
            'HEAD-DATE'      => new TransmissionDate(I18N::translate('Date')),
            'HEAD-LANG'      => new LanguageTag(I18N::translate('Language')),
            'HEAD-PLAC'      => new EmptyElement(I18N::translate('Place hierarchy')),
            'HEAD-PLAC-FORM' => new PlaceHierarchy(I18N::translate('Format')),
            'HEAD-SOUR'      => new ApprovedSystemId(I18N::translate('Application ID')),
            'HEAD-SOUR-DATA' => new NameOfSourceData(I18N::translate('Data')),
            'HEIGHT'         => new CustomElement(I18N::translate('Height')),
            'HUSB'           => new EmptyElement(I18N::translate('Husband')),
            'IDNO'           => new NationalIdNumber(I18N::translate('Identification number')),
            'IMMI'           => new Immigration(I18N::translate('Immigration')),
            'INDI-CENS'      => new Census(I18N::translate('Census')),
            'INDI-EVEN'      => new IndividualEvent(I18N::translate('Event')),
            'INDI-FACT'      => new IndividualFact(I18N::translate('Fact')),
            'INDI-FAMC'      => new ChildFamilyLink(I18N::translate('Family as a child')),
            'INDI-NAME'      => new NamePersonal(I18N::translate('Name')),
            'INDI-NCHI'      => new CountOfChildren(I18N::translate('Number of children')),
            'INDI-RELI'      => new ReligiousAffiliation(I18N::translate('Religion')),
            'INDI-RESI'      => new Residence(I18N::translate('Residence')),
            'INDI-TITL'      => new NobilityTypeTitle(I18N::translate('Title')),
            'INIL'           => new LdsInitiatory(I18N::translate('LDS initiatory')),
            'LANG'           => new LanguageTag(I18N::translate('Language')),
            'LATI'           => new PlaceLatitude(I18N::translate('Latitude')),
            'LEFT'           => new CustomElement(I18N::translate('Left')),
            'LONG'           => new PlaceLongtitude(I18N::translate('Longitude')),
            'MAP'            => new Coordinates(I18N::translate('Coordinates')),
            'MARB'           => new MarriageBanns(I18N::translate('Marriage banns')),
            'MARC'           => new MarriageContract(I18N::translate('Marriage contract')),
            'MARL'           => new MarriageLicence(I18N::translate('Marriage license')),
            'MARR'           => new Marriage(I18N::translate('Marriage')),
            'MARS'           => new MarriageSettlement(I18N::translate('Marriage settlement')),
            'MEDI'           => new SourceMediaTypeEnumeration(I18N::translate('Media type')),
            'MIME'           => new TextMediaType(I18N::translate('Media type')),
            'NAME'           => new NameOfProduct(I18N::translate('Name')),
            'NAME-TRAN'      => new NameTranslation(I18N::translate('Translation')),
            'NAME-TYPE'      => new NameTypeEnumeration(I18N::translate('Type of name')),
            'NATI'           => new NationalOrTribalOrigin(I18N::translate('Nationality')),
            'NATU'           => new Naturalization(I18N::translate('Naturalization')),
            'NICK'           => new NamePieceNickname(I18N::translate('Nickname')),
            'NMR'            => new CountOfMarriages(I18N::translate('Number of marriages')),
            'NO'             => new FamilyNonEvent(I18N::translate('Event did not occur')),
            'NO-DATE'        => new DatePeriod(I18N::translate('Date')),
            'NOTE'           => new NoteOrSharedNote(I18N::translate('Note')),
            'NOTE-TRAN'      => new CustomElement(I18N::translate('Translation')),
            'NPFX'           => new NamePiecePrefix(I18N::translate('Name prefix')),
            'NSFX'           => new NamePieceSuffix(I18N::translate('Name suffix')),
            'OBJE'           => new XrefMedia(I18N::translate('Media object')),
            'OCCU'           => new Occupation(I18N::translate('Occupation')),
            'ORDN'           => new Ordination(I18N::translate('Ordination')),
            'PAGE'           => new WhereWithinSource(I18N::translate('Citation details')),
            'PEDI'           => new PedigreeLinkageTypeEnumeration(I18N::translate('Relationship to parents')),
            'PHON'           => new PhoneNumber(I18N::translate('Phone')),
            'PHRASE'         => new CustomElement(I18N::translate('Phrase')),
            'PLAC'           => new PlaceName(I18N::translate('Place')),
            'PLAC-FORM'      => new PlaceHierarchy(I18N::translate('Format')),
            'PLAC-TRAN'      => new PlaceTranslation(I18N::translate('Translation')),
            'POST'           => new AddressPostalCode(I18N::translate('Postal code')),
            'PROB'           => new Probate(I18N::translate('Probate')),
            'PROP'           => new Possessions(I18N::translate('Property')),
            'PUBL'           => new SourcePublicationFacts(I18N::translate('Publication')),
            'QUAY'           => new CertaintyAssessment(I18N::translate('Quality of data')),
            'REFN'           => new UserReferenceNumber(I18N::translate('Reference number')),
            'RELI'           => new ReligiousAffiliation(I18N::translate('Religion')),
            'REPO'           => new XrefRepository(I18N::translate('Repository')),
            'RESN'           => new RestrictionNotice(I18N::translate('Restriction')),
            'RETI'           => new Retirement(I18N::translate('Retirement')),
            'ROLE'           => new AssociationRole(I18N::translate('Role')),
            'SCHMA'          => new EmptyElement(I18N::translate('Custom GEDCOM tags')),
            'SDATE'          => new CustomElement(I18N::translate('Sort date')),
            'SEX'            => new SexXValue(I18N::translate('Sex')),
            'SLGC'           => new LdsChildSealing(I18N::translate('LDS child sealing')),
            'SLGS'           => new LdsSpouseSealing(I18N::translate('LDS spouse sealing')),
            'SNOTE'          => new XrefSharedNote(I18N::translate('Shared note')),
            'SOUR'           => new XrefSource(I18N::translate('Source citation')),
            'SOUR-DATA'      => new SourceData(I18N::translate('Data')),
            'SOUR-EVEN'      => new EventTypeCitedFrom(I18N::translate('Event')),
            'SPFX'           => new NamePieceSurnamePrefix(I18N::translate('Surname prefix')),
            'SSN'            => new SocialSecurityNumber(I18N::translate('Social security number')),
            'STAE'           => new AddressState(I18N::translate('State')),
            'SUBM'           => new XrefSubmitter(I18N::translate('Submitter')),
            'SUBM-LANG'      => new LanguageTag(I18N::translate('Language')),
            'SURN'           => new NamePieceSurname(I18N::translate('Surname')),
            'TAG'            => new CustomElement(I18N::translate('GEDCOM tag')),
            'TEMP'           => new TempleCode(I18N::translate('Temple')),
            'TEXT'           => new TextFromSource(I18N::translate('Text')),
            'TIME'           => new TimeValue(I18N::translate('Time')),
            'TITL'           => new DescriptiveTitle(I18N::translate('Title')),
            'TOP'            => new CustomElement(I18N::translate('Top')),
            'TYPE'           => new EventOrFactClassification(I18N::translate('Type')),
            'UID'            => new UniqueIdentifier(I18N::translate('Unique identifier')),
            'VERS'           => new VersionNumber(I18N::translate('Version')),
            'WIDTH'          => new CustomElement(I18N::translate('Width')),
            'WIFE'           => new EmptyElement(I18N::translate('Wife')),
            'WILL'           => new Will(I18N::translate('Will')),
            'WWW'            => new AddressWebPage(I18N::translate('URL')),
            'ord-STAT'       => new LdsOrdinanceStatus(I18N::translate('Status')),
            'record-FAM'     => new FamilyRecord(I18N::translate('Family')),
            'record-INDI'    => new IndividualRecord(I18N::translate('Individual')),
            'record-OBJE'    => new MediaRecord(I18N::translate('Media object')),
            'record-REPO'    => new RepositoryRecord(I18N::translate('Repository')),
            'record-SNOTE'   => new NoteRecord(I18N::translate('Shared note')),
            'record-SOUR'    => new SourceRecord(I18N::translate('Source')),
            'record-SUBM'    => new SubmitterRecord(I18N::translate('Submitter')),
            default => null,
        };

        if ($element !== null && $schema) {
            self::subTags($element, $type, str_starts_with($type, 'record-'));
        }

        return $element;
    }
}
