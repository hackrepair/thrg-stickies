<?php
/**
 * Mention emails.
 *
 * @package THRG_Stickies
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds and sends "you were mentioned" emails following the THRG email
 * standard: `[domain] [Stickies] Purpose`. The builders are pure.
 */
final class THRG_STK_Email {

    const THROTTLE = 600;

    /**
     * Site identity for subjects: host plus explicit port, no scheme or path.
     *
     * @param string $home_url Home URL.
     * @param string $site_url Fallback site URL.
     * @return string
     */
    public static function domain( $home_url, $site_url = '' ) {
        foreach ( array( $home_url, $site_url ) as $url ) {
            $parts = parse_url( (string) $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Pure helper, tested without WordPress.
            if ( is_array( $parts ) && ! empty( $parts['host'] ) ) {
                return strtolower( $parts['host'] ) . ( isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '' );
            }
        }
        return 'site';
    }

    /**
     * Subject line.
     *
     * @param string $domain Domain from domain().
     * @return string
     */
    public static function subject( $domain ) {
        $purpose = function_exists( '__' ) ? __( 'You were mentioned in a note', 'thrg-stickies' ) : 'You were mentioned in a note';
        return '[' . $domain . '] [Stickies] ' . $purpose;
    }

    /**
     * Plain-text body.
     *
     * @param array $d author, recipient, page_label, note_url, site_url, excerpt.
     * @return string
     */
    public static function text( array $d ) {
        $lines   = array();
        $lines[] = sprintf( 'Hi %s,', $d['recipient'] );
        $lines[] = '';
        $lines[] = sprintf( '%s mentioned you in a sticky note on %s:', $d['author'], $d['page_label'] );
        $lines[] = '';
        foreach ( explode( "\n", wordwrap( $d['excerpt'], 72 ) ) as $line ) {
            $lines[] = '  > ' . $line;
        }
        $lines[] = '';
        $lines[] = 'View the note:';
        $lines[] = $d['note_url'];
        $lines[] = '';
        $lines[] = 'You need to be logged in to see it.';
        $lines[] = '';
        $lines[] = '--';
        $lines[] = 'Sent by Stickies on ' . $d['site_url'];
        return implode( "\n", $lines ) . "\n";
    }

    /**
     * HTML body (full width, inline styles).
     *
     * @param array $d Same keys as text(), plus optional logo_url.
     * @return string
     */
    public static function html( array $d ) {
        $e    = static function ( $s ) {
            return htmlspecialchars( (string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
        };
        $logo = empty( $d['logo_url'] ) ? '' : '<img src="' . $e( $d['logo_url'] ) . '" width="40" height="40" alt="" style="display:block;border:0;margin:0 0 12px">';
        return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>' . $e( 'Stickies' ) . '</title></head>'
            . '<body style="margin:0;padding:0;background:#f5f7f9;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;color:#18354b">'
            . '<div style="padding:24px 16px">'
            . '<div style="background:#fff;border:1px solid #d5dde4;border-left:4px solid #b34700;border-radius:6px;padding:20px 22px">'
            . $logo
            . '<p style="margin:0 0 14px;font-size:15px">Hi ' . $e( $d['recipient'] ) . ',</p>'
            . '<p style="margin:0 0 14px;font-size:15px"><strong>' . $e( $d['author'] ) . '</strong> mentioned you in a sticky note on <strong>' . $e( $d['page_label'] ) . '</strong>:</p>'
            . '<div style="background:#ffefb1;border-radius:4px;box-shadow:2px 1px 5px rgba(50,48,50,.33);padding:14px 16px;margin:0 0 18px;font-size:16px;line-height:1.4;color:#333;white-space:pre-wrap">' . $e( $d['excerpt'] ) . '</div>'
            . '<p style="margin:0 0 18px"><a href="' . $e( $d['note_url'] ) . '" style="display:inline-block;background:#b34700;color:#fff;text-decoration:none;padding:10px 16px;border-radius:4px;font-weight:600">View the note</a></p>'
            . '<p style="margin:0;font-size:13px;color:#4f6577">You need to be logged in to see it.</p>'
            . '</div>'
            . '<p style="margin:14px 0 0;font-size:12px;color:#4f6577">Sent by Stickies on <a href="' . $e( $d['site_url'] ) . '" style="color:#4f6577">' . $e( $d['site_url'] ) . '</a></p>'
            . '</div></body></html>';
    }

    /**
     * Send mention emails for a note to newly mentioned users.
     *
     * @param array $note      Note row.
     * @param int[] $user_ids  Users to notify.
     * @param int   $author_id Person who mentioned them.
     * @return int Number of emails accepted for sending.
     */
    public static function notify( array $note, array $user_ids, $author_id ) {
        if ( ! THRG_STK_Settings::get( 'emails' ) || ! $user_ids ) {
            return 0;
        }
        $author = get_userdata( (int) $author_id );
        if ( ! $author ) {
            return 0;
        }

        $name_of  = array( 'THRG_STK_Plugin', 'display_name' );
        $domain   = self::domain( home_url(), site_url() );
        $note_url = THRG_STK_Plugin::note_url( $note );
        $label    = '' !== $note['page_title'] ? $note['page_title'] . ' (' . $note['page_path'] . ')' : $note['page_path'];
        $sent     = 0;

        foreach ( array_unique( array_map( 'intval', $user_ids ) ) as $uid ) {
            if ( $uid === (int) $author_id ) {
                continue;
            }
            $user = get_userdata( $uid );
            if ( ! $user || ! is_email( $user->user_email ) ) {
                continue;
            }
            if ( ! THRG_STK_Access::can_view( $note, $uid, THRG_STK_Access::user_caps( $uid ) ) ) {
                continue;
            }
            $throttle = 'thrg_stk_mail_' . (int) $note['id'] . '_' . $uid;
            if ( get_transient( $throttle ) ) {
                continue;
            }

            $data = array(
                'author'     => $author->display_name,
                'recipient'  => $user->display_name,
                'page_label' => $label,
                'note_url'   => $note_url,
                'site_url'   => home_url( '/' ),
                'excerpt'    => THRG_STK_Content::excerpt( $note['content'], $name_of, 300 ),
                'logo_url'   => plugins_url( 'assets/brand/thrg-heart-128.png', THRG_STK_FILE ),
            );
            $text = self::text( $data );

            $headers = array( 'Content-Type: text/html; charset=UTF-8' );
            if ( is_email( $author->user_email ) ) {
                $headers[] = 'Reply-To: ' . str_replace( array( "\r", "\n", '<', '>', '"' ), '', $author->display_name ) . ' <' . $author->user_email . '>';
            }

            $alt = static function ( $mailer ) use ( $text ) {
                $mailer->AltBody = $text; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
            };
            add_action( 'phpmailer_init', $alt );
            $ok = wp_mail( $user->user_email, self::subject( $domain ), self::html( $data ), $headers );
            remove_action( 'phpmailer_init', $alt );

            if ( $ok ) {
                set_transient( $throttle, 1, self::THROTTLE );
                ++$sent;
            }
        }
        return $sent;
    }
}
