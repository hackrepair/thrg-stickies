<?php
/**
 * The welcome note added on a site's first install.
 *
 * @package THRG_Stickies
 */

defined( 'ABSPATH' ) || exit;

/**
 * One friendly "Just me" note on the home page, written by the administrator
 * who activated Stickies. Added once per site, only when the site has no notes
 * yet, so a reinstall that kept its notes never gets a second one.
 */
final class THRG_STK_Intro {

    /** Holds the welcome note's ID once it has been added (or -1 when skipped for good). */
    const OPTION = 'thrg_stk_intro_note';

    /**
     * Should a welcome note be added now? Pure.
     *
     * @param mixed $flag    Stored option value (false when never set).
     * @param int   $user_id Activating user, 0 for WP-CLI or cron.
     * @param int   $count   Notes already on the site.
     * @return string 'add', 'skip' (remember, never try again) or 'wait' (try on a later activation).
     */
    public static function decide( $flag, $user_id, $count ) {
        if ( false !== $flag && null !== $flag && '' !== $flag ) {
            return 'skip';
        }
        if ( (int) $count > 0 ) {
            return 'skip';
        }
        // No logged-in person to own the note (WP-CLI): leave it for a browser activation.
        return (int) $user_id > 0 ? 'add' : 'wait';
    }

    /**
     * The note row. Pure.
     *
     * @param int    $user_id   Author.
     * @param string $now       UTC time, Y-m-d H:i:s.
     * @param string $path      Normalized home page path.
     * @param string $key       Page key for that path.
     * @param string $title     Page title for the list.
     * @param string $content   Sanitized note text.
     * @return array
     */
    public static function row( $user_id, $now, $path, $key, $title, $content ) {
        return array(
            'page_key'   => $key,
            'page_path'  => $path,
            'page_title' => $title,
            'context'    => 'front',
            'content'    => $content,
            // Near the top of the page, to the right of the edge toolbar on common widths.
            'pos_top'    => 140,
            'pos_center' => 520,
            'width'      => 320,
            'height'     => 330,
            'viewport_w' => 1440,
            'color'      => 'yellow',
            'visibility' => 'private',
            'status'     => 'open',
            'author_id'  => (int) $user_id,
            'mentions'   => array(),
            'created_at' => $now,
            'updated_at' => $now,
        );
    }

    /**
     * The welcome text.
     *
     * @param bool $emoji Start with a smiley. Needs a utf8mb4 table, so callers pass false on older databases.
     * @return string
     */
    public static function text( $emoji = false ) {
        $text = __( "This is your first sticky note! Stickies is ready.\n\nNotes stay where you pin them. Drag me, or pick a new color at the top right.\n\n[Hint] type @ in a note to tap a teammate on the shoulder.\n\nWhen you're done with me, click the check on the right to mark me resolved.", 'thrg-stickies' );
        return $emoji ? "\u{1F600} " . $text : $text;
    }

    /**
     * Can this site's database store a smiley? Only utf8mb4 holds 4-byte characters.
     *
     * @return bool
     */
    private static function can_store_emoji() {
        global $wpdb;
        return isset( $wpdb->charset ) && 'utf8mb4' === $wpdb->charset;
    }

    /**
     * Add the welcome note for the current site if this is its first install.
     *
     * @return int New note ID, or 0 when none was added.
     */
    public static function maybe_add() {
        $decision = self::decide( get_option( self::OPTION ), get_current_user_id(), THRG_STK_Notes::count_all() );
        if ( 'wait' === $decision ) {
            return 0;
        }
        if ( 'skip' === $decision ) {
            if ( false === get_option( self::OPTION ) ) {
                add_option( self::OPTION, -1, '', false );
            }
            return 0;
        }

        // Activation runs before init has loaded translations for this plugin.
        THRG_STK_Plugin::textdomain();

        $home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
        $path = THRG_STK_URL::normalize( '' !== $home ? $home : '/', $home, 'front' );
        if ( '' === $path ) {
            return 0;
        }
        $id = THRG_STK_Notes::insert(
            self::row(
                get_current_user_id(),
                current_time( 'mysql', true ),
                $path,
                THRG_STK_URL::key( $path, 'front' ),
                sanitize_text_field( get_bloginfo( 'name' ) ),
                THRG_STK_Content::sanitize( self::text( self::can_store_emoji() ) )
            )
        );
        if ( $id ) {
            add_option( self::OPTION, (int) $id, '', false );
        }
        return (int) $id;
    }

    /**
     * The welcome note, while it is still open and visible to this user.
     *
     * @param int $user_id User.
     * @return array|null
     */
    public static function open_note( $user_id ) {
        $id = (int) get_option( self::OPTION );
        if ( $id <= 0 ) {
            return null;
        }
        $note = THRG_STK_Notes::find( $id );
        if ( ! $note || 'open' !== $note['status'] || ! THRG_STK_Access::can_view( $note, $user_id, THRG_STK_Access::user_caps( $user_id ) ) ) {
            return null;
        }
        return $note;
    }
}
