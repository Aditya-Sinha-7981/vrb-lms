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
