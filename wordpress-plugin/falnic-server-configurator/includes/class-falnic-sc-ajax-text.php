<?php
/**
 * Text sanitization helpers for the AI assistant endpoint.
 *
 * Ported 1:1 from the standalone api/ai_chat.php.
 *
 * @package FalnicServerConfigurator
 */

defined( 'ABSPATH' ) || exit;

class Falnic_SC_Ajax_Text {

	/**
	 * Trim, strip tags, drop control characters and clamp the length.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $limit Max characters.
	 * @return string
	 */
	public static function sanitize_text( $value, $limit = 1000 ) {
		$text = trim( strip_tags( (string) $value ) );
		$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text ) ?? '';
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $limit, 'UTF-8' ) : substr( $text, 0, $limit );
	}

	/**
	 * Pick a whitelist of non-empty fields from a row.
	 *
	 * @param array|null $row    Row.
	 * @param array      $fields Allowed fields.
	 * @return array|null
	 */
	public static function pick_fields( $row, array $fields ) {
		if ( ! $row ) {
			return null;
		}
		$result = array();
		foreach ( $fields as $field ) {
			if ( array_key_exists( $field, $row ) && null !== $row[ $field ] && '' !== $row[ $field ] ) {
				$result[ $field ] = is_string( $row[ $field ] ) ? self::sanitize_text( $row[ $field ], 240 ) : $row[ $field ];
			}
		}
		return $result ?: null;
	}

	/**
	 * Recursively sanitize an associative structure (bounded depth).
	 *
	 * @param mixed $value Value.
	 * @param int   $depth Current depth.
	 * @return mixed
	 */
	public static function sanitize_assoc_recursive( $value, $depth = 0 ) {
		if ( $depth > 3 ) {
			return null;
		}
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $key => $item ) {
				$safe_key        = is_string( $key ) ? self::sanitize_text( $key, 50 ) : $key;
				$out[ $safe_key ] = self::sanitize_assoc_recursive( $item, $depth + 1 );
			}
			return $out;
		}
		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}
		return self::sanitize_text( $value, 180 );
	}

	/**
	 * Clean a customer-facing reply: strip markdown leftovers, tables, clamp.
	 *
	 * @param string $text Raw reply.
	 * @return string
	 */
	public static function clean_customer_reply( $text ) {
		$text = self::sanitize_text( $text, 900 );
		$text = preg_replace( '/\*\*(.*?)\*\*/u', '$1', $text ) ?? $text;
		$text = preg_replace( '/(^|\s)#{1,6}\s*/u', '$1', $text ) ?? $text;
		$text = preg_replace( '/\|[^\n]*\|/u', '', $text ) ?? $text;

		$lines = preg_split( '/\R/u', $text ) ?: array();
		$clean = array();
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) { continue; }
			if ( 0 === strpos( $line, '|' ) || preg_match( '/^[-:| ]{5,}$/u', $line ) ) { continue; }

			$line_length = function_exists( 'mb_strlen' ) ? mb_strlen( $line, 'UTF-8' ) : strlen( $line );
			$chunks      = $line_length > 220 ? ( preg_split( '/(?<=[.!؟])\s+/u', $line ) ?: array( $line ) ) : array( $line );

			foreach ( $chunks as $chunk ) {
				$chunk = trim( $chunk );
				if ( '' === $chunk || preg_match( '/^[-:| ]{5,}$/u', $chunk ) ) { continue; }
				$clean[] = $chunk;
				if ( count( $clean ) >= 4 ) { break 2; }
			}
		}

		return implode( "\n", $clean );
	}

	/**
	 * Extract a JSON object from a raw model reply (tolerates code fences).
	 *
	 * @param string $raw Raw reply.
	 * @return array|null
	 */
	public static function decode_ai_json( $raw ) {
		$text    = trim( (string) $raw );
		$text    = preg_replace( '/^```(?:json)?\s*|\s*```$/u', '', $text ) ?? $text;
		$decoded = json_decode( $text, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}

		$start = strpos( $text, '{' );
		$end   = strrpos( $text, '}' );
		if ( false !== $start && false !== $end && $end > $start ) {
			$candidate = substr( $text, $start, $end - $start + 1 );
			$decoded   = json_decode( $candidate, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}
		return null;
	}

	/**
	 * Whitelisted AI action types.
	 *
	 * @return string[]
	 */
	public static function allowed_action_types() {
		return array( 'set_ram_total', 'set_ram_qty', 'set_cpu_qty', 'set_psu_qty', 'add_drive_raid10', 'set_cpu', 'set_ram', 'set_cpu_ram' );
	}

	/**
	 * Sanitize one AI action item.
	 *
	 * @param mixed $item Raw action.
	 * @return array|null
	 */
	public static function sanitize_action( $item ) {
		if ( ! is_array( $item ) ) {
			return null;
		}
		$type = self::sanitize_text( $item['type'] ?? '', 50 );
		if ( ! in_array( $type, self::allowed_action_types(), true ) ) {
			return null;
		}

		$payload = isset( $item['payload'] ) && is_array( $item['payload'] ) ? $item['payload'] : array();
		$safe_payload = array();
		foreach ( $payload as $key => $value ) {
			$safe_key = self::sanitize_text( $key, 40 );
			if ( '' === $safe_key ) { continue; }
			if ( is_numeric( $value ) ) {
				$safe_payload[ $safe_key ] = (int) $value;
			} elseif ( is_bool( $value ) ) {
				$safe_payload[ $safe_key ] = $value;
			} else {
				$safe_payload[ $safe_key ] = self::sanitize_text( $value, 80 );
			}
		}

		$label = self::sanitize_text( $item['label'] ?? '', 70 );
		if ( '' === $label ) {
			$label = 'اعمال پیشنهاد';
		}

		return array(
			'label'   => $label,
			'type'    => $type,
			'payload' => $safe_payload,
		);
	}
}
