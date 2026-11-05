<?php
/**
 * Plugin Name:       The Hack Repair Guy's Stickies
 * Description:       Pin sticky notes to any spot on any page of your site, mention a teammate, and mark the note resolved when the fix is done.
 * Version:           2.1.4
 * Author:            The Hack Repair Guy
 * Author URI:        https://hackrepair.com/
 * Requires at least: 6.9.4
 * Requires PHP:      7.4
 * Text Domain:       thrg-stickies
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package THRG_Stickies
 */

defined( 'ABSPATH' ) || exit;

const THRG_STK_VERSION = '2.1.4';
define( 'THRG_STK_FILE', __FILE__ );
define( 'THRG_STK_DIR', __DIR__ );

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

register_activation_hook( __FILE__, array( 'THRG_STK_Plugin', 'activate' ) );

THRG_STK_Plugin::boot();
