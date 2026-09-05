<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_vrbcontent\book;

use local_vrbcontent\import_batch;
use local_vrbcontent\validated_result;

/**
 * Turns validated Book rows into real mdl_book_chapters rows. Mirrors the
 * exact sequence mod_book's own edit.php/delete.php use for a new chapter
 * (no book_add_chapter() helper exists in this Moodle version - the real
 * insertion is a direct $DB->insert_record() plus tag/file/event/revision
 * bookkeeping) - verified against installed source in Phase 0, see LOG.md
 * 2026-09-05.
 *
 * @package    local_vrbcontent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class book_provisioner {
    /**
     * @param \stdClass $course
     * @param int $sectionnum
     * @param int $cmid > 0 to use an existing Book activity, 0 to create one.
     * @param string $newbooktitle used only when $cmid === 0.
     * @return \stdClass {cmid, bookid}
     */
    public static function get_or_create_book(\stdClass $course, int $sectionnum, int $cmid, string $newbooktitle): \stdClass {
        global $DB, $CFG;

        if ($cmid > 0) {
            $cm = get_coursemodule_from_id('book', $cmid, $course->id, false, MUST_EXIST);
            return (object) ['cmid' => (int) $cm->id, 'bookid' => (int) $cm->instance];
        }

        require_once($CFG->dirroot . '/course/lib.php');

        $bookdata = new \stdClass();
        $bookdata->course = $course->id;
        $bookdata->modulename = 'book';
        $bookdata->section = $sectionnum;
        $bookdata->visible = 1;
        $bookdata->name = $newbooktitle;
        $bookdata->introeditor = ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0];
        // mod_book defaults (mod_form.php): no chapter numbering, TOC-only nav, no custom titles.
        $bookdata->numbering = 0;
        $bookdata->navstyle = 1;
        $bookdata->customtitles = 0;
        $bookdata = create_module($bookdata);

        return (object) ['cmid' => (int) $bookdata->coursemodule, 'bookid' => (int) $bookdata->instance];
    }

    /**
     * Appends one chapter per validated row, at the end of the book.
     *
     * @param int $bookid
     * @param int $cmid
     * @param validated_result $result
     * @param import_batch $batch created chapter ids are recorded against this batch.
     * @return int[] created chapter ids.
     */
    public static function create_chapters(int $bookid, int $cmid, validated_result $result, import_batch $batch): array {
        global $DB;

        $book = $DB->get_record('book', ['id' => $bookid], '*', MUST_EXIST);
        $context = \context_module::instance($cmid);

        $maxpagenum = (int) $DB->get_field_sql(
            'SELECT MAX(pagenum) FROM {book_chapters} WHERE bookid = ?',
            [$bookid]
        );

        $createdids = [];
        $pagenum = $maxpagenum;
        foreach ($result->rows as $row) {
            $pagenum++;

            $data = new \stdClass();
            $data->bookid = $bookid;
            $data->pagenum = $pagenum;
            $data->subchapter = 0;
            $data->title = (string) $row['title'];
            $data->hidden = 0;
            $data->importsrc = '';
            $data->content = self::render_content($row['fields']);
            $data->contentformat = FORMAT_HTML;
            $data->timecreated = time();
            $data->timemodified = time();

            $data->id = $DB->insert_record('book_chapters', $data);

            $chapter = $DB->get_record('book_chapters', ['id' => $data->id]);
            \mod_book\event\chapter_created::create_from_chapter($book, $context, $chapter)->trigger();

            $createdids[] = (int) $data->id;
            $batch->add_item('chapter', (int) $data->id);
        }

        $DB->set_field('book', 'revision', $book->revision + 1, ['id' => $bookid]);

        return $createdids;
    }

    /**
     * Deletes chapters previously created by a prior import batch (the
     * "Re-import: replace" path) - mirrors mod_book/delete.php's own
     * confirmed-delete sequence exactly (tag removal, file area cleanup,
     * record delete, chapter_deleted event, one revision bump), never a raw
     * DELETE FROM. These imported chapters have no subchapters, so the
     * cascade-delete-subchapters branch in core's delete.php doesn't apply.
     *
     * @param int $bookid
     * @param int $cmid
     * @param int[] $chapterids
     */
    public static function delete_chapters(int $bookid, int $cmid, array $chapterids): void {
        global $DB;

        if (empty($chapterids)) {
            return;
        }

        $book = $DB->get_record('book', ['id' => $bookid], '*', MUST_EXIST);
        $context = \context_module::instance($cmid);
        $fs = get_file_storage();

        foreach ($chapterids as $chapterid) {
            $chapter = $DB->get_record('book_chapters', ['id' => $chapterid, 'bookid' => $bookid]);
            if (!$chapter) {
                continue; // Already gone - tolerate a partially-cleaned-up prior batch.
            }
            \core_tag_tag::remove_all_item_tags('mod_book', 'book_chapters', $chapter->id);
            $fs->delete_area_files($context->id, 'mod_book', 'chapter', $chapter->id);
            $DB->delete_records('book_chapters', ['id' => $chapter->id]);
            \mod_book\event\chapter_deleted::create_from_chapter($book, $context, $chapter)->trigger();
        }

        $DB->set_field('book', 'revision', $book->revision + 1, ['id' => $bookid]);
    }

    /**
     * First mapped template field is always the chapter title (handled by
     * the caller, not here - $row['title'] is already resolved). Every
     * other field becomes one <h5>{label}</h5> followed by the raw cell
     * value, HTML-escaped. Empty values are preserved as an explicit "—",
     * never suppressed or replaced with an invented placeholder.
     *
     * @param array<string,string> $fields label => value, in template order.
     * @return string
     */
    private static function render_content(array $fields): string {
        $html = '';
        foreach ($fields as $label => $value) {
            $displayvalue = ($value === null || trim((string) $value) === '') ? '&mdash;' : nl2br(s($value));
            $html .= \html_writer::tag('h5', s($label));
            $html .= \html_writer::tag('p', $displayvalue);
        }
        return $html;
    }
}
