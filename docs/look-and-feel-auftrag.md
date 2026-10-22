# webtrees7 – Look & Feel

> Archiv (2026-10-18): Der Worktree `webtrees7-look` ist entfernt, `look-and-feel` ist vollständig in `gedcom7-native` übernommen. Der Feinschliff von „Chronik“ kommt am Ende im Haupt-Repo.

Dieser Ordner ist ein Git-Worktree von `~/AI-Projects/webtrees7`, Branch `look-and-feel`. Parallel arbeitet ein
anderer Chat im Hauptordner (Branch `gedcom7-native`) an Stufe B (Bedienung nach GEDCOM 7.0).

## Auftrag

webtrees7 bekommt ein zeitgemäßes Aussehen, ohne seine DNA zu verlieren: gleicher Seitenaufbau, gleiche Begriffe,
gleiche Funktionen, gleiche Menüs. Modern heißt: ruhiger, klarer, gut lesbar, gut auf dem Handy, heller und dunkler
Modus, zeitgemäße Schrift und Abstände, keine verspielten Bilder-Icons aus den 2000ern.

- Umsetzung als **eigenes Theme-Modul** neben den vorhandenen (`app/Module/WebtreesTheme.php`, `ColorsTheme.php` …).
  Die alten Themes bleiben wählbar. Das neue Theme soll Standard werden können.
- CSS liegt in `resources/css/`, gebaut wird mit webpack (`package.json`) nach `public/css/*.min.css`; die gebauten
  Dateien sind im Repo eingecheckt. Das Dev-Image hat kein Node: bauen mit einem Node-Container, z. B.
  `docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app node:22 sh -c "npm ci && npm run build"`.
- Vorgehen: erst Bestandsaufnahme (Themes, Layout `resources/views/layouts/`, Kopfzeile, Menüs, Listen, Seiten),
  dann zwei bis drei Entwürfe zum Abstimmen (Bildschirmfotos oder eine Vorschau-Seite), erst danach umsetzen.

## Grenzen (wegen des parallelen Chats)

- **Nicht anfassen:** Bearbeitungsformulare (`resources/views/edit/*`), `app/Elements/`, `app/Gedcom7/`,
  `app/Services/Gedcom*`, alles zur GEDCOM-Logik. Nur Aussehen: CSS, Theme-Modul, Layout-Views, Kopfzeile, Menüs,
  Listen- und Seitenansichten.
- Den Stand von Stufe B regelmäßig hereinholen: `git merge gedcom7-native`. Nie im Hauptordner arbeiten.
- Pushen nur nach `github` (Repo thobgg/webtrees-gedcom7-lab), Branch `look-and-feel`. Nie nach `upstream`
  (fisharebest, Pushen dort gesperrt).

## Arbeitsweise

- **Nur Docker.** Nichts auf dem Host installieren, keine Server auf dem Host (kein `php -S`, kein Host-Composer).
  `docker compose up -d --build` startet hier eine eigene Dev-Instanz auf **Port 8708** (`.env`), mit eigener
  Datenbank; `docker compose exec webtrees docker/setup-demo.sh` legt admin/admin und den Falkenrath-Baum an.
  Das Compose-Projekt heißt nach dem Ordner (`webtrees7-look`) und stört die Instanz im Hauptordner nicht.
- Tests: `docker compose exec webtrees vendor/bin/phpunit --no-coverage` (17 PDF-Vergleiche in
  `ReportRegressionTest` sind bekannt rot), `vendor/bin/phpstan analyse`, `vendor/bin/phpcs`.
- **Stil wie Greg Roach** (Code, Kommentare, Commits): knapp, englische Kommentare, kurze englische Betreffzeile
  („Add …“, „Fix: …“), kein Text darunter, keine Attribution-Zeilen, keine „webtrees7:“-Markierungen. Wo es
  klar besser geht, besser machen und im Bericht kurz begründen.
- **Sondieren statt umsetzen:** Knappe Ideen des Nutzers sind im Zweifel Fragen. Erst Einschätzung oder Entwurf,
  dann auf ein ausdrückliches „mach“ warten. Vor jedem Commit und Push fragen.
- Antworten auf Deutsch, übersichtlich; Zwischenstände ehrlich benennen.
- Fehler in Gregs webtrees nicht von sich aus suchen oder Issue-Texte schreiben.

## Zum Ausprobieren

Der Nutzer testet auf seinem NAS (Image aus `docker/production/build.sh --save`).
Images baut nur der Chat im Hauptordner; dieser Chat liefert seinen Stand per Merge in `gedcom7-native`, wenn der
Nutzer das will.
