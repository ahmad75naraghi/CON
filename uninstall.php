<?php
/**
 * Uninstall routine.
 *
 * By default the catalog/request tables are PRESERVED so re-installing the
 * plugin never loses data. When the admin explicitly enabled
 * "delete_data_on_uninstall", the plugin-owned tables are dropped too.
 *
 * @package FalnicServerConfigurator
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$falnic_settings = get_option( 'falnic_sc_settings', array() );

// Always clean plugin options + transients (they are plugin-owned metadata).
delete_option( 'falnic_sc_settings' );
delete_option( 'falnic_sc_db_version' );
delete_option( 'falnic_sc_last_schema_check' );
delete_transient( 'falnic_sc_install_report' );

// Purge any remaining data-cache transients.
global $wpdb;
$prefix = is_multisite() ? $wpdb->get_blog_prefix() : $wpdb->prefix;
$rows   = $wpdb->get_col(
	$wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $prefix . '_transient_%falnic_sc_%' )
);
foreach ( (array) $rows as $name ) {
	$key = str_replace( $prefix . '_transient_', '', $name );
	if ( false !== strpos( $key, 'falnic_sc_' ) || false !== strpos( $key, 'falnic_rl_' ) ) {
		delete_transient( $key );
	}
}

// Optionally drop the plugin tables.
if ( is_array( $falnic_settings ) && ! empty( $falnic_settings['delete_data_on_uninstall'] ) ) {
	if ( ! function_exists( 'falnic_sc_tables_for_uninstall' ) ) {
		/**
		 * Resolve plugin table names without loading the whole plugin.
		 */
		function falnic_sc_tables_for_uninstall() {
			$schema = require dirname( __FILE__ ) . '/includes/falnic-sc-schema.php';
			$tables = array();
			foreach ( $schema as $def ) {
				$tables[] = $def['name'];
			}
			return $tables;
		}
	}

	foreach ( falnic_sc_tables_for_uninstall() as $falnic_table ) {
		$wpdb->query( "DROP TABLE IF EXISTS `{$falnic_table}`" );
	}
}
