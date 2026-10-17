# GEDCOM 7 in webtrees7: Stand und Weg

Stand 2026-10-17, Branch `gedcom7-native`.

## Ziel

webtrees7 soll **nativ mit GEDCOM 7.0 arbeiten**: Der Kern selbst liest und schreibt 7.0 – Speicherung, Import,
Anzeige, Bearbeitung, Suche, Berichte, Export. GEDCOM 5.5.1 bleibt nur als Export für ältere Programme.

Natives 7.0 hat Vorrang vor der Nähe zum Code von upstream (fisharebest/webtrees). Neuer Code liegt trotzdem
bevorzugt in eigenen Dateien (`app/Gedcom7/`), und Schemaänderungen haben eine eigene Versionsfolge
(`WT7_SCHEMA_VERSION`, `app/Gedcom7/Schema/`).

## Wo wir stehen: nativ

webtrees7 speichert GEDCOM 7.0, in den bestehenden Spalten `*_gedcom`, so wie webtrees GEDCOM immer speichert: ohne
die `@@`-Escapes (siehe `Gedcom7Record`). Die zweite Spalte `*_gedcom7` aus Schritt A1 ist entfernt (Migration 15;
sie prüft vorher, dass jeder Datensatz übereinstimmt).

- **Lesen:** Der Kern liest 7.0. Was webtrees über 7.0 hinaus kennt (z. B. `PEDI RADA`, `RESN` an Quellen), liest er
  aus der 7.0-Form (`PEDI OTHER` + `PHRASE Rada`, `_RESN`), siehe `Gedcom7Enumerations`.
- **Import einer 7.0-Datei:** Das 7.0 wird normalisiert gespeichert. `reformatRecord()` fasst 7.0-Bäume nicht an.
- **Bearbeiten:** Die Formulare bieten weiter die webtrees-Listen (z. B. Beziehungen als `RELA`, Medien mit
  Dateiendung). Schon beim Speichern wird die Änderung 7.0; offene Änderungen sind also 7.0 wie die angenommenen
  Datensätze. Ausnahme: Änderungen, aus denen in 7.0 neue Datensätze würden (Inline-Quellen), bleiben bis zur nächsten
  Umwandlung des Baums unverändert; „Stammbaum überprüfen“ meldet sie als „kein GEDCOM 7.0“.
- **5.5.1-Bäume** werden umgewandelt (nach jedem 5.5.1-Import, nach dem Zusammenführen mit einem 5.5.1-Baum und per
  Migration): Export als 7.0, Import als 7.0. Ein 7.0-Baum wird bei einer erneuten Umwandlung aus seinen Datensätzen
  aufgebaut.
- **Export:** 7.0 = gespeicherte Datensätze mit `@@`-Escapes; 5.5.1 = `Gedcom551Downgrade` (Daten, Alter, Medien,
  Übersetzungen, Bezeichner, Aufzählungen und Sprachen in 5.5.1-Form, andere 7.0-Strukturen als `_`-Erweiterungen).
- **Prüfung:** „Stammbaum überprüfen“ kennt die 7.0-Strukturen und meldet Datensätze, die kein 7.0 sind.

## Weg A: Bereich für Bereich nativ

Jeder Schritt hat einen Bereich des Kerns auf das 7.0 umgestellt. Bis A8 gab es dafür eine Arbeitsfassung neben dem
7.0, deren Regeln Schritt für Schritt wegfielen; mit A8 Teil 4 ist sie entfallen.

