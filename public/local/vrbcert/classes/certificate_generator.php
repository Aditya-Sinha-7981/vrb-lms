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
 * Renders a certificate to a PDF byte string using Moodle's bundled TCPDF
 * wrapper (\pdf, lib/pdflib.php). No external dependency.
 *
 * Fixed A4-landscape layout; the swappable parts (wording, signatory,
 * per-brand name + band colour) come from {@see template}.
 *
 * @package    local_vrbcert
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certificate_generator {

    /** Page geometry (mm), A4 landscape. */
    const PAGE_W = 297;
    const PAGE_H = 210;
    /** Left/right content margin (mm). */
    const MARGIN = 30;

    /**
     * Render a real certificate.
     *
     * @param certificate_data $data
     * @return string PDF bytes
     */
    public static function render(certificate_data $data): string {
        return self::build(template::get_spec(), $data);
    }

    /**
     * Render a sample certificate from current settings + dummy data.
     * Nothing is stored.
     *
     * @return string PDF bytes
     */
    public static function preview(): string {
        $period = trim((string) get_config('local_vrbcert', 'period'));
        $data = new certificate_data([
            'userid' => 0,
            'fullname' => 'Asha Verma',
            'empcode' => 'EMP-0000',
            'brandkey' => 'brand_veeba',
            'brandname' => 'Veeba',
            'track' => 'perbrand',
            'rank' => 1,
            'scorepercent' => 95.0,
            'period' => $period !== '' ? $period : date('Y'),
            'issuedate' => time(),
        ]);
        return self::build(template::get_spec(), $data);
    }

    /**
     * @param \stdClass $spec from template::get_spec()
     * @param certificate_data $d
     * @return string PDF bytes
     */
    private static function build(\stdClass $spec, certificate_data $d): string {
        global $CFG;
        require_once($CFG->libdir . '/pdflib.php');

        $accent = self::rgb($spec->accentcolour);
        $band = self::rgb(template::brand_colour($d->brandkey));
        $ink = [29, 29, 29];
        $muted = [110, 110, 110];
        $centrew = self::PAGE_W - (2 * self::MARGIN);

        $pdf = new \pdf('L', 'mm', 'A4');
        $pdf->SetCreator('local_vrbcert');
        $pdf->SetAuthor(format_string(get_site()->fullname));
        $pdf->SetTitle($spec->title . ' - ' . $d->fullname);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->SetMargins(0, 0, 0);
        $pdf->AddPage();

        // Border: a heavy outer rule and a hairline inner rule in the brand colour.
        $pdf->SetDrawColor($band[0], $band[1], $band[2]);
        $pdf->SetLineWidth(1.4);
        $pdf->Rect(12, 12, self::PAGE_W - 24, self::PAGE_H - 24);
        $pdf->SetLineWidth(0.3);
        $pdf->Rect(15.5, 15.5, self::PAGE_W - 31, self::PAGE_H - 31);

        // Wordmark.
        $pdf->SetFont('freesans', 'B', 20);
        $pdf->SetTextColor($accent[0], $accent[1], $accent[2]);
        self::centre($pdf, 28, 'VRB LMS', $centrew);

        // Brand / track band.
        $pdf->SetFont('freesans', 'B', 13);
        $pdf->SetTextColor($band[0], $band[1], $band[2]);
        self::centre($pdf, 41, self::upper(template::brand_label($d->brandkey, $d->brandname)), $centrew);
        $pdf->SetDrawColor($band[0], $band[1], $band[2]);
        $pdf->SetLineWidth(0.4);
        $pdf->Line(self::PAGE_W / 2 - 40, 51, self::PAGE_W / 2 + 40, 51);

        // Title.
        $pdf->SetFont('freesans', 'B', 30);
        $pdf->SetTextColor($ink[0], $ink[1], $ink[2]);
        self::centre($pdf, 62, $spec->title, $centrew);

        // Presented-to line.
        $pdf->SetFont('freesans', '', 11);
        $pdf->SetTextColor($muted[0], $muted[1], $muted[2]);
        self::centre($pdf, 88, $spec->presentedto, $centrew);

        // Recipient name.
        $pdf->SetFont('freesans', 'B', 26);
        $pdf->SetTextColor($accent[0], $accent[1], $accent[2]);
        self::centre($pdf, 96, $d->fullname, $centrew);

        // Body lines (wrapped).
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetFont('freesans', '', 13);
        $pdf->MultiCell($centrew, 0, template::expand($spec->bodyline1, $d), 0, 'C', false, 1, self::MARGIN, 116);
        if (trim($spec->bodyline2) !== '') {
            $pdf->SetFont('freesans', '', 12);
            $pdf->MultiCell($centrew, 0, template::expand($spec->bodyline2, $d), 0, 'C', false, 1, self::MARGIN, 132);
        }

        // Divider.
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->SetLineWidth(0.3);
        $pdf->Line(self::PAGE_W / 2 - 55, 158, self::PAGE_W / 2 + 55, 158);

        // Signatory block (left).
        $pdf->SetDrawColor(120, 120, 120);
        $pdf->SetLineWidth(0.3);
        $pdf->Line(42, 176, 112, 176);
        $pdf->SetFont('freesans', 'B', 11);
        $pdf->SetTextColor($ink[0], $ink[1], $ink[2]);
        $pdf->SetXY(42, 177);
        $pdf->Cell(70, 5, $spec->signatoryname, 0, 2, 'L');
        $pdf->SetFont('freesans', '', 9);
        $pdf->SetTextColor($muted[0], $muted[1], $muted[2]);
        $pdf->Cell(70, 4, $spec->signatorytitle, 0, 0, 'L');

        // Issue date block (right).
        $pdf->SetDrawColor(120, 120, 120);
        $pdf->Line(self::PAGE_W - 112, 176, self::PAGE_W - 42, 176);
        $pdf->SetFont('freesans', '', 10);
        $pdf->SetTextColor($ink[0], $ink[1], $ink[2]);
        $pdf->SetXY(self::PAGE_W - 112, 177);
        $pdf->Cell(70, 5, get_string('issuedon', 'local_vrbcert',
            userdate($d->issuedate, get_string('strftimedate', 'langconfig'))), 0, 0, 'R');

        // Footer reference line (period middot employee-code).
        $ref = $d->period . '   ' . "\xC2\xB7" . '   ' . $d->empcode;
        $pdf->SetFont('freesans', '', 8);
        $pdf->SetTextColor(150, 150, 150);
        self::centre($pdf, self::PAGE_H - 20, $ref, $centrew);

        return $pdf->Output('', 'S');
    }

    /**
     * Write one centred line at a given Y across the centred content column.
     *
     * @param \pdf $pdf
     * @param float $y
     * @param string $text
     * @param float $w
     */
    private static function centre(\pdf $pdf, float $y, string $text, float $w): void {
        $pdf->SetXY(self::MARGIN, $y);
        $pdf->Cell($w, 8, $text, 0, 0, 'C');
    }

    /**
     * Uppercase, multibyte-safe.
     *
     * @param string $text
     * @return string
     */
    private static function upper(string $text): string {
        return \core_text::strtoupper($text);
    }

    /**
     * '#rrggbb' -> [r, g, b].
     *
     * @param string $hex
     * @return int[]
     */
    private static function rgb(string $hex): array {
        $hex = ltrim(trim($hex), '#');
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            $hex = '000b43';
        }
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
