<?php
/**
 * The Stickies note list table.
 *
 * @package THRG_Stickies
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- List filters are read-only GET parameters.

/** Lists notes the current user can see. */
class THRG_STK_List_Table extends WP_List_Table {

    /** Constructor. */
    public function __construct() {
        parent::__construct(
            array(
                'singular' => 'thrg_stk_note',
                'plural'   => 'thrg_stk_notes',
                'ajax'     => false,
            )
        );
    }

    /**
     * Columns.
     *
     * @return array
     */
    public function get_columns() {
        return array(
            'cb'         => '<input type="checkbox" />',
            'content'    => __( 'Note', 'thrg-stickies' ),
            'page_path'  => __( 'Page', 'thrg-stickies' ),
            'author'     => __( 'Author', 'thrg-stickies' ),
            'mentions'   => __( 'Mentions', 'thrg-stickies' ),
            'mail'       => __( 'Mention email', 'thrg-stickies' ),
            'status'     => __( 'Status', 'thrg-stickies' ),
            'updated_at' => __( 'Updated', 'thrg-stickies' ),
        );
    }

    /**
     * Sortable columns.
     *
     * @return array
     */
    protected function get_sortable_columns() {
        return array(
            'page_path'  => array( 'page_path', false ),
            'status'     => array( 'status', false ),
            'updated_at' => array( 'updated_at', true ),
        );
    }

    /**
     * Bulk actions.
     *
     * @return array
     */
    protected function get_bulk_actions() {
        return array(
            'resolve' => __( 'Mark resolved', 'thrg-stickies' ),
            'reopen'  => __( 'Reopen', 'thrg-stickies' ),
            'delete'  => __( 'Delete', 'thrg-stickies' ),
        );
    }

    /**
     * Status links above the table.
     *
     * @return array
     */
    protected function get_views() {
        $current = $this->filter( 'status' );
        $mine    = $this->filter( 'mention' );
        $base    = THRG_STK_Admin::list_url();
        $views   = array(
            'all'      => array( '', __( 'All', 'thrg-stickies' ) ),
            'open'     => array( 'open', __( 'Open', 'thrg-stickies' ) ),
            'resolved' => array( 'resolved', __( 'Resolved', 'thrg-stickies' ) ),
        );
        $out     = array();
        foreach ( $views as $key => $view ) {
            $url         = $view[0] ? add_query_arg( 'status', $view[0], $base ) : $base;
            $is_current  = ! $mine && $current === $view[0];
            $out[ $key ] = '<a href="' . esc_url( $url ) . '"' . ( $is_current ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $view[1] ) . '</a>';
        }
        $out['mentions'] = '<a href="' . esc_url( add_query_arg( 'mention', 'me', $base ) ) . '"' . ( $mine ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html__( 'Mentioning me', 'thrg-stickies' ) . '</a>';
        return $out;
    }

    /**
     * A sanitized filter value.
     *
     * @param string $key Query key.
     * @return string
     */
    private function filter( $key ) {
        return isset( $_GET[ $key ] ) ? sanitize_key( wp_unslash( $_GET[ $key ] ) ) : '';
    }

    /**
     * Load rows.
     *
     * @return void
     */
    public function prepare_items() {
        $uid  = get_current_user_id();
        $caps = THRG_STK_Access::user_caps( $uid );
        $per  = 20;
        $args = array(
            'status'   => $this->filter( 'status' ),
            'mention'  => 'me' === $this->filter( 'mention' ) ? $uid : 0,
            'search'   => isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '',
            'orderby'  => $this->filter( 'orderby' ),
            'order'    => $this->filter( 'order' ),
            'per_page' => $per,
            'paged'    => $this->get_pagenum(),
        );
        if ( ! $caps['see_private'] ) {
            $args['exclude_private_of_others'] = $uid;
        }
        $result = THRG_STK_Notes::query( $args );

        // Visibility rules beyond "private" (mentioned-only) are checked per row.
        $items = array();
        foreach ( $result['items'] as $note ) {
            if ( THRG_STK_Access::can_view( $note, $uid, $caps ) ) {
                $items[] = $note;
            }
        }
        $this->items           = $items;
        $this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'content' );
        $this->set_pagination_args(
            array(
                'total_items' => $result['total'],
                'per_page'    => $per,
            )
        );
    }

    /**
     * Empty state.
     *
     * @return void
     */
    public function no_items() {
        esc_html_e( 'No notes yet. Open a page of your site and add one with the note button on the left edge.', 'thrg-stickies' );
    }

    /**
     * Checkbox.
     *
     * @param array $item Note.
     * @return string
     */
    protected function column_cb( $item ) {
        return '<input type="checkbox" name="note[]" value="' . (int) $item['id'] . '" />';
    }

