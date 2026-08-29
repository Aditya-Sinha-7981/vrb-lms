<?php
defined('MOODLE_INTERNAL') || die();

$THEME->name = 'vrblms';
$THEME->parents = ['moove'];
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
$THEME->sheets = ['custom'];

// Plain footer script (no AMD build). Progressive enhancement only —
// currently just the quiz-review score-ring banner. See
// javascript/quizresult.js and style/custom.css section 8.
$THEME->javascripts_footer = ['quizresult'];

$THEME->doctype = 'html5';
