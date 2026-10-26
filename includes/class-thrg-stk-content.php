<?php
/**
 * Note text: sanitizing, mentions and safe rendering.
 *
 * @package THRG_Stickies
 */

defined( 'ABSPATH' ) || exit;

/**
 * Note text is plain text. Mentions are stored as `@[user:123]` tokens.
 * No user-supplied HTML is ever stored or echoed. Pure functions; the only
 * WordPress calls are optional and guarded with function_exists().
 */
final class THRG_STK_Content {

    const MAX_LENGTH   = 2000;
    const MAX_MENTIONS = 10;
    const TOKEN        = '/@\[user:([0-9]{1,20})\]/';

    /**
     * Clean submitted note text.
     *
     * @param mixed $text Raw text.
     * @return string
     */
    public static function sanitize( $text ) {
        if ( ! is_string( $text ) ) {
            return '';
        }
        if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $text, 'UTF-8' ) ) {
            $text = function_exists( 'mb_convert_encoding' ) ? mb_convert_encoding( $text, 'UTF-8', 'UTF-8' ) : '';
        }
        $text = str_replace( array( "\r\n", "\r" ), "\n", $text );
        $text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text );
        $text = preg_replace( '/[\x{200B}\x{FEFF}]/u', '', (string) $text );
        $text = preg_replace( "/\n{4,}/", "\n\n\n", (string) $text );
        $text = trim( (string) $text );
        if ( self::length( $text ) > self::MAX_LENGTH ) {
            $text = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, self::MAX_LENGTH, 'UTF-8' ) : substr( $text, 0, self::MAX_LENGTH );
            // Never leave half a token behind.
            $text = preg_replace( '/@\[[^\]]*$/', '', $text );
        }
        return (string) $text;
    }

    /**
     * Character length.
     *
     * @param string $text Text.
     * @return int
     */
    public static function length( $text ) {
        return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $text, 'UTF-8' ) : strlen( (string) $text );
    }

    /**
     * Unique mentioned user IDs, in order, capped.
     *
     * @param string $text Sanitized text.
     * @return int[]
     */
    public static function mentions( $text ) {
        preg_match_all( self::TOKEN, (string) $text, $m );
        $ids = array();
        foreach ( $m[1] as $id ) {
            $id = (int) $id;
            if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
                $ids[] = $id;
            }
        }
        return array_slice( $ids, 0, self::MAX_MENTIONS );
    }

    /**
     * Turn tokens for users who may not be mentioned into plain "@" text, and
     * anything past the mention cap into plain text too.
     *
     * @param string   $text  Sanitized text.
     * @param callable $valid Receives a user ID, returns a display name or null.
     * @return string
     */
    public static function filter_mentions( $text, $valid ) {
        $allowed = array();
        return (string) preg_replace_callback(
            self::TOKEN,
            static function ( $m ) use ( $valid, &$allowed ) {
                $id = (int) $m[1];
                if ( in_array( $id, $allowed, true ) ) {
                    return $m[0];
                }
                if ( count( $allowed ) < self::MAX_MENTIONS ) {
                    $name = call_user_func( $valid, $id );
                    if ( is_string( $name ) && '' !== $name ) {
                        $allowed[] = $id;
                        return $m[0];
                    }
                    return '@';
                }
                $name = call_user_func( $valid, $id );
                return '@' . ( is_string( $name ) ? $name : '' );
            },
            (string) $text
        );
    }

    /**
     * Render note text as safe HTML: escape, link URLs, draw mention chips, keep line breaks.
     *
     * @param string   $text    Sanitized text.
     * @param callable $name_of Receives a user ID, returns a display name or null.
     * @return string
     */
    public static function render( $text, $name_of ) {
        $html = htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );

        $html = (string) preg_replace_callback(
            '~\bhttps?://(?:(?!&quot;|&#039;|&lt;|&gt;)[^\s<>"\'])+~i',
            static function ( $m ) {
                $url  = $m[0];
                $tail = '';
                while ( preg_match( '/(?:[.,;:!?)\]]|&amp;)$/', $url, $t ) ) {
                    $tail = $t[0] . $tail;
                    $url  = substr( $url, 0, -strlen( $t[0] ) );
                }
                if ( strlen( $url ) < 10 ) {
                    return $m[0];
                }
                $label = strlen( $url ) > 60 ? substr( $url, 0, 57 ) . '&hellip;' : $url;
                $label = preg_replace( '/&[a-z#0-9]*&hellip;$/i', '&hellip;', $label );
                return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer nofollow">' . $label . '</a>' . $tail;
            },
            $html
        );

        $html = (string) preg_replace_callback(
            self::TOKEN,
            static function ( $m ) use ( $name_of ) {
                $id   = (int) $m[1];
                $name = call_user_func( $name_of, $id );
                $name = is_string( $name ) && '' !== $name ? $name : 'user';
                return '<span class="thrg-stk-mention" data-user="' . $id . '">@' . htmlspecialchars( $name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) . '</span>';
            },
            $html
        );

        // A bare <br>: the note CSS keeps real newlines (pre-wrap), so "<br>\n" would show every line break twice.
        $html = str_replace( "\n", '<br>', $html );

        if ( function_exists( 'wp_kses' ) ) {
            $html = wp_kses(
                $html,
                array(
                    'a'    => array(
                        'href'   => true,
                        'target' => true,
                        'rel'    => true,
                    ),
                    'span' => array(
                        'class'     => true,
                        'data-user' => true,
                    ),
                    'br'   => array(),
                ),
                array( 'http', 'https' )
            );
        }
        return $html;
    }

    /**
     * Plain-text excerpt with mentions shown as names.
     *
     * @param string   $text    Sanitized text.
     * @param callable $name_of Receives a user ID, returns a display name or null.
     * @param int      $length  Maximum characters.
     * @return string
     */
    public static function excerpt( $text, $name_of, $length = 300 ) {
        $plain = (string) preg_replace_callback(
            self::TOKEN,
            static function ( $m ) use ( $name_of ) {
                $name = call_user_func( $name_of, (int) $m[1] );
                return '@' . ( is_string( $name ) && '' !== $name ? $name : 'user' );
            },
            (string) $text
        );
        $plain = trim( preg_replace( '/\s+/u', ' ', $plain ) );
        if ( self::length( $plain ) > $length ) {
            $cut   = function_exists( 'mb_substr' ) ? mb_substr( $plain, 0, $length - 1, 'UTF-8' ) : substr( $plain, 0, $length - 1 );
            $plain = rtrim( $cut ) . '…';
        }
        return $plain;
    }
}
