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

/**
 * Strings for local_vrbcontent.
 *
 * @package    local_vrbcontent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'VRB content importer';

// Capabilities.
$string['vrbcontent:import'] = 'Import Book/Quiz content via local_vrbcontent';

// Admin page / index.
$string['adminpageheading'] = 'VRB content importer';
$string['indexintro'] = 'Import client-supplied Book content or quiz questions from a CSV file into an existing course. Placement (course/section/activity) is always chosen manually - this tool never creates or discovers a brand/category/course on its own.';
$string['importbook'] = 'Import Book content';
$string['importbookdesc'] = 'Turn a structured product/knowledge CSV into Book chapters.';
$string['importquiz'] = 'Import Quiz questions';
$string['importquizdesc'] = 'Turn the fixed quiz CSV format into Moodle quiz questions.';

// Book wizard.
$string['bookimporttitle'] = 'Import Book content';
$string['step1title'] = 'Step 1: choose a course';
$string['step2title'] = 'Step 2: choose a section and Book activity';
$string['step3title'] = 'Step 3: choose or define a field template';
$string['step4title'] = 'Step 4: upload the CSV file';
$string['step5title'] = 'Step 5: preview and confirm';
$string['selectcourse'] = 'Course';
$string['selectsection'] = 'Section';
$string['selectbook'] = 'Book activity';
$string['createnewbook'] = '-- Create a new Book activity --';
$string['newbooktitle'] = 'New Book activity name';
$string['template'] = 'Field template';
$string['definenewtemplate'] = '-- Define a new template --';
$string['templatename'] = 'Template name';
$string['titlehint'] = 'The first field you enter becomes each row\'s Book chapter title; every other field becomes labeled chapter content.';
$string['fieldlabel'] = 'Column label (must match the CSV header exactly, case-insensitive)';
$string['addfields'] = 'Add {no} more fields';
$string['needatleasttwofields'] = 'Define at least two fields (one title, one content field).';
$string['duplicatefieldlabel'] = 'The same column label is used more than once.';
$string['csvfile'] = 'CSV file';

// Confirm step.
$string['confirmimport'] = 'Confirm import';
$string['blockednotice'] = 'This file has structural errors and cannot be imported. Fix the source file and start again.';
$string['backtostart'] = 'Back to start';
$string['errorsheading'] = 'Errors - must fix before import';
$string['warningsheading'] = 'Warnings - will still import';
$string['rowcount'] = '{$a} row(s) ready to import.';
$string['duplicatenotice'] = 'This exact content was already imported on {$a}.';
$string['duplicateaction'] = 'What should happen?';
$string['actionskip'] = 'Skip - leave the existing content as-is';
$string['actionreplace'] = 'Re-import (replace) - delete the previous import\'s content and recreate it';
$string['actioncancel'] = 'Cancel';
$string['importcancelled'] = 'Import cancelled.';
$string['importskipped'] = 'Import skipped - existing content left as-is.';
$string['importsuccess'] = 'Import complete: {$a} chapter(s) created.';

// Quiz wizard.
$string['quizimporttitle'] = 'Import Quiz questions';
$string['quizstep2title'] = 'Step 2: choose a section and Quiz activity';
$string['quizstep3title'] = 'Step 3: quiz settings';
$string['quizstep4title'] = 'Step 4: upload the CSV file';
$string['quizstep5title'] = 'Step 5: preview and confirm';
$string['selectquiz'] = 'Quiz activity';
$string['createnewquiz'] = '-- Create a new Quiz activity --';
$string['newquiztitle'] = 'New Quiz activity name';
$string['modulelabel'] = 'Question bank module label (e.g. "Module 1")';
$string['passpercent'] = 'Pass percentage';
$string['maxattempts'] = 'Max attempts (0 = unlimited)';
$string['invalidpasspercent'] = 'Enter a pass percentage between 1 and 100.';
$string['invalidmaxattempts'] = 'Enter 0 (unlimited) or a positive number of attempts.';
$string['existingquiznotice'] = 'This Quiz activity already exists - its pass percentage, max attempts, and completion settings are left as-is. Only the questions below are added to it.';
$string['gatesection'] = 'Lock this section behind passing this quiz';
$string['nogating'] = '-- No gating for now --';
$string['quizimportsuccess'] = 'Import complete: {$a} question(s) created.';
$string['replaceblockedbyattempts'] = 'Cannot replace this quiz\'s questions - it already has real learner attempts, and replacing questions now would corrupt their grading history. Nothing was changed. Use a fresh quiz activity instead, or leave this one\'s existing questions as-is.';
