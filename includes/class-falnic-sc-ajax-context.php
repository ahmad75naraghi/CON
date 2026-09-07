<?php
/**
 * AI context builder: whitelisted, compact context + safe action enrichment.
 *
 * Builds compactConfigSummary, databaseSignals, compactHistory,
 * normalizeAiPayload, enrichWithSafeActions, buildCpuRamAction.
 *
 * @package FalnicServerConfigurator
 */

defined( 'ABSPATH' ) || exit;

class Falnic_SC_Ajax_Context {

	/**
	 * Build the compact, whitelisted context sent to the provider.
	 *
	 * @param array $context Raw front-end context.
	 * @return array
	 */
	public static function compact( array $context ) {
		$config        = isset( $context['currentConfig'] ) && is_array( $context['currentConfig'] ) ? $context['currentConfig'] : array();
		$target        = isset( $context['target'] ) && is_array( $context['target'] ) ? $context['target'] : array();
		$selected_offer = isset( $context['selectedOffer'] ) && is_array( $context['selectedOffer'] ) ? $context['selectedOffer'] : null;

		$drives = array();
		foreach ( ( $config['drives'] ?? array() ) as $drive ) {
			if ( ! is_array( $drive ) ) { continue; }
			$drives[] = array(
				'drive_id' => (int) ( $drive['driveId'] ?? $drive['id'] ?? 0 ),
				'qty'      => max( 1, (int) ( $drive['qty'] ?? 1 ) ),
				'raid'     => Falnic_SC_Ajax_Text::sanitize_text( $drive['raid'] ?? 'none', 20 ),
			);
		}

		$networks = array();
		foreach ( ( $config['networks'] ?? array() ) as $net ) {
			if ( ! is_array( $net ) ) { continue; }
			$networks[] = array(
				'network_id' => (int) ( $net['networkId'] ?? $net['id'] ?? 0 ),
				'qty'        => max( 1, (int) ( $net['qty'] ?? 1 ) ),
			);
		}

		$hbas = array();
		foreach ( ( $config['hbas'] ?? array() ) as $hba ) {
			if ( ! is_array( $hba ) ) { continue; }
			$hbas[] = array(
				'hba_id' => (int) ( $hba['hbaId'] ?? $hba['id'] ?? 0 ),
				'qty'    => max( 1, (int) ( $hba['qty'] ?? 1 ) ),
			);
		}

		return array(
			'page'           => array(
				'active_view'  => Falnic_SC_Ajax_Text::sanitize_text( $context['activeView'] ?? '', 80 ),
				'active_mode'  => Falnic_SC_Ajax_Text::sanitize_text( $context['activeMode'] ?? '', 30 ),
				'current_step' => isset( $context['currentStep'] ) && is_array( $context['currentStep'] ) ? Falnic_SC_Ajax_Text::sanitize_assoc_recursive( $context['currentStep'] ) : null,
				'validation'   => array_map( array( 'Falnic_SC_Ajax_Text', 'sanitize_assoc_recursive' ), isset( $context['validation'] ) && is_array( $context['validation'] ) ? array_slice( $context['validation'], 0, 12 ) : array() ),
			),
			'target'         => array(
				'cores'        => (int) ( $target['cores'] ?? 0 ),
				'ram_gb'       => (int) ( $target['ram'] ?? 0 ),
				'storage_gb'   => (int) ( $target['storage'] ?? 0 ),
				'gpu_required' => ! empty( $target['gpu'] ),
				'network'      => Falnic_SC_Ajax_Text::sanitize_text( $target['network'] ?? 'any', 40 ),
				'form_factor'  => Falnic_SC_Ajax_Text::sanitize_text( $target['formFactor'] ?? 'any', 40 ),
				'usecase'      => Falnic_SC_Ajax_Text::sanitize_text( $target['usecase'] ?? '', 80 ),
			),
			'selected_offer' => $selected_offer ? Falnic_SC_Ajax_Text::pick_fields( $selected_offer, array( 'id', 'title', 'tier', 'label' ) ) : null,
			'selected_config' => array(
				'chassis'               => Falnic_SC_Ajax_Text::pick_fields( $config['chassis'] ?? null, array( 'id', 'model', 'generation', 'form_factor', 'part_number', 'default_controller', 'default_network' ) ),
				'cpu'                   => Falnic_SC_Ajax_Text::pick_fields( $config['cpu'] ?? null, array( 'id', 'model_name', 'cores', 'tdp_watts', 'part_number' ) ),
				'cpu_qty'               => (int) ( $config['cpuQty'] ?? 0 ),
				'ram'                   => Falnic_SC_Ajax_Text::pick_fields( $config['ram'] ?? null, array( 'id', 'model_name', 'capacity_gb', 'speed_mt', 'ram_type', 'part_number' ) ),
				'ram_qty'               => (int) ( $config['ramQty'] ?? 0 ),
				'drives'                => array_slice( $drives, 0, 8 ),
				'controller'            => Falnic_SC_Ajax_Text::pick_fields( $config['controller'] ?? null, array( 'id', 'model_name', 'max_drives', 'form_factor', 'part_number' ) ),
				'sas_expander'          => ! empty( $config['sasExpander'] ),
				'gpu'                   => Falnic_SC_Ajax_Text::pick_fields( $config['gpu'] ?? null, array( 'id', 'model_name', 'memory_gb', 'tdp_watts', 'part_number' ) ),
				'gpu_qty'               => (int) ( $config['gpuQty'] ?? 0 ),
				'networks'              => array_slice( $networks, 0, 6 ),
				'hbas'                  => array_slice( $hbas, 0, 6 ),
				'psu'                   => Falnic_SC_Ajax_Text::pick_fields( $config['psu'] ?? null, array( 'id', 'model_name', 'wattage', 'efficiency', 'part_number' ) ),
				'psu_qty'               => (int) ( $config['psuQty'] ?? 0 ),
				'riser2'                => Falnic_SC_Ajax_Text::pick_fields( $config['riser2'] ?? null, array( 'id', 'model_name', 'total_slots', 'x16_slots' ) ),
				'riser3'                => Falnic_SC_Ajax_Text::pick_fields( $config['riser3'] ?? null, array( 'id', 'model_name', 'total_slots', 'x16_slots' ) ),
				'estimated_power_watts' => (int) ( $config['totalWatts'] ?? 0 ),
				'high_perf_fan_required' => ! empty( $config['reqHighPerfFan'] ),
			),
		);
	}

