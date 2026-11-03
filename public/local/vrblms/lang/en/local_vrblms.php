<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'VRB LMS leaderboard service';
$string['defaultstrategy'] = 'Default ranking strategy';
$string['defaultstrategy_desc'] = 'The ranking strategy used to score and order the regional leaderboard when no strategy is explicitly requested by the caller (block, report, or certificate task). Can still be overridden per call.';
$string['strategy_best'] = 'Best attempt per quiz';
$string['strategy_first'] = 'First attempt per quiz';
$string['unattemptedpolicy'] = 'Quizzes not attempted yet';
$string['unattemptedpolicy_desc'] = 'How quizzes an employee has not attempted yet count towards their leaderboard score. "Count as 0%" averages over every quiz in the employee\'s own brand courses, so finishing more of the training ranks higher. "Leave out" averages only the quizzes attempted, so one high-scoring quiz can outrank someone who completed everything.';
$string['unattempted_zero'] = 'Count as 0% (recommended)';
$string['unattempted_ignore'] = 'Leave out (average attempted quizzes only)';

$string['leaderboard'] = 'Leaderboard';
$string['pageheading'] = 'Regional Leaderboard';
$string['pagesubtitle'] = 'See how you rank across the organisation.';
$string['filterbrand'] = 'Brand';
$string['filterstate'] = 'State';
$string['filtercity'] = 'City';
$string['filterstrategy'] = 'Ranking';
$string['overallbrand'] = 'Overall (all brands)';
$string['allstates'] = 'All states';
$string['allcities'] = 'All cities';
$string['applyfilters'] = 'Apply';
$string['norows'] = 'No employees found for this brand/filter combination.';
$string['nobrands'] = 'No brands are configured yet.';
$string['notenrolled'] = 'You are not enrolled in a brand yet, so there is no course leaderboard to show you.';
$string['overallheading'] = 'Overall (all brands)';
$string['courseheading'] = '{$a}';

$string['columnrank'] = '#';
$string['columnname'] = 'Employee';
$string['columnlocation'] = 'Location';
$string['columnscore'] = 'Score';
$string['columnmodules'] = 'Modules';
$string['columntime'] = 'Time';
