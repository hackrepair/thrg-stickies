<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../includes/class-thrg-stk-url.php';

$n = array( 'THRG_STK_URL', 'normalize' );

check( 'home page', '/' === THRG_STK_URL::normalize( '/' ) );
check( 'adds trailing slash', '/about/' === THRG_STK_URL::normalize( '/about' ) );
check( 'keeps file paths', '/files/a.html' === THRG_STK_URL::normalize( '/files/a.html' ) );
check( 'drops fragment', '/about/' === THRG_STK_URL::normalize( '/about/#team' ) );
check( 'drops scheme and host', '/about/' === THRG_STK_URL::normalize( 'https://example.com:8443/about/' ) );
check( 'collapses slashes', '/a/b/' === THRG_STK_URL::normalize( '/a///b' ) );
check( 'drops index.php', '/' === THRG_STK_URL::normalize( '/index.php' ) );
check( 'drops tracking', '/p/' === THRG_STK_URL::normalize( '/p/?utm_source=x&fbclid=1&gclid=2&utm_campaign=y' ) );
check( 'sorts params', '/p/?a=1&b=2' === THRG_STK_URL::normalize( '/p/?b=2&a=1' ) );
check( 'keeps flag params', '/p/?preview' === THRG_STK_URL::normalize( '/p/?preview' ) );
check( 'keeps dots in names', '/p/?a.b=1' === THRG_STK_URL::normalize( '/p/?a.b=1' ) );
check( 'encodes values', '/p/?q=a%20b' === THRG_STK_URL::normalize( '/p/?q=a+b' ) );
check( 'same key for tracking variants', THRG_STK_URL::key( THRG_STK_URL::normalize( '/p/?utm_x=1' ) ) === THRG_STK_URL::key( THRG_STK_URL::normalize( 'http://other.test/p' ) ) );
check( 'subfolder home stripped', '/about/' === THRG_STK_URL::normalize( '/blog/about/', '/blog/' ) );
check( 'subfolder home root', '/' === THRG_STK_URL::normalize( '/blog/', '/blog' ) );
check( 'subfolder prefix lookalike kept', '/blogger/' === THRG_STK_URL::normalize( '/blogger/', '/blog/' ) );
check( 'rejects dot segments', '' === THRG_STK_URL::normalize( '/a/../b' ) );
check( 'rejects control chars', '' === THRG_STK_URL::normalize( "/a\nb" ) );
check( 'rejects empty', '' === THRG_STK_URL::normalize( '' ) );
check( 'rejects too long', '' === THRG_STK_URL::normalize( '/' . str_repeat( 'a', 3000 ) ) );

check( 'admin keeps screen params', '/wp-admin/post.php?action=edit&post=42' === THRG_STK_URL::normalize( '/wp-admin/post.php?post=42&action=edit&_wpnonce=abc&classic-editor', '/', 'admin' ) );
check( 'admin in subfolder install', '/wp-admin/options-general.php' === THRG_STK_URL::normalize( '/wp/wp-admin/options-general.php', '/', 'admin' ) );
check( 'admin root is dashboard', '/wp-admin/index.php' === THRG_STK_URL::normalize( '/wp-admin/', '/', 'admin' ) );
check( 'admin outside wp-admin rejected', '' === THRG_STK_URL::normalize( '/about/', '/', 'admin' ) );
check( 'admin and front keys differ', THRG_STK_URL::key( '/x', 'admin' ) !== THRG_STK_URL::key( '/x', 'front' ) );
check( 'key is 40 hex', 1 === preg_match( '/^[0-9a-f]{40}$/', THRG_STK_URL::key( '/' ) ) );

done();