	/**
	 * Collect limited DB signals (offers, selected parts, compatible snapshot).
	 *
	 * @param array $context Raw front-end context.
	 * @return array
	 */
	public static function db_signals( array $context ) {
		global $wpdb;

		$signals = array(
			'available_offers'     => array(),
			'selected_parts_from_db' => array(),
			'compatible_snapshot'  => null,
		);

		try {
			$t_offers = Falnic_SC_Tables::table( 'offers' );
			$signals['available_offers'] = (array) $wpdb->get_results(
				"SELECT id,title,cpu_cores,ram_gb,usable_storage_gb,gpu_memory_gb,generation_rank,stock_status,stock_qty,lead_time_days
				 FROM `{$t_offers}`
				 WHERE is_active = 1
				 ORDER BY stock_status ASC, performance_score ASC, id ASC
				 LIMIT 3",
				ARRAY_A
			);

			$config    = isset( $context['currentConfig'] ) && is_array( $context['currentConfig'] ) ? $context['currentConfig'] : array();
			$chassis_id = (int) ( $config['chassis']['id'] ?? 0 );

			$signals['selected_parts_from_db']['drives']   = self::fetch_by_ids( 'drives', self::ids_from_items( $config['drives'] ?? array(), 'driveId' ), array( 'id', 'model_name', 'capacity_gb', 'form_factor', 'interface', 'part_number' ) );
			$signals['selected_parts_from_db']['networks'] = self::fetch_by_ids( 'networks', self::ids_from_items( $config['networks'] ?? array(), 'networkId' ), array( 'id', 'model_name', 'port_count', 'speed_gbps', 'form_factor', 'part_number' ) );
			$signals['selected_parts_from_db']['hbas']     = self::fetch_by_ids( 'hbas', self::ids_from_items( $config['hbas'] ?? array(), 'hbaId' ), array( 'id', 'model_name', 'port_count', 'speed_gbps', 'part_number' ) );

			if ( $chassis_id > 0 ) {
				$t_chassis = Falnic_SC_Tables::table( 'chassis' );
				$chassis   = $wpdb->get_row( $wpdb->prepare(
					"SELECT id,model,generation,form_factor,cpu_socket_type,ram_generation,max_cpus,max_ram_slots,ram_slots_per_cpu,storage_rules,default_controller,default_network
					 FROM `{$t_chassis}` WHERE id = %d LIMIT 1",
					$chassis_id
				), ARRAY_A );

				if ( $chassis ) {
					$storage_rules = json_decode( $chassis['storage_rules'] ?? '{}', true ) ?: array();
					$compat_cid    = wp_json_encode( $chassis_id );
					$compat        = '(compatible_chassis_ids IS NULL OR JSON_CONTAINS(compatible_chassis_ids, %s))';

					$t_cpus = Falnic_SC_Tables::table( 'cpus' );
					$cpus   = (array) $wpdb->get_results( $wpdb->prepare(
						"SELECT id,model_name,cores,tdp_watts,part_number FROM `{$t_cpus}` WHERE socket_type = %s AND {$compat} ORDER BY cores DESC LIMIT 5",
						$chassis['cpu_socket_type'],
						$compat_cid
					), ARRAY_A );

					$t_rams = Falnic_SC_Tables::table( 'rams' );
					$rams   = (array) $wpdb->get_results( $wpdb->prepare(
						"SELECT id,model_name,capacity_gb,speed_mt,part_number FROM `{$t_rams}` WHERE memory_generation = %s AND {$compat} ORDER BY capacity_gb DESC, speed_mt DESC LIMIT 5",
						$chassis['ram_generation'],
						$compat_cid
					), ARRAY_A );

					$t_drives = Falnic_SC_Tables::table( 'drives' );
					$drive_sql   = "SELECT id,model_name,capacity_gb,form_factor,interface,part_number FROM `{$t_drives}` WHERE {$compat}";
					$drive_args  = array( $compat_cid );
					if ( ! empty( $storage_rules['base_drive_type'] ) ) {
						$drive_sql .= ' AND form_factor = %s';
						$drive_args[] = $storage_rules['base_drive_type'];
					}
					$drive_sql .= ' ORDER BY capacity_gb DESC LIMIT 5';
					$drives = (array) $wpdb->get_results( $wpdb->prepare( $drive_sql, $drive_args ), ARRAY_A );

					$t_controllers = Falnic_SC_Tables::table( 'controllers' );
					$controllers   = (array) $wpdb->get_results( $wpdb->prepare(
						"SELECT id,model_name,max_drives,form_factor,part_number FROM `{$t_controllers}` WHERE compatible_chassis_ids IS NULL OR JSON_CONTAINS(compatible_chassis_ids, %s) ORDER BY max_drives DESC LIMIT 5",
						$compat_cid
					), ARRAY_A );

					unset( $chassis['storage_rules'] );
					$signals['compatible_snapshot'] = array(
						'chassis'                  => $chassis,
						'top_compatible_cpus'      => $cpus,
						'top_compatible_rams'      => $rams,
						'top_compatible_drives'    => $drives,
						'top_compatible_controllers' => $controllers,
						'storage_rules'            => $storage_rules,
					);
				}
			}
		} catch ( Exception $e ) {
			$signals['db_warning'] = 'Database context is partially unavailable.';
		}

		return $signals;
	}

