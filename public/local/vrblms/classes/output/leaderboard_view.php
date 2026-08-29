<?php
namespace local_vrblms\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Rendering helpers for leaderboard.php - filter form, ranked table,
 * section headings, time formatting. Ported from the now-removed
 * block_vrblms_leaderboard (see LOG.md).
 *
 * Markup is hand-built (not html_table) so the ranked table can carry the
 * VRB design treatment: rank badges, avatar initials, brand-accented
 * section headings, an own-row highlight. All presentational classes are
 * styled in theme_vrblms/style/custom.css section 10.
 */
class leaderboard_view {

    /** Query-string params this view owns, excluded from hidden passthrough fields. */
    const FILTER_PARAMS = ['vrbbrand', 'vrbstate', 'vrbcity', 'vrbstrategy'];

    /**
     * The admin/manager filter form: Brand (incl. "Overall (all brands)"),
     * State, City, Ranking strategy. GET-based, auto-submitting selects -
     * deliberately plain HTML/inline attributes, not an AMD module, since
     * ARCHITECTURE.md flags this theme's JS build pipeline as unverified.
     *
     * @param \stdClass[] $brands {idnumber, name}
     * @param array $strategies key => display string
     */
    public static function render_filter_form(
        \moodle_url $actionurl,
        array $brands,
        string $selectedbrand,
        array $states,
        string $selectedstate,
        array $cities,
        string $selectedcity,
        array $strategies,
        string $selectedstrategy
    ): string {
        $out = \html_writer::start_tag('form', [
            'method' => 'get',
            'action' => $actionurl->out_omit_querystring(),
            'class' => 'vrb-lb-filters',
        ]);

        foreach ($actionurl->params() as $name => $value) {
            if (in_array($name, self::FILTER_PARAMS, true)) {
                continue;
            }
            $out .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
        }

        // html_writer::select() does not escape option labels (only
        // optgroup labels) - escape these ourselves since brand/state/city
        // values are free text sourced from the database (HR/CSV-imported),
        // not from a fixed enum.
        $brandoptions = ['' => get_string('overallbrand', 'local_vrblms')];
        foreach ($brands as $brand) {
            $brandoptions[$brand->idnumber] = s($brand->name);
        }
        $out .= self::render_select('vrbbrand', get_string('filterbrand', 'local_vrblms'),
                $brandoptions, $selectedbrand, false);

        $stateoptions = ['' => get_string('allstates', 'local_vrblms')]
                + array_combine($states, array_map('s', $states));
        $out .= self::render_select('vrbstate', get_string('filterstate', 'local_vrblms'),
                $stateoptions, $selectedstate, true);

        $cityoptions = ['' => get_string('allcities', 'local_vrblms')]
                + array_combine($cities, array_map('s', $cities));
        $out .= self::render_select('vrbcity', get_string('filtercity', 'local_vrblms'),
                $cityoptions, $selectedcity, true);

        $strategyoptions = ['' => get_string('pluginname', 'local_vrblms')] + $strategies;
        $out .= self::render_select('vrbstrategy', get_string('filterstrategy', 'local_vrblms'),
                $strategyoptions, $selectedstrategy, true);

        $out .= \html_writer::empty_tag('input', [
            'type' => 'submit',
            'value' => get_string('applyfilters', 'local_vrblms'),
            'class' => 'btn btn-secondary btn-sm vrb-lb-apply',
        ]);

        $out .= \html_writer::end_tag('form');
        return \html_writer::div($out, 'vrb-lb-filters-card');
    }

    protected static function render_select(string $name, string $label, array $options, string $selected, bool $autosubmit): string {
        $attributes = ['name' => $name, 'id' => 'id_' . $name, 'class' => 'form-control form-control-sm'];
        if ($autosubmit) {
            $attributes['onchange'] = 'this.form.submit()';
        }
        $out = \html_writer::tag('label', $label, ['for' => 'id_' . $name]);
        $out .= \html_writer::select($options, $name, $selected, false, $attributes);
        return \html_writer::div($out, 'vrb-lb-field');
    }

