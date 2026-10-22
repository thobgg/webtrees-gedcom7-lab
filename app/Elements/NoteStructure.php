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

namespace Fisharebest\Webtrees\Elements;

use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Notes;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Note;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;

use function e;
use function preg_match;
use function strip_tags;
use function strtolower;
use function substr_count;
use function trim;
use function view;

/**
 * NOTE can be text or an XREF.
 */
class NoteStructure extends SubmitterText
{
    // The tag of a link to a shared note, if it is not NOTE.  e.g. SNOTE in GEDCOM 7.0
    protected const string SHARED_NOTE_TAG = '';

    // GEDCOM 7.0: the media type of the text, e.g. "2 NOTE <p>text</p>" + "3 MIME text/html"
    private string $mime = Gedcom7Notes::PLAIN;

    /**
     * The element for a note with a media type, e.g. HTML.
     */
    public function withMime(string $mime): static
    {
        $clone       = clone $this;
        $clone->mime = strtolower(trim($mime));

        return $clone;
    }

    /**
     * HTML notes are sanitised; other notes are markdown or plain text with links, as the tree prefers.
     */
    protected function valueFormatted(string $value, Tree $tree): string
    {
        if ($this->mime === Gedcom7Notes::HTML) {
            return Gedcom7Notes::html($value, $this->mime, $tree);
        }

        return parent::valueFormatted($value, $tree);
    }

    /**
     * An edit control for this data.
     */
    public function edit(string $id, string $name, string $value, Tree $tree): string
    {
        $submitter_text = new SubmitterText('');
        $xref_note      = new XrefNote('');

        // Existing shared note.
        if (preg_match('/^@' . Gedcom::REGEX_XREF . '@$/', $value)) {
            return $xref_note->edit($id, $name, $value, $tree);
        }

        // Existing inline note.
        if ($value !== '') {
            return $submitter_text->edit($id, $name, $value, $tree);
        }

        $options = [
            'inline' => I18N::translate('inline note'),
            'shared' => I18N::translate('shared note'),
        ];

        // New note - either inline or shared
        return
            '<div id="' . e($id) . '-note-structure" data-wt-note-structure' . (static::SHARED_NOTE_TAG === '' ? '' : ' data-wt-note-shared-tag="' . static::SHARED_NOTE_TAG . '"') . '>' .
            '<div id="' . e($id) . '-options" data-wt-note-options>' .
            view('components/radios-inline', ['name' => $id . '-options', 'options' => $options, 'selected' => 'inline']) .
            '</div>' .
            '<div id="' . e($id) . '-inline" data-wt-note-inline>' .
            $submitter_text->edit($id, $name, $value, $tree) .
            '</div>' .
            '<div id="' . e($id) . '-shared" class="d-none" data-wt-note-shared>' .
            $xref_note->edit($id . '-select', $name, $value, $tree) .
            '</div>' .
            '</div>';
    }

    /**
     * Create a label/value pair for this element.
     */
    public function labelValue(string $value, Tree $tree): string
    {
        $id       = Registry::idFactory()->id();
        $expanded = $tree->getPreference('EXPAND_NOTES') === '1';

        // A note structure can contain an inline note or a linked to a shared note.
        if (preg_match('/^@(' . Gedcom::REGEX_XREF . ')@$/', $value, $match) === 1) {
            $note = Registry::noteFactory()->make($match[1], $tree);

            if ($note === null) {
                return parent::labelValue($value, $tree);
            }

            if (!$note->canShow()) {
                return '';
            }

            $label         = '<span class="label">' . I18N::translate('Shared note') . '</span>';
            $value         = $note->getTranslatedNote();
            $html          = $this->withMime($note->mime())->valueFormatted($value, $tree);
            $title         = $value === $note->getNote() ? $note->fullName() : e(Note::firstLineOfTextFromHtml($html));
            $first_line    = '<a href="' . e($note->url()) . '">' . $title . '</a>';

            // Shared note where the title is the same as the text
            if ($html === '<p>' . strip_tags($title) . '</p>') {
                $value = '<a href="' . e($note->url()) . '">' . strip_tags($html) . '</a>';

                return '<div>' . I18N::translate('%1$s: %2$s', $label, $value) . '</div>';
            }

            return
                '<div class="wt-text-overflow-elipsis">' .
                '<button type="button" class="btn btn-text p-0" href="#' . e($id) . '" data-bs-toggle="collapse" aria-controls="' . e($id) . '" aria-expanded="' . ($expanded ? 'true' : 'false') . '">' .
                view('icons/expand') .
                view('icons/collapse') .
                '</button> ' .
                '<span class="label">' . $label . ':</span> ' . $first_line .
                '</div>' .
                '<div id="' . e($id) . '" class="ps-4 collapse ut' . ($expanded ? ' show' : '') . '">' .
                $html .
                '</div>';
        }

        $label = '<span class="label">' . I18N::translate('Note') . '</span>';
        $html  = $this->valueFormatted($value, $tree);

        // Inline note with only one paragraph and inline markup?
        if ($html === strip_tags($html, ['a', 'em', 'p', 'strong']) && substr_count($html, '<p>') === 1) {
            $html  = strip_tags($html, ['a', 'em', 'strong']);
            $value = '<span class="ut">' . $html . '</span>';

            return '<div>' . I18N::translate('%1$s: %2$s', $label, $value) . '</div>';
        }

        $value = e(Note::firstLineOfTextFromHtml($html));
        $value = '<span class="ut collapse ' . ($expanded ? '' : 'show') . ' ' . e($id) . '">' . $value . '</span>';

        return
            '<div class="wt-text-overflow-elipsis">' .
            '<button type="button" class="btn btn-text p-0" href="#" data-bs-target=".' . e($id) . '" data-bs-toggle="collapse" aria-controls="' . e($id) . '" aria-expanded="' . ($expanded ? 'true' : 'false') . '">' .
            view('icons/expand') .
            view('icons/collapse') .
            '</button> ' .
            I18N::translate('%1$s: %2$s', $label, $value) .
            '</div>' .
            '<div class="ps-4 collapse ut ' . ($expanded ? 'show' : '') . ' ' . e($id) . '">' .
            $html .
            '</div>';
    }

    /**
     * Display the value of this type of element.
     */
    public function value(string $value, Tree $tree): string
    {
        if (preg_match('/^@(' . Gedcom::REGEX_XREF . ')@$/', $value, $match) === 1) {
            $note = Registry::noteFactory()->make($match[1], $tree);

            if ($note instanceof Note) {
                return $this->withMime($note->mime())->value($note->getTranslatedNote(), $tree);
            }
        }

        return parent::value($value, $tree);
    }
}
