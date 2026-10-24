<?php
require __DIR__ . '/bootstrap.php';
function __( $text, $domain = '' ) { return $text; }

require __DIR__ . '/../includes/class-thrg-stk-email.php';

check( 'domain from home url', 'example.com' === THRG_STK_Email::domain( 'https://Example.com/blog/' ) );
check( 'domain keeps subdomain', 'dev.example.com' === THRG_STK_Email::domain( 'https://dev.example.com' ) );
check( 'domain keeps explicit port', 'localhost:8081' === THRG_STK_Email::domain( 'http://localhost:8081/' ) );
check( 'domain falls back to site url', 'b.test' === THRG_STK_Email::domain( '', 'http://b.test/wp' ) );
check( 'subject format', '[example.com] [Stickies] You were mentioned in a note' === THRG_STK_Email::subject( 'example.com' ) );
check( 'subject has no user details', false === strpos( THRG_STK_Email::subject( 'x.test' ), '@' ) );

$d = array(
    'author'     => 'Jim <script>',
    'recipient'  => 'Ann',
    'page_label' => 'About "us" (/about/)',
    'note_url'   => 'http://x.test/about/#thrg-stk-5',
    'site_url'   => 'http://x.test/',
    'excerpt'    => 'Fix <b>this</b> & that',
    'logo_url'   => 'http://x.test/heart.png',
);
$html = THRG_STK_Email::html( $d );
check( 'html escapes author', false === strpos( $html, '<script>' ) && false !== strpos( $html, 'Jim &lt;script&gt;' ) );
check( 'html escapes excerpt', false !== strpos( $html, 'Fix &lt;b&gt;this&lt;/b&gt; &amp; that' ) );
check( 'html escapes page label', false !== strpos( $html, 'About &quot;us&quot;' ) );
check( 'html links to note', false !== strpos( $html, 'href="http://x.test/about/#thrg-stk-5"' ) );
check( 'html is full width (no 600px cap)', false === strpos( $html, '600px' ) );
check( 'html names site', false !== strpos( $html, 'Sent by Stickies on' ) );
$text = THRG_STK_Email::text( $d );
check( 'text greets recipient', 0 === strpos( $text, 'Hi Ann,' ) );
check( 'text has link', false !== strpos( $text, "View the note:\nhttp://x.test/about/#thrg-stk-5" ) );
check( 'text quotes excerpt', false !== strpos( $text, '  > Fix <b>this</b> & that' ) );
check( 'text says login needed', false !== strpos( $text, 'You need to be logged in to see it.' ) );

done();
