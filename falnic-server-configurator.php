<?php
/**
 * Plugin Name:       کانفیگوراتور سرور فالنیک (Falnic Server Configurator)
 * Plugin URI:        https://falnic.com
 * Description:       کانفیگوراتور حرفه‌ای سرور HPE برای وردپرس: شورت‌کد، نیازسنجی هوشمند، پیشنهاد سرور آماده، اعتبارسنجی سازگاری سخت‌افزار، دستیار AI و پنل مدیریت کامل قطعات.
 * Version:           1.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Falnic
 * Author URI:        https://falnic.com
 * License:           GPL-2.0-or-later
 * Text Domain:       falnic-server-configurator
 * Domain Path:       /languages
 *
 * @package FalnicServerConfigurator
 */

defined( 'ABSPATH' ) || exit;

// -----------------------------------------------------------------------------
// Core constants
// -----------------------------------------------------------------------------
define( 'FALNIC_SC_VERSION', '1.1.0' );
define( 'FALNIC_SC_DB_VERSION', '1.0.0' );
define( 'FALNIC_SC_FILE', __FILE__ );
define( 'FALNIC_SC_DIR', plugin_dir_path( __FILE__ ) );
define( 'FALNIC_SC_URL', plugin_dir_url( __FILE__ ) );
define( 'FALNIC_SC_BASENAME', plugin_basename( __FILE__ ) );

// -----------------------------------------------------------------------------
// Shared bootstrap (loaded on every request, kept intentionally tiny:
// only function definitions and hook registrations — no heavy work here so the
// plugin adds close-to-zero overhead on requests it is not involved in).
// -----------------------------------------------------------------------------

require_once FALNIC_SC_DIR . 'includes/class-falnic-sc-tables.php';
require_once FALNIC_SC_DIR . 'includes/falnic-sc-functions.php';

// Activation / deactivation: full schema verification + creation + seeding.
register_activation_hook( __FILE__, 'falnic_sc_activate' );

/**
 * Activation callback: lazily loads the installer, then verifies (and if
 * missing creates) every plugin table/column/index and seeds empty catalogs.
 *
 * @return void
 */
function falnic_sc_activate() {
	require_once FALNIC_SC_DIR . 'includes/class-falnic-sc-install.php';
	Falnic_SC_Installer::install();
}

/**
 * Upgrade routine: whenever the stored DB version differs from the code
 * version (e.g. after a plugin update), the schema is re-verified and
 * missing tables/columns/indexes are created automatically.
 *
 * @return void
 */
function falnic_sc_maybe_upgrade() {
	$installed = get_option( 'falnic_sc_db_version', '' );
	if ( FALNIC_SC_DB_VERSION !== $installed ) {
		require_once FALNIC_SC_DIR . 'includes/class-falnic-sc-install.php';
		Falnic_SC_Installer::install();
	}
}
add_action( 'admin_init', 'falnic_sc_maybe_upgrade' );

/**
 * Register public + AJAX hooks.
 *
 * @return void
 */
function falnic_sc_init_public() {
	require_once FALNIC_SC_DIR . 'includes/class-falnic-sc-shortcode.php';
	Falnic_SC_Shortcode::init();

	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		require_once FALNIC_SC_DIR . 'includes/class-falnic-sc-ajax.php';
		Falnic_SC_Ajax::init();
	}
}
add_action( 'init', 'falnic_sc_init_public' );

/**
 * Register admin-only hooks (menu pages, CRUD, settings, tools).
 *
 * @return void
 */
function falnic_sc_init_admin() {
	if ( ! is_admin() ) {
		return;
	}

	require_once FALNIC_SC_DIR . 'includes/class-falnic-sc-admin.php';
	Falnic_SC_Admin::init();
}
add_action( 'init', 'falnic_sc_init_admin' );

/**
 * Plugin action links (quick access to settings/dashboard).
 *
 * @param array $links Existing action links.
 * @return array
 */
function falnic_sc_action_links( $links ) {
	$custom = array(
		sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=falnic-sc' ) ),
			'پنل مدیریت'
		),
		sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=falnic-sc-settings' ) ),
			'تنظیمات'
		),
	);
	return array_merge( $custom, (array) $links );
}
add_filter( 'plugin_action_links_' . FALNIC_SC_BASENAME, 'falnic_sc_action_links' );