| Schritt | Bereich | Was nativ wird |
|---|---|---|
| A1 | Speicherung | 7.0 maßgeblich, Arbeitsfassung verlustfreie Sicht – **erledigt** (seit A8 Teil 4 nur noch eine Spalte) |
| A2 | Notizen | `SNOTE` als eigener Datensatztyp (Liste, Seite, Editor, Berichte, Suche) – **erledigt**; `NOTE` ist nur noch Text: wählt man im Notizfeld „gemeinsame Notiz“, sendet das Formular `SNOTE` (A8 Teil 2) |
| A3 | Datum | Datumsklassen lesen 7.0 (`JULIAN`, `BCE`, `PHRASE`), Kalender-Editor schreibt 7.0, `dates`-Tabelle mit 7.0-Kalendernamen, Altersangaben mit `PHRASE` – **erledigt** |
| A4 | Personen ohne Datensatz, Rollen | `ASSO`/`ROLE`/`PHRASE` statt `RELA`, `_ASSO`, `_GODP`, `_WITN` – **erledigt**; der Editor bietet weiter die übersetzte webtrees-Liste der Beziehungen (als `RELA`), beim Speichern wird daraus `ROLE` (+ `PHRASE`); Bezugspersonen ohne Datensatz: Person leer lassen, Name eintragen |
| A5 | Medien | `FILE` als URL, `FORM` als Medientyp, `MEDI` (+ `PHRASE`) – **erledigt**; `MediaFile` liest das 7.0, `media_file` speichert Medientypen (`image/jpeg`); der Medien-Editor schreibt noch Dateiendung und `TYPE`, beim Speichern wird daraus 7.0 |
| A6 | Namen, Orte | `TRAN` statt `ROMN`/`FONE` – **erledigt**; Namensindex, Orte, Anzeige, erweiterte Suche und Datafix lesen `TRAN`; im Editor gelten die Einstellungen für `ROMN` auch für `TRAN`; eingegebenes `ROMN`/`FONE` wird beim Speichern `TRAN` |
| A7 | Bezeichner | `EXID`, `UID` – **erledigt**; Anzeige mit Namen der registrierten Typen und Links (FamilySearch, GOV), `i_rin` aus `EXID` RIN, neue UIDs sind UUIDs, vorhandene (z. B. von PAF) bleiben beim Bearbeiten unverändert; der Editor bietet `UID` und `EXID`, eingegebenes `AFN`/`RFN`/`RIN` wird beim Speichern `EXID` |
| A8 | Rest | Teil 1 **erledigt**: Aufzählungen (`PEDI`, `NAME:TYPE`, LDS-Status, Rollen in Quellen), Sprachen (BCP 47, Anzeige als Sprachname) und `_RESN` (Datenschutz liest es); Formulare zeigen die webtrees-Werte, gespeichert wird 7.0. Teil 2 **erledigt** (`NOTE` nur inline, siehe A2). Teil 3 entfällt (beim Speichern wird schon alles 7.0; der Editor bietet weiter die webtrees-Listen). Teil 5 **erledigt**: deutsche Übersetzungen, „Stammbaum überprüfen“ kennt die übrigen 7.0-Strukturen (`PHRASE` an Zeigern, `CROP`, `SOUR` unter `NOTE`, Orte unter `SOUR:DATA:EVEN`, Ereignislisten mit „, “). Teil 4 **erledigt**: die Arbeitsfassung ist entfallen, `*_gedcom7` entfernt |

„Stammbaum überprüfen“ kennt bei 7.0-Bäumen die 7.0-Tags von webtrees7 und upstream. Erweiterungen anderer Programme
(z. B. GEDCOM-L `_LOC`, `_POST`, `_RUFNAME`) meldet es wie upstream als benutzerdefiniert.

Folge von A3 für Fremdmodule: Die `dates`-Tabelle hat in `d_type` die 7.0-Namen (`JULIAN`, `_HIJRI` …); Abfragen mit
`@#DJULIAN@` finden nichts mehr. `CalendarEscape` liefert die neuen Werte.

Folge von A5 für Fremdmodule: In 7.0-Bäumen hat `media_file.multimedia_format` Medientypen (`image/jpeg` statt `JPG`),
`MediaFile::format()` ebenso. Die Datensätze haben `FILE` als URL (`my%20photo.jpg`) und `FORM:MEDI` statt
`FORM:TYPE`; `MediaFile::filename()` und `type()` liefern weiter Dateiname und webtrees-Medientyp.

Folge von A7 für Fremdmodule: Die Datensätze haben `UID` statt `_UID` und `EXID` + `TYPE` statt `AFN`, `RFN`, `RIN`,
`_FSFTID` und `_GOV` (z. B. `PLAC:EXID` mit `TYPE https://gov.genealogy.net/` statt `PLAC:_GOV`).

Folge von A8 für Fremdmodule: Die Spalten `*_gedcom7` gibt es nicht mehr; `*_gedcom` enthält das 7.0. Aufzählungen
mit webtrees-Werten stehen als `OTHER` + `PHRASE`, Sprachen als BCP 47 (`de`), `RESN` an Quellen u. a. als `_RESN`.

Jeder Schritt mit Tests (eigene Suite, api4webtrees-Suite, gedcom.io-Testdateien lokal, Falkenrath) und
Vorführung im Container.

## Stufe B: Bedienung nativ

