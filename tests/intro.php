<?php
/** The first-install welcome note: when it is added and what it contains. */
require __DIR__ . '/bootstrap.php';

function __( $s ) { return $s; }

require __DIR__ . '/../includes/class-thrg-stk-url.php';
require __DIR__ . '/../includes/class-thrg-stk-content.php';
require __DIR__ . '/../includes/class-thrg-stk-intro.php';

// When to add it.
check( 'first install adds', 'add' === THRG_STK_Intro::decide( false, 5, 0 ) );
check( 'already added skips', 'skip' === THRG_STK_Intro::decide( 12, 5, 0 ) );
check( 'skipped before stays skipped', 'skip' === THRG_STK_Intro::decide( -1, 5, 0 ) );
check( 'kept notes from a reinstall skip', 'skip' === THRG_STK_Intro::decide( false, 5, 3 ) );
check( 'WP-CLI with no user waits', 'wait' === THRG_STK_Intro::decide( false, 0, 0 ) );
check( 'WP-CLI with notes still skips', 'skip' === THRG_STK_Intro::decide( false, 0, 2 ) );

// What it holds.
$text = THRG_STK_Content::sanitize( THRG_STK_Intro::text( true ) );
$plain = THRG_STK_Content::sanitize( THRG_STK_Intro::text() );
$path = THRG_STK_URL::normalize( '/', '/', 'front' );
$row  = THRG_STK_Intro::row( 5, '2026-10-26 21:00:00', $path, THRG_STK_URL::key( $path, 'front' ), 'Zendogs', $text );

check( 'home page path', '/' === $row['page_path'] );
check( 'front context', 'front' === $row['context'] );
check( 'key matches the home page', THRG_STK_URL::key( '/', 'front' ) === $row['page_key'] );
check( 'just me', 'private' === $row['visibility'] );
check( 'owned by the activating user', 5 === $row['author_id'] );
check( 'open and yellow', 'open' === $row['status'] && 'yellow' === $row['color'] );
check( 'no mentions, so no email', array() === $row['mentions'] && ! THRG_STK_Content::mentions( $text ) );
check( 'text survives sanitizing', $text === trim( THRG_STK_Intro::text( true ) ) );
check( 'text within the limit', THRG_STK_Content::length( $text ) <= THRG_STK_Content::MAX_LENGTH );
check( 'text says Stickies is ready', false !== strpos( $text, 'Stickies is ready' ) );
check( 'text keeps the [Hint] teaser', false !== strpos( $text, '[Hint] type @ in a note' ) );
check( 'text points at the check on the right', false !== strpos( $text, 'click the check on the right to mark me resolved' ) );
check( 'text explains resolving', false !== strpos( $text, 'mark me resolved' ) );
check( 'starts with the smiley when the database can store it', 0 === strpos( $text, "\u{1F600} This is your first sticky note!" ) );
check( 'no 4-byte characters for older utf8 tables', ! preg_match( '/[\x{10000}-\x{10FFFF}]/u', $plain ) && 0 === strpos( $plain, 'This is your first sticky note!' ) );
check( 'smiley survives sanitizing', false !== strpos( $text, "\u{1F600}" ) );
check( 'size inside API limits', $row['width'] >= 120 && $row['width'] <= 2000 && $row['height'] >= 60 && $row['height'] <= 2000 );

// Sites in a subfolder still land on their home page.
$sub = THRG_STK_URL::normalize( '/blog/', '/blog/', 'front' );
check( 'subfolder home normalizes to /', '/' === $sub );

done();
