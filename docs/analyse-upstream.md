# webtrees7 – Analyse der GEDCOM-7.0-Portierung gegenüber fisharebest/webtrees

Stand 22.10.2026, Branch `gedcom7-native` (HEAD 81e7d214c4), Merge-Basis mit `upstream/main`: 22665bb681 (01.10.2026).
Upstream ist seitdem 3 Commits weiter (Abhängigkeiten, Test-Fix), keine Berührung mit den hier geänderten Dateien.

Umfang: 152 Commits, 339 Dateien, +51 113/−998 Zeilen. Davon 27 405 Zeilen Testdaten (`tests/data/*-gedcom7.ged`),
2 230 Zeilen Prüfwerkzeug (`docker/check-gedcom7`), 1 345 Zeilen Doku. Programmcode (`app`, `resources/views|js|xml`):
232 Dateien, +13 194/−930. Neu: `app/Gedcom7/` (37 Dateien, 8 360 Zeilen, davon 1 879 Schema und 1 146 Element-Tabelle),
20 neue Element-Klassen, 29 Feature-Tests, 10 Unit-Tests. Gelöscht: `app/CustomTags/Gedcom7.php`.

Vorbemerkung zur Fragestellung: Ein `ModuleCustomTagsInterface` gibt es in webtrees nicht (weder 2.2 noch 2.3-dev).
Der Modulweg für Kennzeichen ist `ModuleCustomInterface::boot()` mit `Registry::elementFactory()->registerTags()` /
`registerSubTags()`; so machen es `IndividualFactsTabModule`, `ResearchTaskModule` und Vesta. Die Middleware-Reihenfolge
ist `RegisterGedcomTags` → `BootModules`, ein Modul überschreibt also Kern-Definitionen je Pfad. Für Import, Export,
Speichern und Datensatz-Fabriken gibt es keine Hooks; `Registry` erlaubt zwar den Austausch von Fabriken, aber nicht
die Änderung ihrer Interfaces.

## 1. Vollständigkeit

Legende: ✓ umgesetzt, ◐ teilweise, ✗ fehlt. Geprüft am Code, nicht am laufenden System.

