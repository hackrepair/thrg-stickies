<?php
/**
 * Shared helpers for the plain-PHP tests. No WordPress needed.
 * Run any test with: php tests/<name>.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

$GLOBALS['thrg_stk_fail'] = 0;
$GLOBALS['thrg_stk_pass'] = 0;

function check( $name, $cond, $detail = '' ) {
    if ( $cond ) {
        $GLOBALS['thrg_stk_pass']++;
        echo "PASS $name\n";
    } else {
        $GLOBALS['thrg_stk_fail']++;
        echo "FAIL $name" . ( '' !== $detail ? ' :: ' . ( is_string( $detail ) ? $detail : var_export( $detail, true ) ) : '' ) . "\n";
    }
}

function done() {
    echo "\n{$GLOBALS['thrg_stk_pass']} passed, {$GLOBALS['thrg_stk_fail']} failed\n";
    exit( $GLOBALS['thrg_stk_fail'] ? 1 : 0 );
}
