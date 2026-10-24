<?php
/** Uninstall keeps data by default and removes everything when asked. */
require __DIR__ . '/bootstrap.php';

class Role { public $caps = array(); function remove_cap( $c ) { unset( $this->caps[ $c ] ); } }
class FakeDB {
    public $prefix = 'wp_'; public $options = 'wp_options'; public $queries = array();
    function query( $q ) { $this->queries[] = $q; return true; }
    function prepare( $q, ...$a ) { return vsprintf( str_replace( '%s', "'%s'", $q ), $a ); }
    function esc_like( $s ) { return $s; }
}

function run_uninstall( $options ) {
    global $wpdb;
    $GLOBALS['opts']    = array( 'thrg_stk_options' => $options, 'thrg_stk_db_version' => '1', 'thrg_stk_show_welcome' => 1, 'other' => 1 );
    $GLOBALS['meta']    = array( 'thrg_stk_notice_dismissed' => 1 );
    $GLOBALS['roles']   = array( 'administrator' => new Role(), 'editor' => new Role() );
    foreach ( $GLOBALS['roles'] as $r ) { $r->caps = array( 'thrg_stk_read' => true, 'thrg_stk_create' => true, 'edit_posts' => true ); }
    $wpdb = new FakeDB();
    include __DIR__ . '/../uninstall.php';
}

define( 'WP_UNINSTALL_PLUGIN', true );
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); }
function is_multisite() { return false; }
function wp_roles() { return (object) array( 'role_objects' => $GLOBALS['roles'] ); }
function delete_metadata( $t, $id, $k ) { unset( $GLOBALS['meta'][ $k ] ); }

run_uninstall( array( 'delete_data' => 0 ) );
check( 'kept: settings', isset( $GLOBALS['opts']['thrg_stk_options'] ) );
check( 'kept: table', ! $GLOBALS['wpdb']->queries );
check( 'kept: caps', isset( $GLOBALS['roles']['editor']->caps['thrg_stk_read'] ) );

run_uninstall( array( 'delete_data' => 1 ) );
check( 'removed: options', ! isset( $GLOBALS['opts']['thrg_stk_options'] ) && ! isset( $GLOBALS['opts']['thrg_stk_db_version'] ) && ! isset( $GLOBALS['opts']['thrg_stk_show_welcome'] ) );
check( 'other options untouched', isset( $GLOBALS['opts']['other'] ) );
check( 'removed: table', false !== strpos( $GLOBALS['wpdb']->queries[0], 'DROP TABLE IF EXISTS `wp_thrg_stickies`' ) );
check( 'removed: caps only ours', array( 'edit_posts' => true ) === $GLOBALS['roles']['editor']->caps );
check( 'removed: notice meta', ! isset( $GLOBALS['meta']['thrg_stk_notice_dismissed'] ) );
check( 'removed: transients', false !== strpos( $GLOBALS['wpdb']->queries[1], '_transient_thrg_stk_' ) );

done();
