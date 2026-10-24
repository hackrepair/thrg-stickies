<?php
/**
 * Integration test against a real WordPress install with the plugin active.
 *
 * Run inside the site's container, for example:
 *   docker exec <container> php /var/www/html/wp-content/plugins/thrg-stickies/tests/integration.php /var/www/html
 *
 * Creates temporary users and notes, checks the REST API, permissions,
 * sanitizing, visibility, conflicts and mention emails, then removes them.
 * Mail is intercepted with pre_wp_mail; nothing is sent.
 */

// phpcs:ignoreFile -- Test harness.

if ( 'cli' !== PHP_SAPI ) {
    exit;
}

$wp_root = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : '/var/www/html';
if ( ! is_readable( $wp_root . '/wp-load.php' ) ) {
    fwrite( STDERR, "wp-load.php not found in $wp_root\n" );
    exit( 2 );
}
$_SERVER['HTTP_HOST']   = parse_url( 'http://localhost', PHP_URL_HOST );
$_SERVER['REQUEST_URI'] = '/';
define( 'WP_USE_THEMES', false );
require $wp_root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$fail = 0;
$pass = 0;
function check( $name, $cond, $detail = '' ) {
    global $fail, $pass;
    if ( $cond ) {
        $pass++;
        echo "PASS $name\n";
    } else {
        $fail++;
        echo "FAIL $name" . ( '' !== $detail ? ' :: ' . ( is_string( $detail ) ? $detail : json_encode( $detail ) ) : '' ) . "\n";
    }
}

if ( ! class_exists( 'THRG_STK_Plugin' ) ) {
    fwrite( STDERR, "Stickies is not active.\n" );
    exit( 2 );
}

// Mail capture.
$mails = array();
add_filter(
    'pre_wp_mail',
    function ( $null, $atts ) use ( &$mails ) {
        $mails[] = $atts;
        return true;
    },
    10,
    2
);

$suffix  = substr( md5( microtime() ), 0, 6 );
$created = array();
function make_user( $role, $suffix, $name ) {
    $id = wp_insert_user(
        array(
            'user_login'   => "stktest_{$name}_{$suffix}",
            'user_pass'    => wp_generate_password( 20 ),
            'user_email'   => "stktest_{$name}_{$suffix}@example.test",
            'display_name' => ucfirst( $name ) . ' Tester',
            'role'         => $role,
        )
    );
    if ( is_wp_error( $id ) ) {
        fwrite( STDERR, $id->get_error_message() . "\n" );
        exit( 2 );
    }
    return $id;
}
$admin    = make_user( 'administrator', $suffix, 'admin' );
$editor   = make_user( 'editor', $suffix, 'editor' );
$editor2  = make_user( 'editor', $suffix, 'editortwo' );
$author   = make_user( 'author', $suffix, 'author' );
$reader   = make_user( 'subscriber', $suffix, 'reader' );
( new WP_User( $reader ) )->add_cap( 'thrg_stk_read' );
$created = array( $admin, $editor, $editor2, $author, $reader );
$notes   = array();

function req( $method, $route, $params = array(), $user = 0 ) {
    wp_set_current_user( $user );
    $r = new WP_REST_Request( $method, '/thrg-stickies/v1/' . $route );
    if ( 'GET' === $method ) {
        $r->set_query_params( $params );
    } else {
        $r->set_header( 'content-type', 'application/json' );
        $r->set_body( wp_json_encode( $params ) );
    }
    $res = rest_do_request( $r );
    return array( $res->get_status(), $res->get_data() );
}

