<?php
namespace PayWave;

class Rewrite {
    public static function add_rewrite_rule() {
        add_rewrite_rule('^execute-payment/?$', 'index.php?execute_payment=1', 'top');
    }

    public static function add_query_vars($vars) {
        $vars[] = 'execute_payment';
        return $vars;
    }
}
add_action('init', ['PayWave\\Rewrite', 'add_rewrite_rule']);
add_filter('query_vars', ['PayWave\\Rewrite', 'add_query_vars']);