	/**
	 * Distinct ids from an item list.
	 *
	 * @param array  $items Items.
	 * @param string $field Id field name.
	 * @return int[]
	 */
	private static function ids_from_items( array $items, $field ) {
		$ids = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) { continue; }
			$id = (int) ( $item[ $field ] ?? $item['id'] ?? 0 );
			if ( $id > 0 ) { $ids[] = $id; }
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Fetch a column-whitelisted list of rows by ids.
	 *
	 * @param string $table_key Schema key.
	 * @param array  $ids       Row ids.
	 * @param array  $fields    Allowed columns.
	 * @return array
	 */
	private static function fetch_by_ids( $table_key, array $ids, array $fields ) {
		global $wpdb;

		if ( empty( $ids ) ) {
			return array();
		}

		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( empty( $ids ) ) {
			return array();
		}

		$table       = Falnic_SC_Tables::table( $table_key );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$columns      = implode( ',', array_map( static fn( $f ) => "`{$f}`", $fields ) );

		return (array) $wpdb->get_results(
			$wpdb->prepare( "SELECT {$columns} FROM `{$table}` WHERE id IN ({$placeholders}) LIMIT 12", $ids ),
			ARRAY_A
		);
	}

	/**
	 * Compact chat history (last 8 messages, current message skipped).
	 *
	 * @param array  $history         History items (role/text).
	 * @param string $current_message Current message.
	 * @return array
	 */
	public static function compact_history( array $history, $current_message = '' ) {
		$messages = array();
		foreach ( array_slice( $history, -8 ) as $item ) {
			if ( ! is_array( $item ) ) { continue; }
			$role = ( $item['role'] ?? '' ) === 'user' ? 'user' : 'assistant';
			$text = Falnic_SC_Ajax_Text::sanitize_text( $item['text'] ?? '', 700 );
			if ( '' !== $text && '__context_init__' !== $text ) {
				if ( 'user' === $role && '' !== $current_message && $text === $current_message ) { continue; }
				$messages[] = array( 'role' => $role, 'content' => $text );
			}
		}
		return $messages;
	}