Mit Weg A ist der Kern 7.0: Speicherung, Lesen, Anzeige, Export. Die Bedienung ist es nicht. Die Formulare entstehen
aus den 5.5.1-Definitionen, beim Öffnen wird das 7.0 in webtrees-Werte zurückübersetzt, beim Speichern läuft jede
Änderung durch den 5.5.1→7.0-Wandler. Das ist ein Übergang: 7.0 ist kein Dialekt von 5.5.1, und eine Bedienung, die
5.5.1 denkt, kann vieles in 7.0 nicht ausdrücken.

Ziel von Stufe B: Die Bedienung denkt 7.0. Die Formulare schreiben 7.0, der Wandler läuft nur noch beim Import einer
5.5.1-Datei, 5.5.1 gibt es nur an der Grenze (Import, Export).

**Maßstab** ist die Spezifikation 7.0 (gedcom.io) und ihre maschinenlesbare Form `g7validation.json`
(FamilySearch/GEDCOM-registries, in `Gedcom7Schema` und `docker/check-gedcom7`). Ein kanonisches Bedienkonzept gibt
es nicht; die Spezifikation legt das Datenmodell fest, nicht die Formulare. Ein Formular ist 7.0, wenn es genau die
Strukturen der Spezifikation anbietet (plus die dokumentierten Erweiterungen von webtrees) und sie unverändert
speichert.

### Leitbild

Das Datenmodell von 7.0 ist das Bedienmodell. Die Bedienung zeigt die Strukturen der Spezifikation gut, statt
Formulare nachzubauen, die hinterher übersetzt werden.

1. **Wortlaut und Bedeutung.** Jeder genormte Wert kann einen Wortlaut haben (`PHRASE`): „Montag nach Martini 1750“
   neben `BET 11 NOV 1750 AND 17 NOV 1750`, „im 63. Lebensjahr“ neben `62y`, „Gevatter“ neben `GODP`. Das Formular
   hat ein aufklappbares Feld „wörtlich“, die Anzeige zeigt den Wortlaut, dahinter den genormten Wert.
2. **Unbekannt oder belegt nicht.** `NO` mit `DATE` und `SOUR`: je Ereignisart *belegt*, *unbekannt*, *belegt nicht*
   („bis 1850 nicht verheiratet“). Listen und Prüfungen unterscheiden Lücke und Aussage.
3. **Personen ohne Datensatz.** `@VOID@` + `PHRASE` an `ASSO`, `HUSB`, `WIFE`, `CHIL`: Erwähnte Personen erfassen,
   ohne Datensatz („Johann Müller, Bauer zu X“, „Vater unbekannt“); später „daraus eine Person machen“.
4. **Erfassen von der Quelle aus.** Quellenangaben zeigen immer auf einen Datensatz und tragen `PAGE`, `DATA:TEXT`,
   `EVEN` + `ROLE`, `QUAY`, `OBJE`. Ein Quellen-Assistent (Verallgemeinerung des Zensus-Assistenten): Eintrag öffnen,
   Fakten für alle Beteiligten anlegen, jeder bekommt die Quellenangabe mit Rolle.
5. **Beteiligte.** Jedes Ereignis hat Beteiligte mit 7.0-Rolle (`CLERGY`, `GODP`, `WITN`, `OFFICIATOR`, `NGHBR` …,
   sonst `OTHER` + `PHRASE`), mit Datensatz oder ohne (3).
6. **Nachweisstand.** `FAMC:STAT` (`PROVEN`, `CHALLENGED`, `DISPROVEN`), `ALIA` + `PHRASE` („möglicherweise
   identisch“), `QUAY`, `NO`: Diagramme und Listen zeigen, wie sicher etwas ist; offene Hypothesen als eigene Liste.
7. **Mehrsprachig.** `TRAN` + `LANG` an Namen, Orten, Notizen, Medientiteln; Anzeige in der Sprache des Betrachters,
   Originalschrift bleibt sichtbar; `HEAD:LANG` als Standardsprache des Baums.
8. **Medien.** `FILE` als URL, `FORM` als Medientyp, mehrere `FILE` je Objekt (Scan, Abschrift, Übersetzung), `CROP`
   je Verknüpfung (Ausschnitt eines Gruppenfotos für eine Person), GEDZIP für Archiv mit Medien.
9. **Verknüpfungen.** `EXID` mit Typ-URI (FamilySearch, GOV, Wikidata, WikiTree …) als Links; `UID` für Abgleich und
   Zusammenführen.
