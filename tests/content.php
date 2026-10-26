<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../includes/class-thrg-stk-content.php';

$names   = array( 2 => 'Ann', 3 => 'Bo <b>' );
$name_of = static function ( $id ) use ( $names ) {
    return isset( $names[ $id ] ) ? $names[ $id ] : null;
};

// Sanitize.
check( 'non-string becomes empty', '' === THRG_STK_Content::sanitize( array( 'x' ) ) );
check( 'trims and normalizes newlines', "a\nb" === THRG_STK_Content::sanitize( "  a\r\nb  " ) );
check( 'removes control characters', 'ab' === THRG_STK_Content::sanitize( "a\x00\x07b" ) );
check( 'keeps tabs and newlines', "a\tb\nc" === THRG_STK_Content::sanitize( "a\tb\nc" ) );
check( 'limits blank lines', "a\n\n\nb" === THRG_STK_Content::sanitize( "a\n\n\n\n\n\nb" ) );
check( 'keeps html as text', '<b>hi</b>' === THRG_STK_Content::sanitize( '<b>hi</b>' ) );
check( 'caps length', 2000 === THRG_STK_Content::length( THRG_STK_Content::sanitize( str_repeat( 'é', 2500 ) ) ) );
check( 'no half token after cap', false === strpos( THRG_STK_Content::sanitize( str_repeat( 'a', 1995 ) . '@[user:12345]' ), '@[' ) );
check( 'invalid utf-8 is repaired', THRG_STK_Content::sanitize( "a\xC3b" ) === mb_convert_encoding( "a\xC3b", 'UTF-8', 'UTF-8' ) );

// Mentions.
check( 'mentions parsed in order, unique', array( 3, 2 ) === THRG_STK_Content::mentions( 'hi @[user:3] and @[user:2] @[user:3]' ) );
$many = '';
for ( $i = 1; $i <= 15; $i++ ) {
    $many .= "@[user:$i] ";
}
check( 'mentions capped at 10', 10 === count( THRG_STK_Content::mentions( $many ) ) );
check( 'zero id ignored', array() === THRG_STK_Content::mentions( '@[user:0]' ) );
check( 'filter drops unknown users', 'hi @ and @[user:2]' === THRG_STK_Content::filter_mentions( 'hi @[user:9] and @[user:2]', $name_of ) );

// Render.
$r = THRG_STK_Content::render( "<script>alert(1)</script>\nline2", $name_of );
check( 'script escaped', false === strpos( $r, '<script' ) && false !== strpos( $r, '&lt;script&gt;' ) );
check( 'newline to br', false !== strpos( $r, '<br>line2' ) );
check( 'no raw newline left beside the br', false === strpos( $r, "\n" ) );
$r = THRG_STK_Content::render( 'see https://example.com/a?b=1&c=2.', $name_of );
check( 'url linked, trailing dot outside', false !== strpos( $r, '<a href="https://example.com/a?b=1&amp;c=2" target="_blank" rel="noopener noreferrer nofollow">' ) && '.' === substr( $r, -1 ) );
$r = THRG_STK_Content::render( 'x "https://evil.test/" onmouseover="alert(1)', $name_of );
check( 'quote breakout not possible', false === strpos( $r, '" onmouseover' ) && false !== strpos( $r, 'href="https://evil.test/"' ) );
$r = THRG_STK_Content::render( 'javascript:alert(1) and data:text/html,x', $name_of );
check( 'javascript url not linked', false === strpos( $r, '<a' ) );
$r = THRG_STK_Content::render( 'https://a.test/<img src=x onerror=alert(1)>', $name_of );
check( 'tag after url escaped', false === strpos( $r, '<img' ) );
$r = THRG_STK_Content::render( 'hey @[user:3] and @[user:7]', $name_of );
check( 'mention chip with escaped name', false !== strpos( $r, '<span class="thrg-stk-mention" data-user="3">@Bo &lt;b&gt;</span>' ) );
check( 'unknown mention shows generic', false !== strpos( $r, 'data-user="7">@user</span>' ) );
$r = THRG_STK_Content::render( 'https://example.com/' . str_repeat( 'x', 100 ), $name_of );
check( 'long link label shortened', false !== strpos( $r, '&hellip;</a>' ) );
check( 'entity in url not double escaped', false === strpos( THRG_STK_Content::render( 'https://a.test/?x=1&y=2', $name_of ), '&amp;amp;' ) );

// Excerpt.
check( 'excerpt names mentions', 'Hi @Ann see this' === THRG_STK_Content::excerpt( "Hi @[user:2]\nsee   this", $name_of ) );
check( 'excerpt shortened', 10 === mb_strlen( THRG_STK_Content::excerpt( str_repeat( 'a ', 50 ), $name_of, 10 ) ) );

done();
