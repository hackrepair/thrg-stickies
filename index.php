<?php
/**
 * Plugin Name:       The Hack Repair Guy's Stickies
 * Plugin URI:        https://github.com/hackrepair/thrg-stickies
 * Description:       Pin sticky notes to any spot on any page of your site, mention a teammate, and mark the note resolved when the fix is done.
 * Version:           2.1.1
 * Author:            The Hack Repair Guy
 * Author URI:        https://hackrepair.com/
 * Update URI:        https://github.com/hackrepair/thrg-stickies
 * Requires at least: 6.9.4
 * Requires PHP:      7.4
 * Text Domain:       thrg-stickies
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package THRG_Stickies
 */

defined( 'ABSPATH' ) || exit;

const THRG_STK_VERSION = '2.1.1';
define( 'THRG_STK_FILE', __FILE__ );
define( 'THRG_STK_DIR', __DIR__ );

/*
 * GitHub release updates. Deliberately NOT gated by is_admin(): WordPress's
 * update-check and auto-update machinery runs on cron, where is_admin() is false.
 * THRG_STK_SKIP_UPDATER is only defined by the tests.
 */
if ( ! defined( 'THRG_STK_SKIP_UPDATER' ) ) {
    $thrg_stk_puc = __DIR__ . '/plugin-update-checker/plugin-update-checker.php';
    if ( is_readable( $thrg_stk_puc ) ) {
        require_once $thrg_stk_puc;
    }
    unset( $thrg_stk_puc );

    require_once __DIR__ . '/includes/class-thrg-stk-updater.php';
    $thrg_stk_updater = new THRG_STK_Updater();
    $thrg_stk_updater->init( __FILE__ );
    unset( $thrg_stk_updater );
}

require_once __DIR__ . '/includes/class-thrg-stk-url.php';
require_once __DIR__ . '/includes/class-thrg-stk-content.php';
require_once __DIR__ . '/includes/class-thrg-stk-access.php';
require_once __DIR__ . '/includes/class-thrg-stk-schema.php';
require_once __DIR__ . '/includes/class-thrg-stk-settings.php';
require_once __DIR__ . '/includes/class-thrg-stk-notes.php';
require_once __DIR__ . '/includes/class-thrg-stk-email.php';
require_once __DIR__ . '/includes/class-thrg-stk-intro.php';
require_once __DIR__ . '/includes/class-thrg-stk-rest.php';
require_once __DIR__ . '/includes/class-thrg-stk-plugin.php';
require_once __DIR__ . '/includes/class-thrg-stk-admin.php';
require_once __DIR__ . '/includes/class-thrg-stk-details.php';
require_once __DIR__ . '/includes/companion-plugins.php';

register_activation_hook( __FILE__, array( 'THRG_STK_Plugin', 'activate' ) );

THRG_STK_Plugin::boot();