| Struktur | Speichern | Anzeige | Bearbeiten | Import 7.0 | Export 7.0 / 5.5.1 | Prüfung | Lücken |
|---|---|---|---|---|---|---|---|
| `@VOID@` | ✓ | ◐ | ✓ | ✓ | ✓ / ◐ | ✓ | Anzeige nur für `ASSO`, `CHIL`, `SNOTE`-Verweise und in der Strukturansicht. `HUSB`/`WIFE @VOID@` + `PHRASE` werden auf der Familienseite nicht gezeigt. 5.5.1-Export: `ASSO @VOID@` mit Rolle `GODP`/`WITN` wird `_GODP`/`_WITN`, alle anderen Zeiger auf `@VOID@` werden `_TAG` ohne Wert (Unterzeilen bleiben). |
| `SNOTE` | ✓ | ✓ | ✓ | ✓ | ✓ / ✓ | ✓ | Eigener Datensatztyp `SharedNote` (`o_type = SNOTE`), Listen, Suche, Sitemap, Umnummerieren, Datafixes, Berichte-XML, „Verwendet bei“. Im Formular wird `SNOTE` an Fakten nur mit „alle Kennzeichen“ angeboten (Entscheidung; das Notizfeld wählt geteilte Notizen). |
| `PHRASE` | ✓ | ✓ | ✓ | ✓ | ✓ / ◐ | ✓ | An `DATE`, `AGE`, `ROLE`, Aufzählungen (`OTHER`), Zeigern, `MEDI`, `SOUR:EVEN`, `ALIA`. Getippte 5.5.1-Formen (`INT … (…)`, `CHILD`) wandern in die Phrase. 5.5.1-Export: nur `INT … (…)` wo erlaubt, sonst `_PHRASE`. |
| `ROLE` | ✓ | ✓ | ✓ | ✓ | ✓ / ✓ | ✓ | `AssociationRole` mit 7.0-Rollen, webtrees-Beziehungen als Vorschläge für die Phrase; Rückweg zu `RELA`. `ASSO` unter LDS-Verordnungen erlaubt 7.0 nicht → bleibt `_ASSO` + `RELA`, und `fact-association-structure.phtml` liest weiter beide Formen. |
| `NO` | ✓ | ✓ | ✓ | ✓ | ✓ / ✓ (`_NO`) | ✓ | `NO` mit `DatePeriod` + `PHRASE` + `SOUR`, Liste „Nachweisstand“, kein Alter in der Anzeige. **Nicht ausgewertet** in der Logik: `isDead()`, Statistiken, Familienstatus, Listenfilter, Datenschutz kennen `NO` nicht. |
| `TRAN` | ✓ | ✓ | ◐ | ✓ | ✓ / ✓ | ✓ | Namen (Primärname in Betrachtersprache, Original als Zweitname), Orte (mit Sprache), Notizen/`TEXT` (Übersetzung zuerst). `FILE:TRAN` wird gelesen und beim Bearbeiten erhalten (`otherSubstructures`), hat aber keinen Editor. 5.5.1: `TRAN` an `NAME`/`PLAC` → `ROMN`/`FONE`, sonst `_TRAN`. Alte Leser (`ROMN`/`FONE`/`_HEB`) in `GedcomRecord` bleiben. |
| `EXID` | ✓ | ✓ | ✓ | ✓ | ✓ / ✓ | ✓ | 14 Typen aus GEDCOM-registries benannt, Links für FamilySearch, Find a Grave, WikiTree, GOV; `PLAC:EXID`; `i_rin` aus `EXID` RIN; erweiterte Suche `INDI:EXID`. Lücke: GOV an `_LOC`-Datensätzen bleibt `_LOC:_GOV` (Erweiterungsdatensätze werden nicht umgebaut), am Ort steht GOV als `EXID` – zwei Formen nebeneinander. |
| `UID` | ✓ | ✓ | ✓ | ✓ | ✓ / ✓ | ✓ | `UniqueIdentifier` lässt vorhandene Werte unverändert (Upstream-`Uid` ändert sie); `GENERATE_UIDS` schreibt `1 UID` als UUID; `_UID` → `UID`. **Nicht genutzt** für Abgleich/Zusammenführen/Duplikatsuche. |
| `SDATE` | ✓ | ✓ (verborgen) | ◐ | ✓ | ✓ / `_SDATE` | ✓ | `Fact::sortDate()`, `FactSortService`, `FactComparator`. Nur mit „alle Kennzeichen“ editierbar. **Nicht im `dates`-Index**: Kalender, Jahrestage, Listen, Statistiken sortieren weiter nach `DATE`; ein Fakt nur mit Phrase + `SDATE` fehlt dort. |
| `CROP` | ✓ | ✓ | ✗ | ✓ | ✓ / `_CROP` | ✓ | Zuschnitt in signierter Vorschau-URL, `ImageFactory::cropImage()`, Personenseite, Diagrammbild, Medien-Fakten. Kein Editor (nur Rohwerte mit „alle Kennzeichen“), kein Zuschnitt im Betrachter/Lightbox. |
| `MIME` | ✓ | ◐ | ✓ | ✓ | ✓ / `_MIME` | ✓ | Auswahl `text/plain`/`text/html` an `NOTE`/`TEXT` (`TextMediaType`). Eine Darstellung von `text/html`-Notizen als HTML habe ich im Diff nicht gefunden; Notizen werden weiter nach der Baumeinstellung (Markdown/Text) gerendert. |
| `SCHMA` / Erweiterungen | ✓ | ✓ | ✗ | ✓ | ◐ / – | ✓ | `HEAD:SCHMA` aus importierten Dateien bleibt und wird exportiert; Seite „Erweiterungen“ je Baum (`_TAGS`, Anzahl, Beispielpfad, URI); CheckTree meldet Datensätze, die kein 7.0 sind. **Fehlt:** eigene `SCHMA`-Einträge für webtrees (`_WT_USER`, `_RESN`, `_NONE`, `_ASSO`, `_FORM`, `_X7_`-XREFs) und GEDCOM-L (`_LOC`, `_GOV`, …) – bewusst, weil keine belegten URIs; `SCHMA` nicht editierbar; ein neu angelegter Baum hat kein `SCHMA`. |
| GEDZIP | – | – | – | ✓ | ✓ | ✓ | Upload, Server-Datei, CLI; `gedcom.ged` plus Medien, `..`/absolute Pfade übergangen, vorhandene Dateien bleiben; `.gdz` in der Dateiliste des Datenordners. Kein Hinweis, wenn Medien im Archiv fehlen. |
| Export-Header | | | | | ✓ | | `BOM`, `1 GEDC`/`2 VERS 7.0`, `1 SOUR webtrees` (`NAME`, `VERS`), `DATE`/`TIME`, aus dem gespeicherten HEAD: `COPR`, `LANG`, `PLAC`, `NOTE`, `SCHMA`, `SUBM`; kein `CHAR`/`FILE`/`DEST`/`FORM`/`SUBN`; keine `CONC`-Umbrüche; jeder Datensatz läuft durch den Wandler (Zeiger auf nicht exportierte Datensätze → `@VOID@`, `@@`-Escapes). Lücke: kein `HEAD:LANG`, wenn der Baum keines hat (Übersetzungen ohne `LANG` verlieren dann ihre Bezugssprache). |

Weitere Punkte:

