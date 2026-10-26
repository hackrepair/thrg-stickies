<?php
/**
 * Note storage.
 *
 * @package THRG_Stickies
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- This class is the plugin's own table access layer; every query is prepared.

/** Reads and writes the notes table. Every query is prepared. */
final class THRG_STK_Notes {

    const PER_PAGE_LIMIT = 200;
    const SITE_LIMIT     = 5000;

    /** Column formats for insert/update. */
    const FORMATS = array(
        'revision'    => '%d',
        'page_key'    => '%s',
        'page_path'   => '%s',
        'page_title'  => '%s',
        'context'     => '%s',
        'content'     => '%s',
        'pos_top'     => '%d',
        'pos_center'  => '%d',
        'width'       => '%d',
        'height'      => '%d',
        'viewport_w'  => '%d',
        'color'       => '%s',
        'visibility'  => '%s',
        'status'      => '%s',
        'author_id'   => '%d',
        'mentions'    => '%s',
        'created_at'  => '%s',
        'updated_at'  => '%s',
        'edited_at'   => '%s',
        'resolved_by' => '%d',
        'resolved_at' => '%s',
    );

    /**
     * One note.
     *
     * @param int $id Note ID.
     * @return array|null
     */
    public static function find( $id ) {
        global $wpdb;
        $table = THRG_STK_Schema::table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from the site prefix.
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", (int) $id ), ARRAY_A );
        return $row ? self::hydrate( $row ) : null;
    }