try {
    global $wpdb;
    check( 'schema is current', THRG_STK_Schema::VERSION === get_option( THRG_STK_Schema::VERSION_OPTION ) );
    check( 'edited_at column exists', (bool) $wpdb->get_var( "SHOW COLUMNS FROM `" . THRG_STK_Schema::table() . "` LIKE 'edited_at'" ) );

    // Capabilities from activation defaults.
    check( 'admin has all caps', user_can( $admin, 'thrg_stk_read' ) && user_can( $admin, 'thrg_stk_create' ) && user_can( $admin, 'thrg_stk_manage' ) );
    check( 'editor can read and create', user_can( $editor, 'thrg_stk_read' ) && user_can( $editor, 'thrg_stk_create' ) && ! user_can( $editor, 'thrg_stk_manage' ) );
    check( 'author has no access by default', ! user_can( $author, 'thrg_stk_read' ) );

    // Logged out and unauthorised.
    list( $s ) = req( 'GET', 'notes', array( 'uri' => '/' ), 0 );
    check( 'logged out list is refused', 401 === $s, $s );
    list( $s ) = req( 'POST', 'notes', array( 'uri' => '/', 'content' => 'x' ), 0 );
    check( 'logged out create is refused', 401 === $s, $s );
    list( $s ) = req( 'GET', 'notes', array( 'uri' => '/' ), $author );
    check( 'author without access is refused', 403 === $s, $s );
    list( $s ) = req( 'POST', 'notes', array( 'uri' => '/', 'content' => 'x' ), $reader );
    check( 'reader cannot create', 403 === $s, $s );
    list( $s ) = req( 'GET', 'users', array( 'search' => 'stk' ), $reader );
    check( 'reader cannot search users', 403 === $s, $s );

    // Create.
    $uri = '/stickies-test-' . $suffix . '/?utm_source=x';
    list( $s, $d ) = req(
        'POST',
        'notes',
        array(
            'uri'        => $uri,
            'content'    => "Fix <script>alert(1)</script> here https://example.com/a?b=1 @[user:$editor2] @[user:$author]",
            'pos_top'    => 300,
            'pos_center' => 200,
            'width'      => 260,
            'height'     => 180,
            'color'      => 'blue',
            'title'      => 'Test <b>Page</b>',
        ),
        $editor
    );
    check( 'editor creates note', 201 === $s, array( $s, $d ) );
    $n1      = isset( $d['id'] ) ? (int) $d['id'] : 0;
    $notes[] = $n1;
    check( 'rendered escapes script', false === strpos( $d['rendered'], '<script' ) && false !== strpos( $d['rendered'], '&lt;script&gt;' ) );
    check( 'rendered links url', false !== strpos( $d['rendered'], '<a href="https://example.com/a?b=1"' ) );
    check( 'mention of editor2 kept as chip', false !== strpos( $d['rendered'], 'data-user="' . $editor2 . '"' ) );
    check( 'mention of author (no access) dropped', false === strpos( $d['content'], '@[user:' . $author . ']' ) );
    check( 'page normalized, tracking dropped', '/stickies-test-' . $suffix . '/' === $d['page'], $d['page'] );
    check( 'color saved', 'blue' === $d['color'] );
    check( 'author can edit own', true === $d['can_edit'] );
    $row = THRG_STK_Notes::find( $n1 );
    check( 'title stored as text', 'Test Page' === $row['page_title'], $row['page_title'] );
    check( 'one mention email sent', 1 === count( $mails ), count( $mails ) );
    $log = json_decode( $row['mail_status'], true );
    check( 'accepted mail status recorded', 'accepted' === $log[ $editor2 ]['state'] );
    delete_transient( 'thrg_stk_mail_' . $n1 . '_' . $editor2 );
    $fail_mail = static function () { return false; };
    add_filter( 'pre_wp_mail', $fail_mail, 99 );
    THRG_STK_Email::notify( $row, array( $editor2 ), $editor );
    remove_filter( 'pre_wp_mail', $fail_mail, 99 );
    array_pop( $mails ); // Discard the intentionally failed attempt from the capture.
    $failed_row = THRG_STK_Notes::find( $n1 );
    $failed_log = json_decode( $failed_row['mail_status'], true );
    check( 'failed mail status recorded', 'failed' === $failed_log[ $editor2 ]['state'] );
    check( 'mail logging does not change revision', $row['revision'] === $failed_row['revision'] );
    set_transient( 'thrg_stk_mail_' . $n1 . '_' . $editor2, 1, 600 );

    if ( $mails ) {
        $host = THRG_STK_Email::domain( home_url(), site_url() );
        check( 'mention email subject', '[' . $host . '] [Stickies] You were mentioned in a note' === $mails[0]['subject'], $mails[0]['subject'] );
        check( 'mention email to editor2', 'stktest_editortwo_' . $suffix . '@example.test' === $mails[0]['to'] );
        check( 'mention email links to note', false !== strpos( $mails[0]['message'], '#thrg-stk-' . $n1 ) );
        check( 'mention email body escaped', false === strpos( $mails[0]['message'], '<script' ) );
    }

    // Read back and visibility.
    list( $s, $d ) = req( 'GET', 'notes', array( 'uri' => 'https://other.test/stickies-test-' . $suffix ), $reader );
    check( 'reader sees team note via any host', 200 === $s && 1 === count( $d['notes'] ), array( $s, $d ) );
    check( 'reader cannot edit', false === $d['notes'][0]['can_edit'] );

    list( $s ) = req( 'PATCH', 'notes/' . $n1, array( 'content' => 'hijack' ), $editor2 );
    check( 'other editor cannot edit', 403 === $s, $s );
    list( $s, $d ) = req( 'PATCH', 'notes/' . $n1, array( 'status' => 'resolved' ), $editor2 );
    check( 'mentioned editor can resolve', 200 === $s && 'resolved' === $d['status'] && 'Editortwo Tester' === $d['resolved_by'], array( $s, $d ) );
    list( $s, $d ) = req( 'PATCH', 'notes/' . $n1, array( 'status' => 'open' ), $editor );
    check( 'author can reopen', 200 === $s && 'open' === $d['status'] );
    check( 'resolve/reopen is not an edit', false === $d['edited'] );

    // Conflict.
    $stamp = $d['updated_at'];
    sleep( 1 );
    list( $s, $d ) = req( 'PATCH', 'notes/' . $n1, array( 'content' => 'Edited by admin', 'updated_at' => $stamp ), $admin );
    check( 'admin (manager) can edit', 200 === $s, array( $s, $d ) );
    check( 'text change marks edited', true === $d['edited'] );
    list( $s, $d ) = req( 'PATCH', 'notes/' . $n1, array( 'content' => 'Stale edit', 'updated_at' => $stamp ), $editor );
    check( 'stale edit gets 409', 409 === $s, $s );
    check( '409 carries current note', isset( $d['data']['note']['content'] ) && 'Edited by admin' === $d['data']['note']['content'] );
    $rev = (int) $d['data']['note']['revision'];
    list( $s ) = req( 'PATCH', 'notes/' . $n1, array( 'content' => 'Old revision', 'revision' => $rev - 1 ), $admin );
    check( 'stale revision gets 409', 409 === $s, $s );

    // A writer that wins between the read and the write still yields a 409 with the latest note.
    $race = static function ( $sql ) use ( $n1 ) {
        static $busy = false;
        global $wpdb;
        if ( ! $busy && 0 === strpos( ltrim( $sql ), 'UPDATE `' . THRG_STK_Schema::table() . '`' ) && false !== strpos( $sql, '`content` = \'Race loser\'' ) ) {
            $busy = true;
            $wpdb->query( $wpdb->prepare( 'UPDATE `' . THRG_STK_Schema::table() . '` SET revision = revision + 1, content = %s WHERE id = %d', 'Race winner', $n1 ) ); // phpcs:ignore
            $busy = false;
        }
        return $sql;
    };
    add_filter( 'query', $race );
    list( $s, $d ) = req( 'PATCH', 'notes/' . $n1, array( 'content' => 'Race loser', 'revision' => $rev ), $admin );
    remove_filter( 'query', $race );
    check( 'lost write race gets 409', 409 === $s, $s );
    check( 'lost race returns winning note', isset( $d['data']['note']['content'] ) && 'Race winner' === $d['data']['note']['content'], $d );

    // Bulk resolve and reopen advance the revision.
    wp_set_current_user( $admin );
    $before = THRG_STK_Notes::find( $n1 );
    check( 'bulk resolve applies', 1 === THRG_STK_Admin::apply( 'resolve', array( $n1 ) ) );
    $after = THRG_STK_Notes::find( $n1 );
    check( 'bulk resolve bumps revision', $after['revision'] === $before['revision'] + 1 && 'resolved' === $after['status'] );
    check( 'bulk reopen applies', 1 === THRG_STK_Admin::apply( 'reopen', array( $n1 ) ) );

    // Geometry validation.
    list( $s ) = req( 'PATCH', 'notes/' . $n1, array( 'width' => 99999 ), $editor );
    check( 'oversize width rejected', 400 === $s, $s );
    list( $s ) = req( 'PATCH', 'notes/' . $n1, array( 'color' => 'red;}' ), $editor );
    check( 'bad color rejected', 400 === $s, $s );
    list( $s ) = req( 'POST', 'notes', array( 'uri' => '/a/../b', 'content' => 'x' ), $editor );
    check( 'bad page rejected', 400 === $s, $s );

    // Private and mentioned-only.
    list( $s, $d ) = req( 'POST', 'notes', array( 'uri' => $uri, 'content' => 'Just me', 'visibility' => 'private' ), $editor );
    $n2      = (int) $d['id'];
    $notes[] = $n2;
    list( $s, $d ) = req( 'POST', 'notes', array( 'uri' => $uri, 'content' => "Only @[user:$reader]", 'visibility' => 'mentioned' ), $editor );
    $n3      = (int) $d['id'];
    $notes[] = $n3;
    list( $s, $d ) = req( 'GET', 'notes', array( 'uri' => $uri ), $editor2 );
    $ids = wp_list_pluck( $d['notes'], 'id' );
    check( 'other editor sees team only', in_array( $n1, $ids, true ) && ! in_array( $n2, $ids, true ) && ! in_array( $n3, $ids, true ), $ids );
    list( $s, $d ) = req( 'GET', 'notes', array( 'uri' => $uri ), $reader );
    $ids = wp_list_pluck( $d['notes'], 'id' );
    check( 'mentioned reader sees mentioned-only', in_array( $n3, $ids, true ) && ! in_array( $n2, $ids, true ), $ids );
    list( $s, $d ) = req( 'GET', 'notes', array( 'uri' => $uri ), $admin );
    $ids = wp_list_pluck( $d['notes'], 'id' );
    check( 'admin does not see private on page', ! in_array( $n2, $ids, true ) && in_array( $n3, $ids, true ), $ids );
    list( $s ) = req( 'PATCH', 'notes/' . $n2, array( 'content' => 'x' ), $admin );
    check( 'admin cannot touch private note it cannot see', 404 === $s, $s );

    // Settings can remain checked after role capabilities are removed externally.
    wp_set_current_user( $editor );
    get_role( 'editor' )->remove_cap( THRG_STK_Access::CAP_READ );
    get_role( 'editor' )->remove_cap( THRG_STK_Access::CAP_CREATE );
    THRG_STK_Access::sync_roles();
    check( 'saved editor permissions repair missing role caps', user_can( $editor, THRG_STK_Access::CAP_READ ) && user_can( $editor, THRG_STK_Access::CAP_CREATE ) );
    check( 'repair refreshes current user capability cache', current_user_can( THRG_STK_Access::CAP_CREATE ) );
    check( 'repair does not grant unconfigured author access', ! user_can( $author, THRG_STK_Access::CAP_READ ) );

    // Mention search.
    list( $s, $d ) = req( 'GET', 'users', array( 'search' => 'editortwo_' . $suffix ), $editor );
    check( 'user search finds editor2', 200 === $s && 1 === count( $d['users'] ) && $editor2 === $d['users'][0]['id'], $d );
    check( 'user search returns no email or login', ! isset( $d['users'][0]['email'] ) && ! isset( $d['users'][0]['login'] ) );
    list( $s, $d ) = req( 'GET', 'users', array( 'search' => 'author_' . $suffix ), $editor );
    check( 'user search skips users without access', 0 === count( $d['users'] ) );

    // Duplicate display names stay distinct; email lookup is administrator-only.
    wp_update_user( array( 'ID' => $editor, 'display_name' => 'Jim ' . $suffix ) );
    wp_update_user( array( 'ID' => $editor2, 'display_name' => 'Jim ' . $suffix ) );
    list( $s, $d ) = req( 'GET', 'users', array( 'search' => 'Jim ' . $suffix ), $editor );
    check( 'duplicate names both returned', 2 === count( $d['users'] ) );
    check( 'duplicate labels distinct', $d['users'][0]['label'] !== $d['users'][1]['label'] );
    check( 'editor labels contain no email', false === strpos( wp_json_encode( $d['users'] ), '@example.test' ) );
    $address = get_userdata( $editor2 )->user_email;
    list( $s, $d ) = req( 'GET', 'users', array( 'search' => $address ), $admin );
    check( 'admin can search exact email', 1 === count( $d['users'] ) && $editor2 === $d['users'][0]['id'] );
    check( 'admin label identifies email', false !== strpos( $d['users'][0]['label'], $address ) );
    list( $s, $d ) = req( 'GET', 'users', array( 'search' => $address ), $editor );
    check( 'editor cannot search email', 0 === count( $d['users'] ) );

    // Administrators are told why a match is not offered; nobody else is.
    list( $s, $d ) = req( 'GET', 'users', array( 'search' => 'author_' . $suffix ), $admin );
    check( 'admin sees why a match cannot be mentioned', 0 === count( $d['users'] ) && 1 === count( $d['hints'] ) && false !== strpos( $d['hints'][0]['text'], 'cannot see notes yet' ) && false !== strpos( $d['hints'][0]['url'], 'page=thrg-stickies-settings' ), $d );
    check( 'admin hint names the account email', false !== strpos( $d['hints'][0]['text'], '@' ) );
    list( $s, $d ) = req( 'GET', 'users', array( 'search' => 'author_' . $suffix ), $editor );
    check( 'editor gets no hints and no account details', array() === $d['hints'] && false === strpos( wp_json_encode( $d ), '@example.test' ), $d );
    list( $s, $d ) = req( 'GET', 'users', array( 'search' => 'editortwo_' . $suffix ), $admin );
    check( 'no hint for someone who can be mentioned', array() === $d['hints'] && 1 === count( $d['users'] ), $d );
    list( $s, $d ) = req( 'GET', 'users', array( 'search' => 'zzzz_nobody_' . $suffix ), $admin );
    check( 'no hint when nobody matches', array() === $d['hints'] && array() === $d['users'] );
    wp_update_user( array( 'ID' => $editor, 'display_name' => 'Editor Tester' ) );
    wp_update_user( array( 'ID' => $editor2, 'display_name' => 'Editortwo Tester' ) );

    // Email throttle and new-mention diff.
    $mails = array();
    list( $s ) = req( 'PATCH', 'notes/' . $n3, array( 'content' => "Only @[user:$reader] again" ), $editor );
    check( 'no repeat email for same mention', 0 === count( $mails ), count( $mails ) );

    // Delete.
    list( $s ) = req( 'DELETE', 'notes/' . $n1, array(), $editor2 );
    check( 'other editor cannot delete', 403 === $s, $s );
    list( $s ) = req( 'DELETE', 'notes/' . $n1, array(), $editor );
    check( 'author deletes', 200 === $s && null === THRG_STK_Notes::find( $n1 ) );

    // Settings matrix applies to roles.
    $before = get_option( THRG_STK_Settings::OPTION );
    $opts   = THRG_STK_Settings::all();
    $opts['roles']['author'] = array( 'read' );
    update_option( THRG_STK_Settings::OPTION, THRG_STK_Settings::sanitize( $opts ) );
    check( 'matrix grants author read', user_can( $author, 'thrg_stk_read' ) && ! user_can( $author, 'thrg_stk_create' ) );
    update_option( THRG_STK_Settings::OPTION, $before );
    check( 'matrix restored', ! user_can( $author, 'thrg_stk_read' ) && user_can( $editor, 'thrg_stk_create' ) );
} finally {
    foreach ( $notes as $id ) {
        THRG_STK_Notes::delete( $id );
    }
    foreach ( $created as $uid ) {
        wp_delete_user( $uid );
    }
    global $wpdb;
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_%thrg\\_stk\\_mail\\_%'" );
}

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