	/**
	 * Normalize a raw provider reply into the structured payload.
	 *
	 * @param string $raw Raw reply.
	 * @return array
	 */
	public static function normalize_payload( $raw ) {
		$decoded = Falnic_SC_Ajax_Text::decode_ai_json( $raw );
		if ( ! is_array( $decoded ) ) {
			return array(
				'reply'         => Falnic_SC_Ajax_Text::clean_customer_reply( $raw ),
				'question'      => 'مایلید کدام بخش را دقیق‌تر بررسی کنیم؟',
				'quick_replies' => array(
					array( 'label' => 'بررسی رم', 'message' => 'رم این کانفیگ را بررسی کن' ),
					array( 'label' => 'بررسی ذخیره‌سازی', 'message' => 'ذخیره‌سازی و RAID را بررسی کن' ),
				),
				'actions'       => array(),
			);
		}

		$quick_replies = array();
		foreach ( ( $decoded['quick_replies'] ?? array() ) as $item ) {
			if ( ! is_array( $item ) ) { continue; }
			$label       = Falnic_SC_Ajax_Text::sanitize_text( $item['label'] ?? '', 60 );
			$msg         = Falnic_SC_Ajax_Text::sanitize_text( $item['message'] ?? $label, 220 );
			$quick_action = Falnic_SC_Ajax_Text::sanitize_action( $item['action'] ?? null );
			if ( '' !== $label && '' !== $msg ) {
				$reply = array( 'label' => $label, 'message' => $msg );
				if ( $quick_action ) { $reply['action'] = $quick_action; }
				$quick_replies[] = $reply;
			}
			if ( count( $quick_replies ) >= 3 ) { break; }
		}

		$actions = array();
		foreach ( ( $decoded['actions'] ?? array() ) as $item ) {
			$action = Falnic_SC_Ajax_Text::sanitize_action( $item );
			if ( $action ) { $actions[] = $action; }
			if ( count( $actions ) >= 2 ) { break; }
		}

		$reply = Falnic_SC_Ajax_Text::clean_customer_reply( (string) ( $decoded['reply'] ?? '' ) );
		if ( '' === $reply ) {
			$reply = 'اطلاعات فعلی کانفیگ را بررسی کردم. برای راهنمایی دقیق‌تر، لطفاً یکی از گزینه‌های زیر را انتخاب کنید.';
		}
		$question = Falnic_SC_Ajax_Text::sanitize_text( $decoded['question'] ?? '', 180 );
		if ( '' === $question ) {
			$question = 'دوست دارید کدام بخش را تغییر یا بررسی کنیم؟';
		}

		return array(
			'reply'         => $reply,
			'question'      => $question,
			'quick_replies' => $quick_replies,
			'actions'       => $actions,
		);
	}

