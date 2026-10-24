<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../includes/class-thrg-stk-schema.php';
require __DIR__ . '/../includes/class-thrg-stk-notes.php';

$sql = THRG_STK_Schema::sql( 'wp_thrg_stickies', 'DEFAULT CHARSET=utf8mb4' );
check( 'create statement', 0 === strpos( $sql, 'CREATE TABLE wp_thrg_stickies (' ) );
check( 'dbDelta primary key has two spaces', false !== strpos( $sql, 'PRIMARY KEY  (id)' ) );
check( 'page key indexed', false !== strpos( $sql, 'KEY page_key (page_key)' ) );
check( 'no zero-date defaults', false === strpos( $sql, '0000-00-00' ) );
check( 'charset appended', false !== strpos( $sql, ') DEFAULT CHARSET=utf8mb4;' ) );
foreach ( array_keys( THRG_STK_Notes::FORMATS ) as $col ) {
    check( "column $col exists", 1 === preg_match( '/^\s+' . $col . ' /m', $sql ) );
}
$row = THRG_STK_Notes::hydrate( array( 'id' => '5', 'author_id' => '2', 'mentions' => ',3,4,', 'pos_top' => '-5' ) );
check( 'hydrate ints', 5 === $row['id'] && 2 === $row['author_id'] && -5 === $row['pos_top'] );
check( 'hydrate mentions', array( 3, 4 ) === $row['mentions'] );
check( 'hydrate empty mentions', array() === THRG_STK_Notes::hydrate( array( 'mentions' => '' ) )['mentions'] );

done();
