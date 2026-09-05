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

namespace local_vrbcontent\quiz;

use local_vrbcontent\csv_reader;
use local_vrbcontent\validated_result;

/**
 * Validates parsed Quiz CSV rows against the fixed source format: Q.No,
 * Topic, Question, A, B, C, D, Correct Answer, Explanation - one row is
 * one single-answer MCQ. Pure PHP - no Moodle DB calls.
 *
 * @package    local_vrbcontent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_row_validator {
    /** @var string[] The fixed, required column set for a quiz source CSV, in the order the brief defines them. */
    private const REQUIRED_COLUMNS = ['Q.No', 'Topic', 'Question', 'A', 'B', 'C', 'D', 'Correct Answer', 'Explanation'];

    /** @var string[] The four option-letter columns. */
    private const OPTION_LETTERS = ['A', 'B', 'C', 'D'];

    /**
     * @param array{header: string[], rows: array<int, array<string,string>>, error: string|null} $parsed
     * @return validated_result rows shaped as
     *         [{qno, topic, question, options: [letter=>text,...], correctanswer, explanation}, ...]
     */
    public static function validate(array $parsed): validated_result {
        $result = new validated_result();

        if ($parsed['error'] !== null) {
            $result->add_error(csv_reader::describe_error($parsed['error']));
            return $result;
        }

        $headerlookup = [];
        foreach ($parsed['header'] as $col) {
            $headerlookup[strtolower(trim($col))] = $col;
        }
        $missing = [];
        foreach (self::REQUIRED_COLUMNS as $required) {
            if (!array_key_exists(strtolower($required), $headerlookup)) {
                $missing[] = $required;
            }
        }
        if (!empty($missing)) {
            $result->add_error('CSV is missing required column(s): ' . implode(', ', $missing));
            return $result;
        }

        $seenqno = [];
        foreach ($parsed['rows'] as $i => $row) {
            $displayrow = $i + 2;
            $get = function (string $col) use ($row, $headerlookup): string {
                $sourcecol = $headerlookup[strtolower($col)];
                return trim((string) ($row[$sourcecol] ?? ''));
            };

            $qno = $get('Q.No');
            $topic = $get('Topic');
            $question = $get('Question');
            $options = [];
            foreach (self::OPTION_LETTERS as $letter) {
                $value = $get($letter);
                if ($value !== '') {
                    $options[$letter] = $value;
                }
            }
            $correctanswer = strtoupper($get('Correct Answer'));
            $explanation = $get('Explanation');

            if ($qno === '') {
                $result->add_error("Row $displayrow: Q.No is empty.");
            } else if (isset($seenqno[$qno])) {
                $result->add_error("Row $displayrow: duplicate Q.No \"$qno\" (already used on row {$seenqno[$qno]}).");
            } else {
                $seenqno[$qno] = $displayrow;
            }

            if ($question === '') {
                $result->add_error("Row $displayrow: Question text is empty.");
            }

            if (count($options) < 2) {
                $result->add_error("Row $displayrow: fewer than 2 of A-D are populated.");
            }

            if ($correctanswer === '') {
                $result->add_error("Row $displayrow: Correct Answer is empty.");
            } else if (!isset($options[$correctanswer])) {
                $result->add_error(
                    "Row $displayrow: Correct Answer \"$correctanswer\" is not one of the populated options."
                );
            }

            if ($topic === '') {
                $result->add_warning("Row $displayrow: Topic is empty.");
            }
            if ($explanation === '') {
                $result->add_warning("Row $displayrow: Explanation is empty - general feedback will be left blank.");
            }

            $result->rows[] = [
                'qno' => $qno,
                'topic' => $topic,
                'question' => $question,
                'options' => $options,
                'correctanswer' => $correctanswer,
                'explanation' => $explanation,
            ];
        }

        return $result;
    }
}