	/**
	 * Deterministic safe CPU/RAM action built from DB signals.
	 *
	 * @param array  $compact    Compact context.
	 * @param array  $db_signals DB signals.
	 * @param string $focus_text Combined intent text.
	 * @return array|null
	 */
	private static function build_cpu_ram_action( array $compact, array $db_signals, $focus_text ) {
		$focus = function_exists( 'mb_strtolower' ) ? mb_strtolower( $focus_text, 'UTF-8' ) : strtolower( $focus_text );

		$is_cpu_ram_intent = false !== strpos( $focus, 'cpu' ) || false !== strpos( $focus, 'پردازنده' ) || false !== strpos( $focus, 'رم' ) || false !== stripos( $focus_text, 'ram' );
		$is_apply_intent   = false !== strpos( $focus, 'اصلاح' ) || false !== strpos( $focus, 'ارتقا' ) || false !== strpos( $focus, 'تغییر' ) || false !== strpos( $focus, 'درست' ) || false !== strpos( $focus, 'اعمال' );
		if ( ! $is_cpu_ram_intent || ! $is_apply_intent ) {
			return null;
		}

		$snapshot = isset( $db_signals['compatible_snapshot'] ) && is_array( $db_signals['compatible_snapshot'] ) ? $db_signals['compatible_snapshot'] : array();
		$cpus     = isset( $snapshot['top_compatible_cpus'] ) && is_array( $snapshot['top_compatible_cpus'] ) ? $snapshot['top_compatible_cpus'] : array();
		$rams     = isset( $snapshot['top_compatible_rams'] ) && is_array( $snapshot['top_compatible_rams'] ) ? $snapshot['top_compatible_rams'] : array();
		if ( ! $cpus && ! $rams ) {
			return null;
		}

		$target  = $compact['target'] ?? array();
		$config  = $compact['selected_config'] ?? array();
		$chassis = isset( $config['chassis'] ) && is_array( $config['chassis'] ) ? $config['chassis'] : array();
		$max_cpus      = max( 1, (int) ( $chassis['max_cpus'] ?? 2 ) );
		$max_ram_slots = max( 1, (int) ( $chassis['max_ram_slots'] ?? 24 ) );
		$needed_cores  = max( 1, (int) ( $target['cores'] ?? 0 ) );
		$needed_ram    = max( 16, (int) ( $target['ram_gb'] ?? 0 ) );
		$gpu           = isset( $config['gpu'] ) && is_array( $config['gpu'] ) ? $config['gpu'] : null;
		if ( $gpu ) {
			$needed_ram = max( $needed_ram, (int) ( $gpu['memory_gb'] ?? 0 ) * max( 1, (int) ( $config['gpu_qty'] ?? 1 ) ) * 2 );
		}

		$selected_cpu     = null;
		$selected_cpu_qty = max( 1, (int) ( $config['cpu_qty'] ?? 1 ) );
		if ( $cpus ) {
			$best_score = PHP_INT_MAX;
			foreach ( $cpus as $cpu ) {
				$cores = max( 1, (int) ( $cpu['cores'] ?? 1 ) );
				for ( $qty = 1; $qty <= $max_cpus; $qty++ ) {
					$total = $cores * $qty;
					if ( $total < $needed_cores ) { continue; }
					$score = ( $total - $needed_cores ) + ( $qty * 2 );
					if ( $score < $best_score ) {
						$best_score       = $score;
						$selected_cpu     = $cpu;
						$selected_cpu_qty = $qty;
					}
				}
			}
			if ( ! $selected_cpu ) {
				$selected_cpu     = $cpus[0];
				$selected_cpu_qty = min( $max_cpus, 1 );
			}
		}

		$selected_ram     = null;
		$selected_ram_qty = max( 1, (int) ( $config['ram_qty'] ?? 1 ) );
		if ( $rams ) {
			$best_score = PHP_INT_MAX;
			foreach ( $rams as $ram ) {
				$cap = max( 1, (int) ( $ram['capacity_gb'] ?? 1 ) );
				$qty = (int) ceil( $needed_ram / $cap );
				$qty = max( 1, min( $max_ram_slots, $qty ) );
				if ( $qty * $cap < $needed_ram ) { continue; }
				if ( $selected_cpu_qty > 1 && $qty % $selected_cpu_qty !== 0 ) {
					$qty += $selected_cpu_qty - ( $qty % $selected_cpu_qty );
				}
				if ( $qty > $max_ram_slots ) { continue; }
				$score = ( $qty * $cap - $needed_ram ) + $qty;
				if ( $score < $best_score ) {
					$best_score       = $score;
					$selected_ram     = $ram;
					$selected_ram_qty = $qty;
				}
			}
			if ( ! $selected_ram ) {
				$selected_ram     = $rams[0];
				$selected_ram_qty = min( $max_ram_slots, max( 1, (int) ceil( $needed_ram / max( 1, (int) ( $selected_ram['capacity_gb'] ?? 1 ) ) ) ) );
			}
		}

		$payload = array();
		if ( $selected_cpu ) {
			$payload['cpu_id'] = (int) $selected_cpu['id'];
			$payload['cpu_qty'] = $selected_cpu_qty;
		}
		if ( $selected_ram ) {
			$payload['ram_id'] = (int) $selected_ram['id'];
			$payload['ram_qty'] = $selected_ram_qty;
		}
		if ( ! $payload ) {
			return null;
		}

		$label_parts = array();
		if ( $selected_cpu ) { $label_parts[] = $selected_cpu_qty . '× CPU'; }
		if ( $selected_ram ) { $label_parts[] = ( $selected_ram_qty * (int) ( $selected_ram['capacity_gb'] ?? 0 ) ) . 'GB RAM'; }

		return array(
			'label'   => 'اعمال اصلاح CPU/RAM (' . implode( '، ', $label_parts ) . ')',
			'type'    => 'set_cpu_ram',
			'payload' => $payload,
		);
	}

