<?php
/**
 * Shortcode + front-end asset loader.
 *
 * Assets (CSS/JS/font) are registered globally but ONLY enqueued when the
 * shortcode actually renders, so nothing loads on any other page — the plugin
 * is invisible for the rest of the site.
 *
 * @package FalnicServerConfigurator
 */

defined( 'ABSPATH' ) || exit;

require_once FALNIC_SC_DIR . 'includes/class-falnic-sc-ai.php';

class Falnic_SC_Shortcode {

	/**
	 * Whether the app markup already rendered on this request.
	 *
	 * @var bool
	 */
	private static $rendered = false;

	/**
	 * Register shortcode + asset hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( 'falnic_server_configurator', array( __CLASS__, 'render' ) );
		add_shortcode( 'hpe_server_configurator', array( __CLASS__, 'render' ) ); // legacy alias

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	/**
	 * Register (not enqueue) all front-end assets. Versions are derived from
	 * file modification time for reliable cache busting without query-string
	 * churn on every page load.
	 *
	 * @return void
	 */
	public static function register_assets() {
		$css_ver = (string) filemtime( FALNIC_SC_DIR . 'assets/style.css' );
		$js_ver  = (string) filemtime( FALNIC_SC_DIR . 'assets/main.js' );
		$sec_ver = (string) filemtime( FALNIC_SC_DIR . 'assets/js/security.js' );

		wp_register_style( 'falnic-sc-app', FALNIC_SC_URL . 'assets/style.css', array(), $css_ver );
		wp_register_script( 'falnic-sc-security', FALNIC_SC_URL . 'assets/js/security.js', array(), $sec_ver, true );
		wp_register_script( 'falnic-sc-app', FALNIC_SC_URL . 'assets/main.js', array( 'falnic-sc-security' ), $js_ver, true );
	}

	/**
	 * Render the configurator app.
	 *
	 * @param array|string $atts    Shortcode attributes (unused for now).
	 * @return string
	 */
	public static function render( $atts = array() ) {
		// Guard against duplicate output when the shortcode is used twice.
		if ( self::$rendered ) {
			return '';
		}
		self::$rendered = true;

		// Enqueue only now — this is the proof the shortcode is on the page.
		if ( ! wp_style_is( 'falnic-sc-app', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'falnic-sc-app' );
		wp_enqueue_script( 'falnic-sc-app' );

		wp_localize_script(
			'falnic-sc-app',
			'FALNIC_SC_CONFIG',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'falnic_sc_public' ),
				'homeUrl'   => home_url( '/' ),
				'pageUrl'   => is_singular() ? get_permalink() : home_url( '/' ),
				'aiEnabled' => (bool) Falnic_SC_AI::is_configured(),
				'version'   => FALNIC_SC_VERSION,
			)
		);

		ob_start();
		include FALNIC_SC_DIR . 'templates/app-shell.php';
		return (string) ob_get_clean();
	}
}