- **Import 7.0:** Header erkennt `GEDC:VERS 7.x`, `reformatRecord()` wird umgangen, `@@` entschärft, XREFs über 20 Zeichen gekürzt (`_X7_`-Checksumme), Datensätze ohne XREF bekommen einen. Der Datensatz wird dabei durch den Wandler normalisiert. Rundreise Export→Import ist laut Prüfskript identisch (nicht von mir nachgestellt).
- **Validierung:** drei Stellen mit derselben Basis (`Gedcom7Problems`): beim Tippen (`CheckGedcom7`), beim Speichern (Fakt, Datensatz, neue Person/Familie, „Trotzdem speichern“), in CheckTree. Geprüft werden Vorkommen und Pflichtfelder gegen das Schema; **Werte** nur indirekt (der Wandler verwirft die Struktur → „Wert fehlt oder ungültig“), ohne Angabe des Grunds. `LANG` akzeptiert die bekannte Liste plus ein BCP-47-Muster. Unabhängiger Validator gegen `g7validation.json` nur im Docker-Prüfskript, nicht im Produkt.
- **Datum:** `CalendarEscape` trägt die 7.0-Namen; Parser nimmt beide Schreibweisen; `dates.d_type` enthält `JULIAN`, `_HIJRI` … statt `@#DJULIAN@`.
- **Medien:** `FILE` als URL (percent-kodiert), `FORM` als Medientyp, `MEDI` + `PHRASE`; `media_file.multimedia_format` auf 255 Zeichen erweitert (Migration11), Werte wie `image/jpeg`.
- **Einschränkungen:** `RESN none` → `_NONE`; an `SOUR`, `REPO`, `SUBM`, `SNOTE` als `_RESN` (7.0 kennt dort kein `RESN`), Datenschutz liest beides.
- **Noch 5.5.1-Erbe (laut eigener Doku und Code):** Faktenzeilen-Formulare auf Gregs Element-System (kein Struktur-Editor, Roh-GEDCOM bleibt); `QUAY` nur angezeigt; PDF-Berichte nicht systematisch geprüft; Upstream-Normalisierungen bleiben (`MARR:TYPE civil` → `CIVIL`, `MARR Y` mit Datum verliert `Y`, `NAME //` leer); `default_resn` und `unique_facts` nennen noch `AFN`/`RFN`/`RIN`; der alte Zensus-Assistent besteht neben dem Quellen-Assistenten.
- **Erprobung:** Falkenrath, maximal70, 21 Testdateien von gedcom.io; keine großen Bäume anderer Programme.

## 2. Core-Änderungen gegenüber Upstream, nach Datei

Basis: `git diff 22665bb681 HEAD`. Einordnung: **a) nur im Core** (kein Hook, geschlossene Klassen, Interface-Änderung, Views) oder
**b) als Custom Module möglich** (`boot()` + `registerTags`/`registerSubTags`, `ModuleDataFixInterface`, eigene Routen/Views,
`ModuleListInterface`, `ModuleThemeInterface`, eigene Migrationen über Moduleinstellungen).

### Speicherung, Import, Export – a) nur Core

| Datei | Änderung |
|---|---|
| `app/Services/GedcomImportService.php` (+35/−10) | 7.0-Datei: kein `reformatRecord()`, `@@` entschärfen, Normalisierung; akzeptierte Änderungen durch `Gedcom7Storage::edit()`; `UID` statt `_UID`; `i_rin` aus `EXID`; Medientyp bis 255 Zeichen; `@VOID@` nicht in `link`; am `TRLR` einer 5.5.1-Datei Baum umwandeln. Begründung a: `importRecord()` ist der einzige Weg in die Tabellen, kein Hook. |
| `app/Services/GedcomExportService.php` (+102/−15) | Parameter `GedcomVersion`, 7.0-Header, BOM, Wandler je Datensatz, erzeugte Datensätze vor `TRLR`, 5.5.1-Downgrade für 7.0-Bäume, kein `wrapLongLines` bei 7.0, `FILE`-URL beim Zippen. a: Export-Schleife und Header sind privat/kernintern; Signaturen nur additiv erweitert. |
| `app/Services/TreeService.php` (+68/−3) | Neuer Baum mit `GEDC:VERS 7.0`; `importGedzipFile()`/`importGedzipMedia()`. a (Baumanlage); GEDZIP-Import wäre b (eigene Route). |
| `app/Services/AdminService.php` (+8) | `.gdz` in der Dateiliste des Datenordners. a. |
| `app/Http/Controllers/ImportGedcom.php` (+44/−2), `app/Cli/Commands/TreeImport.php` (+34), `TreeExport.php` (+12), `ExportGedcomClient.php` (+3/−1), `app/Module/ClippingsCartModule.php` (+4/−2), `resources/views/admin/gedcom-export-options.phtml` (+14), `admin/trees-import.phtml` | GEDZIP-Upload, Option `--gedcom-version`, Auswahl 5.5.1/7.0. a für die vorhandenen Handler/Views; b als eigene Export-/Import-Route (so arbeitet ExtendedImportExport). |
| `app/Cli/Commands/TreeGedcom7.php` (neu), `app/Cli/Console.php` | Kommandozeile `tree-gedcom7`. a: Kommandoliste ist fest. Ersatz b: Datafix/Verwaltungsroute. |
| `app/Gedcom7/Gedcom7Storage.php` (neu, 196), `Gedcom7TreeConverter.php` (neu, 321), `app/Gedcom7/Schema/Migration0–17` (neu), `app/Http/Middleware/UpdateDatabaseSchema.php`, `app/Http/RequestHandlers/SetupWizard.php` | Zweite Migrationsfolge `WT7_SCHEMA_VERSION`, Baumumwandlung mit 5.5.1-Kopie, Sicherheitsnetz `pendingChange()`. a für Middleware/Setup; Migrationen und Umwandlung wären b (Modul-Migrationen per Einstellung, Umwandlung als `ModuleDataFixInterface` oder Verwaltungsroute). |
| `app/Tree.php` (+26/−12), `app/GedcomRecord.php` (`updateRecord`) | `CREA` + `CHAN` bei neuen Datensätzen, `Gedcom7Storage::pendingChange()` vor jedem `change`-Insert. a: Persistenz, kein Hook. |
| `app/Auth.php` (+6) | 5.5.1-Bäume nur lesbar. a. |
| `app/Http/Controllers/ConvertTreeToGedcom7.php` (neu), `resources/views/admin/trees.phtml` (+20) | Knopf „In GEDCOM 7.0 umwandeln“, Warnung. View a; Route b. |

