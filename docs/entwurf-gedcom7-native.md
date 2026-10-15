# Entwurf: GEDCOM 7 als Speicherformat (Etappe 1)

Stand 2026-10-15, Branch `gedcom7-native`. Entschieden, noch nicht umgesetzt.

## Ziel

**Grundsatz:** Der Code bleibt so weit wie möglich identisch mit upstream (fisharebest/webtrees). Abweichungen
nur, wo GEDCOM 7 sie zwingend verlangt; neuer Code liegt möglichst in eigenen Dateien (`app/Gedcom7/`), in
upstream-Dateien kommen nur kleine Einhängepunkte. Schemaänderungen des Forks haben eine **eigene
Versionsfolge** (`WT7_SCHEMA_VERSION`, `app/Gedcom7/Schema/`), damit sie nicht mit künftigen upstream-Migrationen
kollidieren.

webtrees7 speichert GEDCOM 7.0 als **maßgebliche Daten**, importiert und exportiert 7.0 direkt und behält den
5.5.1-Export. Gleichzeitig soll **möglichst viel bestehender Code unverändert** bleiben – inklusive der rund
42 700 Tests.

## Warum nicht „beim Laden übersetzen“

Die erste Idee war: Datenbank enthält 7.0, beim Laden eines Datensatzes wird nach 5.5.1 übersetzt, beim
Speichern zurück. Das reicht nicht, weil viel Code **am Datensatz-Objekt vorbei direkt im gespeicherten Text**
arbeitet:

- etwa 25 SQL-Abfragen mit `LIKE`/`REGEXP` auf `i_gedcom`, `f_gedcom` … (`StatisticsData`, `SearchService`,
  `Report/ListBuilder`, `CalendarService`, `FixSearchAndReplace`)
- direkte Schreibzugriffe mit SQL-`REPLACE` (`RenumberTree`, 31 Stellen) und Kopien ganzer Tabellen (`MergeTrees`)
- die Indextabellen (`dates`, `name`, `placelinks`, `link`), die beim Import per Regex aus dem Text gebaut werden

All das würde bei 7.0 in der Spalte still falsche Ergebnisse liefern.

## Vorschlag: zwei Spalten – 7.0 maßgeblich, „Arbeitsfassung“ für den bestehenden Code

Jede Datensatztabelle (`individuals`, `families`, `sources`, `media`, `other`) bekommt eine **zweite Spalte**
(`i_gedcom7`, `f_gedcom7`, …):

| Spalte | Inhalt | Wer liest |
|---|---|---|
| `*_gedcom7` (neu) | GEDCOM 7.0, **maßgeblich** | 7.0-Export, später schrittweise der übrige Code |
| `*_gedcom` (bisher) | **Arbeitsfassung**: daraus abgeleitet, in der Form, die der bestehende Code versteht | alles Bestehende: Factories, Ansichten, SQL-Suchen, Indextabellen, Editoren, Berichte |

Damit läuft der gesamte bestehende Code unverändert weiter. Umgewandelt wird **nur beim Schreiben**, nicht beim
Lesen – ein Cache ist nicht nötig.

### Die Arbeitsfassung

Die Arbeitsfassung ist **webtrees-intern** und muss kein gültiges 5.5.1 sein. Regel: *7.0 bleibt stehen, wo der
bestehende Code es nicht auswertet; nur was er auswertet, wird in die 5.5.1-Form gebracht.* Das hält die
Umwandlung klein und sicher.

