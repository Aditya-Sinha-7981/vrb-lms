<?php
defined('MOODLE_INTERNAL') || die();

$THEME->name = 'vrblms';
$THEME->parents = ['moove'];
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
$THEME->sheets = ['custom'];

// Drop the generic Dashboard (/my/) primary-nav item — "My courses" is the
// intended employee landing/course-selection page. Core-documented
// mechanism (lib/classes/navigation/views/primary.php, theme/upgrade.txt),
// not a CSS hide: the node is never added, and /my/ itself is untouched.
$THEME->removedprimarynavitems = ['myhome'];

// Plain footer script (no AMD build). Progressive enhancement only —
// currently just the quiz-review score-ring banner. See
// javascript/quizresult.js and style/custom.css section 8.
$THEME->javascripts_footer = ['quizresult'];

$THEME->doctype = 'html5';