### Datenmodell und Kernklassen – a) nur Core

| Datei | Änderung |
|---|---|
| `app/Date.php` (+16/−2), `app/Date/JulianDate.php` | Phrase („Montag nach Martini (17. November 1750)“), `BCE`. a: `final`/Kernklasse. |
| `app/Factories/CalendarDateFactory.php` (+15/−5), `app/Enums/CalendarEscape.php` (8/8) | 7.0-Kalendernamen als Werte, Parser für beide Formen, `BCE`. a: Enum-Werte landen in `dates.d_type` (Statistik-SQL). Fabrik wäre per `Registry::calendarDateFactory()` austauschbar, der Enum nicht. |
| `app/Fact.php` (+49/−7) | `attribute()` versteht `OTHER` + `PHRASE`, `date()` mit Phrase, `sortDate()`, `canShow()` mit `Hidden`-Bypass (Upstream-Bug), `canEdit()` sperrt `CHAN`/`CREA`, Anzeige von Aufzählungswerten (`NO NATU`). a. |
| `app/GedcomRecord.php` (+49/−9) | `TRAN` in `getAllNames()`, Primär-/Zweitname nach Betrachtersprache, `_RESN`, `CHAN`/`CREA` nicht als fehlende Fakten, `TRAN` in Ortsliste. a. |
| `app/Note.php` (+9), `app/Factories/NoteFactory.php` (+9/−3), `app/Factories/GedcomRecordFactory.php` (+8), `app/Gedcom.php` (`RECORD_TYPES`), `app/Services/PendingChangesService.php`, `DataFixService.php`, `LinkedRecordService.php` (+22) | Datensatztyp `SharedNote`, Übersetzungen, `sharedNoteUses()`. a: `Gedcom::RECORD_TYPES` und Fabriken-Umschaltung sind Kern; Fabriken per `Registry` austauschbar, aber `SharedNote` muss im Kern bekannt sein (Routen, Icons, Renumber). |
| `app/MediaFile.php` (+66/−7), `app/Individual.php`, `app/Http/Controllers/IndividualPage.php`, `MediaFileThumbnail.php` (+5), `app/Factories/ImageFactory.php` (+27), `app/Contracts/ImageFactoryInterface.php` (+5/−1) | `FILE`-URL, `FORM` Medientyp, `MEDI` + `PHRASE`, `otherSubstructures()`, `withCrop()`, `crop`-Parameter in signierter URL, `cropImage()`. a: Interface-Änderung; `MediaFile` ist Kernklasse. |
| `app/Statistics.php` (7/6), `app/StatisticsData.php` (10/9) | `d_type` über `CalendarEscape`, `BCE`. a (Folge des Enums). |
| `app/Comparators/FactComparator.php`, `TagComparator.php`, `app/Services/FactSortService.php` | `SDATE`, `EXID`/`UID` in der Reihenfolge. a (Kernsortierung). |
| `app/Services/MediaFileService.php` (+36/−17) | `createMediaFileGedcom()` schreibt URL/Medientyp/`MEDI`/`PHRASE`; `mediumTag()`. a. |
| `app/Services/GedcomEditService.php` (+45/−4) | Datum/Alter → Phrase, leerer Zeiger mit `PHRASE` → `@VOID@`, `automaticStructures()` (CHAN/CREA), `0:0`-Filter, `isHiddenTag()` mit `str_ends_with` (Upstream-Bug). a. |
| `app/Http/Controllers/Add*/Edit*/Create*` (ca. 20 Dateien), `app/Http/Gedcom7ProblemsTrait.php` (neu), `CheckGedcom7.php` (neu), `resources/views/edit/*` | Prüfung beim Speichern mit „Trotzdem speichern“, `_RESN` und `SNOTE` in den Neuanlage-Dialogen, `MEDI`/`PHRASE` in Mediendialogen, `EditNote` tag-neutral. a: Handler sind `final` und in `WebRoutes` verdrahtet. |
| `app/Http/Controllers/CheckTree.php` (+21/−1) | Schema registrieren, `@VOID@`, `SNOTE`-Zieltyp, Medientypen, „Datensatz ist kein GEDCOM 7.0“. a. |
| `RenumberTree`, `SearchGeneral`, `SearchAdvanced`, `SitemapDataXml`, `SitemapIndexXml`, `ControlPanel`, `SharedNotePage`, `SourcePage`, `TreePreferences`, `Login`, `HelpText`, `SiteTags` | `SNOTE` in Abfragen, `EXID`/`TRAN` statt `AFN`/`ROMN`, Quelleneinträge, Datumsbeispiele in 7.0-Form, Abschnitt „GEDCOM 7“ der Kennzeichen-Seite entfernt. a. |
| `app/Http/Middleware/Router.php` (+5) | Leerer Pfad → `/` (Upstream-Bug). a. |
| `app/Site.php`, `app/Gedcom.php` `HIDDEN_TAGS`/`CUSTOM_FAMILY_TAGS`, `resources/views/admin/tags.phtml` (+22/−164) | `HIDE_*` auf 7.0 (`TRAN`, `INIL`, `FAX`; `AFN`/`RFN`/`RIN`/`FONE`/`ROMN` weg), `FAM:CENS`/`RESI` sichtbar. a. |
| `app/Gedcom.php` `registerTags()` (+78/−116) | Keine 5.5.1-Tabelle mehr; Erweiterungen gefiltert durch `Gedcom7Definitions::gedcom7Tags()`, dann `Gedcom7Elements`, dann `registerSchema()`. a: Reihenfolge der Kern-Registrierung. Ein Modul kann in `boot()` nur **darüber** registrieren. |
| `app/Factories/ElementFactory.php` (+77/−8), `app/Contracts/ElementFactoryInterface.php` (+5) | Wildcards nach Ebenen gruppiert (41 → 21 ms), Element je Strukturtyp als Rückfall, `registerSchema()`. a: Interface. Die Beschleunigung ist eigenständig. |
| Geänderte Elemente: `DateValue`, `DateValueExact`, `AgeAtEvent` (PHRASE-Unterfeld, `withPhrase()`), `RestrictionNotice` (`_NONE`), `AbstractXrefElement` + 10 `Xref*` (`void`), `NoteStructure` (`SHARED_NOTE_TAG`, Übersetzung), `LdsOrdinanceStatus` (`DNS_CAN`), `FamilyCensus` (Ereignis), `ExternalIdentifierType` (Datalist, Bezeichnung) | a für die Kernklassen; **b möglich** als Unterklassen, die ein Modul per `registerTags()` für dieselben Pfade einträgt (`$elements + $this->elements`: die spätere Registrierung gewinnt). |
| Views (48 Dateien, +990/−220): `fact.phtml`, `fact-date.phtml` (Phrase, kein Alter bei `NO`), `fact-place.phtml` (`TRAN` + `LANG`, `EXID`), `fact-association-structure.phtml` (`ROLE`, `@VOID@` + `PHRASE`, „Eine Person erstellen“), `fact-gedcom-fields.phtml` (`OTHER` + `PHRASE`, Notiz-Übersetzungen), `family-page-children.phtml` (Kinder ohne Datensatz), `chart-box.phtml` (`FAMC:STAT` „?“), `individual-page-name.phtml`, `modules/relatives/family.phtml` (`STAT`), `components/select-*.phtml` + `select-void.phtml`, Menüs (`Structure`, Quellen-Assistent), `note-page*`, `record-page*`, `modals/media-file-fields.phtml`, Berichte-XML (`SNOTE`, `UID`) | a: Kern-Views sind nicht überschreibbar. |
| `resources/js/webtrees/calendar-widget.js`, `forms/note-structure.js` | Kalendernamen, `SNOTE`-Umschaltung. a (gebaute Assets). |
| `resources/lang/de/messages.po` (+270) | Übersetzungen. a für den Kern; Modul: `customTranslations()`. |
| `app/Module/CensusAssistantModule.php` (`SNOTE`), `FixNameSlashesAndSpaces` (`TRAN`), `FixSearchAndReplace`, `ModuleDataFixTrait`, `NoteListModule`, `NotesTabModule`, `IndividualMetadataModule` (`EXID`, `UID`), `SlideShowModule` (Medientypen) | a für die Kernmodule; b: ein Custom Module kann ein Kernmodul deaktivieren und ersetzen. |
| `app/Services/UpgradeService.php` (+2/−62), `app/Webtrees.php` (`STABILITY`), `app/Module/FanChartModule.php` (Theme-Farben) | Fork-Kennung, keine Update-Abfrage, Fächer in Theme-Optik. Nicht für Upstream. |

