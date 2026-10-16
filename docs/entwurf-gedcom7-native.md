# GEDCOM 7 in webtrees7: Stand und Weg

Stand 2026-10-16, Branch `gedcom7-native`.

## Ziel

webtrees7 soll **nativ mit GEDCOM 7.0 arbeiten**: Der Kern selbst liest und schreibt 7.0 – Speicherung, Import,
Anzeige, Bearbeitung, Suche, Berichte, Export. GEDCOM 5.5.1 bleibt nur als Export für ältere Programme.

Natives 7.0 hat Vorrang vor der Nähe zum Code von upstream (fisharebest/webtrees). Neuer Code liegt trotzdem
bevorzugt in eigenen Dateien (`app/Gedcom7/`), und Schemaänderungen haben eine eigene Versionsfolge
(`WT7_SCHEMA_VERSION`, `app/Gedcom7/Schema/`).

## Wo wir stehen: ein Übergang, noch nicht nativ

Der Kern von webtrees arbeitet **noch nicht** mit 7.0. Er liest und schreibt eine **Arbeitsfassung**, die im
Wesentlichen GEDCOM 5.5.1 ist. Daneben steht das GEDCOM 7.0:

| Spalte | Inhalt | Rolle |
|---|---|---|
| `*_gedcom7` | GEDCOM 7.0 | **maßgeblich** (seit Schritt A1) |
| `*_gedcom` | Arbeitsfassung | verlustfreie, abgeleitete Sicht für den bestehenden Code |

- **Arbeitsfassung** (`Gedcom7WorkingCopy`): 7.0, außer an den Stellen, die der bestehende Code auswertet; dort die
  5.5.1-Form (`PEDI RADA`, Sprachnamen statt BCP 47, `RESN` an Quellen …). Notizen (A2), Daten und Altersangaben (A3), Bezugspersonen (A4),
  Medien (A5), Übersetzungen von Namen und Orten (A6) und Bezeichner (A7) sind schon 7.0.
  Jede Arbeitsfassung wird geprüft: `Gedcom7Converter(Arbeitsfassung)` muss das 7.0 Zeichen für Zeichen ergeben.
- **Import einer 7.0-Datei:** Das 7.0 wird (normalisiert) gespeichert, die Arbeitsfassung daraus abgeleitet.
  `reformatRecord()` fasst 7.0-Bäume nicht an.
- **Bearbeiten:** Der Editor schreibt teils noch 5.5.1-Formen (`RELA`, `AGE CHILD` …). Schon beim Speichern wird die
  Änderung zuerst 7.0, dann wird daraus die Arbeitsfassung neu erzeugt; die offene Änderung ist also eine Arbeitsfassung
  wie die angenommenen Datensätze. Beim Annehmen geschieht dasselbe noch einmal, danach entstehen die Indextabellen.
  Ausnahme: Änderungen, aus denen in 7.0 neue Datensätze würden (Inline-Quellen), bleiben bis zum Annehmen unverändert.
- **5.5.1-Bäume** werden umgewandelt (nach jedem 5.5.1-Import und per Migration): Export als 7.0, Import als 7.0.
  Ein 7.0-Baum wird bei einer erneuten Umwandlung aus seinem gespeicherten 7.0 aufgebaut.
- **Export:** 7.0 = gespeichertes 7.0; 5.5.1 = Arbeitsfassung, 7.0-Strukturen als `_`-Erweiterungen.
- **Prüfung:** „Stammbaum überprüfen“ meldet Datensätze, deren Arbeitsfassung nicht zum 7.0 passt.

Was ein Betrachter der Datenbank sieht: in `*_gedcom` weiterhin 5.5.1-nahe Daten, in `*_gedcom7` das 7.0.

## Weg A: Bereich für Bereich nativ

Jeder Schritt stellt einen Bereich des Kerns auf das 7.0 um. Die zugehörigen Regeln der Arbeitsfassung fallen weg;
wenn keine mehr übrig sind, entfällt die Arbeitsfassung.

