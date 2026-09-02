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

use local_vrblms\api;
use local_vrblms\ranking\strategy_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Orchestrates certificate issuance.
 *
 * HARD CONSTRAINT: this consumes \local_vrblms\api's per-brand and overall
 * leaderboard methods EXACTLY as they are. Every row those methods return
 * (already ranked and Top-N limited by local_vrblms) is a qualifier. This
 * class never re-ranks, re-sorts, re-filters, or reinterprets that output;
 * `rank` and `score_percent` are copied onto the certificate for display
 * only. Per-brand and overall are independent tracks - one employee can
 * qualify on several and receives one certificate per (brand-or-overall,
 * period).
 *
 * @package    local_vrbcert
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class issuer {

    /**
     * Evaluate the leaderboards now and issue certificates.
     *
     * @param string|null $period null => the configured award period
     * @param int $issuedby admin userid, or 0 for cron
     * @param bool $reissue replace certificates that already exist for this period
     * @param array $scope optional narrowing of what to issue this run:
     *   - 'tracks'    => string[] subset of ['perbrand','overall'] (default: both, still gated by the enabled settings)
     *   - 'brandkeys' => string[] brand cohort idnumbers to limit the per-brand track to (default: all brands)
     *   - 'topn'      => int override for the Top-N cut on every selected track (default: the per-track setting)
     * @return array summary: period, issued, reissued, skipped, errors[], bytrack[], scope
     */
    public static function run(?string $period = null, int $issuedby = 0, bool $reissue = false, array $scope = []): array {
        $config = get_config('local_vrbcert');

        $summary = [
            'period' => '',
            'issued' => 0,
            'reissued' => 0,
            'skipped' => 0,
            'errors' => [],
            'bytrack' => [],
            'disabled' => false,
        ];

        if (empty($config->enabled)) {
            $summary['disabled'] = true;
            return $summary;
        }

        $period = $period !== null && trim($period) !== ''
            ? trim($period)
            : (trim((string) $config->period) !== '' ? trim((string) $config->period) : date('Y'));
        $summary['period'] = $period;

        // Effective strategy key: the plugin override, else whatever local_vrblms
        // resolves as its default - recorded so an issued certificate is
        // reproducible.
        $strategykey = trim((string) ($config->strategykey ?? ''));
        $effectivestrategy = $strategykey !== '' ? $strategykey : self::site_default_strategy();

        $summary['scope'] = self::normalise_scope($scope);
        $qualifiers = self::collect_qualifiers($config, $strategykey !== '' ? $strategykey : null, $summary['scope']);

        foreach ($qualifiers as $q) {
            $trackkey = $q->brandkey;
            if (!isset($summary['bytrack'][$trackkey])) {
                $summary['bytrack'][$trackkey] = [
                    'brandname' => $q->brandname,
                    'track' => $q->track,
                    'issued' => 0,
                    'reissued' => 0,
                    'skipped' => 0,
                ];
            }

            try {
                $existing = issued_certificate::find($q->userid, $q->brandkey, $period);
                if ($existing && !$reissue) {
                    $summary['skipped']++;
                    $summary['bytrack'][$trackkey]['skipped']++;
                    continue;
                }
                if ($existing) {
                    issued_certificate::delete((int) $existing->id);
                }

                $data = certificate_data::from_leaderboard_row(
                    $q->row, $q->brandkey, $q->brandname, $q->track, $period
                );
                $pdf = certificate_generator::render($data);
                issued_certificate::create($data, $effectivestrategy, $issuedby, $pdf);

                if ($existing) {
                    $summary['reissued']++;
                    $summary['bytrack'][$trackkey]['reissued']++;
                } else {
                    $summary['issued']++;
                    $summary['bytrack'][$trackkey]['issued']++;
                }
            } catch (\Throwable $e) {
                $summary['errors'][] = "user {$q->userid} / {$q->brandkey}: " . $e->getMessage();
            }
        }

        return $summary;
    }

    /**
     * Clean a caller-supplied scope array into a predictable shape.
     *
     * @param array $scope
     * @return array {tracks: string[], brandkeys: string[], topn: int|null}
     */
    private static function normalise_scope(array $scope): array {
        $tracks = $scope['tracks'] ?? ['perbrand', 'overall'];
        $tracks = array_values(array_intersect(['perbrand', 'overall'], (array) $tracks));
        if (empty($tracks)) {
            $tracks = ['perbrand', 'overall'];
        }
        $brandkeys = array_values(array_filter(array_map('strval', (array) ($scope['brandkeys'] ?? []))));
        $topn = isset($scope['topn']) && (int) $scope['topn'] > 0 ? (int) $scope['topn'] : null;

        return ['tracks' => $tracks, 'brandkeys' => $brandkeys, 'topn' => $topn];
    }

    /**
     * Build the flat qualifier list from the two tracks, honouring the run scope.
     *
     * @param object $config
     * @param string|null $strategykey passed straight to the api (null = site default)
     * @param array $scope normalised scope from normalise_scope()
     * @return \stdClass[] each: {userid, brandkey, brandname, track, row}
     */
    private static function collect_qualifiers($config, ?string $strategykey, array $scope): array {
        $out = [];

        if (!empty($config->perbrand_enabled) && in_array('perbrand', $scope['tracks'], true)) {
            $topn = $scope['topn'] ?? max(1, (int) $config->perbrand_topn);
            foreach (api::get_brands() as $brand) {
                if (!empty($scope['brandkeys']) && !in_array($brand->idnumber, $scope['brandkeys'], true)) {
                    continue;
                }
                $rows = api::get_leaderboard($brand->idnumber, null, null, $strategykey, $topn);
                foreach ($rows as $row) {
                    $out[] = (object) [
                        'userid' => (int) $row->userid,
                        'brandkey' => $brand->idnumber,
                        'brandname' => $brand->name,
                        'track' => 'perbrand',
                        'row' => $row,
                    ];
                }
            }
        }

        if (!empty($config->overall_enabled) && in_array('overall', $scope['tracks'], true)) {
            $topn = $scope['topn'] ?? max(1, (int) $config->overall_topn);
            $rows = api::get_overall_leaderboard(null, null, $strategykey, $topn);
            $overallname = get_string('overall', 'local_vrbcert');
            foreach ($rows as $row) {
                $out[] = (object) [
                    'userid' => (int) $row->userid,
                    'brandkey' => 'overall',
                    'brandname' => $overallname,
                    'track' => 'overall',
                    'row' => $row,
                ];
            }
        }

        return $out;
    }

    /**
     * @return string the ranking strategy key local_vrblms would use by default
     */
    private static function site_default_strategy(): string {
        $key = (string) get_config('local_vrblms', 'defaultstrategy');
        $available = array_keys(strategy_manager::get_available_strategies());
        return in_array($key, $available, true) ? $key : strategy_manager::DEFAULT_KEY;
    }
}
