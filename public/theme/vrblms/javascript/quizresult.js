/*
 * theme_vrblms — quiz results banner.
 *
 * Progressive enhancement for mod/quiz/review.php (the post-submit
 * landing). Reads the grade Moove already renders in
 * .moove-quizreviewsummary and injects a score-ring + pass/fail banner
 * at the top, matching docs/design_refer/quiz_results_vrb_learning_hub/.
 * Plain footer script (declared in the theme config.php) — no AMD build,
 * no core edits. Styling lives in style/custom.css (.vrb-qr-*).
 *
 * The review page carries no grade-to-pass element, so the pass line is
 * fixed at 60% — this environment's uniform pass mark across all three
 * brand quizzes (see LOG.md). If quizzes ever get differing pass marks
 * this needs the real value (server-side / a mod_quiz renderer).
 */
(function () {
    "use strict";

    if (!document.body || document.body.id !== "page-mod-quiz-review") {
        return;
    }

    var PASS_PERCENT = 60;

    function firstNumber(text) {
        var m = String(text).replace(/,/g, "").match(/-?\d+(?:\.\d+)?/);
        return m ? parseFloat(m[0]) : null;
    }

    var boxes = document.querySelectorAll(
        ".moove-quizreviewsummary .moove-infobox"
    );
    if (!boxes.length) {
        return;
    }

    var grade = null;
    var gradeMax = null;
    var marks = null;
    var duration = null;

    Array.prototype.forEach.call(boxes, function (box) {
        var titleEl = box.querySelector(".moove-infobox-title");
        var valueEl = box.querySelector(
            ".moove-infobox-content--small, .moove-infobox-content"
        );
        if (!titleEl || !valueEl) {
            return;
        }
        var title = titleEl.textContent.trim().toLowerCase();
        var value = valueEl.textContent.trim();

        if (title === "grade") {
            var parts = value.split(/out of/i);
            grade = firstNumber(parts[0]);
            gradeMax = firstNumber(parts[1]);
        } else if (title === "marks") {
            marks = value;
        } else if (title === "duration") {
            duration = value;
        }
    });

    if (grade === null || !gradeMax) {
        return;
    }

    var pct = Math.max(0, Math.min(100, Math.round((grade / gradeMax) * 100)));
    var passed = pct >= PASS_PERCENT;

    var radius = 45;
    var circumference = 2 * Math.PI * radius;
    var dashOffset = circumference * (1 - pct / 100);

    var banner = document.createElement("div");
    banner.className =
        "vrb-qr-result " +
        (passed ? "vrb-qr-result--pass" : "vrb-qr-result--fail");

    var html =
        '<div class="vrb-qr-ring">' +
        '<svg viewBox="0 0 100 100" aria-hidden="true">' +
        '<circle class="vrb-qr-ring-track" cx="50" cy="50" r="' +
        radius +
        '"></circle>' +
        '<circle class="vrb-qr-ring-arc" cx="50" cy="50" r="' +
        radius +
        '" stroke-dasharray="' +
        circumference.toFixed(2) +
        '" stroke-dashoffset="' +
        dashOffset.toFixed(2) +
        '"></circle>' +
        "</svg>" +
        '<span class="vrb-qr-pct">' +
        pct +
        "%</span>" +
        "</div>" +
        '<h2 class="vrb-qr-title">' +
        (passed
            ? "Module passed"
            : "Not quite — " + PASS_PERCENT + "% needed to pass") +
        "</h2>" +
        '<p class="vrb-qr-sub">' +
        (passed
            ? "You’ve completed this assessment."
            : "Review your answers below, then retry from the module page.") +
        "</p>";

    if (marks) {
        html +=
            '<div class="vrb-qr-stats">' +
            "<div><span>Score</span><strong>" +
            marks +
            "</strong></div>" +
            (duration
                ? "<div><span>Time</span><strong>" + duration + "</strong></div>"
                : "") +
            "</div>";
    }

    banner.innerHTML = html;

    var summary =
        document.querySelector(".moove-summary-table") ||
        document.querySelector(".moove-quizreviewsummary");
    if (!summary || !summary.parentNode) {
        return;
    }
    summary.parentNode.insertBefore(banner, summary);
    // The banner now carries score + time; the verbose Moove strip
    // (Started/Completed/Marks/Grade) would just repeat it.
    summary.hidden = true;

    var firstQuestion = document.querySelector(".que");
    if (firstQuestion && firstQuestion.parentNode) {
        var reviewHead = document.createElement("div");
        reviewHead.className = "vrb-qr-reviewhead";
        reviewHead.innerHTML =
            "<h3>Review your answers</h3>" +
            "<p>See the correct answers and explanations for each question.</p>";
        firstQuestion.parentNode.insertBefore(reviewHead, firstQuestion);
    }
})();