10. **Erweiterungen bewusst.** Eine Verwaltungsseite zeigt, welche `_TAGS` ein Baum hat, ihre URI aus `SCHMA` und ob
    sie bekannt sind (gedcom.io, GEDCOM-L); die eigenen Erweiterungen von webtrees stehen im Export mit `SCHMA`.
11. **Das Schema prüft beim Eingeben.** Vorkommen und Wertebereiche aus dem Schema; alle Aufzählungen gleich, mit
    „Andere“ + `PHRASE` (`PEDI`, `ROLE`, `MEDI`, `NAME:TYPE`, `QUAY`, `SEX` mit `X`, `RESN` mit mehreren Werten).
12. **Zwei Ansichten.** Geführte Erfassung für den Alltag; Strukturansicht (vollständig, gegen das Schema geprüft)
    für Forschende, statt GEDCOM-Text roh zu bearbeiten.
13. **Kleineres.** `SDATE` zum Sortieren, `CREA`/`CHAN` als Verlauf, `TIME` mit `Z`, `NOTE` mit `MIME text/html`,
    `SNOTE` mit Anzeige der Verwendung, `PLAC:FORM`/`MAP`.

Was es nur wegen 5.5.1 gibt (Notizfeld für Text oder Zeiger, `_GODP`/`_WITN`, Dateiendungen), entfällt, statt
umgebaut zu werden. Den größten Unterschied machen 1, 2, 3 + 5 und 4; sie gehören in B3 und B4 (Quellen zuerst).

Offen dabei: Wie viel Tiefe zeigt die Hauptansicht (webtrees denkt in Faktenzeilen, 7.0 in Strukturen), und wie viel
sieht man ohne Aufklappen?

### Was Weg A hinterlassen hat (Lückenliste)

Jede Stelle, an der heute übersetzt wird, ist eine Lücke der Bedienung.

| Lücke | Wo | Heute |
|---|---|---|
| Wandler beim Speichern | `Gedcom7Storage::pendingChange()` aus `Tree` (4×) und `GedcomRecord::updateRecord()`; `GedcomImportService` bei `$update` | jede Änderung wird wie ein 5.5.1-Import umgewandelt |
| Rückübersetzung beim Öffnen | `GedcomEditService::insertMissing*Subtags()` mit `Gedcom7Associates::forEditing()`, `Gedcom7Enumerations::forEditing()` | `ROLE` → `RELA`, `@VOID@` + `PHRASE` → `_GODP`/`_WITN`, `OTHER` + `PHRASE` → webtrees-Wert, BCP 47 → Sprachname |
| Inline-Quellen | `Gedcom7Storage::edit()` (`generatedRecords()`), `CheckTree` | bleiben 5.5.1 bis zur nächsten Umwandlung des Baums |
| Medien | `AddMediaFile`, `EditMediaFile`, `CreateMediaObjectFromFile`, `modals/media-file-fields` | Dateiname, Dateiendung und `TYPE`; der Wandler macht URL, Medientyp, `MEDI` |
| Namen, Orte | `Gedcom::NAME_ROMN`, `PLAC_ROMN`, `NameRomanizedVariation` | Eingabe als `ROMN`/`FONE`, Einstellungen für `ROMN` gelten für `TRAN` |
| Bezeichner | Editor, `Gedcom7Identifiers` | Eingabe von `AFN`/`RFN`/`RIN` wird `EXID` |
| Notizen | `NoteStructure`, JS von note-structure | ein Feld für Text oder Zeiger; das JS sendet `SNOTE` |
| Definitionen | `Gedcom::registerTags()` | `gedcom551Tags()`, darüber `webtreesTags()`, darüber `Gedcom7Tags` |
| Baumeinstellungen | `Tree::DEFAULT_PREFERENCES` (`*_FACTS_*`), `admin/trees-preferences`, `admin/tags` | Listen mit 5.5.1-Tags |
| Anzeige | u. a. `fact.phtml`, `fact-association-structure.phtml`, `fact-place.phtml`, `chart-box.phtml`, Medienseiten | lesen 5.5.1- und 7.0-Tags nebeneinander |
| 5.5.1-Bäume | Baumeinstellung `GEDCOM7` und die Verzweigungen nach `isGedcom7Tree()` (Import, Zusammenführen, Export, `MediaFile`, Zensus-Assistent, CheckTree, CLI) | 5.5.1-Bäume gibt es weiter |
| Nur in 7.0 | `Gedcom7Schema` kennt sie, die Bedienung nicht | `NO`, `SDATE`, `CREA`, `PHRASE` als eigenes Feld, `SCHMA`, `FILE:TRAN`, `NOTE:MIME`/`LANG`/`TRAN` |

