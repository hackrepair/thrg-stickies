<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../includes/class-thrg-stk-schema.php';
require __DIR__ . '/../includes/class-thrg-stk-notes.php';
// Simulate two writers that both read revision 1 before either writes.
$wpdb = new class {
    public $prefix = 'wp_';
    public $row = array( 'revision' => 1, 'content' => 'Original' );
    public function update( $table, $data, $where, $formats, $where_formats ) {
        if ( $where['revision'] !== $this->row['revision'] ) { return 0; }
        $this->row = array_merge( $this->row, $data );
        return 1;
    }
};
check( 'first writer wins', THRG_STK_Notes::update( 1, array( 'content' => 'First' ), 1 ) );
check( 'revision advances without clock dependency', 2 === $wpdb->row['revision'] );
check( 'stale writer rejected', ! THRG_STK_Notes::update( 1, array( 'content' => 'Stale' ), 1 ) );
check( 'winning content preserved', 'First' === $wpdb->row['content'] );
check( 'fresh writer succeeds', THRG_STK_Notes::update( 1, array( 'content' => 'Fresh' ), 2 ) );


done();
