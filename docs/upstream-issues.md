# Issues in upstream webtrees (2.3.0-dev)

Found while working on webtrees7, checked against fisharebest/webtrees `main` (2026-10-16).
Each section can be posted as a GitHub issue.

Posted 2026-10-22, PRs from the fork thobgg/webtrees (branches `fix/*`, upstream/main 209a4511ce):

| Section | Issue | PR | Status (2026-10-23) |
|---|---|---|---|
| 1 Home page 404 | #5529 | #5531 | open |
| 2 Hidden CHR | #5427 (existing, comment) | #5535 | open |
| 3 RESN facts deleted | #5528 | #5530 | confirmed by git-bisect; fix moved to `AccessLevel::allows()` as suggested, open |
| 4 DNS_CAN | #5534 | #5538 | closed, fixed differently in 1511a31edb (7.0 values normalised to 5.5.1) |
| 5 Uid::canonical() | #5532 | #5536 | PAF conversion is intended; PR reduced to the fallback (no random UUID for unrecognised values), open |
| 6 Slide show | #5533 (follow-up to #5277) | #5537 | merged (b5ad2d6451) |

## 1. Home page without `?route=` returns 404

With ugly URLs (no `rewrite_urls`), `http://example.com/` and `http://example.com/index.php` return
"404 Not Found". `index.php?route=%2F` works.

Cause: `Router::process()` uses the empty `route` parameter as the path, and an empty path matches no route.
This came with 8b21d51 "Routing not working in subdirectory". With pretty URLs, a request for exactly the
base path (e.g. `/webtrees`) also becomes an empty path.

Suggested fix, after stripping the base path:

```php
if ($path === '') {
    $path = '/';
}
```

## 2. "Add a christening" shows no fields when CHR is hidden

When "Christening" is hidden in the control panel (`HIDE_CHR`), the form to add a christening shows
no date, place or any other field.

Cause: `GedcomEditService::isHiddenTag()` compares with `str_contains()`, and `Gedcom::HIDDEN_TAGS['CHR']`
contains `INDI:CHR`. So `INDI:CHR:DATE`, `INDI:CHR:PLAC`, etc. are all hidden. The same applies to other
hidden events, e.g. `INDI:CHRA`.

Suggested fix: hide only the tag itself or tags that end with it, e.g. compare `$tag === $hidden_tag ||
str_ends_with($tag, $hidden_tag)`, or match whole path segments.

## 3. Editing a record deletes facts with a restriction notice

Editing a record (e.g. the name of an individual) deletes facts that have `2 RESN confidential` (or
`privacy`), even for a manager.

Cause: `GedcomRecord::updateFact()` rebuilds the record from `facts([], false, AccessLevel::Hidden, true)`.
`AccessLevel::Hidden` is meant to bypass privacy, but `Fact::canShow()` checks the `RESN` of the fact first,
so these facts are dropped. `GedcomRecord::canShow()` already has the bypass:

```php
// We use this value to bypass privacy checks. For example,
// when updating a record, or when downloading data.
if ($access_level === AccessLevel::Hidden) {
    return true;
}
```

The same lines at the start of `Fact::canShow()` fix it (webtrees7: cd937efa92).

## 4. `LdsOrdinanceStatus` cannot show or select `DNS_CAN`

`LdsOrdinanceStatus` (GEDCOM 7.0 `INIL:STAT`, etc.) converts `DNS/CAN` to `DNS_CAN` in `canonical()`, but
its `values()` use the key `DNS/CAN`. So `DNS_CAN` is shown as the raw value, and the edit control
cannot select it.

Suggested fix: use the key `DNS_CAN` in `values()` (webtrees7: df51660f96).

## 5. `Uid::canonical()` changes existing UIDs

`Uid` (GEDCOM 7.0 `UID`) returns a new random UUID for any value that does not contain a UUID, and it
reformats a PAF-style UID (32 hex digits and a 4-digit checksum, as written by `_UID`) as a UUID, dropping
the checksum. So saving an edit form with an existing `UID` can change the identifier, and other
applications no longer recognise the record.

Suggested fix: format only values that are UUIDs, and keep other values, e.g.

```php
if (preg_match('/^[0-9a-f]{8}-?[0-9a-f]{4}-?[0-9a-f]{4}-?[0-9a-f]{4}-?[0-9a-f]{12}$/i', $value) === 1) {
    // format as now
}

return $value;
```

## 6. Slideshow block finds no images on case-sensitive databases

`SlideShowModule` filters with `whereIn('media_file.multimedia_format', ['bmp', 'gif', 'jpeg', 'jpg', ...])`,
but `GedcomImportService` stores `multimedia_format` in upper case (`JPG`), see also Migration45. MySQL and
MariaDB compare without regard to case, so it works there. PostgreSQL compares with regard to case, so
the block would find no images. (Found by reading the code, not tested on PostgreSQL.)

Suggested fix: upper-case values in `SUPPORTED_FORMATS`.