### Schritte

| Schritt | Bereich | Was nativ wird |
|---|---|---|
| B0 | Messen | Editor-Rundreise im Prüfskript: jedes Formular unverändert speichern, der Wandler darf nichts ändern. Zählt die Lücken je Bereich; ein Bereich ist fertig, wenn dort nichts mehr umgewandelt wird – **erledigt**, siehe Messung B0 |
| B1 | Nur 7.0-Bäume | Migration wandelt alle 5.5.1-Bäume um (vorher Sicherung), ein 5.5.1-Import wird sofort umgewandelt; die Baumeinstellung `GEDCOM7` und die Verzweigungen nach `isGedcom7Tree()` entfallen |
| B2 | Definitionen | Elemente aus dem 7.0-Schema statt `gedcom551Tags()` + Schichten; Erweiterungen von webtrees (`_RESN`, `_TODO` …) ausdrücklich, im Export mit `SCHMA`; Baumeinstellungen und `admin/tags` mit 7.0-Tags (Migration der gespeicherten Listen) |
| B3 | Bausteine | Eingabefelder für Aufzählung + „Andere“ mit `PHRASE`, `PHRASE` an Datum, Alter und Zeigern, Zeiger auf `@VOID@` („ohne Datensatz“, **erledigt**), Aufzählung + „Andere“ für `NAME:TYPE`, `PEDI`, LDS-Status und Rollen in Quellen (**erledigt**: das Formular bietet die 7.0-Werte, „Andere“ ist `OTHER` + `PHRASE`, die übrigen webtrees-Werte stehen als Vorschläge an der Phrase; Entscheidung 1), Sprache (BCP 47), Medientyp; Prüfung beim Speichern (**erledigt** für „Fakt hinzufügen/bearbeiten“, „Datensatz bearbeiten“ und die Formulare für neue Personen und Familien: `Gedcom7Problems` meldet fehlende Pflichtfelder, ungültige Werte und Strukturen, die 7.0 dort nicht kennt; das Formular kommt mit Hinweisen zurück, „Trotzdem speichern“ speichert) |
| B4 | Editoren | Bereich für Bereich; mit jedem Bereich entfallen seine Regeln im Wandler und in `forEditing()`: Quellen (Zitat nur als Zeiger, „neue Quelle“ legt den Datensatz an), Bezugspersonen (`ROLE`, **erledigt**: das Formular hat `ROLE` mit den 7.0-Rollen und `PHRASE`, die webtrees-Beziehungen wie „Patin“ sind Vorschläge für die Phrase; beim Öffnen wird nichts mehr zurückübersetzt), Medien (URL, `FORM`, `MEDI`, `CROP`, `TITL`), Namen und Orte (`TRAN` + `LANG`), Bezeichner (`EXID` + `TYPE`, `UID`), Notizen (`NOTE` mit `MIME`/`LANG`/`TRAN`, `SNOTE`) |
| B5 | Nur in 7.0 | Anzeige und Editor für `NO` („nie verheiratet“), `SDATE`, `CREA`/`CHAN`, mehrere `FILE`/`TRAN` |
| B6 | Abschluss | `pendingChange()` entfällt; der Wandler läuft nur noch beim Import von 5.5.1; Anzeige liest nur noch 7.0-Tags |

Reihenfolge: B0 zuerst, dann B1 und B2 (Grundlage), B3 vor B4. In B4 zuerst die Quellen, dort ist heute der Bruch.

Folge für upstream: Views, `Elements`, Baumeinstellungen und Editoren weichen stark ab; Merges werden teurer (Weg A).
Folge für Fremdmodule: nur noch 7.0-Bäume und 7.0-Tags, auch in den Baumeinstellungen.

### Messung B0

