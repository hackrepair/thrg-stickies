<?php
/**
 * REST API for the on-page notes.
 *
 * @package THRG_Stickies
 */

defined( 'ABSPATH' ) || exit;

/** Routes under thrg-stickies/v1. Every route needs a logged-in user and the REST nonce. */
final class THRG_STK_REST {

    const NS = 'thrg-stickies/v1';

    /**
     * Register hooks.
     *
     * @return void
     */
    public static function boot() {
        add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
    }

    /**
     * Register routes.
     *
     * @return void
     */
    public static function routes() {
        $geometry = array(
            'pos_top'    => array(
                'type'    => 'integer',
                'minimum' => -1000,
                'maximum' => 200000,
            ),
            'pos_center' => array(
                'type'    => 'integer',
                'minimum' => -5000,
                'maximum' => 5000,
            ),
            'width'      => array(
                'type'    => 'integer',
                'minimum' => 120,
                'maximum' => 2000,
            ),
            'height'     => array(
                'type'    => 'integer',
                'minimum' => 60,
                'maximum' => 2000,
            ),
            'viewport_w' => array(
                'type'    => 'integer',
                'minimum' => 0,
                'maximum' => 10000,
            ),
            'color'      => array(
                'type' => 'string',
                'enum' => THRG_STK_Access::COLORS,
            ),
            'visibility' => array(
                'type' => 'string',
                'enum' => THRG_STK_Access::VISIBILITY,
            ),
            'content'    => array(
                'type'      => 'string',
                'maxLength' => 20000,
            ),
        );
        $page     = array(
            'uri'     => array(
                'type'      => 'string',
                'required'  => true,
                'maxLength' => 4096,
            ),
            'context' => array(
                'type'    => 'string',
                'enum'    => array( 'front', 'admin' ),
                'default' => 'front',
            ),
        );

        register_rest_route(
            self::NS,
            '/notes',
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( __CLASS__, 'list_notes' ),
                    'permission_callback' => array( __CLASS__, 'can_read' ),
                    'args'                => $page,
                ),
                array(
                    'methods'             => 'POST',
                    'callback'            => array( __CLASS__, 'create_note' ),
                    'permission_callback' => array( __CLASS__, 'can_create' ),
                    'args'                => array_merge(
                        $page,
                        $geometry,
                        array(
                            'title' => array(
                                'type'      => 'string',
                                'maxLength' => 1000,
                            ),
                        )
                    ),
                ),
            )
        );

        register_rest_route(
            self::NS,
            '/notes/(?P<id>\d+)',
            array(
                array(
                    'methods'             => 'PATCH',
                    'callback'            => array( __CLASS__, 'update_note' ),
                    'permission_callback' => array( __CLASS__, 'can_read' ),
                    'args'                => array_merge(
                        $geometry,
                        array(
                            'status'     => array(
                                'type' => 'string',
                                'enum' => THRG_STK_Access::STATUSES,
                            ),
                            'revision'   => array(
                                'type'    => 'integer',
                                'minimum' => 1,
                            ),
                            'updated_at' => array(
                                'type'    => 'string',
                                'pattern' => '^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$',
                            ),
                        )
                    ),
                ),
                array(
                    'methods'             => 'DELETE',
                    'callback'            => array( __CLASS__, 'delete_note' ),
                    'permission_callback' => array( __CLASS__, 'can_read' ),
                ),
            )
        );

        register_rest_route(
            self::NS,
            '/users',
            array(
                'methods'             => 'GET',
                'callback'            => array( __CLASS__, 'search_users' ),
                'permission_callback' => array( __CLASS__, 'can_create' ),
                'args'                => array(
                    'search' => array(
                        'type'      => 'string',
                        'required'  => true,
                        'minLength' => 1,
                        'maxLength' => 60,
                    ),
                ),
            )
        );
    }

    /**
     * Permission: logged in and can see notes.
     *
     * @return bool
     */
    public static function can_read() {
        $caps = THRG_STK_Access::user_caps( get_current_user_id() );
        return $caps['read'];
    }

    /**
     * Permission: logged in and can add notes.
     *
     * @return bool
     */
    public static function can_create() {
        $caps = THRG_STK_Access::user_caps( get_current_user_id() );
        return $caps['create'];
    }

    /**
     * Resolve the page from request args.
     *
     * @param WP_REST_Request $req Request.
     * @return array|WP_Error path, key, context.
     */
    private static function page( $req ) {
        $context = 'admin' === $req['context'] ? 'admin' : 'front';
        $home    = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
        $path    = THRG_STK_URL::normalize( (string) $req['uri'], $home, $context );
        if ( '' === $path ) {
            return new WP_Error( 'thrg_stk_bad_page', __( 'Stickies could not tell which page this is.', 'thrg-stickies' ), array( 'status' => 400 ) );
        }
        return array(
            'path'    => $path,
            'key'     => THRG_STK_URL::key( $path, $context ),
            'context' => $context,
        );
    }

    /**
     * GET /notes
     *
     * @param WP_REST_Request $req Request.
     * @return WP_REST_Response|WP_Error
     */
    public static function list_notes( $req ) {
        $page = self::page( $req );
        if ( is_wp_error( $page ) ) {
            return $page;
        }
        $uid  = get_current_user_id();
        $caps = THRG_STK_Access::user_caps( $uid );
        $out  = array();
        foreach ( THRG_STK_Notes::for_page( $page['key'] ) as $note ) {
            if ( THRG_STK_Access::can_view( $note, $uid, $caps ) && ( 'private' !== $note['visibility'] || (int) $note['author_id'] === $uid ) ) {
                $out[] = self::prepare( $note, $uid, $caps );
            }
        }
        return rest_ensure_response(
            array(
                'notes' => $out,
                'page'  => $page['path'],
            )
        );
    }

    /**
     * POST /notes
     *
     * @param WP_REST_Request $req Request.
     * @return WP_REST_Response|WP_Error
     */
    public static function create_note( $req ) {
        $page = self::page( $req );
        if ( is_wp_error( $page ) ) {
            return $page;
        }
        if ( THRG_STK_Notes::count_page( $page['key'] ) >= (int) apply_filters( 'thrg_stk_page_limit', THRG_STK_Notes::PER_PAGE_LIMIT ) ) {
            return new WP_Error( 'thrg_stk_page_full', __( 'This page already has the most notes Stickies allows. Resolve and delete a few, then try again.', 'thrg-stickies' ), array( 'status' => 409 ) );
        }
        if ( THRG_STK_Notes::count_all() >= (int) apply_filters( 'thrg_stk_site_limit', THRG_STK_Notes::SITE_LIMIT ) ) {
            return new WP_Error( 'thrg_stk_site_full', __( 'This site has reached the Stickies note limit. Delete notes you no longer need in the Stickies list.', 'thrg-stickies' ), array( 'status' => 409 ) );
        }

        $uid     = get_current_user_id();
        $content = self::clean_content( $req['content'] );
        $now     = current_time( 'mysql', true );
        $data    = array(
            'page_key'   => $page['key'],
            'page_path'  => $page['path'],
            'page_title' => self::clean_title( $req['title'] ),
            'context'    => $page['context'],
            'content'    => $content,
            'pos_top'    => (int) $req['pos_top'],
            'pos_center' => (int) $req['pos_center'],
            'width'      => null === $req['width'] ? 270 : (int) $req['width'],
            'height'     => null === $req['height'] ? 190 : (int) $req['height'],
            'viewport_w' => (int) $req['viewport_w'],
            'color'      => $req['color'] ? $req['color'] : 'yellow',
            'visibility' => $req['visibility'] ? $req['visibility'] : 'team',
            'status'     => 'open',
            'author_id'  => $uid,
            'mentions'   => THRG_STK_Content::mentions( $content ),
            'created_at' => $now,
            'updated_at' => $now,
        );
        $id      = THRG_STK_Notes::insert( $data );
        if ( ! $id ) {
            return new WP_Error( 'thrg_stk_db', __( 'The note could not be saved. Please try again.', 'thrg-stickies' ), array( 'status' => 500 ) );
        }
        $note = THRG_STK_Notes::find( $id );
        THRG_STK_Email::notify( $note, $note['mentions'], $uid );

        $response = rest_ensure_response( self::prepare( $note, $uid, THRG_STK_Access::user_caps( $uid ) ) );
        $response->set_status( 201 );
        return $response;
    }

    /**
     * PATCH /notes/<id>
     *
     * @param WP_REST_Request $req Request.
     * @return WP_REST_Response|WP_Error
     */
    public static function update_note( $req ) {
        $uid  = get_current_user_id();
        $caps = THRG_STK_Access::user_caps( $uid );
        $note = THRG_STK_Notes::find( (int) $req['id'] );
        if ( ! $note || ! THRG_STK_Access::can_view( $note, $uid, $caps ) ) {
            return new WP_Error( 'thrg_stk_missing', __( 'This note was deleted or you can no longer see it.', 'thrg-stickies' ), array( 'status' => 404 ) );
        }

        $params  = $req->get_json_params();
        $params  = is_array( $params ) ? $params : $req->get_body_params();
        $editing = array_intersect( array_keys( (array) $params ), array( 'content', 'pos_top', 'pos_center', 'width', 'height', 'viewport_w', 'color', 'visibility' ) );
        $status  = null !== $req['status'] && $req['status'] !== $note['status'];

        if ( $editing && ! THRG_STK_Access::can_edit( $note, $uid, $caps ) ) {
            return new WP_Error( 'thrg_stk_forbidden', __( 'Only the person who wrote this note, or someone who manages notes, can change it.', 'thrg-stickies' ), array( 'status' => 403 ) );
        }
        if ( $status && ! THRG_STK_Access::can_resolve( $note, $uid, $caps ) ) {
            return new WP_Error( 'thrg_stk_forbidden', __( 'You can’t resolve this note.', 'thrg-stickies' ), array( 'status' => 403 ) );
        }
        if ( ( null !== $req['revision'] && (int) $req['revision'] !== $note['revision'] ) || ( $req['updated_at'] && $editing && $req['updated_at'] !== $note['updated_at'] ) ) {
            return new WP_Error(
                'thrg_stk_conflict',
                __( 'Someone else changed this note while you were editing. Reload it to see their version.', 'thrg-stickies' ),
                array(
                    'status' => 409,
                    'note'   => self::prepare( $note, $uid, $caps ),
                )
            );
        }

        $data = array();
        foreach ( array( 'pos_top', 'pos_center', 'width', 'height', 'viewport_w' ) as $int ) {
            if ( in_array( $int, $editing, true ) ) {
                $data[ $int ] = (int) $req[ $int ];
            }
        }
        foreach ( array( 'color', 'visibility' ) as $enum ) {
            if ( in_array( $enum, $editing, true ) && $req[ $enum ] ) {
                $data[ $enum ] = $req[ $enum ];
            }
        }
        $new_mentions = array();
        if ( in_array( 'content', $editing, true ) ) {
            $data['content']  = self::clean_content( $req['content'] );
            $data['mentions'] = THRG_STK_Content::mentions( $data['content'] );
            $new_mentions     = array_diff( $data['mentions'], $note['mentions'] );
        }
        if ( $status ) {
            $data['status']      = $req['status'];
            $data['resolved_by'] = 'resolved' === $req['status'] ? $uid : 0;
            $data['resolved_at'] = 'resolved' === $req['status'] ? current_time( 'mysql', true ) : null;
        }
        if ( ! $data ) {
            return rest_ensure_response( self::prepare( $note, $uid, $caps ) );
        }
        $data['updated_at'] = current_time( 'mysql', true );
        if ( isset( $data['content'] ) && $data['content'] !== $note['content'] ) {
            $data['edited_at'] = $data['updated_at'];
        }

        if ( ! THRG_STK_Notes::update( $note['id'], $data, $note['revision'] ) ) {
            $latest = THRG_STK_Notes::find( $note['id'] );
            if ( ! $latest ) {
                return new WP_Error( 'thrg_stk_missing', __( 'This note was deleted or you can no longer see it.', 'thrg-stickies' ), array( 'status' => 404 ) );
            }
            if ( $latest['revision'] !== $note['revision'] ) {
                // Another save won the race between our read and our write.
                return new WP_Error(
                    'thrg_stk_conflict',
                    __( 'Someone else changed this note while you were editing. Reload it to see their version.', 'thrg-stickies' ),
                    array(
                        'status' => 409,
                        'note'   => self::prepare( $latest, $uid, $caps ),
                    )
                );
            }
            return new WP_Error( 'thrg_stk_db', __( 'The note could not be saved. Please try again.', 'thrg-stickies' ), array( 'status' => 500 ) );
        }
        $note = THRG_STK_Notes::find( $note['id'] );
        if ( $new_mentions ) {
            THRG_STK_Email::notify( $note, $new_mentions, $uid );
        }
        return rest_ensure_response( self::prepare( $note, $uid, $caps ) );
    }

    /**
     * DELETE /notes/<id>
     *
     * @param WP_REST_Request $req Request.
     * @return WP_REST_Response|WP_Error
     */
    public static function delete_note( $req ) {
        $uid  = get_current_user_id();
        $caps = THRG_STK_Access::user_caps( $uid );
        $note = THRG_STK_Notes::find( (int) $req['id'] );
        if ( ! $note || ! THRG_STK_Access::can_view( $note, $uid, $caps ) ) {
            return rest_ensure_response( array( 'deleted' => true ) );
        }
        if ( ! THRG_STK_Access::can_delete( $note, $uid, $caps ) ) {
            return new WP_Error( 'thrg_stk_forbidden', __( 'Only the person who wrote this note, or someone who manages notes, can delete it.', 'thrg-stickies' ), array( 'status' => 403 ) );
        }
        THRG_STK_Notes::delete( $note['id'] );
        return rest_ensure_response( array( 'deleted' => true ) );
    }

    /**
     * GET /users: mention suggestions among people who can see notes.
     *
     * @param WP_REST_Request $req Request.
     * @return WP_REST_Response
     */
    public static function search_users( $req ) {
        $term  = trim( sanitize_text_field( (string) $req['search'] ) );
        $users = array();
        if ( '' !== $term ) {
            $can_list = current_user_can( 'list_users' );
            $columns  = array( 'user_login', 'user_nicename', 'display_name' );
            if ( $can_list ) {
                $columns[] = 'user_email';
            }
            $query = new WP_User_Query(
                array(
                    'search'         => '*' . $term . '*',
                    'search_columns' => $columns,
                    'capability__in' => array_values( THRG_STK_Access::caps() ),
                    'number'         => 10,
                    'orderby'        => 'display_name',
                    'fields'         => $can_list ? array( 'ID', 'display_name', 'user_email' ) : array( 'ID', 'display_name' ),
                    'count_total'    => false,
                )
            );
            foreach ( $query->get_results() as $user ) {
                $users[] = array(
                    'id'     => (int) $user->ID,
                    'name'   => $user->display_name,
                    /* translators: 1: display name, 2: account ID. */
                    'label'  => $can_list ? $user->display_name . ' (' . $user->user_email . ')' : sprintf( __( '%1$s (user #%2$d)', 'thrg-stickies' ), $user->display_name, $user->ID ),
                    'avatar' => get_avatar_url( (int) $user->ID, array( 'size' => 48 ) ),
                );
            }
        }
        return rest_ensure_response( array( 'users' => $users ) );
    }

    /**
     * Sanitize content and drop mentions of people who cannot see notes.
     *
     * @param mixed $raw Submitted content.
     * @return string
     */
    private static function clean_content( $raw ) {
        $text = THRG_STK_Content::sanitize( null === $raw ? '' : $raw );
        return THRG_STK_Content::filter_mentions(
            $text,
            static function ( $id ) {
                $caps = THRG_STK_Access::user_caps( $id );
                return $caps['read'] ? THRG_STK_Plugin::display_name( $id ) : null;
            }
        );
    }

    /**
     * Clean a page title.
     *
     * @param mixed $raw Title.
     * @return string
     */
    private static function clean_title( $raw ) {
        $title = sanitize_text_field( is_string( $raw ) ? $raw : '' );
        return function_exists( 'mb_substr' ) ? mb_substr( $title, 0, 200 ) : substr( $title, 0, 200 );
    }

    /**
     * Note as sent to the browser.
     *
     * @param array $note Note row.
     * @param int   $uid  Current user.
     * @param array $caps Current user's capabilities.
     * @return array
     */
    public static function prepare( array $note, $uid, array $caps ) {
        $name_of  = array( 'THRG_STK_Plugin', 'display_name' );
        $mentions = array();
        foreach ( $note['mentions'] as $id ) {
            $mentions[] = array(
                'id'   => (int) $id,
                'name' => (string) THRG_STK_Plugin::display_name( $id ),
            );
        }
        return array(
            'id'          => (int) $note['id'],
            'content'     => $note['content'],
            'rendered'    => THRG_STK_Content::render( $note['content'], $name_of ),
            'mentions'    => $mentions,
            'pos_top'     => $note['pos_top'],
            'pos_center'  => $note['pos_center'],
            'width'       => $note['width'],
            'height'      => $note['height'],
            'viewport_w'  => $note['viewport_w'],
            'color'       => $note['color'],
            'visibility'  => $note['visibility'],
            'status'      => $note['status'],
            'author'      => array(
                'id'   => (int) $note['author_id'],
                'name' => (string) THRG_STK_Plugin::display_name( $note['author_id'] ),
            ),
            'created'     => gmdate( 'c', (int) strtotime( $note['created_at'] . ' UTC' ) ),
            'updated'     => gmdate( 'c', (int) strtotime( $note['updated_at'] . ' UTC' ) ),
            'updated_at'  => $note['updated_at'],
            'revision'    => $note['revision'],
            'edited'      => ! empty( $note['edited_at'] ),
            'resolved_by' => $note['resolved_by'] ? (string) THRG_STK_Plugin::display_name( $note['resolved_by'] ) : '',
            'page'        => $note['page_path'],
            'can_edit'    => THRG_STK_Access::can_edit( $note, $uid, $caps ),
            'can_resolve' => THRG_STK_Access::can_resolve( $note, $uid, $caps ),
            'can_delete'  => THRG_STK_Access::can_delete( $note, $uid, $caps ),
        );
    }
}
