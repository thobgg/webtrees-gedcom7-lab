# Extension tags of webtrees7

GEDCOM 7.0 asks that every extension tag in a file is documented with a URI in the header (`HEAD:SCHMA`).
This page is that documentation for the tags that webtrees7 writes. The export of a tree lists, for each of these
tags that the tree uses, a `2 TAG` line with the URI of the section below, unless the header of the tree documents
the tag differently. The page “Extension tags” in the control panel shows and edits these URIs.

Most of the tags come from [GEDCOM-L](https://genealogy.net/GEDCOM/), the group of German-speaking developers of
genealogy applications, whose addendum to GEDCOM 5.5.1 (revision 2, 2020-12-01) defines shared location records.
webtrees7 treats these structures like those of the standard: see `app/Gedcom7/GedcomLSchema.php`.

Where the addendum uses the structures of GEDCOM 5.5.1, webtrees7 uses the structures of GEDCOM 7.0 inside the
extension: dates are `DATE` with `PHRASE`, languages are BCP 47 tags, sources, notes, media and coordinates are the
standard structures, the GOV identifier is an `EXID` with the registered type `https://gov.genealogy.net/`, and the
unique identifier is a `UID`.

## _LOC

A shared location record, or a link to one.

```
0 @L1@ _LOC
1 NAME <Text>                       {1:M}  the name, with its ABBR, DATE, LANG and SOUR
1 TYPE <Text>                       {0:M}  the type of location, with _GOVTYPE, DATE and SOUR
1 _POST <Text>                      {0:M}  see _POST
1 EXID <Text> + TYPE <URI>          {0:M}  e.g. the GOV identifier
1 MAP                               {0:1}  LATI and LONG
1 _MAIDENHEAD <Text>                {0:1}  see _MAIDENHEAD
1 RELI <Text>                       {0:1}
1 EVEN <Text>                       {0:M}  with the event details of GEDCOM 7.0
1 _LOC @<XREF>@                     {0:M}  the parent location, with TYPE POLI, RELI, GEOG or CULT, DATE and SOUR
1 _DMGD <Text>                      {0:M}  see _DMGD
1 _AIDN <Text>                      {0:M}  see _AIDN
1 OBJE, NOTE, SNOTE, SOUR, RESN, UID, CHAN, CREA
```

As a substructure of `PLAC`, `_LOC` is a pointer to the location record of the place:

```
2 PLAC Hof Nr. 1, Bienenbüttel, Uelzen, Niedersachsen, Deutschland
3 _LOC @L2@
```

## _POST

A postal code, as a substructure of `_LOC` or `PLAC`, with an optional `DATE` (the period of validity) and `SOUR`.

## _MAIDENHEAD

A [Maidenhead locator](https://en.wikipedia.org/wiki/Maidenhead_Locator_System), e.g. `JO53fd`, as a substructure of
`_LOC` or `PLAC`.

## _GOVTYPE

The GOV type of a location (`_LOC:TYPE:_GOVTYPE`), a number from the type list of the
[Geschichtliches Ortsverzeichnis](https://wiki.genealogy.net/GOV/Webservice), e.g. `51` for a town.

## _AIDN

An administrative identifier of a location (`_LOC:_AIDN`), e.g. a municipality key, with a required `TYPE`, and an
optional `DATE` and `SOUR`.

## _DMGD

Demographic data of a location (`_LOC:_DMGD`), e.g. the number of inhabitants, with a required `TYPE`, and an optional
`DATE` and `SOUR`.

## _GOV

The GEDCOM-L form of the GOV identifier. webtrees7 writes the GOV identifier as `EXID` with the type
`https://gov.genealogy.net/`, and converts `_GOV` to it on import. `_GOV` remains in records that were imported before
this conversion existed.

## _UID

The GEDCOM-L form of the unique identifier. webtrees7 writes `UID`, and converts `_UID` to it on import. `_UID` remains
in records that were imported before this conversion existed.

## _ASSO

An associate of an LDS ordinance. GEDCOM 7.0 permits `ASSO` on events, but not on ordinances; webtrees keeps such an
associate as `_ASSO`, with the substructures of `ASSO`.

## _WT_USER

The user who made the last change (`CHAN:_WT_USER`), as webtrees has always written it.

## Other tags beginning with an underscore

A structure of GEDCOM 5.5.1 that GEDCOM 7.0 does not permit at its place is kept with an underscore on import, e.g. a
second `DATE` of an event becomes `_DATE`, and `SUBN` becomes `_SUBN`. These tags keep the meaning of the GEDCOM 5.5.1
tag without the underscore. They are not documented in the header, because they come from the imported file, not from
webtrees7.