`docker/check-gedcom7.sh editor` (Wegwerf-Datenbank, 25 Bäume: Falkenrath 5.5.1 und 7.0, Demo, 21 Testdateien von
gedcom.io) stellt jedes Formular so dar, wie der Browser es zeigt (`edit/edit-gedcom-fields`), sendet es unverändert
(Felder wie ein Browser, das Skript von note-structure nachgebildet) und speichert es wie webtrees: Fakten mit
`insertMissingFactSubtags()` und dem Ersetzen im Datensatz wie `updateFact()`, andere Datensätze mit
`insertMissingRecordSubtags()`. Ergebnisse in `build/gedcom7-check/editor-gaps.txt` und `editor-losses.txt`.

Zweiter Teil, **neue Eingaben**: Für jeden Fakt, den man einer Person, Familie, Quelle, einem Archiv, Medium oder
Einsender hinzufügen kann, wird das Formular dargestellt und ausgefüllt (Pflichtauswahlen mit dem ersten Wert, Zeiger
auf `@VOID@`, Texte mit gültigen Beispielen), dann wird jeweils ein Feld geändert: jeder Wert jeder Auswahlliste, bei
Daten und Altersangaben typische Eingaben (`abt 1850`, `@#DJULIAN@ 1 JAN 1700`, `INT 1850 (Easter)`, `CHILD`).
Gezählt wird, was der Wandler beim Speichern zusätzlich ändert. Ergebnisse in `editor-new-gaps.txt`. Nicht gemessen:
„Kind mit Familie verknüpfen“, Zensus-Assistent, die Dialoge zum Anlegen von Quellen, Medien usw.

**Lücken** (der Wandler muss beim Speichern ändern), Stand 2026-10-17, 14 985 Formulare:

| Bereich | Formulare | Was das Formular sendet → was gespeichert wird |
|---|---|---|
| Bezugspersonen | 829 | `ASSO:RELA godparent` → `ROLE GODP`, `RELA witness` → `ROLE WITN`, `RELA godfather` → `ROLE GODP` + `PHRASE` |
| Medien | 728 | `FILE:FORM IMAGE/JPEG` → `image/jpeg` (das Formular schreibt Medientypen groß) |
| Quellen | 428 | `SOUR:DATA:EVEN BIRT,CHR` → `BIRT, CHR`; `SOUR:EVEN:ROLE (Pate)` → `OTHER` + `PHRASE Pate` |
| LDS | 4 | `SLGC` ohne `FAMC` wird `_SLGC` (siehe Verluste) |
| Aufzählungen | 3 | `RESN NONE` → `_NONE`, `STAT DNS/CAN` → `DNS_CAN`, `NAME:TYPE CHANGE` → `OTHER` + `PHRASE` |

Namen und Orte (`TRAN`), Bezeichner, Notizen und Daten: keine Lücken beim unveränderten Speichern.

**Verluste** (unverändert gespeichert, und der Datensatz ist ein anderer). Alle kommen aus dem Formular, keiner aus
dem Wandler:

- Zeiger auf `@VOID@` ohne `PHRASE` gehen verloren: `ASSO`, `ALIA`, `ANCI`, `DESI`, `SOUR` (samt `PAGE`),
  `ADOP:FAMC` (samt `ADOP`), `SLGC:FAMC` (danach wird aus `SLGC` beim Speichern `_SLGC`). Die Auswahllisten kennen
  `@VOID@` nicht. → B3 (Zeiger „ohne Datensatz“) – **behoben**: In 7.0-Bäumen bieten alle Auswahllisten für Zeiger
  „Ohne Datensatz“ (`@VOID@`) an.
- ~~`SEX X` fehlt in der Auswahl~~ und ~~`LANG en-US` wird `EN-US`~~: Fehler der Messung (das Prüfskript registrierte
  die Tags ohne die Erweiterungen, anders als `RegisterGedcomTags`); mit den Tags wie in webtrees kein Verlust.
  `_LOC:NAME:LANG` ist jetzt `LanguageTag`, der Import macht aus `German` `de`; die 7.0-Definitionen werden
  zuletzt registriert, damit sie die 5.5.1-Definitionen anderer Programme ersetzen.
- ~~`NAME:TYPE OTHER` + `PHRASE Name type phrase` wird `PHRASE NAME TYPE PHRASE`~~ (behoben mit Aufzählung + „Andere“),
  `ASSO:ROLE:PHRASE godfather` wird `Godfather` (B4, Bezugspersonen).
- GEDCOM-L `_MAIDENHEAD JO53FD` wird `JO53fd` (Schreibweise des Maidenhead-Locators, wie upstream).
- `FAM:SUBM`, `INDI:SUBM` und `FAM:OBJE:TITL` gehen verloren; der Wert von Erweiterungsdatensätzen (`0 @R1@ _RECORD
  text`) geht verloren. → B2
