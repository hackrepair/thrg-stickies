<?php
/**
 * Uninstall: remove plugin data only if the site owner chose to.
 *
 * The "Delete all plugin data" option on Settings > Stickies decides, per site.
 * When it is off, notes, settings and permissions are kept for a reinstall.
 *
 * @package THRG_Stickies
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! function_exists( 'thrg_stk_uninstall_site' ) ) {
    /**
     * Delete this site's plugin data if its owner opted in.
     *
     * @return void
     */
    function thrg_stk_uninstall_site() {
        global $wpdb;
        $options = get_option( 'thrg_stk_options', array() );
        if ( ! is_array( $options ) || empty( $options['delete_data'] ) ) {
            return;
        }

        $table = $wpdb->prefix . 'thrg_stickies';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Removing this plugin's own table; name built from the site prefix.
        $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );

        foreach ( array( 'thrg_stk_options', 'thrg_stk_db_version', 'thrg_stk_show_welcome', 'thrg_stk_intro_note' ) as $name ) {
            delete_option( $name );
        }

        foreach ( wp_roles()->role_objects as $role ) {
            foreach ( array( 'thrg_stk_read', 'thrg_stk_create', 'thrg_stk_manage' ) as $cap ) {
                $role->remove_cap( $cap );
            }
        }

        // The update checker's saved state and the GitHub rate-limit note (both site-wide options).
        delete_site_option( 'external_updates-thrg-stickies' );
        delete_site_transient( 'thrg_stk_github_limit' );

        delete_metadata( 'user', 0, 'thrg_stk_notice_dismissed', '', true );

        // Mention-email throttles.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time cleanup of this plugin's transients.
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_thrg_stk_' ) . '%', $wpdb->esc_like( '_transient_timeout_thrg_stk_' ) . '%' ) );
    }
}

if ( is_multisite() ) {
    foreach ( get_sites(
        array(
            'fields' => 'ids',
            'number' => 0,
        )
    ) as $thrg_stk_site_id ) {
        switch_to_blog( $thrg_stk_site_id );
        thrg_stk_uninstall_site();
        restore_current_blog();
    }
} else {
    thrg_stk_uninstall_site();
}
