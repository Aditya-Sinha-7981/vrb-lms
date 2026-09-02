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

namespace local_vrbcert;

defined('MOODLE_INTERNAL') || die();

/**
 * Everything one certificate needs in order to be rendered.
 *
 * Values come straight from a \local_vrblms\api leaderboard row (plus the
 * configured period) - this object never re-derives rank or score.
 *
 * @package    local_vrbcert
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certificate_data {

    /** @var int */
    public $userid;
    /** @var string Already-formatted full name (from the API row). */
    public $fullname;
    /** @var string Employee Code (user idnumber, from the API row). */
    public $empcode;
    /** @var string Brand cohort idnumber, or 'overall'. */
    public $brandkey;
    /** @var string Display brand/overall name. */
    public $brandname;
    /** @var string 'perbrand' | 'overall'. */
    public $track;
    /** @var int|null Leaderboard rank at issue time (display only). */
    public $rank;
    /** @var float|null Score percent at issue time (display only). */
    public $scorepercent;
    /** @var string Award-period label. */
    public $period;
    /** @var int Issue timestamp. */
    public $issuedate;

    /**
     * @param array $props keyed by the public property names above.
     */
    public function __construct(array $props = []) {
        foreach ($props as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
        if (empty($this->issuedate)) {
            $this->issuedate = time();
        }
    }

    /**
     * Build from a \local_vrblms\api leaderboard row.
     *
     * @param \stdClass $row row from api::get_leaderboard() / get_overall_leaderboard()
     * @param string $brandkey brand cohort idnumber, or 'overall'
     * @param string $brandname display name
     * @param string $track 'perbrand' | 'overall'
     * @param string $period award-period label
     * @return self
     */
    public static function from_leaderboard_row(\stdClass $row, string $brandkey, string $brandname,
            string $track, string $period): self {
        return new self([
            'userid' => (int) $row->userid,
            'fullname' => (string) $row->fullname,
            'empcode' => (string) ($row->idnumber ?? ''),
            'brandkey' => $brandkey,
            'brandname' => $brandname,
            'track' => $track,
            'rank' => isset($row->rank) ? (int) $row->rank : null,
            'scorepercent' => isset($row->score_percent) ? (float) $row->score_percent : null,
            'period' => $period,
            'issuedate' => time(),
        ]);
    }
}