| Schritt | Bereich | Was nativ wird |
|---|---|---|
| A1 | Speicherung | 7.0 maßgeblich, Arbeitsfassung verlustfreie Sicht – **erledigt** |
| A2 | Notizen | `SNOTE` als eigener Datensatztyp (Liste, Seite, Editor, Berichte, Suche) – **erledigt**; offen: `NOTE` nur noch inline (die Eingabe erlaubt noch Verweise, die beim Speichern zu `SNOTE` werden) |
| A3 | Datum | Datumsklassen lesen 7.0 (`JULIAN`, `BCE`, `PHRASE`), Kalender-Editor schreibt 7.0, `dates`-Tabelle mit 7.0-Kalendernamen, Altersangaben mit `PHRASE` – **erledigt** |
| A4 | Personen ohne Datensatz, Rollen | `ASSO`/`ROLE`/`PHRASE` statt `RELA`, `_ASSO`, `_GODP`, `_WITN` – **erledigt**; der Editor bietet weiter die übersetzte webtrees-Liste der Beziehungen (als `RELA`), beim Speichern wird daraus `ROLE` (+ `PHRASE`); Bezugspersonen ohne Datensatz: Person leer lassen, Name eintragen |
| A5 | Medien | `FILE` als URL, `FORM` als Medientyp, `MEDI` (+ `PHRASE`) – **erledigt**; `MediaFile` liest das 7.0, `media_file` speichert Medientypen (`image/jpeg`); der Medien-Editor schreibt noch Dateiendung und `TYPE`, beim Speichern wird daraus 7.0 |
| A6 | Namen, Orte | `TRAN` statt `ROMN`/`FONE` – **erledigt**; Namensindex, Orte, Anzeige, erweiterte Suche und Datafix lesen `TRAN`; im Editor gelten die Einstellungen für `ROMN` auch für `TRAN`; eingegebenes `ROMN`/`FONE` wird beim Speichern `TRAN` |
| A7 | Bezeichner | `EXID`, `UID` – **erledigt**; Anzeige mit Namen der registrierten Typen und Links (FamilySearch, GOV), `i_rin` aus `EXID` RIN, neue UIDs sind UUIDs, vorhandene (z. B. von PAF) bleiben beim Bearbeiten unverändert; der Editor bietet `UID` und `EXID`, eingegebenes `AFN`/`RFN`/`RIN` wird beim Speichern `EXID` |
| A8 | Rest | Editoren, Berichte, Suche, Statistik, Datafixes; danach entfällt die Arbeitsfassung |

„Stammbaum überprüfen“ kennt bei 7.0-Bäumen die 7.0-Tags von webtrees7 und upstream; Strukturen späterer Schritte
(z. B. `NOTE:TRAN`, `CROP`, `FAMC:STAT:PHRASE`) meldet es noch als unbekannt.

Folge von A3 für Fremdmodule: Die `dates`-Tabelle hat in `d_type` die 7.0-Namen (`JULIAN`, `_HIJRI` …); Abfragen mit
`@#DJULIAN@` finden nichts mehr. `CalendarEscape` liefert die neuen Werte.

Folge von A5 für Fremdmodule: In 7.0-Bäumen hat `media_file.multimedia_format` Medientypen (`image/jpeg` statt `JPG`),
`MediaFile::format()` ebenso. Die Arbeitsfassung hat `FILE` als URL (`my%20photo.jpg`) und `FORM:MEDI` statt
`FORM:TYPE`; `MediaFile::filename()` und `type()` liefern weiter Dateiname und webtrees-Medientyp.

Folge von A7 für Fremdmodule: Die Arbeitsfassung hat `UID` statt `_UID` und `EXID` + `TYPE` statt `AFN`, `RFN`, `RIN`,
`_FSFTID` und `_GOV` (z. B. `PLAC:EXID` mit `TYPE https://gov.genealogy.net/` statt `PLAC:_GOV`).

Jeder Schritt mit Tests (eigene Suite, api4webtrees-Suite, gedcom.io-Testdateien lokal, Falkenrath) und
Vorführung im Container.

## Bekannte Fehler von upstream (2.3-dev)

- Behoben: `Fact::canShow(AccessLevel::Hidden)` umging den Datenschutz nicht – beim Bearbeiten gingen Fakten mit `RESN` verloren.

Nicht behoben:

- Ohne Parameter `route` gibt die Startseite 404 (leerer Pfad statt `/`).
