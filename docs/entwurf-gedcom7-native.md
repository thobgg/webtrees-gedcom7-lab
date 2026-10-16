# GEDCOM 7 in webtrees7: Stand und Weg

Stand 2026-10-16, Branch `gedcom7-native`.

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

## Bekannte Fehler von upstream (2.3-dev)

Siehe `docs/upstream-issues.md` (englisch, als Text für GitHub-Issues bei fisharebest/webtrees). In webtrees7 behoben:
Fakten mit `RESN` gingen beim Bearbeiten verloren; `LdsOrdinanceStatus` kannte `DNS_CAN` nicht; `Uid` änderte
vorhandene UIDs (webtrees7 nutzt `UniqueIdentifier`). Nicht behoben: Startseite ohne `?route=` gibt 404; „Taufe
hinzufügen“ zeigt keine Felder, wenn die Taufe ausgeblendet ist; Diashow auf PostgreSQL (in webtrees7 nur noch bei
5.5.1-Bäumen, 7.0-Bäume speichern Medientypen in Kleinschreibung).
