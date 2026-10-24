<?php
/**
 * Page keys: which page a note belongs to.
 *
 * @package THRG_Stickies
 */

defined( 'ABSPATH' ) || exit;

/**
 * Turns a request path into a stable page key. Pure functions, no WordPress calls.
 *
 * The key ignores scheme, host and port, so notes survive HTTP to HTTPS moves,
 * staging copies and proxies. Tracking parameters are dropped and the rest sorted.
 */
final class THRG_STK_URL {

    /** Query parameters that never identify a different page. */
    const TRACKING = array( 'fbclid', 'gclid', 'msclkid', 'dclid', 'mc_cid', 'mc_eid', '_ga', '_gl', 'ref', 'yclid', 'igshid', 'thrg-stk' );

    /** Admin query parameters that identify a screen; everything else is ignored. */
    const ADMIN_KEEP = array( 'page', 'post', 'action', 'taxonomy', 'post_type', 'tab', 'tag_ID', 'user_id' );

    /** Longest stored path. */
    const MAX_LENGTH = 2048;

    /**
     * Normalize a path and query.
     *
     * @param string $uri       Path with optional query and fragment (a full URL is accepted too).
     * @param string $home_path Path part of the home URL, for sites in a subfolder.
     * @param string $context   'front' or 'admin'.
     * @return string Normalized path, '' when the input cannot be used.
     */
    public static function normalize( $uri, $home_path = '/', $context = 'front' ) {
        $uri = trim( (string) $uri );
        if ( '' === $uri || strlen( $uri ) > 4096 || preg_match( '/[\x00-\x1F\x7F]/', $uri ) ) {
            return '';
        }

        $hash = strpos( $uri, '#' );
        if ( false !== $hash ) {
            $uri = substr( $uri, 0, $hash );
        }

        // Drop any scheme and host.
        if ( preg_match( '#^[a-z][a-z0-9+.\-]*://[^/?]*#i', $uri, $m ) ) {
            $uri = substr( $uri, strlen( $m[0] ) );
        } elseif ( 0 === strpos( $uri, '//' ) ) {
            $uri = preg_replace( '#^//[^/?]*#', '', $uri );
        }

        $qpos  = strpos( $uri, '?' );
        $path  = false === $qpos ? $uri : substr( $uri, 0, $qpos );
        $query = false === $qpos ? '' : substr( $uri, $qpos + 1 );

        $path = '/' . ltrim( preg_replace( '#/{2,}#', '/', $path ), '/' );
        if ( preg_match( '#(^|/)\.\.?(/|$)#', $path ) ) {
            return '';
        }

        if ( 'admin' === $context ) {
            $at = strpos( $path, '/wp-admin/' );
            if ( false === $at ) {
                return '';
            }
            $path = substr( $path, $at );
            if ( '/wp-admin/' === $path ) {
                $path = '/wp-admin/index.php';
            }
        } else {
            $home = '/' . trim( (string) $home_path, '/' );
            $home = '/' === $home ? '/' : $home . '/';
            if ( '/' !== $home ) {
                if ( 0 === strpos( $path . '/', $home ) ) {
                    $path = '/' . substr( $path, strlen( $home ) );
                }
            }
            $path = preg_replace( '#/index\.php$#', '/', $path );
            $last = substr( $path, strrpos( $path, '/' ) + 1 );
            if ( '' !== $last && false === strpos( $last, '.' ) ) {
                $path .= '/';
            }
        }

        $params = self::parse_query( $query );
        $kept   = array();
        foreach ( $params as $name => $value ) {
            if ( 'admin' === $context ) {
                if ( in_array( $name, self::ADMIN_KEEP, true ) ) {
                    $kept[ $name ] = $value;
                }
            } elseif ( 0 !== strpos( $name, 'utm_' ) && ! in_array( $name, self::TRACKING, true ) ) {
                $kept[ $name ] = $value;
            }
        }
        ksort( $kept, SORT_STRING );

        $pairs = array();
        foreach ( $kept as $name => $value ) {
            $pairs[] = rawurlencode( $name ) . ( null === $value ? '' : '=' . rawurlencode( $value ) );
        }

        $result = $path . ( $pairs ? '?' . implode( '&', $pairs ) : '' );
        return strlen( $result ) > self::MAX_LENGTH ? '' : $result;
    }

    /**
     * The indexed key for a normalized path.
     *
     * @param string $normalized Output of normalize().
     * @param string $context    'front' or 'admin'.
     * @return string 40 hex characters.
     */
    public static function key( $normalized, $context = 'front' ) {
        return sha1( ( 'admin' === $context ? 'admin' : 'front' ) . '|' . $normalized );
    }

    /**
     * Parse a query string without PHP's dot-to-underscore rewriting. Later
     * values win; array-style names are kept as plain strings.
     *
     * @param string $query Raw query string.
     * @return array<string,string|null>
     */
    private static function parse_query( $query ) {
        $out = array();
        foreach ( explode( '&', (string) $query ) as $pair ) {
            if ( '' === $pair ) {
                continue;
            }
            $eq   = strpos( $pair, '=' );
            $name = rawurldecode( str_replace( '+', ' ', false === $eq ? $pair : substr( $pair, 0, $eq ) ) );
            if ( '' === $name ) {
                continue;
            }
            $out[ $name ] = false === $eq ? null : rawurldecode( str_replace( '+', ' ', substr( $pair, $eq + 1 ) ) );
        }
        return $out;
    }
}