### Als Custom Module umsetzbar – b)

| Datei(en) | Begründung |
|---|---|
| Neue Elemente: `AssociationRole`, `AssociationStructure`, `ChildFamilyLink`, `EnumerationPhrase`, `EventTypeList`, `ExternalIdentifierLink`, `Gedcom7EnumerationTrait`, `LanguageTag`, `MediaType`, `NameTranslation`, `NameTypeEnumeration`, `NoteOrSharedNote`, `PedigreeLinkageTypeEnumeration`, `PlaceTranslation`, `SourceMediaTypeEnumeration`, `TextMediaType`, `TranslationLanguage`, `UniqueIdentifier` (+1 083 Zeilen) | In `boot()` per `registerTags()` für die 7.0-Pfade eintragen; Bezeichnungen über `customTranslations()`. |
| `app/Gedcom7/Gedcom7Schema.php`, `Gedcom7Definitions.php`, `Gedcom7Elements.php` | Reine Daten/Tabellen; `registerSubTags()` aus dem Modul ergänzt Unterfelder (Vorkommen, Sichtbarkeit) nach der Kern-Registrierung. Nur das Entfernen von 5.5.1-Unterfeldern (`0:0`) und die Rückfall-Logik `findElementByType()` brauchen den Kern. |
| `Gedcom7Converter.php`, `Gedcom7Payload.php`, `Gedcom7Node.php`, `Gedcom7Record.php`, `Gedcom551Downgrade.php`, `Gedcom7Enumerations.php`, `Gedcom7Associates.php`, `Gedcom7Identifiers.php`, `Gedcom7Media.php`, `Gedcom7Translations.php`, `Gedcom7Extensions.php`, `Gedcom7Problems.php`, `Gedcom7Structure.php` (≈ 4 700 Zeilen) | Bibliothekscode ohne Kernabhängigkeit außer `Registry::elementFactory()`. Im Modul nutzbar als `ModuleDataFixInterface` („5.5.1 → 7.0 je Datensatz“, mit Vorschau), als eigene Export-Route 7.0/GEDZIP/5.5.1-Downgrade (`GedcomExportService::export()` ist öffentlich; eigene Schleife nötig) und als eigene Import-Route (`GedcomImportService::importRecord()` ist öffentlich, aber `reformatRecord()` läuft dort immer). Das Sicherheitsnetz beim Speichern und die Prüfung beim Speichern bleiben Core. |
| `app/Http/Controllers/AddSourceEntry.php` (398), `app/Services/SourceEntryService.php` (245), `resources/views/edit/source-entry.phtml`, `lists/source-entries-table.phtml` | Quellen-Assistent: eigene Route + Views; schreibt über `createFact()`/`createIndividual()`. Einstieg: statt der Menüeinträge in `individual-page-menu`/`record-page-menu` (Core) ein Reiter (`ModuleTabInterface`) oder Menü. |
| `CreateIndividualFromPhrase.php`, `ExtensionTags.php` + `admin/trees-extensions.phtml`, `StructurePage.php` + `structure-page.phtml`/`structure-node.phtml` | Eigene Routen und Views. Nur die Links in den Kern-Menüs sind Core. |
| `app/Module/EvidenceListModule.php` (143) + `modules/evidence-list/page.phtml` | `ModuleListInterface`, fertig als Modul. |
| `app/Module/ChronicleTheme.php`, `resources/css/chronicle.css`, `modules/chronicle-theme/color-mode.phtml`, Schriften, `webpack.config.js`, `package.json` | `ModuleThemeInterface`; hat mit GEDCOM 7 nichts zu tun. |
| `app/Enums/GedcomVersion.php` | Hilfs-Enum, im Modul möglich. |
| `docker/*`, `compose.yaml`, `.dockerignore`, `docs/*`, `README.md` | Fork-Infrastruktur, nicht für Upstream. |
| `tests/*` (+4 914 Zeilen, 2 Testbäume) | Begleiten die jeweiligen PRs. |