- Wie upstream: `MARR:TYPE civil` wird `CIVIL` (164× Falkenrath), `MARR Y`/`EMIG Y`/`IMMI Y` mit Datum oder Ort
  verliert das `Y`, `NAME //` wird leer.

**Lücken bei neuen Eingaben** (Stand 2026-10-17, 11 200 Formulare, Zahlen = Formulare über alle Fakten):

| Bereich | Formulare | Was das Formular sendet → was gespeichert wird |
|---|---|---|
| Daten | ~700 | getippte 5.5.1-Daten: `abt 1850` → `ABT 1850`, `@#DJULIAN@` → `JULIAN`, `INT 1850 (Easter)` → `1850` + `PHRASE`, `B.C.` → `BCE` – **behoben**: Datums- und Altersfelder schreiben in 7.0-Bäumen 7.0, Wörter kommen ins neue Phrasenfeld |
| Übersetzungen | ~230 | Orte und Namen bieten `ROMN` und `FONE` an → `TRAN`; unter `NAME:TRAN` gibt es `NOTE` und `SOUR`, die 7.0 dort nicht kennt – **behoben**: alle Orte und Namen bieten `TRAN` statt `ROMN`/`FONE` (Orte mit eigenem Element, z. B. `INDI:BIRT:PLAC`, hatten bisher gar kein `TRAN`), `NAME:TRAN` hat die Unterfelder von 7.0 |
| LDS | ~320 | `ASSO` unter `BAPL`, `CONL`, `ENDL`, `SLGC`, `SLGS` (in 7.0 nicht erlaubt) → `_ASSO` – **behoben**, die Formulare bieten es nicht mehr an; offen: `STAT` ohne `STAT:DATE` (in 7.0 Pflicht) → `_STAT`, braucht Prüfung beim Eingeben |
| Einschränkung | ~120 | `RESN NONE` → `_NONE`; `RESN` an Quellen, Archiven, Einsendern → `_RESN` – **behoben** (Entscheidung 2026-10-17: „none“ bleibt als Erweiterung `_NONE`): das Formular schreibt in 7.0-Bäumen `_NONE`, Notizen, Archive, Quellen und Einsender bieten `_RESN` an |
| Alter | ~45 | `CHILD`, `INFANT`, `STILLBORN` → `< 8y` usw. + `PHRASE` – **behoben** (wie Daten) |
| Medien | 4 | `MEDI` mit webtrees-Werten (`CERTIFICATE` …) → `OTHER` + `PHRASE` – **behoben** (Aufzählung + „Andere“, webtrees-Werte als Vorschläge) |
| Quellen | 1 | `SOUR:DATA:EVEN` leer, aber mit Datum und Ort → `_EVEN` |

### B4 Quellen: Konzept

Leitbild Punkt 4: erfassen von der Quelle aus. Stand 2026-10-17, aus dem Code gelesen (nicht gemessen).

**Was schon 7.0 ist:** Eine Quellenangabe zeigt immer auf einen Datensatz (Auswahlliste, „Ohne Datensatz“ =
`@VOID@`); „Quelle anlegen“ legt den Datensatz sofort an (Dialog `CreateSource`); `SOUR:EVEN:ROLE` hat die 7.0-Rollen
mit „Andere“ + `PHRASE`; das Notizfeld der Quellenangabe kann auf eine gemeinsame Notiz zeigen (`SNOTE`); Inline-Quellen werden beim Import und bei der Umwandlung eigene Datensätze.

**Lücken:**

| Wo | Heute | 7.0 |
|---|---|---|
| Dialog „Quelle anlegen“ | schreibt `RESN` | `_RESN` (wie im Datensatz-Formular) |
| Zensus-Assistent | legt die Notiz als `0 @@ NOTE` an, der Wandler macht `SNOTE` daraus | gleich `SNOTE` |
| Quellenangabe | `DATA:TEXT` einmal (`webtreesSubTags` setzt `0:1`), ohne Feld für Sprache und Medientyp | `TEXT` mehrfach, mit `MIME` und `LANG` (z. B. Abschrift und Übersetzung) |
| Quellenangabe | `EVEN` ohne Phrase | `EVEN` + `PHRASE` (z. B. „Taufeintrag“) |
| Quelle | `TEXT` ohne Feld für Sprache und Medientyp | `TEXT` mit `MIME`/`LANG` |