| 7.0 | Arbeitsfassung | Grund |
|---|---|---|
| `DATE JULIAN 1700`, `BCE`, `DATE … + PHRASE` | `@#DJULIAN@ 1700`, `B.C.`, `INT … (…)` | Datumsklassen, `dates`-Tabelle, Kalender |
| `0 @N1@ SNOTE`, `n SNOTE @N1@` | `0 @N1@ NOTE`, `n NOTE @N1@` | Notiz-Listen, -Seiten, -Editoren |
| `ASSO` + `ROLE`/`PHRASE` | `ASSO` + `RELA` | Verwandtschaftsanzeige |
| `NAME`/`PLAC` + `TRAN` + `LANG` | `ROMN`/`FONE` | Namens- und Ortsindex |
| `FILE` (URL) + `FORM` (MIME) + `MEDI` | `FILE` (Pfad) + `FORM` (Endung) + `TYPE` | Medienzugriff, `media_file` |
| `UID` | `_UID` | Duplikat-/Merge-Logik |
| `AGE < 8y` + `PHRASE` | `AGE CHILD` | Altersanzeige |
| `LANG de` | `LANG German` | Sprachfelder |
| `EXID`, `NO`, `SDATE`, `CREA`, `INIL`, `SEX X` … | **unverändert** | wird nicht ausgewertet bzw. ist in `CustomTags/Gedcom7` schon bekannt |

Wo eine 5.5.1-Form nicht verlustfrei zurückführt, wird die 7.0-Information als internes Extension-Tag
(`_WT7_…`) mitgeführt.

### Die zwei Umwandlungen

- **Arbeitsfassung → 7.0:** der vorhandene `Gedcom7Converter` (aus dem Export-Commit).
- **7.0 → Arbeitsfassung:** neu, aber nur für die Zeilen der Tabelle oben. Wiederverwendbar sind `Gedcom7Node`,
  `Gedcom7Schema` und die Wertetabellen in `Gedcom7Payload`.

**Bedingung, die immer gelten muss:** `nach7(nachArbeitsfassung(x)) == x` für jedes normalisierte 7.0. Das wird
auf allen Testdateien automatisch geprüft (siehe Tests).

## Datenflüsse

**Import 7.0-Datei** (`HEAD:GEDC:VERS 7.0`, wird heute nicht ausgewertet): je Datensatz 7.0 normalisieren →
`*_gedcom7`; Arbeitsfassung erzeugen → bestehender `importRecord` (füllt `*_gedcom` und alle Indextabellen
wie bisher).

**Import 5.5.1-Datei:** wie bisher; zusätzlich `*_gedcom7` per Converter. Der Converter braucht die vollständige
xref-Liste (ungültige xrefs umbenennen, `@VOID@`) → Vorab-Durchlauf über `gedcom_chunk`. **Ungültige xrefs
werden beim Import einmal umbenannt**, damit beide Spalten dieselben xrefs haben.

**Bearbeiten:** Editoren und Datafixes schreiben weiter die Arbeitsfassung. Die Änderung läuft wie heute über
`change` → `PendingChangesService::acceptRecord` → `GedcomImportService::updateRecord`. **An dieser einen Stelle**
wird zusätzlich `*_gedcom7` = Converter(Arbeitsfassung) geschrieben. Direkte SQL-Schreiber (`RenumberTree`,
`MergeTrees`) berechnen danach `*_gedcom7` für den Baum neu.

**Export 7.0:** ohne Datenschutzfilter direkt aus `*_gedcom7`; mit Datenschutzfilter wie heute (gefilterte
Arbeitsfassung → Converter).
**Export 5.5.1:** Arbeitsfassung wie bisher, verbliebene 7.0-Tags (`EXID`, `NO`, …) werden zu `_`-Tags
herabgestuft. Ein vollständiger Rückwärts-Converter entfällt damit weitgehend.

**Bestehende Bäume (Migration):** eine eigene webtrees7-Migration legt die Spalten an und erweitert `i_sex` um
`X`. Ungültige xrefs werden umbenannt, `*_gedcom7` wird per Converter gefüllt (wie der heutige Export).

## Risiken

1. **Editoren verwerfen unbekannte Zeilen** der Arbeitsfassung → Datenverlust in 7.0. Gegenmaßnahme:
   `CustomTags/Gedcom7` immer registrieren (heute nur optional); Bearbeitungs-Rundlauf-Tests je Elementtyp.