Kurz: rund 60 % der Codezeilen (Schema, Wandler, Elemente, Assistent, Liste, Theme, Strukturseite) wären als Modul lebensfähig,
aber ohne die Kern-Haken (Import ohne `reformatRecord()`, Export-Schleife, `pendingChange()` beim Speichern, `Fact::date()`,
`MediaFile`, `SharedNote` als Datensatztyp, Views) bliebe ein Modul bei „7.0 anzeigen und exportieren“ stehen; natives Speichern geht nur im Core.

## 3. Risiken mit 7er-Daten

**Kernfunktionen von webtrees (für Code, der aus Upstream übernommen ist oder dort entsteht):**

- `dates.d_type` hat `JULIAN`, `GREGORIAN`, `_HIJRI` statt `@#DJULIAN@` … – jede SQL-Abfrage mit den alten Werten liefert nichts (im Kern in `Statistics`/`StatisticsData` angepasst, sonst nirgends geprüft).
- `media_file.multimedia_format` enthält `image/jpeg` statt `JPG`, Spalte 255 statt 4 Zeichen; Code, der auf Endungen filtert (SlideShow angepasst), findet nichts.
- `other.o_type = 'SNOTE'`: jede Abfrage `o_type = 'NOTE'` übersieht geteilte Notizen (im Kern 12 Stellen angepasst).
- `change.new_gedcom`/`old_gedcom` sind 7.0; Module, die die Änderungstabelle lesen oder schreiben (api4webtrees `Changes`, `MergeUndo`), sehen 7.0.
- `NOTE @X@` → `SNOTE @X@`, `_UID` → `UID`, `_ASSO` → `ASSO`, `RELA` → `ROLE`, `_GODP`/`_WITN` → `ASSO @VOID@` + `PHRASE`, `ROMN`/`FONE` → `TRAN`, `AFN`/`RFN`/`RIN`/`_FSFTID`/`PLAC:_GOV` → `EXID` + `TYPE`, `FORM jpg`/`TYPE photo` → `FORM image/jpeg`/`MEDI PHOTO`, `FILE` percent-kodiert, `RESN none` → `_NONE`, `RESN` an `SOUR`/`REPO`/`SUBM`/`SNOTE` → `_RESN`, `PEDI rada` → `PEDI OTHER` + `PHRASE`, `@#DJULIAN@`/`B.C.`/`INT` → `JULIAN`/`BCE`/`PHRASE`, kein `CONC`, keine Zeilenlängenbegrenzung: alles, was GEDCOM-Text mit Regex liest, muss beide Formen kennen. Die Kern-API (`Fact::attribute()`, `Fact::date()`, `MediaFile::filename()`/`type()`, `GedcomRecord::facts()`) kapselt das weitgehend, Rohzugriffe nicht.
- **Sicherheitsnetz beim Speichern:** Schreibt ein Modul 5.5.1 (`2 _GOV`, `2 NOTE @N1@`, `1 _UID`, `2 _ASSO`), wird es beim Speichern umgewandelt und protokolliert. Das Modul findet danach mit seinen eigenen Mustern nicht wieder, was es geschrieben hat. Inline-Quellen aus Modulen bleiben bis zur nächsten Baumumwandlung 5.5.1 und erscheinen in CheckTree als Fehler.
- `_LOC`-Datensätze bleiben unangetastet (nur `LANG`-Werte werden BCP 47), `PLAC:_GOV` am Ereignis wird aber `EXID`: GOV-Kennungen stehen danach in zwei Formen.
- Module, die in `boot()` 5.5.1-Pfade registrieren (`INDI:*:PLAC:_GOV`, `INDI:NAME:ROMN`), bekommen sie in die Formulare; der Kern filtert nur die eingebauten `app/CustomTags/*`. Gespeichert wird dann wieder umgewandelt (Schleife aus Registrieren, Eingeben, Umwandeln).
- Update: verbliebene 5.5.1-Bäume werden in der ersten Anfrage nach dem Update umgewandelt (Migration17), große Bäume riskieren Zeitüberschreitung; Bäume mit Fehler bleiben nur lesbar (`Auth::isEditor()` false auch für Verwalter).
- Leistung: Wandler je Datensatz bei jedem Export und bei jedem Speichern; `registerSchema()` je Anfrage (gemessen 21 ms).
- Merges von Upstream: `Gedcom.php`, `ElementFactory`, Elemente, 48 Views, `Fact`/`GedcomRecord`/`MediaFile` weichen stark ab; Gregs Kennzeichen-Änderungen kommen nicht mehr von selbst.