**Quellen-Assistent** (Verallgemeinerung des Zensus-Assistenten). Ein Eintrag einer Quelle, z. B. ein Taufeintrag,
wird einmal erfasst und belegt danach alle Beteiligten:

1. *Eintrag*: Quelle wählen oder anlegen, Fundstelle (`PAGE`), Abschrift (`DATA:TEXT`, Sprache), Datum des Eintrags
   (`DATA:DATE`), Scan (`OBJE`), Bewertung (`QUAY`).
2. *Ereignis*: Art (`SOUR:EVEN`, z. B. `BAPM`), Datum, Ort; Vorlagen für Taufe, Trauung, Begräbnis, Zensus (die
   Vorlage legt fest, welche Rollen vorgeschlagen werden).
3. *Beteiligte*: je Zeile eine Rolle (`CHIL`, `FATH`, `MOTH`, `GODP`, `WITN`, `CLERGY`, `OFFICIATOR` …, sonst „Andere“ +
   Phrase) und eine Person: vorhandene wählen, neue anlegen oder „ohne Datensatz“ (Name als Phrase).
4. *Ergebnis*: das Ereignis bei der Hauptperson (z. B. `BAPM` des Täuflings) mit der Quellenangabe (`EVEN BAPM` +
   `ROLE CHIL`); die übrigen Beteiligten als `ASSO` mit `ROLE` am Ereignis (mit Datensatz oder `@VOID@` + `PHRASE`),
   jede mit der Quellenangabe (`EVEN BAPM` + ihre `ROLE`).

Der Zensus ist derselbe Ablauf mit einer Vorlage, in der jeder Beteiligte ein eigenes `CENS` bekommt; der
Zensus-Assistent kann darin aufgehen.

**Offene Fragen:**

1. Vorlagen zuerst: Taufe, Trauung, Begräbnis, Zensus – oder nur Taufe als Muster?
2. Eltern im Taufeintrag: nur als `ASSO` (`FATH`/`MOTH`) am Ereignis, oder auch die Familie belegen (`SOUR` an `FAM`
   mit `EVEN BAPM`)? Die Abstammung selbst (`FAMC`) kann in 7.0 keine Quellenangabe tragen.
3. Wo beginnt der Assistent: auf der Seite der Quelle („Eintrag erfassen“), bei der Person („Ereignis aus einer Quelle
   erfassen“) oder an beiden Stellen?
4. Zensus-Assistent: im Quellen-Assistenten aufgehen lassen oder vorerst daneben behalten?
5. Neue Personen im Assistenten: nur Name und Geschlecht, oder das volle Formular „neue Person“?

Reihenfolge: zuerst die Lücken (klein, wie bisher mit Messung und Prüfung beim Speichern), dann der Assistent mit
einer Vorlage, dann weitere Vorlagen.

### Offene Entscheidungen

1. ~~webtrees-Listen als Vorschläge für `PHRASE` zu `OTHER`, oder nur die Aufzählungen von 7.0?~~ Entschieden
   (2026-10-17): an 7.0 orientieren; gespeichert wird die 7.0-Aufzählung, „Andere“ = `OTHER` + `PHRASE`, die
   webtrees-Werte sind Vorschläge für die Phrase.
2. B1: vorhandene 5.5.1-Bäume beim Update zwangsweise umwandeln (unumkehrbar, vorher Sicherung), oder erst auf
   Knopfdruck und bis dahin nur lesen?
3. Felder, die man sofort sieht: wie heute über die Baumeinstellungen, oder neue Standards aus dem 7.0-Schema?

## Bekannte Fehler von upstream (2.3-dev)

Siehe `docs/upstream-issues.md` (englisch, als Text für GitHub-Issues bei fisharebest/webtrees). In webtrees7 behoben:
Fakten mit `RESN` gingen beim Bearbeiten verloren; `LdsOrdinanceStatus` kannte `DNS_CAN` nicht; `Uid` änderte
vorhandene UIDs (webtrees7 nutzt `UniqueIdentifier`). Nicht behoben: Startseite ohne `?route=` gibt 404; „Taufe
hinzufügen“ zeigt keine Felder, wenn die Taufe ausgeblendet ist; Diashow auf PostgreSQL (in webtrees7 nur noch bei
5.5.1-Bäumen, 7.0-Bäume speichern Medientypen in Kleinschreibung).