2. **Spalten laufen auseinander**, wenn ein Schreiber vorbeigeht. Gegenmaßnahme: zentrale Schreibstelle, eine
   Prüfung in „Stammbaum überprüfen“ (beide Spalten müssen ineinander überführbar sein).
3. **Doppelter Speicherplatz** für den GEDCOM-Text – bei Bäumen dieser Größe unkritisch.
4. Die Arbeitsfassung ist **interner Zwischenzustand**: Ansichten zeigen zunächst weiter 5.5.1-Begriffe
   (z. B. RELA statt ROLE). Das wird in den späteren Etappen Bereich für Bereich behoben.

## Tests

- **Rundlauf** 7.0 → Arbeitsfassung → 7.0 identisch: Falkenrath-7.0, die zehn Fehlerdateien aus
  `falkenrath/fehler/`, die offiziellen 7.0-Testdateien von gedcom.io.
- **Import/Export** im Container: Falkenrath-7.0 importieren, als 7.0 exportieren, mit js-gedcom gegen die
  Registry prüfen und mit der Referenzfassung vergleichen.
- **Bestehende Suite** muss unverändert grün bleiben (Ausgangsstand: 17 umgebungsbedingte PDF-Snapshot-Fehler).

## Danach: Bereich für Bereich auf 7.0

Jeder spätere Schritt stellt einen Bereich (Notizen, Datum, Medien, Namen …) auf `*_gedcom7` um; die
entsprechende Zeile der Arbeitsfassungs-Tabelle entfällt. Wenn die Tabelle leer ist, entfällt die alte Spalte.

## Aufwand Etappe 1 (Schätzung)

| Teil | Aufwand |
|---|---|
| Schema (webtrees7-Migration 1) + Migration bestehender Bäume | M |
| 7.0 → Arbeitsfassung (nur Tabellenzeilen) | M |
| Import 7.0 + xref-Vorabdurchlauf | M |
| Schreibstelle, RenumberTree/MergeTrees | S |
| Export 7.0 direkt, 5.5.1 herabstufen | S |
| Rundlauf- und Importtests | M |

## Entscheidungen (2026-10-15)

1. Zwei Spalten wie beschrieben.
2. Ungültige xrefs werden beim Import und bei der Migration umbenannt.
3. Neue Spalte `*_gedcom7`; die bestehende Spalte `*_gedcom` behält ihren Namen.

## Stand der Umsetzung (2026-10-15)

| Teil | Wo | Stand |
|---|---|---|
| Schema (`*_gedcom7`, `SEX X`) | `app/Gedcom7/Schema/Migration0` | fertig, getestet mit SQLite, MariaDB, PostgreSQL |
| Arbeitsfassung | `Gedcom7WorkingCopy` | fertig; jede Regel wird pro Datensatz geprüft |
| Import von 7.0-Dateien | `Gedcom7Storage`, Einhängepunkte in `GedcomImportService` | fertig |
| 7.0 beim Bearbeiten | `Gedcom7Storage::store()` nach jedem `importRecord` | fertig |
| Umwandlung bestehender Bäume | `Gedcom7TreeConverter`, `tree-gedcom7`, Migration 1 | fertig, auch automatisch nach 5.5.1-Importen |
| RenumberTree, MergeTrees | `Gedcom7Storage::rebuildTree()` | fertig |
| 5.5.1-Export | `Gedcom551Downgrade` | fertig |
| 7.0-Export | unverändert: Converter(Arbeitsfassung) = gespeichertes 7.0 | fertig |

Abweichung vom Entwurf: Das 7.0 wird nicht neben der Arbeitsfassung aus der Datei übernommen, sondern immer aus der
Arbeitsfassung erzeugt, die der bestehende Code gespeichert hat (`*_gedcom7 = Converter(*_gedcom)`). So sind die beiden
Spalten per Konstruktion gleich, und webtrees normalisiert 7.0-Importe wie 5.5.1-Importe.

Ändern sich die Regeln der Arbeitsfassung, wandelt eine webtrees7-Migration alle Bäume erneut um (siehe Migration 2).
