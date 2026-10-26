<?php
/**
 * Boot, activation and on-page loading.
 *
 * @package THRG_Stickies
 */

defined( 'ABSPATH' ) || exit;

/** Wires everything together and decides when the note app loads. */
final class THRG_STK_Plugin {

    /** @var array<int,string|null> Display name cache. */
    private static $names = array();

    /** @var bool Whether the note app is loaded on this request. */
    private static $loaded = false;

    /**
     * Register hooks.
     *
     * @return void
     */
    public static function boot() {
        add_action( 'init', array( __CLASS__, 'textdomain' ) );
        add_action( 'init', array( 'THRG_STK_Schema', 'maybe_upgrade' ), 5 );
        add_action( 'init', array( 'THRG_STK_Access', 'sync_roles' ), 6 );
        add_action( 'wp_initialize_site', array( __CLASS__, 'new_site' ), 200 );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_front' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ) );
        add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 90 );
        THRG_STK_Settings::boot();
        THRG_STK_REST::boot();
        THRG_STK_Admin::boot();
        THRG_STK_Details::boot();
    }

    /**
     * Translations.
     *
     * @return void
     */
    public static function textdomain() {
        load_plugin_textdomain( 'thrg-stickies', false, dirname( plugin_basename( THRG_STK_FILE ) ) . '/languages' );
    }

    /**
     * Activation: table, settings and permissions for this site, or every site
     * when network-activated.
     *
     * @param bool $network_wide Network activation.
     * @return void
     */
    public static function activate( $network_wide = false ) {
        if ( is_multisite() && $network_wide ) {
            foreach ( get_sites(
                array(
                    'fields' => 'ids',
                    'number' => 0,
                )
            ) as $site_id ) {
                switch_to_blog( $site_id );
                self::activate_site();
                restore_current_blog();
            }
            return;
        }
        self::activate_site();
    }

    /**
     * Set up one site.
     *
     * @return void
     */
    public static function activate_site() {
        THRG_STK_Schema::install();
        if ( false === get_option( THRG_STK_Settings::OPTION ) ) {
            add_option( THRG_STK_Settings::OPTION, THRG_STK_Settings::defaults() );
        }
        THRG_STK_Access::apply_matrix( (array) THRG_STK_Settings::get( 'roles' ) );
        THRG_STK_Intro::maybe_add();
        update_option( 'thrg_stk_show_welcome', 1, false );
    }

    /**
     * New site on a network where the plugin is network-active.
     *
     * @param WP_Site $site New site.
     * @return void
     */
    public static function new_site( $site ) {
        if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if ( is_plugin_active_for_network( plugin_basename( THRG_STK_FILE ) ) ) {
            switch_to_blog( (int) $site->blog_id );
            self::activate_site();
            restore_current_blog();
        }
    }

    /**
     * Display name for a user ID, cached for the request.
     *
     * @param int $id User ID.
     * @return string|null
     */
    public static function display_name( $id ) {
        $id = (int) $id;
        if ( ! array_key_exists( $id, self::$names ) ) {
            $user               = $id > 0 ? get_userdata( $id ) : false;
            self::$names[ $id ] = $user ? $user->display_name : null;
        }
        return self::$names[ $id ];
    }

    /**
     * Link that opens the page and scrolls to the note.
     *
     * @param array $note Note row.
     * @return string
     */
    public static function note_url( array $note ) {
        $path = (string) $note['page_path'];
        $base = 'admin' === $note['context'] ? site_url( $path ) : home_url( $path );
        return $base . '#thrg-stk-' . (int) $note['id'];
    }

    /**
     * Should the note app load here?
     *
     * @param string $context front or admin.
     * @return bool
     */
    private static function should_load( $context ) {
        if ( ! is_user_logged_in() || ! THRG_STK_Settings::get( $context ) ) {
            return false;
        }
        if ( 'front' === $context && ( is_customize_preview() || is_embed() || is_feed() ) ) {
            return false;
        }
        if ( 'admin' === $context && defined( 'IFRAME_REQUEST' ) && IFRAME_REQUEST ) {
            return false; // Framed dashboard pages such as View details.
        }
        $caps = THRG_STK_Access::user_caps( get_current_user_id() );
        return (bool) apply_filters( 'thrg_stk_load', $caps['read'], $context );
    }

    /**
     * Front end.
     *
     * @return void
     */
    public static function enqueue_front() {
        if ( self::should_load( 'front' ) ) {
            self::enqueue( 'front' );
        }
    }

    /**
     * Dashboard screens.
     *
     * @return void
     */
    public static function enqueue_admin() {
        if ( self::should_load( 'admin' ) ) {
            self::enqueue( 'admin' );
        }
    }

    /**
     * Load the note app.
     *
     * @param string $context front or admin.
     * @return void
     */
    private static function enqueue( $context ) {
        $uid  = get_current_user_id();
        $caps = THRG_STK_Access::user_caps( $uid );
        $url  = plugins_url( 'assets/', THRG_STK_FILE );

        wp_enqueue_style( 'thrg-stk-notes', $url . 'css/thrg-stk-notes.css', array(), THRG_STK_VERSION );
        wp_enqueue_script(
            'thrg-stk-notes',
            $url . 'js/thrg-stk-notes.js',
            array(),
            THRG_STK_VERSION,
            array(
				'in_footer' => true,
				'strategy'  => 'defer',
            )
        );

        $config = array(
            'rest'       => esc_url_raw( rest_url( THRG_STK_REST::NS . '/' ) ),
            'nonce'      => wp_create_nonce( 'wp_rest' ),
            'context'    => $context,
            'user'       => array(
                'id'   => $uid,
                'name' => (string) self::display_name( $uid ),
            ),
            'caps'       => array(
                'create' => $caps['create'],
            ),
            'toolbar'    => THRG_STK_Settings::get( 'toolbar' ),
            'breakpoint' => (int) THRG_STK_Settings::get( 'breakpoint' ),
            'autosave'   => (bool) THRG_STK_Settings::get( 'autosave' ),
            'maxLength'  => THRG_STK_Content::MAX_LENGTH,
            'colors'     => THRG_STK_Access::COLORS,
            'listUrl'    => current_user_can( 'edit_posts' ) ? THRG_STK_Admin::list_url() : '',
            'i18n'       => self::strings(),
        );
        wp_add_inline_script( 'thrg-stk-notes', 'window.thrgStickies = ' . wp_json_encode( $config ) . ';', 'before' );
        self::$loaded = true;
    }

    /**
     * Admin bar entry.
     *
     * @param WP_Admin_Bar $bar Admin bar.
     * @return void
     */
    public static function admin_bar( $bar ) {
        if ( ! self::$loaded || ! in_array( THRG_STK_Settings::get( 'toolbar' ), array( 'both', 'adminbar' ), true ) ) {
            return;
        }
        $bar->add_node(
            array(
                'id'    => 'thrg-stickies',
                'title' => '<span class="ab-icon" aria-hidden="true"></span><span class="ab-label">' . esc_html__( 'Stickies', 'thrg-stickies' ) . '</span> <span class="thrg-stk-ab-count" aria-hidden="true">0</span>',
                'href'  => '#thrg-stk-panel',
                'meta'  => array(
                    'class' => 'thrg-stk-ab',
                    'title' => __( 'Show the sticky notes on this page', 'thrg-stickies' ),
                ),
            )
        );
    }

    /**
     * Interface text for the browser.
     *
     * @return array<string,string>
     */
    private static function strings() {
        return array(
            'toolbar'       => __( 'Stickies', 'thrg-stickies' ),
            'add'           => __( 'Add a note', 'thrg-stickies' ),
            'hide'          => __( 'Hide notes', 'thrg-stickies' ),
            'show'          => __( 'Show notes', 'thrg-stickies' ),
            'list'          => __( 'Notes on this page', 'thrg-stickies' ),
            'close'         => __( 'Close', 'thrg-stickies' ),
            'save'          => __( 'Save note (Ctrl+S)', 'thrg-stickies' ),
            'saved'         => __( 'Saved', 'thrg-stickies' ),
            'delete'        => __( 'Delete note', 'thrg-stickies' ),
            'confirmDelete' => __( 'Delete this note?', 'thrg-stickies' ),
            'yesDelete'     => __( 'Delete', 'thrg-stickies' ),
            'cancel'        => __( 'Cancel', 'thrg-stickies' ),
            'resolve'       => __( 'Mark resolved', 'thrg-stickies' ),
            'reopen'        => __( 'Reopen', 'thrg-stickies' ),
            'resolved'      => __( 'Resolved', 'thrg-stickies' ),
            'toastResolved' => __( 'Note marked resolved.', 'thrg-stickies' ),
            'toastReopened' => __( 'Note reopened.', 'thrg-stickies' ),
            'undo'          => __( 'Undo', 'thrg-stickies' ),
            /* translators: %s: name of the person who resolved the note */
            'resolvedBy'    => __( 'Resolved by %s', 'thrg-stickies' ),
            'color'         => __( 'Note color', 'thrg-stickies' ),
            'colors'        => wp_json_encode(
                array(
                    'yellow' => __( 'Yellow', 'thrg-stickies' ),
                    'blue'   => __( 'Blue', 'thrg-stickies' ),
                    'green'  => __( 'Green', 'thrg-stickies' ),
                    'pink'   => __( 'Pink', 'thrg-stickies' ),
                    'gray'   => __( 'Gray', 'thrg-stickies' ),
                )
            ),
            'visibility'    => __( 'Who can see this note', 'thrg-stickies' ),
            'visTeam'       => __( 'Team', 'thrg-stickies' ),
            'visMentioned'  => __( 'Mentioned only', 'thrg-stickies' ),
            'visPrivate'    => __( 'Just me', 'thrg-stickies' ),
            'visHelp'       => __( 'Team: everyone with Stickies access can see this note. Mentioned only: just you and the people you @mention.', 'thrg-stickies' ),
            'placeholder'   => __( 'Write a note. Type @ to mention someone.', 'thrg-stickies' ),
            'move'          => __( 'Drag to move. With the note focused, arrow keys move it and Shift+arrow keys resize it.', 'thrg-stickies' ),
            /* translators: %s: name of the note's author */
            'note'          => __( 'Sticky note by %s', 'thrg-stickies' ),
            'edited'        => __( 'edited', 'thrg-stickies' ),
            'empty'         => __( 'No notes on this page yet.', 'thrg-stickies' ),
            'emptyHidden'   => __( 'Notes are hidden. Choose Show notes to bring them back.', 'thrg-stickies' ),
            'showResolved'  => __( 'Show resolved notes', 'thrg-stickies' ),
            'manageAll'     => __( 'All notes on this site', 'thrg-stickies' ),
            'smallScreen'   => __( 'On small screens, notes are listed here instead of on the page.', 'thrg-stickies' ),
            'errorGeneric'  => __( 'Something went wrong. Please try again.', 'thrg-stickies' ),
            'errorNetwork'  => __( 'Stickies could not reach your site. Check your connection and try again.', 'thrg-stickies' ),
            'errorSession'  => __( 'Your login session changed. Reload the page and try again.', 'thrg-stickies' ),
            // One of these is shown at random when someone tries to save an empty note.
            'errorEmpty'    => array(
                __( 'Oopsy! Out of ink?', 'thrg-stickies' ),
                __( 'Using invisible ink?', 'thrg-stickies' ),
                __( "Oh, you're joking?", 'thrg-stickies' ),
                __( "Don't be shy.", 'thrg-stickies' ),
                __( "Blank notes don't stick.", 'thrg-stickies' ),
                __( 'Stickies need love too!', 'thrg-stickies' ),
            ),
            'reload'        => __( 'Reload note', 'thrg-stickies' ),
            'unsaved'       => __( 'Save Me', 'thrg-stickies' ),
            /* translators: %d: maximum number of characters */
            'tooLong'       => __( 'Notes can be up to %d characters.', 'thrg-stickies' ),
            'mentionList'   => __( 'People you can mention', 'thrg-stickies' ),
            'noMatches'     => __( 'No one matches', 'thrg-stickies' ),
            'justNow'       => __( 'just now', 'thrg-stickies' ),
            /* translators: %d: number of minutes */
            'minutesAgo'    => __( '%d min ago', 'thrg-stickies' ),
            /* translators: %d: number of hours */
            'hoursAgo'      => __( '%d hr ago', 'thrg-stickies' ),
            /* translators: %d: number of days */
            'daysAgo'       => __( '%d days ago', 'thrg-stickies' ),
            'you'           => __( 'You', 'thrg-stickies' ),
        );
    }
}