	/**
	 * Enrich a normalized payload with safe, deterministic actions.
	 *
	 * @param array  $payload     Normalized payload.
	 * @param array  $compact     Compact context.
	 * @param array  $db_signals  DB signals.
	 * @param string $focus_text  Intent text.
	 * @return array
	 */
	public static function enrich_with_safe_actions( array $payload, array $compact, array $db_signals = array(), $focus_text = '' ) {
		$config = $compact['selected_config'] ?? array();
		$target = $compact['target'] ?? array();
		$ram    = isset( $config['ram'] ) && is_array( $config['ram'] ) ? $config['ram'] : null;
		$ram_qty = (int) ( $config['ram_qty'] ?? 0 );
		$current_total_ram = $ram ? ( (int) ( $ram['capacity_gb'] ?? 0 ) * max( 1, $ram_qty ) ) : 0;
		$target_ram        = max( (int) ( $target['ram_gb'] ?? 0 ), $current_total_ram );

		$reply_text    = $focus_text . ' ' . ( $payload['reply'] ?? '' ) . ' ' . ( $payload['question'] ?? '' );
		$cpu_ram_action = self::build_cpu_ram_action( $compact, $db_signals, $reply_text );
		if ( $cpu_ram_action ) {
			$has_cpu_ram_action = false;
			foreach ( ( $payload['actions'] ?? array() ) as $action ) {
				if ( in_array( $action['type'] ?? '', array( 'set_cpu', 'set_ram', 'set_cpu_ram' ), true ) ) {
					$has_cpu_ram_action = true;
				}
			}
			if ( ! $has_cpu_ram_action ) {
				$payload['actions'][] = $cpu_ram_action;
			}
			$payload['quick_replies'][] = array(
				'label'   => 'بله، CPU و RAM را اصلاح کن',
				'message' => 'بله، اول CPU و رم را اصلاح کن',
				'action'  => $cpu_ram_action,
			);
		}

		$is_ram_focused = false !== stripos( $reply_text, 'RAM' ) || false !== strpos( $reply_text, 'رم' );
		if ( $is_ram_focused && $ram && $current_total_ram > 0 ) {
			$capacity     = max( 1, (int) ( $ram['capacity_gb'] ?? 1 ) );
			$suggest_total = max( $target_ram, $current_total_ram * 2 );
			$suggest_total = min( 1024, (int) ( ceil( $suggest_total / $capacity ) * $capacity ) );
			$suggest_qty   = max( $ram_qty + 1, (int) ceil( $suggest_total / $capacity ) );

			$has_ram_action = false;
			foreach ( ( $payload['actions'] ?? array() ) as $action ) {
				if ( ( $action['type'] ?? '' ) === 'set_ram_total' || ( $action['type'] ?? '' ) === 'set_ram_qty' ) {
					$has_ram_action = true;
				}
			}
			if ( ! $has_ram_action && $suggest_qty > $ram_qty ) {
				$payload['actions'][] = array(
					'label'   => 'ارتقای RAM به ' . ( $suggest_qty * $capacity ) . 'GB',
					'type'    => 'set_ram_qty',
					'payload' => array( 'qty' => $suggest_qty ),
				);
			}
		}

		if ( empty( $payload['quick_replies'] ) ) {
			$payload['quick_replies'] = array(
				array( 'label' => 'اقتصادی‌ترش کن', 'message' => 'چطور این کانفیگ را اقتصادی‌تر کنم؟' ),
				array( 'label' => 'برای رشد آینده', 'message' => 'برای رشد آینده کدام بخش را ارتقا بدهم؟' ),
				array( 'label' => 'بررسی ریسک‌ها', 'message' => 'ریسک‌های اصلی این کانفیگ چیست؟' ),
			);
		}

		$payload['actions']       = array_slice( $payload['actions'] ?? array(), 0, 2 );
		$payload['quick_replies'] = array_slice( $payload['quick_replies'] ?? array(), 0, 3 );

		return $payload;
	}
}
