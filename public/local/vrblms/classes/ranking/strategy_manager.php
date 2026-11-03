<?php
namespace local_vrblms\ranking;

defined('MOODLE_INTERNAL') || die();

/**
 * Registry/factory for ranking strategies. This is the one place that
 * knows the full set of available strategies - settings.php (for the
 * admin default-strategy dropdown) and api.php (for resolving a per-call
 * override) both go through here instead of hardcoding strategy names.
 */
class strategy_manager {

    /** @var array key => fully-qualified class name */
    protected static $strategies = [
        'best' => best_attempt_strategy::class,
        'first' => first_attempt_strategy::class,
    ];

    const DEFAULT_KEY = 'best';

    /** Unattempted quizzes score 0% (the average covers every expected quiz). */
    const UNATTEMPTED_ZERO = 'zero';
    /** Unattempted quizzes are left out (the average covers attempted quizzes only). */
    const UNATTEMPTED_IGNORE = 'ignore';

    /**
     * @return array policy key => display string, for the admin setting.
     */
    public static function get_unattempted_policies(): array {
        return [
            self::UNATTEMPTED_ZERO => get_string('unattempted_zero', 'local_vrblms'),
            self::UNATTEMPTED_IGNORE => get_string('unattempted_ignore', 'local_vrblms'),
        ];
    }

    /**
     * How quizzes an employee hasn't attempted yet count towards their
     * score. Site-wide, applies to every strategy.
     */
    public static function get_unattempted_policy(): string {
        $policy = get_config('local_vrblms', 'unattemptedpolicy');
        return $policy === self::UNATTEMPTED_IGNORE ? self::UNATTEMPTED_IGNORE : self::UNATTEMPTED_ZERO;
    }

    /**
     * @return array key => display string, for admin settings / UI dropdowns.
     */
    public static function get_available_strategies(): array {
        $options = [];
        foreach (array_keys(self::$strategies) as $key) {
            $options[$key] = get_string('strategy_' . $key, 'local_vrblms');
        }
        return $options;
    }

    /**
     * Resolves a strategy key to an instance. Falls back to the site's
     * configured default, then to a hardcoded default, if no key (or an
     * unrecognised key) is given.
     */
    public static function get_strategy(?string $key = null): ranking_strategy {
        if ($key === null || !isset(self::$strategies[$key])) {
            $key = get_config('local_vrblms', 'defaultstrategy');
        }
        if (!is_string($key) || !isset(self::$strategies[$key])) {
            $key = self::DEFAULT_KEY;
        }

        $classname = self::$strategies[$key];
        return new $classname();
    }
}