**Bekannte Module:**

- **Vesta (Hartenthaler):** Gov4Webtrees liest `PLAC:_GOV` und `_LOC:_GOV` – am Ereignis steht jetzt `EXID` mit GOV-URI, die Kennung ist für das Modul weg; Shared Places liest `_LOC` und `PLAC:_LOC` (bleiben), aber `_LOC:NAME:LANG` ist `de` statt `German`; Vesta Common registriert eigene Kennzeichen per `registerTags()` (5.5.1-Pfade, siehe oben); Module mit eigenen Datumsmustern (`@#D…@`) oder `o_type = 'NOTE'`-Abfragen übersehen Daten. Nicht am Code geprüft, aus der bekannten Arbeitsweise der Module abgeleitet.
- **ExtendedImportExport (Jefferson49):** ruft `GedcomExportService` (Signaturen sind abwärtskompatibel, Standard 5.5.1 → bekommt den Downgrade), legt aber eigene Filter mit 5.5.1-Regex darüber, darunter eine eigene 5.5.1→7.0-Umwandlung: doppelte Umwandlung bzw. Filter auf Daten, die schon 7.0 sind; Import über die Modulroute landet in `importRecord()` und wird wie eine Datei behandelt (Header entscheidet). Nicht geprüft.
- **api4webtrees 1.x (öffentliche Fassung):** liest `_UID` (69 Stellen), `_ASSO`/`RELA` (167), `ROMN`/`FONE` (95), `RIN` (32), `_GOV` (47), `NOTE @`, `FORM`-Endungen; schreibt 5.5.1 über `POST Fact` u. a. – gegen webtrees7 liefert sie leere `uids`, keine Paten/Zeugen, keine GOV-Kennungen am Ort, und jede Schreibroute läuft ins Sicherheitsnetz. Dafür gibt es `api4webtrees7` 2.0.0 (Level 38, schreibt 7.0, `date.gedcom` bleibt 5.5.1-Form für die Apps). Apps (wtAnd/wtWin) sind unverändert nutzbar, wenn der Server 2.0.0 hat.
- **Eigene Module (webtrees/):** `ortsregister` (`_LOC` 247×, `_GOV` 25×, `_UID` 8×, `NOTE @` 7×, `CONC` 4×) – GOV am Ereignis und Notizzeiger brechen; `ofb4webtrees` (`_GODP`, `_ASSO`, `_UID`, `_LOC`) – Paten/Zeugen nicht mehr gefunden; `sammlungen` – Dateipfade aus `FILE` sind percent-kodiert, Abgleich mit dem Medienordner nur über `MediaFile::filename()` sicher (Zahl der Treffer im Grep: 0, also nicht über Kennzeichen, sondern Pfade; nicht geprüft).
- Allgemein jedes Modul mit `preg_match` auf GEDCOM-Text, mit Annahmen über 255 Zeichen je Zeile, mit `CONC`, oder mit eigener Date-Klasse.

## 4. Vorschlag: Reihenfolge einzeln einreichbarer Pull Requests

Vom risikoärmsten zum tiefsten Eingriff. Jeder PR eine Datei-Gruppe, ein Test, Gregs Stil (knappe englische Betreffzeile,
kein Begleittext, keine Attribution). Basis `upstream/main` neu abzweigen, nicht `gedcom7-native`.

