<?php
/**
 * AI provider gateway (OpenAI-compatible).
 *
 * Ported from the standalone api/ai_chat.php + config/ai.php, but credentials
 * live in WordPress settings (stored in the DB, never exposed to the browser)
 * and the HTTP call goes through the WordPress HTTP API instead of raw cURL.
 *
 * @package FalnicServerConfigurator
 */

defined( 'ABSPATH' ) || exit;

require_once FALNIC_SC_DIR . 'includes/falnic-sc-functions.php';

class Falnic_SC_AI {

	/**
	 * Resolved AI configuration from plugin settings.
	 *
	 * @return array{enabled:bool,endpoint:string,api_key:string,model:string,timeout:int,max_tokens:int,temperature:float}
	 */
	public static function config() {
		$settings = falnic_sc_settings();

		return array(
			'enabled'     => ! empty( $settings['ai_enabled'] ),
			'endpoint'    => trim( (string) $settings['ai_endpoint'] ),
			'api_key'     => (string) $settings['ai_api_key'],
			'model'       => trim( (string) $settings['ai_model'] ),
			'timeout'     => max( 5, (int) $settings['ai_timeout'] ),
			'max_tokens'  => max( 64, (int) $settings['ai_max_tokens'] ),
			'temperature' => (float) $settings['ai_temperature'],
		);
	}

	/**
	 * Is the assistant usable with the current settings?
	 *
	 * @return bool
	 */
	public static function is_configured() {
		$config = self::config();
		return $config['enabled'] && '' !== $config['endpoint'] && '' !== $config['api_key'];
	}

	/**
	 * Call the OpenAI-compatible chat completions endpoint.
	 *
	 * @param array $messages Chat messages (role/content).
	 * @return string Raw model reply.
	 * @throws Exception With a user-safe message on any failure.
	 */
	public static function chat( array $messages ) {
		$config = self::config();

		if ( ! $config['enabled'] ) {
			throw new Exception( 'دستیار هوشمند غیرفعال است.' );
		}
		if ( '' === $config['endpoint'] || '' === $config['api_key'] ) {
			throw new Exception( 'AI provider is not configured.' );
		}

		$payload = array(
			'model'       => $config['model'],
			'stream'      => false,
			'temperature' => $config['temperature'],
			'max_tokens'  => $config['max_tokens'],
			'messages'    => array_values( $messages ),
		);

		$response = wp_remote_post(
			$config['endpoint'],
			array(
				'timeout'    => max( 5, $config['timeout'] ),
				'headers'    => array(
					'Authorization' => 'Bearer ' . $config['api_key'],
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'       => wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'user-agent' => 'Falnic-Server-Configurator/' . FALNIC_SC_VERSION,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new Exception( 'AI provider connection failed.' );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			throw new Exception( 'AI provider returned HTTP ' . $code . '.' );
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		$reply   = $decoded['choices'][0]['message']['content'] ?? $decoded['choices'][0]['text'] ?? null;

		if ( ! is_string( $reply ) || '' === trim( $reply ) ) {
			throw new Exception( 'AI provider returned an empty response.' );
		}

		return trim( $reply );
	}
}
