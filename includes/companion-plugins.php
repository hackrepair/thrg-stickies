<?php
/**
 * Companion plugins for the native View Details dialog.
 *
 * @package THRG_Stickies
 */

defined( 'ABSPATH' ) || exit;

/**
 * Append the companion guide only to this plugin's information.
 *
 * @param mixed  $result Plugin information or the original API result.
 * @param string $action Requested API action.
 * @param object $args   API arguments.
 * @return mixed
 */
function thrg_stk_companion_plugin_info( $result, $action, $args ) {
    if ( 'plugin_information' !== $action || ! is_object( $args ) || ! isset( $args->slug ) || THRG_STK_Details::slug() !== $args->slug || ! is_object( $result ) || ! isset( $result->sections ) || ! is_array( $result->sections ) ) {
        return $result;
    }
    wp_enqueue_style( 'thrg-stk-plugin-details', plugins_url( 'assets/css/thrg-stk-plugin-details.css', THRG_STK_FILE ), array(), THRG_STK_VERSION );
    $cards = array(
        array( 'Site Inspector', 'A friendly, read-only security checkup that keeps a history of your reports and shows what changed.', 'https://github.com/hackrepair/thrg-site-inspector' ),
        array( 'Plugin Detective', 'Find the plugin behind a conflict, with recovery tools for when your dashboard is unavailable.', 'https://github.com/hackrepair/hackrepair-plugin-detective' ),
        array( 'Group Password Reset', 'Reset passwords by role, choose who to exclude, and send secure reset links when needed.', 'https://github.com/hackrepair/group-password-reset' ),
        array( 'Disable Other Plugin Notices', 'Group plugin and theme notices into a collapsible panel while keeping WordPress core notices visible.', 'https://github.com/hackrepair/disable-other-plugin-notices' ),
        array( 'Post Update Email Notifier', 'Notify selected team members when published content changes, with configurable update emails.', 'https://github.com/tvcnet/hackrepair-post-update-email-notifier' ),
        array( 'Plugin Archiver', 'Move unused plugins out of the Plugins list without deleting them, and restore their files when needed.', 'https://wordpress.org/plugins/hackrepair-plugin-archiver/' ),
        array( 'Bunker', 'Lock down the parts of WordPress that bots hammer, while the forms behind your static website keep working.', 'https://github.com/hackrepair/wp-bunker' ),
    );
    $html  = '<div class="thrg_stk-toolkit"><p>More tools from The Hack Repair Guy</p><p>Seven focused ways to look after your WordPress site. Use only what you need.</p>';
    foreach ( $cards as $card ) {
        $html .= '<div class="thrg_stk-toolkit-card"><h3>' . esc_html( $card[0] ) . '</h3><p>' . esc_html( $card[1] ) . '</p><p><a href="' . esc_url( $card[2] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( 'Explore ' . $card[0] ) . ' &rarr;</a></p></div>';
    }
    $html .= '<p>' . esc_html__( 'If you found these plugins helpful, please consider a donation.', 'thrg-stickies' ) . ' <a href="https://hackrepair.com/donations/buy-jim-a-coffee" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Buy Jim a cup of coffee', 'thrg-stickies' ) . '</a>.</p>';
    $html .= '<p>Each plugin works independently. Links open in a new tab.</p></div>';

    $result->sections['other_THRG_plugins'] = $html;
    return $result;
}
add_filter( 'plugins_api', 'thrg_stk_companion_plugin_info', 100, 3 );
