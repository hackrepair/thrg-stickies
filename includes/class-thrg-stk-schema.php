<?php
/**
 * Database table.
 *
 * @package THRG_Stickies
 */

defined( 'ABSPATH' ) || exit;

/** One table per site holds every note. */
final class THRG_STK_Schema {

    const VERSION        = '2';
    const VERSION_OPTION = 'thrg_stk_db_version';

    /**
     * Table name for the current site.
     *
     * @return string
     */
    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'thrg_stickies';
    }

    /**
     * CREATE TABLE statement in dbDelta's format.
     *
     * @param string $table   Table name.
     * @param string $charset Charset/collation clause.
     * @return string
     */
    public static function sql( $table, $charset ) {
        return "CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  page_key char(40) NOT NULL DEFAULT '',
  page_path varchar(2048) NOT NULL DEFAULT '',
  page_title varchar(255) NOT NULL DEFAULT '',
  context varchar(10) NOT NULL DEFAULT 'front',
  content text NOT NULL,
  pos_top int(11) NOT NULL DEFAULT 0,
  pos_center int(11) NOT NULL DEFAULT 0,
  width smallint(5) unsigned NOT NULL DEFAULT 270,
  height smallint(5) unsigned NOT NULL DEFAULT 190,
  viewport_w smallint(5) unsigned NOT NULL DEFAULT 0,
  color varchar(10) NOT NULL DEFAULT 'yellow',
  visibility varchar(10) NOT NULL DEFAULT 'team',
  status varchar(10) NOT NULL DEFAULT 'open',
  author_id bigint(20) unsigned NOT NULL DEFAULT 0,
  mentions varchar(255) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  edited_at datetime NULL DEFAULT NULL,
  resolved_by bigint(20) unsigned NOT NULL DEFAULT 0,
  resolved_at datetime NULL DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY page_key (page_key),
  KEY author_id (author_id),
  KEY status (status)
) {$charset};";
    }

    /**
     * Create or upgrade the table for the current site.
     *
     * @return void
     */
    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( self::sql( self::table(), $wpdb->get_charset_collate() ) );
        update_option( self::VERSION_OPTION, self::VERSION, false );
    }

    /**
     * Install or upgrade when the stored version differs (new sites, updates).
     *
     * @return void
     */
    public static function maybe_upgrade() {
        if ( self::VERSION !== get_option( self::VERSION_OPTION ) ) {
            self::install();
        }
    }

    /**
     * Drop the table for the current site (uninstall only).
     *
     * @return void
     */
    public static function drop() {
        global $wpdb;
        $table = self::table();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Removing this plugin's own table on uninstall; the name is built from the site prefix.
        $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
        delete_option( self::VERSION_OPTION );
    }
}
