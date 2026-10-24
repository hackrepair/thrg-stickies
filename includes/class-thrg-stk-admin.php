<?php
/**
 * The Stickies menu, Plugins-screen links and the welcome notice.
 *
 * @package THRG_Stickies
 */

defined( 'ABSPATH' ) || exit;

/** Dashboard screens. */
final class THRG_STK_Admin {

    const PAGE = 'thrg-stickies';

    /**
     * Register hooks.
     *
     * @return void
     */
    public static function boot() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ), 9 );
        add_action( 'admin_post_thrg_stk_note_action', array( __CLASS__, 'row_action' ) );
        add_action( 'admin_notices', array( __CLASS__, 'welcome' ) );
        add_action( 'admin_post_thrg_stk_dismiss', array( __CLASS__, 'dismiss' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( THRG_STK_FILE ), array( __CLASS__, 'action_links' ) );
        add_filter( 'removable_query_args', array( __CLASS__, 'removable_args' ) );
    }

    /**
     * Drop the one-time result arguments from the address bar after display.
     *
     * @param string[] $args Removable arguments.
     * @return string[]
     */
    public static function removable_args( $args ) {
        $args[] = 'stk_done';
        $args[] = 'stk_count';
        return $args;
    }

    /**
     * Who may open the list: anyone who can see notes and work in the dashboard.
     *
     * @return bool
     */
    public static function can_list() {
        $caps = THRG_STK_Access::user_caps( get_current_user_id() );
        return $caps['read'];
    }

    /**
     * The Stickies item in the left menu, with the note list and Settings under it.
     *
     * @return void
     */
    public static function menu() {
        $hooks   = array();
        $hooks[] = add_menu_page(
            __( 'Stickies', 'thrg-stickies' ),
            __( 'Stickies', 'thrg-stickies' ),
            THRG_STK_Access::CAP_READ,
            self::PAGE,
            array( __CLASS__, 'render' ),
            self::menu_icon(),
            26
        );
        $hooks[] = add_submenu_page(
            self::PAGE,
            __( 'Stickies', 'thrg-stickies' ),
            __( 'All stickies', 'thrg-stickies' ),
            THRG_STK_Access::CAP_READ,
            self::PAGE,
            array( __CLASS__, 'render' )
        );
        foreach ( array_unique( array_filter( $hooks ) ) as $hook ) {
            add_action( 'admin_print_styles-' . $hook, array( __CLASS__, 'enqueue_admin_styles' ) );
            add_action( 'load-' . $hook, array( __CLASS__, 'bulk_action' ) );
        }
    }

    /**
     * Menu icon: the same square-with-plus note icon as the Stickies toolbar.
     *
     * @return string
     */
    public static function menu_icon() {
        return 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0iI2E3YWFhZCI+PHBhdGggZmlsbC1ydWxlPSJldmVub2RkIiBkPSJNNiAzaDEyYTMgMyAwIDAgMSAzIDN2MTJhMyAzIDAgMCAxLTMgM0g2YTMgMyAwIDAgMS0zLTNWNmEzIDMgMCAwIDEgMy0zem0wIDJhMSAxIDAgMCAwLTEgMXYxMmExIDEgMCAwIDAgMSAxaDEyYTEgMSAwIDAgMCAxLTFWNmExIDEgMCAwIDAtMS0xSDZ6Ii8+PHBhdGggZD0iTTExIDhoMnYzaDN2MmgtM3YzaC0ydi0zSDh2LTJoM3oiLz48L3N2Zz4=';
    }

    /**
     * Address of the note list.
     *
     * @return string
     */
    public static function list_url() {
        return admin_url( 'admin.php?page=' . self::PAGE );
    }

    /**
     * Styles for this plugin's screens.
     *
     * @return void
     */
    public static function enqueue_admin_styles() {
        $url = plugins_url( 'assets/', THRG_STK_FILE );
        wp_enqueue_style( 'thrg-stk-brand', $url . 'brand/thrg-brand.css', array(), THRG_STK_VERSION );
        wp_enqueue_style( 'thrg-stk-admin', $url . 'css/thrg-stk-admin.css', array( 'thrg-stk-brand' ), THRG_STK_VERSION );
    }

    /**
     * Shared screen header.
     *
     * @param string $subtitle Screen name under the plugin name.
     * @param string $back_url Optional address the heart links to (the all-stickies list from other screens).
     * @return void
     */
    public static function header( $subtitle, $back_url = '' ) {
        $heart = '<img class="stk-mascot thrg-brand-heart" src="' . esc_url( plugins_url( 'assets/brand/thrg-heart-128.png', THRG_STK_FILE ) ) . '" alt="" width="56" height="56" />';
        ?>
        <div class="stk-head">
            <?php if ( $back_url ) : ?>
                <a class="stk-heart-link" href="<?php echo esc_url( $back_url ); ?>" title="<?php esc_attr_e( 'Back to all stickies', 'thrg-stickies' ); ?>"><?php echo $heart; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built above from escaped parts. ?><span class="screen-reader-text"><?php esc_html_e( 'Back to all stickies', 'thrg-stickies' ); ?></span></a>
            <?php else : ?>
                <?php echo $heart; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built above from escaped parts. ?>
            <?php endif; ?>
            <div>
                <p class="stk-eyebrow"><?php echo esc_html( "The Hack Repair Guy's" ); ?></p>
                <h1><?php esc_html_e( 'Stickies', 'thrg-stickies' ); ?> <span class="stk-version"><?php echo esc_html( 'v' . THRG_STK_VERSION ); ?></span></h1>
                <p class="stk-byline"><?php echo esc_html( $subtitle ); ?></p>
            </div>
        </div>
        <hr class="wp-header-end" />
        <?php
    }

    /**
     * Shared Support container and footer.
     *
     * @return void
     */
    public static function footer() {
        ?>
        <section id="support" class="stk-support" aria-labelledby="stk-support-title">
            <h2 id="stk-support-title" class="stk-section"><?php esc_html_e( 'Support', 'thrg-stickies' ); ?></h2>
            <div class="stk-card">
                <p>&#9749; <?php echo wp_kses_post( __( 'If you found this plugin helpful, please <a href="https://hackrepair.com/donations/buy-jim-a-coffee" target="_blank" rel="noopener">buy Jim a cup of coffee?</a>', 'thrg-stickies' ) ); ?></p>
                <p><span class="dashicons dashicons-editor-help" aria-hidden="true"></span> <?php echo wp_kses_post( __( 'Questions and Feedback: For questions, ideas, or help with the plugin, <a href="https://github.com/hackrepair/thrg-stickies/discussions" target="_blank" rel="noopener">visit GitHub Discussions</a>.', 'thrg-stickies' ) ); ?></p>
            </div>
        </section>
        <footer class="stk-footer">
            <div class="stk-footer-col">
                <p><?php esc_html_e( 'Notes are stored on this site only and are shown to logged-in people with Stickies permissions. Visitors never see them.', 'thrg-stickies' ); ?></p>
            </div>
            <div class="stk-footer-col stk-help">
                <img src="<?php echo esc_url( plugins_url( 'assets/brand/thrg-heart-128.png', THRG_STK_FILE ) ); ?>" alt="" width="44" height="44" />
                <p><?php esc_html_e( 'Something on your site acting strange?', 'thrg-stickies' ); ?><br />
                <?php
                printf(
                    /* translators: %s: link to The Hack Repair Guy */
                    esc_html__( 'Contact %s.', 'thrg-stickies' ),
                    '<a href="https://hackrepair.com/" title="' . esc_attr__( 'The Hack Repair Guy: WordPress security and hack repair', 'thrg-stickies' ) . '" target="_blank" rel="noopener noreferrer">The Hack Repair Guy<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'thrg-stickies' ) . '</span></a>'
                );
                ?>
                </p>
            </div>
        </footer>
        <?php
    }

    /**
     * Render the note list.
     *
     * @return void
     */
    public static function render() {
        if ( ! self::can_list() ) {
            return;
        }
        require_once __DIR__ . '/class-thrg-stk-list-table.php';
        $table = new THRG_STK_List_Table();
        $table->prepare_items();
        $front = THRG_STK_Settings::get( 'front' );
        ?>
        <div class="wrap thrg-stk-admin">
            <?php self::header( __( 'Sticky notes pinned to pages of this site', 'thrg-stickies' ) ); ?>
            <?php self::result_notice(); ?>
            <div class="stk-callout">
                <p>
                    <?php
                    if ( $front ) {
                        printf(
                            /* translators: %s: link to the home page */
                            esc_html__( 'To add a note, open any page of the site while logged in (for example the %s) and use the note button on the left edge or Stickies in the admin bar.', 'thrg-stickies' ),
                            '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'home page', 'thrg-stickies' ) . '</a>'
                        );
                    } else {
                        esc_html_e( 'Notes on the site are turned off in Stickies > Settings.', 'thrg-stickies' );
                    }
                    ?>
                </p>
            </div>
            <form method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>" />
                <?php
                $table->views();
                $table->search_box( __( 'Search notes', 'thrg-stickies' ), 'thrg-stk-search' );
                $table->display();
                ?>
            </form>
            <?php self::footer(); ?>
        </div>
        <?php
    }

    /**
     * Result message after an action.
     *
     * @return void
     */
    private static function result_notice() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only message selection.
        $done = isset( $_GET['stk_done'] ) ? sanitize_key( wp_unslash( $_GET['stk_done'] ) ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only count.
        $count    = isset( $_GET['stk_count'] ) ? absint( $_GET['stk_count'] ) : 0;
        $messages = array(
            /* translators: %d: number of notes */
            'resolve' => _n( '%d note marked resolved.', '%d notes marked resolved.', $count, 'thrg-stickies' ),
            /* translators: %d: number of notes */
            'reopen'  => _n( '%d note reopened.', '%d notes reopened.', $count, 'thrg-stickies' ),
            /* translators: %d: number of notes */
            'delete'  => _n( '%d note deleted.', '%d notes deleted.', $count, 'thrg-stickies' ),
        );
        if ( isset( $messages[ $done ] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( $messages[ $done ], $count ) ) . '</p></div>';
        } elseif ( 'none' === $done ) {
            echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'No notes were changed. You can only change notes you wrote, unless you manage everyone’s notes.', 'thrg-stickies' ) . '</p></div>';
        }
    }

    /**
     * Apply an action to notes the current user may change.
     *
     * @param string $action resolve, reopen or delete.
     * @param int[]  $ids    Note IDs.
     * @return int Notes changed.
     */
    public static function apply( $action, array $ids ) {
        $uid   = get_current_user_id();
        $caps  = THRG_STK_Access::user_caps( $uid );
        $count = 0;
        foreach ( array_unique( array_map( 'absint', $ids ) ) as $id ) {
            $note = THRG_STK_Notes::find( $id );
            if ( ! $note ) {
                continue;
            }
            if ( 'delete' === $action && THRG_STK_Access::can_delete( $note, $uid, $caps ) ) {
                $count += THRG_STK_Notes::delete( $id ) ? 1 : 0;
            } elseif ( in_array( $action, array( 'resolve', 'reopen' ), true ) && THRG_STK_Access::can_resolve( $note, $uid, $caps ) ) {
                $status = 'resolve' === $action ? 'resolved' : 'open';
                if ( $status === $note['status'] ) {
                    continue;
                }
                $count += THRG_STK_Notes::update(
                    $id,
                    array(
                        'status'      => $status,
                        'resolved_by' => 'resolved' === $status ? $uid : 0,
                        'resolved_at' => 'resolved' === $status ? current_time( 'mysql', true ) : null,
                        'updated_at'  => current_time( 'mysql', true ),
                    ),
                    $note['revision']
                ) ? 1 : 0;
            }
        }
        return $count;
    }

    /**
     * Row action handler (admin-post).
     *
     * @return void
     */
    public static function row_action() {
        $id     = isset( $_GET['note'] ) ? absint( $_GET['note'] ) : 0;
        $action = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';
        check_admin_referer( 'thrg_stk_' . $action . '_' . $id );
        if ( ! self::can_list() ) {
            wp_die( esc_html__( 'You do not have permission to change notes.', 'thrg-stickies' ), 403 );
        }
        $count = self::apply( $action, array( $id ) );
        self::back( $count ? $action : 'none', $count );
    }

    /**
     * Bulk action handler (runs on load of the list screen).
     *
     * @return void
     */
    public static function bulk_action() {
        $action = '';
        foreach ( array( 'action', 'action2' ) as $field ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified below before anything changes.
            if ( isset( $_GET[ $field ] ) && '-1' !== $_GET[ $field ] ) {
                $action = sanitize_key( wp_unslash( $_GET[ $field ] ) );
                break;
            }
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified below before anything changes.
        if ( ! in_array( $action, array( 'resolve', 'reopen', 'delete' ), true ) || empty( $_GET['note'] ) ) {
            return;
        }
        check_admin_referer( 'bulk-thrg_stk_notes' );
        if ( ! self::can_list() ) {
            wp_die( esc_html__( 'You do not have permission to change notes.', 'thrg-stickies' ), 403 );
        }
        $ids   = array_map( 'absint', (array) wp_unslash( $_GET['note'] ) );
        $count = self::apply( $action, $ids );
        self::back( $count ? $action : 'none', $count );
    }

    /**
     * Return to the list with a result.
     *
     * @param string $done  Action done.
     * @param int    $count Notes changed.
     * @return void
     */
    private static function back( $done, $count ) {
        $args = array(
            'page'      => self::PAGE,
            'stk_done'  => $done,
            'stk_count' => (int) $count,
        );
        // Keep the list's search, filters, sort and page. Bulk actions submit them;
        // row actions arrive from the list, so read them from the referring URL.
        $from = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce checked by the caller; values are sanitized below.
        if ( empty( $from['s'] ) && empty( $from['status'] ) && empty( $from['mention'] ) && empty( $from['paged'] ) && wp_get_referer() ) {
            wp_parse_str( (string) wp_parse_url( wp_get_referer(), PHP_URL_QUERY ), $from );
        }
        foreach ( array( 'status', 'mention', 'orderby', 'order' ) as $key ) {
            if ( ! empty( $from[ $key ] ) && is_string( $from[ $key ] ) ) {
                $args[ $key ] = sanitize_key( wp_unslash( $from[ $key ] ) );
            }
        }
        if ( ! empty( $from['s'] ) && is_string( $from['s'] ) ) {
            $args['s'] = rawurlencode( sanitize_text_field( wp_unslash( $from['s'] ) ) );
        }
        if ( ! empty( $from['paged'] ) ) {
            $args['paged'] = absint( $from['paged'] );
        }
        $url = add_query_arg( $args, admin_url( 'admin.php' ) );
        wp_safe_redirect( $url );
        exit;
    }

    /**
     * Plugins screen links.
     *
     * @param string[] $links Existing links.
     * @return string[]
     */
    public static function action_links( $links ) {
        array_unshift(
            $links,
            '<a href="' . esc_url( admin_url( 'admin.php?page=' . THRG_STK_Settings::PAGE ) ) . '">' . esc_html__( 'Settings', 'thrg-stickies' ) . '</a>',
            '<a href="' . esc_url( self::list_url() ) . '">' . esc_html__( 'Notes', 'thrg-stickies' ) . '</a>'
        );
        return $links;
    }

    /**
     * One-time welcome after activation, for administrators.
     *
     * @return void
     */
    public static function welcome() {
        if ( ! get_option( 'thrg_stk_show_welcome' ) || ! current_user_can( 'manage_options' ) || get_user_meta( get_current_user_id(), 'thrg_stk_notice_dismissed', true ) ) {
            return;
        }
        $screen = get_current_screen();
        if ( ! $screen || ! in_array( $screen->id, array( 'plugins', 'dashboard', 'toplevel_page_' . self::PAGE, 'stickies_page_' . self::PAGE, 'stickies_page_' . THRG_STK_Settings::PAGE ), true ) ) {
            return;
        }
        $dismiss = wp_nonce_url( admin_url( 'admin-post.php?action=thrg_stk_dismiss' ), 'thrg_stk_dismiss' );
        ?>
        <div class="notice notice-info">
            <p><strong><?php esc_html_e( 'Stickies is ready.', 'thrg-stickies' ); ?></strong>
            <?php esc_html_e( 'Open any page of your site while logged in and use the note button on the left edge to pin a note. Administrators and editors can use it now.', 'thrg-stickies' ); ?></p>
            <p>
                <a class="button button-primary" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Open the site', 'thrg-stickies' ); ?></a>
                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . THRG_STK_Settings::PAGE ) ); ?>"><?php esc_html_e( 'Settings', 'thrg-stickies' ); ?></a>
                <a href="<?php echo esc_url( $dismiss ); ?>" style="margin-left:8px"><?php esc_html_e( 'Dismiss', 'thrg-stickies' ); ?></a>
            </p>
        </div>
        <?php
    }

    /**
     * Dismiss the welcome for this user.
     *
     * @return void
     */
    public static function dismiss() {
        check_admin_referer( 'thrg_stk_dismiss' );
        update_user_meta( get_current_user_id(), 'thrg_stk_notice_dismissed', 1 );
        wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
        exit;
    }
}
