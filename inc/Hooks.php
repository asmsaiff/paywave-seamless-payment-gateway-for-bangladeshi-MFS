<?php
namespace PayWave;

class Hooks {
    public static function on_activation() {
        // Call custom rewrite rule
        Rewrite::add_rewrite_rule();
        flush_rewrite_rules();
    }

    public static function on_deactivation() {
        flush_rewrite_rules();
    }
}