    /**
     * Note excerpt with color swatch and row actions.
     *
     * @param array $item Note.
     * @return string
     */
    protected function column_content( $item ) {
        $uid     = get_current_user_id();
        $caps    = THRG_STK_Access::user_caps( $uid );
        $excerpt = THRG_STK_Content::excerpt( $item['content'], array( 'THRG_STK_Plugin', 'display_name' ), 140 );
        $vis     = array(
            'team'      => __( 'Team', 'thrg-stickies' ),
            'mentioned' => __( 'Mentioned only', 'thrg-stickies' ),
            'private'   => __( 'Just me', 'thrg-stickies' ),
        );
        $out     = '<span class="stk-swatch stk-swatch--' . esc_attr( $item['color'] ) . '" aria-hidden="true"></span>';
        $out    .= '<strong><a href="' . esc_url( THRG_STK_Plugin::note_url( $item ) ) . '">' . esc_html( '' !== $excerpt ? $excerpt : __( '(empty note)', 'thrg-stickies' ) ) . '</a></strong>';
        if ( 'team' !== $item['visibility'] ) {
            $out .= ' <span class="stk-tag">' . esc_html( $vis[ $item['visibility'] ] ) . '</span>';
        }

        $actions = array(
            'view' => '<a href="' . esc_url( THRG_STK_Plugin::note_url( $item ) ) . '">' . esc_html__( 'Open on page', 'thrg-stickies' ) . '</a>',
        );
        if ( THRG_STK_Access::can_resolve( $item, $uid, $caps ) ) {
            $do             = 'resolved' === $item['status'] ? 'reopen' : 'resolve';
            $actions[ $do ] = '<a href="' . esc_url( $this->action_url( $do, $item['id'] ) ) . '">' . esc_html( 'resolve' === $do ? __( 'Mark resolved', 'thrg-stickies' ) : __( 'Reopen', 'thrg-stickies' ) ) . '</a>';
        }
        if ( THRG_STK_Access::can_delete( $item, $uid, $caps ) ) {
            $actions['delete'] = '<a class="submitdelete" href="' . esc_url( $this->action_url( 'delete', $item['id'] ) ) . '">' . esc_html__( 'Delete', 'thrg-stickies' ) . '</a>';
        }
        return $out . $this->row_actions( $actions );
    }

    /**
     * Nonced row action link.
     *
     * @param string $verb Action.
     * @param int    $id Note ID.
     * @return string
     */
    private function action_url( $verb, $id ) {
        return wp_nonce_url(
            add_query_arg(
                array(
                    'action' => 'thrg_stk_note_action',
                    'do'     => $verb,
                    'note'   => (int) $id,
                ),
                admin_url( 'admin-post.php' )
            ),
            'thrg_stk_' . $verb . '_' . (int) $id
        );
    }

    /**
     * Page column.
     *
     * @param array $item Note.
     * @return string
     */
    protected function column_page_path( $item ) {
        $title = '' !== $item['page_title'] ? '<span class="stk-page-title">' . esc_html( $item['page_title'] ) . '</span><br />' : '';
        $where = 'admin' === $item['context'] ? ' <span class="stk-tag">' . esc_html__( 'Dashboard', 'thrg-stickies' ) . '</span>' : '';
        return $title . '<code>' . esc_html( $item['page_path'] ) . '</code>' . $where;
    }

    /**
     * Author column.
     *
     * @param array $item Note.
     * @return string
     */
    protected function column_author( $item ) {
        $name = THRG_STK_Plugin::display_name( $item['author_id'] );
        return esc_html( null === $name ? __( '(deleted user)', 'thrg-stickies' ) : $name );
    }

    /**
     * Mentions column.
     *
     * @param array $item Note.
     * @return string
     */
    protected function column_mentions( $item ) {
        $names = array();
        foreach ( $item['mentions'] as $id ) {
            $name = THRG_STK_Plugin::display_name( $id );
            if ( null !== $name ) {
                $names[] = '@' . $name;
            }
        }
        return $names ? esc_html( implode( ', ', $names ) ) : '<span aria-hidden="true">&mdash;</span>';
    }

    /**
     * Latest send result for each current mention.
     *
     * @param array $item Note.
     * @return string
     */
    protected function column_mail( $item ) {
        $log = ! empty( $item['mail_status'] ) ? json_decode( $item['mail_status'], true ) : array();
        $out = array();
        foreach ( $item['mentions'] as $uid ) {
            $entry = is_array( $log ) && isset( $log[ $uid ] ) ? $log[ $uid ] : null;
            $label = __( 'No recorded send', 'thrg-stickies' );
            if ( is_array( $entry ) && isset( $entry['state'], $entry['time'] ) ) {
                $label  = 'accepted' === $entry['state'] ? __( 'Accepted by mailer', 'thrg-stickies' ) : __( 'Send failed', 'thrg-stickies' );
                $label .= ' (' . $entry['time'] . ' UTC)';
            }
            $out[] = esc_html( THRG_STK_Plugin::display_name( $uid ) . ': ' . $label );
        }
        return $out ? implode( '<br />', $out ) : '&mdash;';
    }

    /**
     * Status column.
     *
     * @param array $item Note.
     * @return string
     */
    protected function column_status( $item ) {
        if ( 'resolved' === $item['status'] ) {
            $by = $item['resolved_by'] ? THRG_STK_Plugin::display_name( $item['resolved_by'] ) : null;
            return '<span class="stk-chip stk-chip--ok">' . esc_html__( 'Resolved', 'thrg-stickies' ) . '</span>' . ( $by ? '<br /><small>' . esc_html( $by ) . '</small>' : '' );
        }
        return '<span class="stk-chip stk-chip--open">' . esc_html__( 'Open', 'thrg-stickies' ) . '</span>';
    }

    /**
     * Updated column.
     *
     * @param array $item Note.
     * @return string
     */
    protected function column_updated_at( $item ) {
        $ts = (int) strtotime( $item['updated_at'] . ' UTC' );
        /* translators: %s: human time difference */
        return '<time datetime="' . esc_attr( gmdate( 'c', $ts ) ) . '" title="' . esc_attr( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts ) ) . '">' . esc_html( sprintf( __( '%s ago', 'thrg-stickies' ), human_time_diff( $ts ) ) ) . '</time>';
    }
}
