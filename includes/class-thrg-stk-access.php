<?php
/**
 * Who may see and change a note.
 *
 * @package THRG_Stickies
 */

defined( 'ABSPATH' ) || exit;

/**
 * Capabilities and per-note visibility. The decision functions are pure:
 * they take the note, the user ID and that user's capabilities as plain values.
 */
final class THRG_STK_Access {

    const CAP_READ   = 'thrg_stk_read';
    const CAP_CREATE = 'thrg_stk_create';
    const CAP_MANAGE = 'thrg_stk_manage';

    const VISIBILITY = array( 'team', 'mentioned', 'private' );
    const COLORS     = array( 'yellow', 'blue', 'green', 'pink', 'gray' );
    const STATUSES   = array( 'open', 'resolved' );

    /**
     * Map of short names to capability names.
     *
     * @return array<string,string>
     */
    public static function caps() {
        return array(
            'read'   => self::CAP_READ,
            'create' => self::CAP_CREATE,
            'manage' => self::CAP_MANAGE,
        );
    }

    /**
     * Can this user see the note?
     *
     * @param array $note    Note row (author_id, visibility, mentions as int[]).
     * @param int   $user_id User ID.
     * @param array $caps    read, create, manage, see_private booleans.
     * @return bool
     */
    public static function can_view( array $note, $user_id, array $caps ) {
        $user_id = (int) $user_id;
        if ( $user_id <= 0 || empty( $caps['read'] ) ) {
            return false;
        }
        $is_author = (int) $note['author_id'] === $user_id;
        switch ( isset( $note['visibility'] ) ? $note['visibility'] : 'team' ) {
            case 'private':
                return $is_author || ( ! empty( $caps['manage'] ) && ! empty( $caps['see_private'] ) );
            case 'mentioned':
                return $is_author || ! empty( $caps['manage'] ) || self::is_mentioned( $note, $user_id );
            default:
                return true;
        }
    }

    /**
     * Can this user change the note's text, place, size, color and visibility?
     *
     * @param array $note    Note row.
     * @param int   $user_id User ID.
     * @param array $caps    Capability booleans.
     * @return bool
     */
    public static function can_edit( array $note, $user_id, array $caps ) {
        if ( ! self::can_view( $note, $user_id, $caps ) ) {
            return false;
        }
        if ( ! empty( $caps['manage'] ) ) {
            return true;
        }
        return ! empty( $caps['create'] ) && (int) $note['author_id'] === (int) $user_id;
    }

    /**
     * Can this user mark the note resolved or reopen it? Editors of the note and
     * anyone it mentions.
     *
     * @param array $note    Note row.
     * @param int   $user_id User ID.
     * @param array $caps    Capability booleans.
     * @return bool
     */
    public static function can_resolve( array $note, $user_id, array $caps ) {
        return self::can_edit( $note, $user_id, $caps ) ||
            ( self::can_view( $note, $user_id, $caps ) && self::is_mentioned( $note, $user_id ) );
    }

    /**
     * Same rule as editing.
     *
     * @param array $note    Note row.
     * @param int   $user_id User ID.
     * @param array $caps    Capability booleans.
     * @return bool
     */
    public static function can_delete( array $note, $user_id, array $caps ) {
        return self::can_edit( $note, $user_id, $caps );
    }

    /**
     * Is the user mentioned in the note?
     *
     * @param array $note    Note row.
     * @param int   $user_id User ID.
     * @return bool
     */
    public static function is_mentioned( array $note, $user_id ) {
        $mentions = isset( $note['mentions'] ) && is_array( $note['mentions'] ) ? $note['mentions'] : array();
        return in_array( (int) $user_id, array_map( 'intval', $mentions ), true );
    }

    /**
     * Capability booleans for a user, from WordPress.
     *
     * @param int $user_id User ID.
     * @return array
     */
    public static function user_caps( $user_id ) {
        $user_id = (int) $user_id;
        $out     = array(
            'read'        => false,
            'create'      => false,
            'manage'      => false,
            'see_private' => false,
        );
        if ( $user_id <= 0 ) {
            return $out;
        }
        foreach ( self::caps() as $short => $cap ) {
            $out[ $short ] = user_can( $user_id, $cap );
        }
        // Creating or managing notes implies seeing them.
        $out['read']        = $out['read'] || $out['create'] || $out['manage'];
        $out['see_private'] = $out['manage'] && THRG_STK_Settings::get( 'managers_see_private' );
        return $out;
    }

    /**
     * Keep persisted role capabilities aligned with the saved permission matrix.
     * Role editors, imports or migrations can remove caps without changing settings.
     *
     * @return void
     */
    public static function sync_roles() {
        self::apply_matrix( (array) THRG_STK_Settings::get( 'roles' ) );
    }

    /**
     * Apply the saved role matrix to WordPress roles. Administrators always
     * keep all three capabilities.
     *
     * @param array $matrix role => list of 'read'|'create'|'manage'.
     * @return void
     */
    public static function apply_matrix( array $matrix ) {
        if ( ! function_exists( 'wp_roles' ) ) {
            return;
        }
        $changed = false;
        foreach ( wp_roles()->role_objects as $name => $role ) {
            $granted = 'administrator' === $name ? array_keys( self::caps() ) : ( isset( $matrix[ $name ] ) ? (array) $matrix[ $name ] : array() );
            foreach ( self::caps() as $short => $cap ) {
                $want = in_array( $short, $granted, true );
                if ( $want && ! $role->has_cap( $cap ) ) {
                    $role->add_cap( $cap );
                    $changed = true;
                } elseif ( ! $want && $role->has_cap( $cap ) ) {
                    $role->remove_cap( $cap );
                    $changed = true;
                }
            }
        }
        if ( $changed && function_exists( 'wp_get_current_user' ) ) {
            // A user loaded before the repair may still have cached role caps.
            wp_get_current_user()->get_role_caps();
        }
    }

    /**
     * Remove the capabilities from every role (uninstall).
     *
     * @return void
     */
    public static function remove_all() {
        if ( ! function_exists( 'wp_roles' ) ) {
            return;
        }
        foreach ( wp_roles()->role_objects as $role ) {
            foreach ( self::caps() as $cap ) {
                $role->remove_cap( $cap );
            }
        }
    }
}
