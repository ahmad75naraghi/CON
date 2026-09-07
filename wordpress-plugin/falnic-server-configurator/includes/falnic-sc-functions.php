<?php
/**
 * Shared helper functions.
 *
 * @package FalnicServerConfigurator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Default plugin settings.
 *
 * @return array
 */
function falnic_sc_default_settings() {
	return array(
		// AI assistant (OpenAI-compatible provider).
		'ai_enabled'            => true,
		'ai_endpoint'           => '',
		'ai_api_key'            => '',
		'ai_model'              => 'Antigravity-Gemini',
		'ai_timeout'            => 25,
		'ai_max_tokens'         => 900,
		'ai_temperature'        => 0.25,
		// Performance.
		'cache_ttl'             => 3600,
		'ai_rate_limit'         => 30,
		'submit_rate_limit'     => 20,
		// Data handling.
		'delete_data_on_uninstall' => 0,
	);
}

/**
 * Read plugin settings merged with defaults.
 *
 * @return array
 */
function falnic_sc_settings() {
	$saved = get_option( 'falnic_sc_settings', array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}

	return array_merge( falnic_sc_default_settings(), $saved );
}

/**
 * Read a single setting.
 *
 * @param string $key     Setting key.
 * @param mixed  $default Fallback when missing.
 * @return mixed
 */
function falnic_sc_setting( $key, $default = null ) {
	$settings = falnic_sc_settings();
	$defaults = falnic_sc_default_settings();

	if ( array_key_exists( $key, $settings ) && null !== $settings[ $key ] && '' !== $settings[ $key ] ) {
		return $settings[ $key ];
	}

	return array_key_exists( $key, $defaults ) ? $defaults[ $key ] : $default;
}

/**
 * Simple transient based rate limiter for public endpoints.
 *
 * @return void
 */
function falnic_sc_purge_data_cache() {
	global $wpdb;

	$prefix = is_multisite() ? $wpdb->get_blog_prefix() : $wpdb->prefix;
	$like   = $prefix . '_transient_falnic_sc_data_%';
	$rows   = $wpdb->get_col(
		$wpdb->prepare( 'SELECT option_name FROM ' . $wpdb->options . ' WHERE option_name LIKE %s', $like )
	);

	foreach ( (array) $rows as $name ) {
		$key = str_replace( $prefix . '_transient_', '', $name );
		delete_transient( $key );
	}

	// Also drop the timeout variants stored by set_transient.
	$like_to = $prefix . '_transient_timeout_falnic_sc_data_%';
	$rows    = $wpdb->get_col(
		$wpdb->prepare( 'SELECT option_name FROM ' . $wpdb->options . ' WHERE option_name LIKE %s', $like_to )
	);
	foreach ( (array) $rows as $name ) {
		$key = str_replace( $prefix . '_transient_timeout_', '', $name );
		delete_transient( $key );
	}
}

/**
 * Simple transient based rate limiter for public endpoints.
 *
 * @param string $bucket Bucket name (e.g. ai_chat).
 * @param int    $limit  Max requests inside the window.
 * @param int    $window Window size in seconds.
 * @return bool True when the request is allowed.
 */
function falnic_sc_rate_limit( $bucket, $limit, $window = 3600 ) {
	$limit = max( 0, (int) $limit );
	if ( 0 === $limit ) {
		return true; // 0 = disabled limit.
	}

	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	$key = 'falnic_rl_' . md5( $bucket . '|' . $ip );
	$now = time();

	$data = get_transient( $key );
	if ( ! is_array( $data ) || ! isset( $data['reset'], $data['count'] ) || $now >= (int) $data['reset'] ) {
		$data = array(
			'reset' => $now + max( 60, $window ),
			'count' => 0,
		);
	}

	$data['count']++;

	// Keep the same remaining window instead of extending it on every hit.
	set_transient( $key, $data, max( 60, (int) $data['reset'] - $now ) );

	return $data['count'] <= $limit;
}

/**
 * Multibyte-safe truncate helper for admin list cells.
 *
 * @param string $text  Text.
 * @param int    $limit Max characters.
 * @return string
 */
function falnic_mb_truncate( $text, $limit = 60 ) {
	$text = (string) $text;
	if ( function_exists( 'mb_strlen' ) && mb_strlen( $text, 'UTF-8' ) > $limit ) {
		return mb_substr( $text, 0, $limit, 'UTF-8' ) . '…';
	}
	if ( ! function_exists( 'mb_strlen' ) && strlen( $text ) > $limit ) {
		return substr( $text, 0, $limit ) . '…';
	}
	return $text;
}

/**
 * Validate + normalize a JSON column value coming from the admin editor.
 *
 * @param mixed  $raw     Raw input (string expected).
 * @param string $column  Column name (used in the error message).
 * @return string Encoded JSON.
 * @throws Exception When the value is not valid JSON.
 */
function falnic_sc_validate_json_field( $raw, $column ) {
	$text = trim( (string) $raw );
	if ( '' === $text ) {
		$text = 'null';
	}

	$decoded = json_decode( $text, true );
	if ( json_last_error() !== JSON_ERROR_NONE ) {
		throw new Exception( sprintf( 'مقدار فیلد %s باید JSON معتبر باشد.', $column ) );
	}

	// Re-encode so stored JSON is always normalized (keys sorted kept as-is).
	return wp_json_encode( $decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}