    /**
     * Notes on one page, oldest first.
     *
     * @param string $page_key Page key.
     * @return array[]
     */
    public static function for_page( $page_key ) {
        global $wpdb;
        $table = THRG_STK_Schema::table();
        $rows  = $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from the site prefix.
            $wpdb->prepare( "SELECT * FROM `{$table}` WHERE page_key = %s ORDER BY id ASC LIMIT %d", $page_key, self::page_limit() ),
            ARRAY_A
        );
        return array_map( array( __CLASS__, 'hydrate' ), (array) $rows );
    }

    /**
     * The most notes one page may hold; the thrg_stk_page_limit filter can change it.
     *
     * @return int
     */
    public static function page_limit() {
        return max( 1, (int) apply_filters( 'thrg_stk_page_limit', self::PER_PAGE_LIMIT ) );
    }

    /**
     * Count notes on a page.
     *
     * @param string $page_key Page key.
     * @return int
     */
    public static function count_page( $page_key ) {
        global $wpdb;
        $table = THRG_STK_Schema::table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from the site prefix.
        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE page_key = %s", $page_key ) );
    }

    /**
     * Count all notes.
     *
     * @return int
     */
    public static function count_all() {
        global $wpdb;
        $table = THRG_STK_Schema::table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from the site prefix.
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
    }

    /**
     * Insert a note.
     *
     * @param array $data Column values.
     * @return int New ID, 0 on failure.
     */
    public static function insert( array $data ) {
        global $wpdb;
        $data = self::dehydrate( $data );
        $ok   = $wpdb->insert( THRG_STK_Schema::table(), $data, self::formats( $data ) );
        return $ok ? (int) $wpdb->insert_id : 0;
    }

    /**
     * Update a note.
     *
     * @param int   $id   Note ID.
     * @param array $data Column values.
     * @param int|null $expected_revision Expected version, or current version.
     * @return bool
     */
    public static function update( $id, array $data, $expected_revision = null ) {
        global $wpdb;
        $data = self::dehydrate( $data );
        if ( ! $data ) {
            return true;
        }
        if ( null === $expected_revision ) {
            $current = self::find( $id );
            if ( ! $current ) {
                return false;
            }
            $expected_revision = $current['revision'];
        }
        $data['revision'] = (int) $expected_revision + 1;
        return 1 === $wpdb->update(
            THRG_STK_Schema::table(),
            $data,
            array(
                'id'       => (int) $id,
                'revision' => (int) $expected_revision,
            ),
            self::formats( $data ),
            array( '%d', '%d' )
        );
    }

    /**
     * Delete a note.
     *
     * @param int $id Note ID.
     * @return bool
     */
    public static function delete( $id ) {
        global $wpdb;
        return (bool) $wpdb->delete( THRG_STK_Schema::table(), array( 'id' => (int) $id ), array( '%d' ) );
    }

    /**
     * Notes for the dashboard list.
     *
     * @param array $args status, author, mention, visible_to, search, orderby, order, per_page, paged.
     * @return array{items: array[], total: int}
     */
    public static function query( array $args ) {
        global $wpdb;
        $table  = THRG_STK_Schema::table();
        $where  = array( '1=1' );
        $params = array();

        if ( ! empty( $args['status'] ) && in_array( $args['status'], THRG_STK_Access::STATUSES, true ) ) {
            $where[]  = 'status = %s';
            $params[] = $args['status'];
        }
        if ( ! empty( $args['author'] ) ) {
            $where[]  = 'author_id = %d';
            $params[] = (int) $args['author'];
        }
        if ( ! empty( $args['mention'] ) ) {
            $where[]  = 'mentions LIKE %s';
            $params[] = '%,' . (int) $args['mention'] . ',%';
        }
        if ( isset( $args['search'] ) && '' !== $args['search'] ) {
            $like     = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
            $where[]  = '(content LIKE %s OR page_path LIKE %s OR page_title LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ( ! empty( $args['visible_to'] ) ) {
            // Same rule as THRG_STK_Access::can_view() for people who cannot manage notes, so paging and totals count only what they can open.
            $where[]  = "(visibility = 'team' OR author_id = %d OR (visibility = 'mentioned' AND mentions LIKE %s))";
            $params[] = (int) $args['visible_to'];
            $params[] = '%,' . (int) $args['visible_to'] . ',%';
        }
        if ( ! empty( $args['exclude_private_of_others'] ) ) {
            $where[]  = "(visibility <> 'private' OR author_id = %d)";
            $params[] = (int) $args['exclude_private_of_others'];
        }

        $orderby = isset( $args['orderby'] ) && in_array( $args['orderby'], array( 'updated_at', 'created_at', 'page_path', 'status' ), true ) ? $args['orderby'] : 'updated_at';
        $order   = isset( $args['order'] ) && 'asc' === strtolower( (string) $args['order'] ) ? 'ASC' : 'DESC';
        $per     = isset( $args['per_page'] ) ? max( 1, min( 100, (int) $args['per_page'] ) ) : 20;
        $paged   = isset( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1;

        $where_sql = implode( ' AND ', $where );
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table name from the site prefix; WHERE pieces are fixed strings with placeholders; ORDER BY values are allowlisted above.
        $count_sql = "SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}";
        $total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );
        $list_sql  = "SELECT * FROM `{$table}` WHERE {$where_sql} ORDER BY {$orderby} {$order}, id DESC LIMIT %d OFFSET %d";
        $rows      = $wpdb->get_results( $wpdb->prepare( $list_sql, array_merge( $params, array( $per, ( $paged - 1 ) * $per ) ) ), ARRAY_A );
        // phpcs:enable

        return array(
            'items' => array_map( array( __CLASS__, 'hydrate' ), (array) $rows ),
            'total' => $total,
        );
    }

    /**
     * Database row to typed array.
     *
     * @param array $row Raw row.
     * @return array
     */
    public static function hydrate( array $row ) {
        foreach ( array( 'revision', 'id', 'pos_top', 'pos_center', 'width', 'height', 'viewport_w', 'author_id', 'resolved_by' ) as $int ) {
            $row[ $int ] = isset( $row[ $int ] ) ? (int) $row[ $int ] : 0;
        }
        $row['mentions'] = isset( $row['mentions'] ) ? array_values( array_filter( array_map( 'intval', explode( ',', (string) $row['mentions'] ) ) ) ) : array();
        return $row;
    }

    /**
     * Typed array to database values; unknown keys are dropped.
     *
     * @param array $data Values.
     * @return array
     */
    private static function dehydrate( array $data ) {
        if ( isset( $data['mentions'] ) && is_array( $data['mentions'] ) ) {
            $ids              = array_values( array_filter( array_map( 'intval', $data['mentions'] ) ) );
            $data['mentions'] = $ids ? ',' . implode( ',', $ids ) . ',' : '';
        }
        return array_intersect_key( $data, self::FORMATS );
    }

    /**
     * Formats in the order of the data keys.
     *
     * @param array $data Values.
     * @return string[]
     */
    private static function formats( array $data ) {
        $out = array();
        foreach ( array_keys( $data ) as $key ) {
            $out[] = self::FORMATS[ $key ];
        }
        return $out;
    }
}
