<?php
/**
 * Loads the plugin with minimal WordPress stubs and checks its wiring.
 * Behaviour is covered against a real WordPress by tests/integration.php.
 */
require __DIR__ . '/bootstrap.php';
define( 'THRG_STK_SKIP_UPDATER', true );

$GLOBALS['hooks']      = array();
$GLOBALS['activation'] = null;
function add_action( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['hooks'][ $h ][] = $cb; }
function add_filter( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['hooks'][ $h ][] = $cb; }
function register_activation_hook( $f, $cb ) { $GLOBALS['activation'] = $cb; }
function plugin_basename( $f ) { return basename( dirname( $f ) ) . '/' . basename( $f ); }
function get_option( $k, $d = false ) { return isset( $GLOBALS['opts'][ $k ] ) ? $GLOBALS['opts'][ $k ] : $d; }
function absint( $n ) { return abs( (int) $n ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $k ) ); }

require __DIR__ . '/../index.php';

function hooked( $hook, $cb ) {
    return isset( $GLOBALS['hooks'][ $hook ] ) && in_array( $cb, $GLOBALS['hooks'][ $hook ], true );
}

check( 'version constant', '2.0.5' === THRG_STK_VERSION );
check( 'header version matches constant', 1 === preg_match( '/^ \* Version:\s+' . preg_quote( THRG_STK_VERSION, '/' ) . '$/m', file_get_contents( __DIR__ . '/../index.php' ) ) );
check( 'readme stable tag matches', 1 === preg_match( '/^Stable tag: ' . preg_quote( THRG_STK_VERSION, '/' ) . '$/m', file_get_contents( __DIR__ . '/../readme.txt' ) ) );
check( 'activation hook', array( 'THRG_STK_Plugin', 'activate' ) === $GLOBALS['activation'] );
check( 'front enqueue hooked', hooked( 'wp_enqueue_scripts', array( 'THRG_STK_Plugin', 'enqueue_front' ) ) );
check( 'admin enqueue hooked', hooked( 'admin_enqueue_scripts', array( 'THRG_STK_Plugin', 'enqueue_admin' ) ) );
check( 'rest routes hooked', hooked( 'rest_api_init', array( 'THRG_STK_REST', 'routes' ) ) );
check( 'schema upgrade hooked', hooked( 'init', array( 'THRG_STK_Schema', 'maybe_upgrade' ) ) );
check( 'settings registered', hooked( 'admin_init', array( 'THRG_STK_Settings', 'register' ) ) );
check( 'tools page', hooked( 'admin_menu', array( 'THRG_STK_Admin', 'menu' ) ) );
check( 'view details', hooked( 'plugins_api', array( 'THRG_STK_Details', 'info' ) ) && hooked( 'plugins_api', 'thrg_stk_companion_plugin_info' ) );
check( 'no ajax nopriv handlers', ! preg_grep( '/^wp_ajax_nopriv_/', array_keys( $GLOBALS['hooks'] ) ) );

// Settings defaults and sanitizing.
$d = THRG_STK_Settings::defaults();
check( 'front on, admin on by default', 1 === $d['front'] && 1 === $d['admin'] );
check( 'editors read and create by default', array( 'read', 'create' ) === $d['roles']['editor'] );
check( 'data kept by default', 0 === $d['delete_data'] );
function wp_roles() { return (object) array( 'role_names' => array( 'administrator' => 'Administrator', 'editor' => 'Editor', 'author' => 'Author' ) ); }
$s = THRG_STK_Settings::sanitize(
    array(
        'toolbar'    => 'evil',
        'breakpoint' => '99999',
        'roles'      => array(
            'administrator' => array( 'read' ),
            'author'        => array( 'create', 'bogus' ),
            'ghost'         => array( 'read' ),
        ),
    )
);
check( 'bad toolbar value falls back', 'both' === $s['toolbar'] );
check( 'breakpoint clamped', 2000 === $s['breakpoint'] );
check( 'administrator row ignored', ! isset( $s['roles']['administrator'] ) );
check( 'create implies read, unknown grants dropped', array( 'read', 'create' ) === $s['roles']['author'] );
check( 'unknown role dropped', ! isset( $s['roles']['ghost'] ) );
check( 'flags off when absent', 0 === $s['front'] && 0 === $s['emails'] );

// Shipped files.
$root = dirname( __DIR__ );
foreach ( array( 'readme.txt', 'uninstall.php', 'assets/css/thrg-stk-notes.css', 'assets/js/thrg-stk-notes.js', 'assets/img/pin-heart.svg', 'assets/brand/thrg-heart-128.png', 'assets/icon-128x128.png', 'assets/banner-772x250.png' ) as $f ) {
    check( "ships $f", is_readable( "$root/$f" ) );
}
$js = file_get_contents( "$root/assets/js/thrg-stk-notes.js" );
check( 'js has no eval', false === strpos( $js, 'eval(' ) );
preg_match_all( '/innerHTML\s*=\s*([^;]+);/', $js, $m );
$bad = array_filter( $m[1], function ( $rhs ) { return 0 !== strpos( $rhs, 'SVG[' ) && false === strpos( $rhs, '.rendered' ); } );
check( 'js innerHTML only for static svg and server-rendered html', count( $m[1] ) >= 4 && ! $bad, $bad );

done();
