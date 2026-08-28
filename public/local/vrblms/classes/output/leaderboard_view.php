<?php
namespace local_vrblms\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Rendering helpers for leaderboard.php - filter form, ranked table, time
 * formatting. Ported from the now-removed block_vrblms_leaderboard (see
 * LOG.md) rather than duplicated, since that block no longer exists.
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
            'class' => 'vrb-leaderboard-filters',
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
            'class' => 'btn btn-secondary btn-sm vrb-leaderboard-apply',
        ]);

        $out .= \html_writer::end_tag('form');
        return $out;
    }

    protected static function render_select(string $name, string $label, array $options, string $selected, bool $autosubmit): string {
        $attributes = ['name' => $name, 'id' => 'id_' . $name, 'class' => 'form-control form-control-sm'];
        if ($autosubmit) {
            $attributes['onchange'] = 'this.form.submit()';
        }
        $out = \html_writer::tag('label', $label, ['for' => 'id_' . $name, 'class' => 'vrb-leaderboard-label']);
        $out .= \html_writer::select($options, $name, $selected, false, $attributes);
        return \html_writer::div($out, 'vrb-leaderboard-field');
    }

    /**
     * @param \stdClass[] $rows ranked rows from local_vrblms\api.
     * @param int $highlightuserid userid to visually highlight (0 = none),
     *        e.g. the viewer's own row on their own-course/overall views.
     */
    public static function render_table(array $rows, int $highlightuserid = 0): string {
        if (empty($rows)) {
            return \html_writer::tag('p', get_string('norows', 'local_vrblms'));
        }

        $table = new \html_table();
        $table->attributes['class'] = 'table table-sm vrb-leaderboard-table';
        $table->head = [
            get_string('columnrank', 'local_vrblms'),
            get_string('columnname', 'local_vrblms'),
            get_string('columnlocation', 'local_vrblms'),
            get_string('columnscore', 'local_vrblms'),
            get_string('columnmodules', 'local_vrblms'),
            get_string('columntime', 'local_vrblms'),
        ];

        foreach ($rows as $row) {
            $location = trim(($row->city ?: '') . ($row->city && $row->state ? ', ' : '') . ($row->state ?: ''));
            $tablerow = new \html_table_row([
                $row->rank,
                \html_writer::span(s($row->fullname), '', ['title' => s($row->idnumber)]),
                $location !== '' ? s($location) : '-',
                number_format($row->score_percent, 1) . '%',
                $row->quizzes_completed . '/' . $row->quizzes_total,
                self::format_seconds($row->total_time_seconds),
            ]);
            if ($highlightuserid && (int) $row->userid === $highlightuserid) {
                $tablerow->attributes['class'] = 'vrb-leaderboard-own-row';
            }
            $table->data[] = $tablerow;
        }

        return \html_writer::div(\html_writer::table($table), 'vrb-leaderboard-table-wrap');
    }

    public static function format_seconds(int $seconds): string {
        if ($seconds <= 0) {
            return '-';
        }
        $minutes = intdiv($seconds, 60);
        $remainder = $seconds % 60;
        if ($minutes === 0) {
            return "{$remainder}s";
        }
        return "{$minutes}m {$remainder}s";
    }
}
