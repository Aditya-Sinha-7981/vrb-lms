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
 * Resolves plugin settings into a render spec and expands text tokens.
 *
 * The layout itself is fixed (see certificate_generator); this class only
 * supplies the swappable bits: the wording, the signatory, and the
 * per-brand printed name + band colour.
 *
 * @package    local_vrbcert
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template {

    /** Brand cohort idnumber => settings slug. Environment brands. */
    const BRAND_SLUGS = [
        'brand_veeba' => 'veeba',
        'brand_woktok' => 'woktok',
        'brand_zyro' => 'zyro',
    ];

    /**
     * @return \stdClass render spec: title, presentedto, bodyline1, bodyline2,
     *         signatoryname, signatorytitle, accentcolour (hex).
     */
    public static function get_spec(): \stdClass {
        $config = get_config('local_vrbcert');

        $spec = new \stdClass();
        $spec->title = self::setting($config, 'certtitle', get_string('certtitle_default', 'local_vrbcert'));
        $spec->presentedto = self::setting($config, 'presentedto', get_string('presentedto_default', 'local_vrbcert'));
        $spec->bodyline1 = self::setting($config, 'bodyline1', get_string('bodyline1_default', 'local_vrbcert'));
        $spec->bodyline2 = self::setting($config, 'bodyline2', get_string('bodyline2_default', 'local_vrbcert'));
        $spec->signatoryname = self::setting($config, 'signatoryname', get_string('signatoryname_default', 'local_vrbcert'));
        $spec->signatorytitle = self::setting($config, 'signatorytitle', get_string('signatorytitle_default', 'local_vrbcert'));
        $spec->accentcolour = self::colour(self::setting($config, 'accentcolour', '#000B43'), '#000B43');

        return $spec;
    }

    /**
     * The printed brand name for a certificate: the admin override if set,
     * otherwise the display name captured at issue time.
     *
     * @param string $brandkey brand cohort idnumber, or 'overall'
     * @param string $fallback display name to use when no override is set
     * @return string
     */
    public static function brand_label(string $brandkey, string $fallback): string {
        if (isset(self::BRAND_SLUGS[$brandkey])) {
            $override = trim((string) get_config('local_vrbcert', 'brand_' . self::BRAND_SLUGS[$brandkey] . '_label'));
            if ($override !== '') {
                return $override;
            }
        }
        return $fallback;
    }

    /**
     * The band colour for a certificate. Per-brand colour for a brand
     * certificate; the default accent for the overall track.
     *
     * @param string $brandkey brand cohort idnumber, or 'overall'
     * @return string hex colour
     */
    public static function brand_colour(string $brandkey): string {
        $default = self::colour((string) get_config('local_vrbcert', 'accentcolour'), '#000B43');
        if (isset(self::BRAND_SLUGS[$brandkey])) {
            return self::colour(
                (string) get_config('local_vrbcert', 'brand_' . self::BRAND_SLUGS[$brandkey] . '_colour'),
                $default
            );
        }
        return $default;
    }

    /**
     * Expand {placeholders} in a template string against one certificate's data.
     *
     * @param string $text
     * @param certificate_data $d
     * @return string plain text
     */
    public static function expand(string $text, certificate_data $d): string {
        $score = $d->scorepercent === null ? '' : format_float($d->scorepercent, 1) . '%';
        $rank = $d->rank === null ? '' : (string) $d->rank;
        $replacements = [
            '{fullname}' => $d->fullname,
            '{brandname}' => self::brand_label($d->brandkey, $d->brandname),
            '{empcode}' => $d->empcode,
            '{score}' => $score,
            '{rank}' => $rank,
            '{rankordinal}' => $d->rank === null ? '' : self::ordinal($d->rank),
            '{period}' => $d->period,
            '{date}' => userdate($d->issuedate, get_string('strftimedate', 'langconfig')),
            '{sitename}' => format_string(get_site()->fullname, true, ['context' => \context_system::instance()]),
        ];
        return strtr($text, $replacements);
    }

    /**
     * 1 => "1st", 2 => "2nd", 3 => "3rd", 11 => "11th" ...
     *
     * @param int $n
     * @return string
     */
    public static function ordinal(int $n): string {
        $abs = abs($n);
        if (($abs % 100) >= 11 && ($abs % 100) <= 13) {
            $suffix = 'th';
        } else {
            $suffix = ['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'][$abs % 10];
        }
        return $n . $suffix;
    }

    /**
     * @param object $config
     * @param string $key
     * @param string $default
     * @return string
     */
    private static function setting($config, string $key, string $default): string {
        $value = isset($config->$key) ? trim((string) $config->$key) : '';
        return $value === '' ? $default : $value;
    }

    /**
     * Normalise a stored colour value to a #rrggbb string, or a fallback.
     *
     * @param string $value
     * @param string $fallback
     * @return string
     */
    private static function colour(string $value, string $fallback): string {
        $value = trim($value);
        if (preg_match('/^#?[0-9a-fA-F]{6}$/', $value)) {
            return '#' . ltrim($value, '#');
        }
        return $fallback;
    }
}