1. **Fehlerbehebungen ohne Verhaltensänderung für 5.5.1** (je ein PR, Texte in `docs/upstream-issues.md`):
   `Router` leerer Pfad → `/`; `GedcomEditService::isHiddenTag()` mit `str_ends_with`; `Fact::canShow()` `Hidden`-Bypass
   (Fakten mit `RESN` gehen beim Bearbeiten verloren); `LdsOrdinanceStatus` Schlüssel `DNS_CAN`; `Uid::canonical()` lässt
   vorhandene UIDs stehen (in webtrees7 als neue Klasse gelöst – für Upstream `Uid.php` patchen); `SlideShowModule`
   Groß-/Kleinschreibung. Stand 23.10.: Slideshow gemergt, DNS_CAN von Greg anders gelöst, RESN und Uid nach seinen Hinweisen angepasst, zwei ohne Reaktion (Tabelle in `upstream-issues.md`).
2. **`ElementFactory`: Wildcards nach Ebenen gruppiert** (41 → 21 ms je Anfrage), ohne `registerSchema()`/Typ-Rückfall. Reiner Umbau, Test `ElementFactoryTest`.
3. **`@VOID@` lesend tolerieren:** `CheckTree` und `GedcomImportService::updateLinks()` überspringen `VOID`; `AbstractXrefElement::void()` und `components/select-void.phtml` als opt-in; Anzeige `ASSO @VOID@` + `PHRASE` in `fact-association-structure.phtml`. Upstream zeigt für `VOID` schon „Not recorded“; das ergänzt nur.
4. **7.0-Datumsformen lesen, Speicherung unverändert:** `CalendarDateFactory` versteht `JULIAN …`, `FRENCH_R`, `BCE` zusätzlich zu den Escapes (Enum-Werte **nicht** ändern, sonst bricht `dates.d_type`); `Date` mit Phrase, `Fact::date()` liest `DATE:PHRASE`, `fact-date.phtml` zeigt Alter mit Phrase. Upstream importiert 7.0-Dateien heute schon als Fremd-Kennzeichen und zeigt diese Daten falsch.
5. **`SDATE` zum Sortieren:** `Fact::sortDate()`, `FactSortService`, `FactComparator`, `SDATE` in `fact.phtml` ausblenden. Klein, eigener Test.
6. **`SNOTE` lesend:** `NoteFactory` erkennt `SNOTE`-Datensätze, `whereIn('o_type', ['NOTE','SNOTE'])` in Listen, Suche, Sitemap, Datafixes, Warenkorb, Notizen-Reiter, `GedcomTextReader`, Berichte-XML. Größter lesender PR, aber additiv; `SharedNote` als eigene Klasse erst, wenn Greg den Datensatztyp will (ggf. als Issue vorab).
7. **`TRAN` lesend:** `GedcomRecord::getAllNames()` nimmt `TRAN`, `fact-place.phtml` zeigt `TRAN` mit Sprache, `FixNameSlashesAndSpaces` kennt `TRAN`; die Betrachtersprache-Logik (Primärname) als zweiter Schritt.
8. **`EXID`/`UID` anzeigen:** `ExternalIdentifierType` mit Bezeichnungen und Datalist der registrierten Typen, `ExternalIdentifierLink`, `TagComparator`, `IndividualMetadataModule`, `SearchAdvanced` zusätzlich (nicht statt) `AFN`.
9. **Medien lesend:** `MediaFile` versteht `FORM` als Medientyp und `MEDI`, `mimeType()` aus `FORM`; `CheckTree` akzeptiert Medientypen. `CROP` separat, weil `ImageFactoryInterface` einen Parameter bekommt (optional, abwärtskompatibel).
10. **GEDZIP-Import:** `TreeService::importGedzipFile()`/`importGedzipMedia()`, `ImportGedcom`, `TreeImport`, `AdminService` `.gdz`, `trees-import.phtml`. Abgeschlossen, eigener Test `GedzipTest`; Export gibt es in Upstream schon.
11. **Export als GEDCOM 7.0 (Option):** zuerst die Bibliothek (`Gedcom7Schema`, `Gedcom7Node`, `Gedcom7Payload`, `Gedcom7Converter`, Unit-Tests) als eigener PR, dann `GedcomVersion`, Header, BOM, kein `CONC`, Auswahl im Export-Dialog und `--gedcom-version`. Vorher als Issue abstimmen: 3 500 Zeilen aus `g7validation.json` erzeugter Code sind für Greg eine Grundsatzfrage.
12. **Formulare für 7.0** (Phrasenfelder, Aufzählungen mit `OTHER` + `PHRASE`, `ROLE`, `_NONE`/`_RESN`, `SNOTE`-JS, Prüfung beim Speichern): ändert das Verhalten für alle Nutzer und setzt Nr. 11 voraus; nur nach Grundsatzentscheidung, nicht stückweise.
13. **Natives Speichern** (`Gedcom7Storage`, Migrationen, Baumumwandlung, `Gedcom7Definitions`/`Gedcom7Elements` statt 5.5.1-Tabellen, `HIDDEN_TAGS`/`Site`, Kennzeichen-Seite): kein PR, sondern Vorschlag/RFC an Greg mit webtrees7 als Beleg. Nach meiner Einschätzung außerhalb dessen, was Upstream kurzfristig annimmt.

Nicht einreichen: `UpgradeService` ohne Update-Abfrage, `Webtrees::STABILITY`, `ChronicleTheme`/Fächer-Farben (eigenes Theme-Modul), `docker/`, `docs/`, `api4webtrees7`.