    /**
     * A brand-accented section heading, e.g. "Veeba" or "Overall (all brands)".
     *
     * @param string $name display text (already plain, will be escaped here)
     * @param string $key   'brand_veeba' | 'brand_woktok' | 'brand_zyro' | 'overall' | ''
     *                       - used only to pick the accent colour class.
     */
    public static function render_section_heading(string $name, string $key = ''): string {
        $slug = '';
        if (strpos($key, 'brand_') === 0) {
            $slug = substr($key, strlen('brand_'));
        } else if ($key === 'overall') {
            $slug = 'overall';
        }
        $class = 'vrb-lb-section' . ($slug !== '' ? ' vrb-lb-section--' . $slug : '');
        return \html_writer::tag('h3', s($name), ['class' => $class]);
    }

    /**
     * @param \stdClass[] $rows ranked rows from local_vrblms\api.
     * @param int $highlightuserid userid to visually highlight (0 = none),
     *        e.g. the viewer's own row on their own-course/overall views.
     */
    public static function render_table(array $rows, int $highlightuserid = 0): string {
        if (empty($rows)) {
            return \html_writer::tag('p', get_string('norows', 'local_vrblms'), ['class' => 'text-muted']);
        }

        $head = \html_writer::tag('tr',
            \html_writer::tag('th', get_string('columnrank', 'local_vrblms'), ['class' => 'vrb-lb-rank-cell'])
            . \html_writer::tag('th', get_string('columnname', 'local_vrblms'))
            . \html_writer::tag('th', get_string('columnlocation', 'local_vrblms'))
            . \html_writer::tag('th', get_string('columnscore', 'local_vrblms'), ['class' => 'vrb-lb-num'])
            . \html_writer::tag('th', get_string('columnmodules', 'local_vrblms'), ['class' => 'vrb-lb-num'])
            . \html_writer::tag('th', get_string('columntime', 'local_vrblms'), ['class' => 'vrb-lb-num'])
        );

        $bodyrows = '';
        foreach ($rows as $row) {
            $location = trim(($row->city ?: '') . ($row->city && $row->state ? ', ' : '') . ($row->state ?: ''));

            $rankclass = 'vrb-lb-rank';
            $rank = (int) $row->rank;
            if ($rank >= 1 && $rank <= 3) {
                $rankclass .= ' vrb-lb-rank--' . $rank;
            }

            $namecell = \html_writer::div(
                \html_writer::span(self::initials($row->fullname), 'vrb-lb-avatar', ['aria-hidden' => 'true'])
                . \html_writer::span(s($row->fullname), 'vrb-lb-name', ['title' => s($row->idnumber)]),
                'vrb-lb-name-cell'
            );

            $cells = \html_writer::tag('td', \html_writer::span($rank, $rankclass), ['class' => 'vrb-lb-rank-cell'])
                . \html_writer::tag('td', $namecell)
                . \html_writer::tag('td', $location !== '' ? s($location) : '—')
                . \html_writer::tag('td', number_format((float) $row->score_percent, 1) . '%', ['class' => 'vrb-lb-num vrb-lb-score'])
                . \html_writer::tag('td', $row->quizzes_completed . '/' . $row->quizzes_total, ['class' => 'vrb-lb-num'])
                . \html_writer::tag('td', self::format_seconds((int) $row->total_time_seconds), ['class' => 'vrb-lb-num']);

            $trattrs = [];
            if ($highlightuserid && (int) $row->userid === $highlightuserid) {
                $trattrs['class'] = 'vrb-leaderboard-own-row';
            }
            $bodyrows .= \html_writer::tag('tr', $cells, $trattrs);
        }

        $table = \html_writer::tag('table',
            \html_writer::tag('thead', $head) . \html_writer::tag('tbody', $bodyrows),
            ['class' => 'vrb-lb-table']);

        return \html_writer::div(\html_writer::div($table, 'vrb-lb-scroll'), 'vrb-lb-table-card');
    }

    /** First + last initial of a full name, for the avatar circle. */
    protected static function initials(string $fullname): string {
        $parts = preg_split('/\s+/', trim($fullname), -1, PREG_SPLIT_NO_EMPTY);
        if (empty($parts)) {
            return '?';
        }
        $first = \core_text::substr($parts[0], 0, 1);
        $last = count($parts) > 1 ? \core_text::substr($parts[count($parts) - 1], 0, 1) : '';
        return \core_text::strtoupper($first . $last);
    }

    public static function format_seconds(int $seconds): string {
        if ($seconds <= 0) {
            return '—';
        }
        $minutes = intdiv($seconds, 60);
        $remainder = $seconds % 60;
        if ($minutes === 0) {
            return "{$remainder}s";
        }
        return "{$minutes}m {$remainder}s";
    }
}
