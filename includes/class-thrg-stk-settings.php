<?php
/**
 * Stickies > Settings.
 *
 * @package THRG_Stickies
 */

defined( 'ABSPATH' ) || exit;

/** Saved settings, the settings screen and the role matrix. */
final class THRG_STK_Settings {

    const OPTION = 'thrg_stk_options';
    const PAGE   = 'thrg-stickies-settings';

    /**
     * Default settings.
     *
     * @return array
     */
    public static function defaults() {
        return array(
            'front'                => 1,
            'admin'                => 1,
            'toolbar'              => 'both',
            'breakpoint'           => 782,
            'emails'               => 1,
            'autosave'             => 0,
            'managers_see_private' => 0,
            'roles'                => array( 'editor' => array( 'read', 'create' ) ),
            'delete_data'          => 0,
        );
    }

    /**
     * All settings merged with defaults.
     *
     * @return array
     */
    public static function all() {
        $saved = get_option( self::OPTION, array() );
        return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
    }

    /**
     * One setting.
     *
     * @param string $key Setting name.
     * @return mixed
     */
    public static function get( $key ) {
        $all = self::all();
        return isset( $all[ $key ] ) ? $all[ $key ] : null;
    }

    /**
     * Register hooks.
     *
     * @return void
     */
    public static function boot() {
        add_action( 'admin_init', array( __CLASS__, 'register' ) );
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'after_save' ), 10, 2 );
        add_action( 'add_option_' . self::OPTION, array( __CLASS__, 'after_add' ), 10, 2 );
    }

    /**
     * Register the option.
     *
     * @return void
     */
    public static function register() {
        register_setting(
            'thrg_stk_settings',
            self::OPTION,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( __CLASS__, 'sanitize' ),
                'default'           => self::defaults(),
            )
        );
    }

    /**
     * Sanitize submitted settings.
     *
     * @param mixed $input Submitted value.
     * @return array
     */
    public static function sanitize( $input ) {
        $input = is_array( $input ) ? $input : array();
        $out   = array();
        foreach ( array( 'front', 'admin', 'emails', 'autosave', 'managers_see_private', 'delete_data' ) as $flag ) {
            $out[ $flag ] = empty( $input[ $flag ] ) ? 0 : 1;
        }
        $out['toolbar']    = isset( $input['toolbar'] ) && in_array( $input['toolbar'], array( 'edge', 'adminbar', 'both' ), true ) ? $input['toolbar'] : 'both';
        $out['breakpoint'] = isset( $input['breakpoint'] ) ? max( 0, min( 2000, absint( $input['breakpoint'] ) ) ) : 782;

        $roles = array();
        $known = function_exists( 'wp_roles' ) ? array_keys( wp_roles()->role_names ) : array();
        if ( isset( $input['roles'] ) && is_array( $input['roles'] ) ) {
            foreach ( $input['roles'] as $role => $grants ) {
                $role = sanitize_key( $role );
                if ( 'administrator' === $role || ! in_array( $role, $known, true ) || ! is_array( $grants ) ) {
                    continue;
                }
                $grants = array_values( array_intersect( array( 'read', 'create', 'manage' ), array_map( 'sanitize_key', $grants ) ) );
                // Creating or managing notes needs seeing them.
                if ( $grants && ! in_array( 'read', $grants, true ) ) {
                    array_unshift( $grants, 'read' );
                }
                if ( $grants ) {
                    $roles[ $role ] = $grants;
                }
            }
        }
        $out['roles'] = $roles;
        return $out;
    }

    /**
     * Apply role changes after saving.
     *
     * @param mixed $old_value Previous value.
     * @param mixed $value     New value.
     * @return void
     */
    public static function after_save( $old_value, $value ) {
        unset( $old_value );
        THRG_STK_Access::apply_matrix( is_array( $value ) && isset( $value['roles'] ) ? (array) $value['roles'] : array() );
    }

    /**
     * Apply role changes when the option is first created.
     *
     * @param string $name  Option name.
     * @param mixed  $value Value.
     * @return void
     */
    public static function after_add( $name, $value ) {
        self::after_save( null, $value );
        unset( $name );
    }

    /**
     * Stickies > Settings.
     *
     * @return void
     */
    public static function menu() {
        $hook = add_submenu_page(
            THRG_STK_Admin::PAGE,
            __( 'Stickies Settings', 'thrg-stickies' ),
            __( 'Settings', 'thrg-stickies' ),
            'manage_options',
            self::PAGE,
            array( __CLASS__, 'render' )
        );
        add_action( 'admin_print_styles-' . $hook, array( 'THRG_STK_Admin', 'enqueue_admin_styles' ) );
    }

    /**
     * Render the settings screen.
     *
     * @return void
     */
    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $o     = self::all();
        $name  = self::OPTION;
        $roles = wp_roles()->role_names;
        $grant = array(
            'read'   => __( 'See notes', 'thrg-stickies' ),
            'create' => __( 'Add notes', 'thrg-stickies' ),
            'manage' => __( "Manage everyone's notes", 'thrg-stickies' ),
        );
        ?>
        <div class="wrap thrg-stk-admin">
            <?php THRG_STK_Admin::header( __( 'Choose who can use Stickies and where notes appear', 'thrg-stickies' ), THRG_STK_Admin::list_url() ); ?>
            <form method="post" action="options.php">
                <?php settings_fields( 'thrg_stk_settings' ); ?>

                <h2 class="stk-section"><?php esc_html_e( 'Who can do what', 'thrg-stickies' ); ?></h2>
                <div class="stk-card">
                    <p><?php esc_html_e( 'Only logged-in people with one of these roles see the Stickies toolbar. Visitors never see notes or load any Stickies files. Anyone can edit, move, resolve or delete their own notes.', 'thrg-stickies' ); ?></p>
                    <table class="widefat striped stk-matrix">
                        <thead><tr>
                            <th scope="col"><?php esc_html_e( 'Role', 'thrg-stickies' ); ?></th>
                            <?php foreach ( $grant as $label ) : ?>
                                <th scope="col"><?php echo esc_html( $label ); ?></th>
                            <?php endforeach; ?>
                        </tr></thead>
                        <tbody>
                        <?php foreach ( $roles as $role => $role_name ) : ?>
                            <tr>
                                <th scope="row"><?php echo esc_html( translate_user_role( $role_name ) ); ?></th>
                                <?php
                                foreach ( $grant as $short => $label ) :
                                    $is_admin = 'administrator' === $role;
                                    $checked  = $is_admin || ( isset( $o['roles'][ $role ] ) && in_array( $short, (array) $o['roles'][ $role ], true ) );
                                    ?>
                                    <td>
                                        <label class="screen-reader-text" for="<?php echo esc_attr( "stk-{$role}-{$short}" ); ?>"><?php echo esc_html( translate_user_role( $role_name ) . ': ' . $label ); ?></label>
                                        <input type="checkbox" id="<?php echo esc_attr( "stk-{$role}-{$short}" ); ?>" name="<?php echo esc_attr( "{$name}[roles][{$role}][]" ); ?>" value="<?php echo esc_attr( $short ); ?>" <?php checked( $checked ); ?> <?php disabled( $is_admin ); ?> />
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="description"><?php esc_html_e( 'Administrators always have every permission. Adding or managing notes includes seeing them.', 'thrg-stickies' ); ?></p>
                    <p><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[managers_see_private]" value="1" <?php checked( $o['managers_see_private'] ); ?> /> <?php esc_html_e( "Let people who manage everyone's notes see notes marked Just me (in the Stickies list only)", 'thrg-stickies' ); ?></label></p>
                </div>

                <h2 class="stk-section"><?php esc_html_e( 'Where notes appear', 'thrg-stickies' ); ?></h2>
                <div class="stk-card">
                    <p><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[front]" value="1" <?php checked( $o['front'] ); ?> /> <?php esc_html_e( 'On the site (front end)', 'thrg-stickies' ); ?></label></p>
                    <p><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[admin]" value="1" <?php checked( $o['admin'] ); ?> /> <?php esc_html_e( 'On dashboard screens', 'thrg-stickies' ); ?></label></p>
                    <fieldset>
                        <legend><?php esc_html_e( 'Show the Stickies controls', 'thrg-stickies' ); ?></legend>
                        <p><label><input type="radio" name="<?php echo esc_attr( $name ); ?>[toolbar]" value="both" <?php checked( 'both', $o['toolbar'] ); ?> /> <?php esc_html_e( 'On the left edge of the page and in the admin bar', 'thrg-stickies' ); ?></label><br />
                        <label><input type="radio" name="<?php echo esc_attr( $name ); ?>[toolbar]" value="edge" <?php checked( 'edge', $o['toolbar'] ); ?> /> <?php esc_html_e( 'On the left edge of the page only', 'thrg-stickies' ); ?></label><br />
                        <label><input type="radio" name="<?php echo esc_attr( $name ); ?>[toolbar]" value="adminbar" <?php checked( 'adminbar', $o['toolbar'] ); ?> /> <?php esc_html_e( 'In the admin bar only', 'thrg-stickies' ); ?></label></p>
                    </fieldset>
                    <p><label for="stk-breakpoint"><?php esc_html_e( 'On screens narrower than', 'thrg-stickies' ); ?></label>
                        <input type="number" class="small-text" id="stk-breakpoint" min="0" max="2000" name="<?php echo esc_attr( $name ); ?>[breakpoint]" value="<?php echo esc_attr( $o['breakpoint'] ); ?>" /> px,
                        <?php esc_html_e( 'notes are listed in the Stickies panel instead of being drawn on the page.', 'thrg-stickies' ); ?></p>
                </div>

                <h2 class="stk-section"><?php esc_html_e( 'Writing notes', 'thrg-stickies' ); ?></h2>
                <div class="stk-card">
                    <p><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[emails]" value="1" <?php checked( $o['emails'] ); ?> /> <?php esc_html_e( 'Email people when they are mentioned with @', 'thrg-stickies' ); ?></label></p>
                    <p><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[autosave]" value="1" <?php checked( $o['autosave'] ); ?> /> <?php esc_html_e( 'Also save text automatically a moment after typing stops (moving, resizing and recoloring a note always save right away)', 'thrg-stickies' ); ?></label></p>
                </div>

                <h2 class="stk-section"><?php esc_html_e( 'Uninstall', 'thrg-stickies' ); ?></h2>
                <div class="stk-card">
                    <p><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[delete_data]" value="1" <?php checked( $o['delete_data'] ); ?> /> <?php esc_html_e( 'Delete all plugin data when the plugin is deleted', 'thrg-stickies' ); ?></label></p>
                    <p class="description"><?php esc_html_e( 'Removes every note, these settings and the Stickies permissions. Leave this off to keep your notes for a reinstall.', 'thrg-stickies' ); ?></p>
                </div>

                <?php submit_button( __( 'Save settings', 'thrg-stickies' ) ); ?>
            </form>
            <?php THRG_STK_Admin::footer(); ?>
        </div>
        <?php
    }
}
