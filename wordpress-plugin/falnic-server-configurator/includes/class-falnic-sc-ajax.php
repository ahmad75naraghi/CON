<?php
/**
 * AJAX endpoints (public front-end API).
 *
 * Faithful port of the standalone api/*.php endpoints onto WordPress:
 *
 *   falnic_sc_get_data   ← api/get_data.php
 *   falnic_sc_recommend  ← api/recommend_servers.php
 *   falnic_sc_submit     ← api/submit_config.php
 *   falnic_sc_ai_chat    ← api/ai_chat.php
 *
 * Response contracts are byte-compatible with the original endpoints so the
 * existing front-end (assets/main.js) keeps working unchanged.
 *
 * @package FalnicServerConfigurator
 */

defined( 'ABSPATH' ) || exit;

require_once FALNIC_SC_DIR . 'includes/class-falnic-sc-ai.php';
require_once FALNIC_SC_DIR . 'includes/class-falnic-sc-ajax-text.php';
require_once FALNIC_SC_DIR . 'includes/class-falnic-sc-ajax-context.php';

class Falnic_SC_Ajax {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		$actions = array(
			'falnic_sc_get_data'  => 'get_data',
			'falnic_sc_recommend' => 'recommend_servers',
			'falnic_sc_submit'    => 'submit_config',
			'falnic_sc_ai_chat'   => 'ai_chat',
		);
		foreach ( $actions as $action => $method ) {
			add_action( 'wp_ajax_' . $action, array( __CLASS__, $method ) );
			add_action( 'wp_ajax_nopriv_' . $action, array( __CLASS__, $method ) );
		}
	}

	/**
	 * Verify the public nonce for every endpoint.
	 *
	 * @return void
	 */
	private static function verify_nonce() {
		if ( ! check_ajax_referer( 'falnic_sc_public', 'nonce', false ) ) {
			wp_send_json_error(
				array( 'message' => 'نشست شما منقضی شده است؛ لطفاً صفحه را تازه‌سازی کنید.' ),
				403
			);
		}
	}

	/**
	 * Decoded JSON payload sent by the front-end client.
	 *
	 * @return array
	 */
	private static function payload() {
		$raw  = isset( $_POST['payload'] ) ? wp_unslash( $_POST['payload'] ) : '{}';
		$data = json_decode( (string) $raw, true );
		return is_array( $data ) ? $data : array();
	}

	// -------------------------------------------------------------------
	// get_data
	// -------------------------------------------------------------------

	/**
	 * JSON columns decoded per table key (same map as the standalone app).
	 *
	 * @var array
	 */
	private static $json_fields = array(
		'chassis'        => array( 'storage_rules', 'cooling_rules' ),
		'cpus'           => array( 'cooling_requirements', 'compatible_chassis_ids' ),
		'rams'           => array( 'compatible_cpu_ids', 'compatible_chassis_ids' ),
		'gpus'           => array( 'compatible_chassis_ids' ),
		'psus'           => array( 'input_voltage_support', 'compatible_chassis_ids' ),
		'drives'         => array( 'compatible_chassis_ids' ),
		'controllers'    => array( 'supported_interfaces', 'compatible_chassis_ids' ),
		'networks'       => array( 'compatible_chassis_ids' ),
		'risers'         => array( 'compatible_chassis_ids' ),
		'hbas'           => array( 'compatible_chassis_ids' ),
		'optical_drives' => array( 'compatible_chassis_ids' ),
	);

	/**
	 * Decode whitelisted JSON columns on a rows list (in place).
	 *
	 * @param array[] $rows   Rows.
	 * @param array   $fields JSON column names.
	 * @return void
	 */
	private static function decode_json_fields( array &$rows, array $fields ) {
		foreach ( $rows as &$row ) {
			foreach ( $fields as $field ) {
				if ( array_key_exists( $field, $row ) && null !== $row[ $field ] && '' !== $row[ $field ] ) {
					$row[ $field ] = json_decode( $row[ $field ] );
				}
			}
		}
		unset( $row );
	}

	/**
	 * GET: chassis list, or all components compatible with one chassis.
	 *
	 * @return void
	 */
	public static function get_data() {
		global $wpdb;

		self::verify_nonce();

		$chassis_id = isset( $_GET['chassis_id'] ) ? absint( $_GET['chassis_id'] ) : null;

		$cache_ttl = (int) falnic_sc_setting( 'cache_ttl', 3600 );
		$cache_key = $chassis_id ? 'falnic_sc_data_chassis_' . $chassis_id : 'falnic_sc_data_all';

		if ( $cache_ttl > 0 ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached && is_array( $cached ) ) {
				wp_send_json( $cached );
			}
		}

		try {
			$t_chassis = Falnic_SC_Tables::table( 'chassis' );

			if ( ! $chassis_id ) {
				// Mode 1: only the chassis list for the first dropdown.
				$rows     = (array) $wpdb->get_results( "SELECT * FROM `{$t_chassis}`", ARRAY_A );
				$rows     = Falnic_SC_Tables::cast_rows( 'chassis', $rows );
				self::decode_json_fields( $rows, self::$json_fields['chassis'] );

				$response = array( 'status' => 'success', 'data' => array( 'chassis' => $rows ) );
				if ( $cache_ttl > 0 ) {
					set_transient( $cache_key, $response, $cache_ttl );
				}
				wp_send_json( $response );
			}

			// Mode 2: components compatible with the given chassis.
			$chassis = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t_chassis}` WHERE id = %d", $chassis_id ), ARRAY_A );
			if ( ! $chassis ) {
				wp_send_json( array( 'status' => 'error', 'message' => 'شاسی مورد نظر یافت نشد.' ), 404 );
			}
			$chassis = Falnic_SC_Tables::cast_rows( 'chassis', $chassis );

			$compat    = '(compatible_chassis_ids IS NULL OR JSON_CONTAINS(compatible_chassis_ids, %s))';
			$cid_param = wp_json_encode( (int) $chassis_id );

			$data      = array( 'chassis' => array( $chassis ) );
			$simple    = array(
				'psus'           => 'psus',
				'controllers'    => 'controllers',
				'gpus'           => 'gpus',
				'networks'       => 'networks',
				'risers'         => 'risers',
				'hbas'           => 'hbas',
				'optical_drives' => 'optical_drives',
			);

			// CPUs: socket must match + explicit compatibility.
			$t_cpus = Falnic_SC_Tables::table( 'cpus' );
			$stmt   = $wpdb->prepare( "SELECT * FROM `{$t_cpus}` WHERE socket_type = %s AND {$compat}", $chassis['cpu_socket_type'], $cid_param );
			$data['cpus'] = Falnic_SC_Tables::cast_rows( 'cpus', (array) $wpdb->get_results( $stmt, ARRAY_A ) );

			// RAMs: memory generation must match.
			$t_rams = Falnic_SC_Tables::table( 'rams' );
			$stmt   = $wpdb->prepare( "SELECT * FROM `{$t_rams}` WHERE memory_generation = %s AND {$compat}", $chassis['ram_generation'], $cid_param );
			$data['rams'] = Falnic_SC_Tables::cast_rows( 'rams', (array) $wpdb->get_results( $stmt, ARRAY_A ) );

			// Drives: filtered by the chassis base drive type.
			$storage_rules   = json_decode( (string) $chassis['storage_rules'], true ) ?: array();
			$base_drive_type = $storage_rules['base_drive_type'] ?? null;

			$t_drives = Falnic_SC_Tables::table( 'drives' );
			if ( $base_drive_type ) {
				$stmt = $wpdb->prepare( "SELECT * FROM `{$t_drives}` WHERE form_factor = %s AND {$compat}", $base_drive_type, $cid_param );
			} else {
				$stmt = $wpdb->prepare( "SELECT * FROM `{$t_drives}` WHERE {$compat}", $cid_param );
			}
			$data['drives'] = Falnic_SC_Tables::cast_rows( 'drives', (array) $wpdb->get_results( $stmt, ARRAY_A ) );

			// Remaining tables: compatibility filter only.
			foreach ( $simple as $out_key => $table_key ) {
				$table         = Falnic_SC_Tables::table( $table_key );
				$stmt          = $wpdb->prepare( "SELECT * FROM `{$table}` WHERE {$compat}", $cid_param );
				$data[ $out_key ] = Falnic_SC_Tables::cast_rows( $table_key, (array) $wpdb->get_results( $stmt, ARRAY_A ) );
			}

			foreach ( self::$json_fields as $key => $fields ) {
				if ( isset( $data[ $key ] ) ) {
					self::decode_json_fields( $data[ $key ], $fields );
				}
			}

			$response = array( 'status' => 'success', 'data' => $data );
			if ( $cache_ttl > 0 ) {
				set_transient( $cache_key, $response, $cache_ttl );
			}
			wp_send_json( $response );
		} catch ( Exception $e ) {
			wp_send_json( array( 'status' => 'error', 'message' => 'خطای داخلی سرور.' ), 500 );
		}
	}

	// -------------------------------------------------------------------
	// recommend_servers
	// -------------------------------------------------------------------

	/**
	 * POST: exactly three ready offers (eco / managed / advanced).
	 *
	 * @return void
	 */
	public static function recommend_servers() {
		self::verify_nonce();

		$request   = self::payload();
		$target    = isset( $request['target'] ) && is_array( $request['target'] ) ? $request['target'] : array();
		$answers   = isset( $request['answers'] ) && is_array( $request['answers'] ) ? $request['answers'] : array();

		try {
			$normalized = self::normalize_target( $target, $answers );
			$catalog    = self::catalog_offers();
			$picks      = self::choose_recommendations( $catalog, $normalized );

			$hydrated = array_map(
				static function ( $offer ) use ( $normalized ) {
					return self::hydrate_offer( $offer, $normalized );
				},
				$picks
			);

			wp_send_json( array( 'status' => 'success', 'target' => $normalized, 'offers' => $hydrated ) );
		} catch ( Exception $e ) {
			wp_send_json( array( 'status' => 'error', 'message' => 'خطای داخلی سرور.' ), 500 );
		}
	}

	/**
	 * Normalize the guidance target (ported 1:1 from api/recommend_servers.php).
	 *
	 * @param array $target  Raw target.
	 * @param array $answers Wizard answers.
	 * @return array
	 */
	private static function normalize_target( array $target, array $answers ) {
		$services       = $answers['1'] ?? $answers[1] ?? array();
		$services       = is_array( $services ) ? $services : array();
		$users          = (int) ( $answers['2'][0] ?? $answers[2][0] ?? 1 );
		$perf           = $answers['3'][0] ?? $answers[3][0] ?? 'med';
		$wants_growth   = ( ( $answers['4'][0] ?? $answers[4][0] ?? 'no' ) === 'yes' );
		$local_storage  = ( ( $answers['5'][0] ?? $answers[5][0] ?? 'no' ) === 'no' );
		$infrastructure = $answers['6'] ?? $answers[6] ?? array();
		$infrastructure = is_array( $infrastructure ) ? $infrastructure : array();

		$cores   = (int) ( $target['cores'] ?? 0 );
		$ram     = (int) ( $target['ram'] ?? 0 );
		$storage = (int) ( $target['storage'] ?? 0 );
		$gpu     = ! empty( $target['gpu'] );
		$usecase = $target['usecase'] ?? 'Guidance';

		if ( $cores <= 1 || $ram <= 1 ) {
			$cores   = 8 * max( 1, $users );
			$ram     = 32 * max( 1, $users );
			$storage = $local_storage ? 2000 * max( 1, $users ) : 500;
		}

		if ( in_array( 'db', $services, true ) ) { $cores += 8; $ram += 64; $storage += $local_storage ? 1000 : 500; $usecase = 'Database'; }
		if ( in_array( 'virt', $services, true ) ) { $cores += 8; $ram += 96; $usecase = 'Virtualization'; }
		if ( in_array( 'ai', $services, true ) ) { $cores += 8; $ram += 64; $gpu = true; $usecase = 'AI'; }
		if ( in_array( 'storage', $services, true ) ) { $storage += 4000; $usecase = 'File Server'; }
		if ( in_array( 'web', $services, true ) ) { $cores += 4; $ram += 16; }
		if ( in_array( 'accounting', $services, true ) || in_array( 'crm', $services, true ) ) { $ram += 16; }

		if ( 'max' === $perf ) { $cores = (int) ceil( $cores * 1.5 ); $ram = (int) ceil( $ram * 1.5 ); $storage = (int) ceil( $storage * 1.25 ); }
		if ( 'min' === $perf ) { $cores = (int) ceil( $cores * 0.75 ); $ram = (int) ceil( $ram * 0.75 ); }
		if ( $wants_growth ) { $cores = (int) ceil( $cores * 1.25 ); $ram = (int) ceil( $ram * 1.25 ); $storage = (int) ceil( $storage * 1.25 ); }

		return array(
			'usecase'      => $usecase,
			'services'     => array_values( $services ),
			'user_factor'  => max( 1, $users ),
			'performance'  => $perf,
			'wants_growth' => $wants_growth,
			'cores'        => max( 4, $cores ),
			'ram'          => max( 16, $ram ),
			'storage'      => max( 500, $storage ),
			'gpu'          => $gpu,
			'network'      => in_array( 'fiber', $infrastructure, true ) ? 'fiber' : 'any',
			'formFactor'   => in_array( 'rack', $infrastructure, true ) ? 'rack' : 'any',
		);
	}

	/**
	 * Fallback offers used when the catalog table is unavailable/empty
	 * (ported verbatim from the standalone app).
	 *
	 * @return array
	 */
	private static function fallback_offers() {
		$json = '[{"id":1,"title":"DL360 Gen9 اقتصادی","description":"کم‌هزینه‌ترین سرور آماده‌ای که حداقل نیازهای پایه را پوشش می‌دهد و برای شروع کار مناسب است.","icon":"🖨️","cpu_cores":12,"ram_gb":64,"usable_storage_gb":2000,"raw_storage_gb":4000,"gpu_memory_gb":0,"generation_rank":9,"expansion_score":35,"performance_score":12064,"bullets":["ضعیف‌ترین گزینه‌ای که کار را راه می‌اندازد","مناسب سرویس‌های عمومی، حسابداری، CRM و تیم‌های کوچک","مصرف توان و هزینه اولیه کمتر"],"selected_components":{"chassis_id":3,"cpu":{"id":55,"qty":1},"ram":{"id":20,"qty":2},"drives":[{"id":127,"qty":2,"raid":"1"}],"controller":null,"sas_expander":false,"gpu":null,"networks":[],"riser2":null,"riser3":null,"psu":{"id":13,"qty":2},"hbas":[],"optical_drives":[]}},{"id":2,"title":"DL380 Gen10 مدیریت‌شده","description":"ترکیب استاندارد و متعادل با پردازنده دوگانه، رم بیشتر، RAID سخت‌افزاری و فضای توسعه مناسب برای چند سال آینده.","icon":"🏢","cpu_cores":32,"ram_gb":256,"usable_storage_gb":3840,"raw_storage_gb":7680,"gpu_memory_gb":0,"generation_rank":10,"expansion_score":70,"performance_score":32256,"bullets":["تعادل خوب بین هزینه، کارایی و پایداری","فضای توسعه مناسب برای رشد سازمان","مناسب دیتابیس، مجازی‌سازی سبک و سرویس‌های سازمانی"],"selected_components":{"chassis_id":1,"cpu":{"id":25,"qty":2},"ram":{"id":5,"qty":4},"drives":[{"id":31,"qty":4,"raid":"10"}],"controller":{"id":2},"sas_expander":false,"gpu":null,"networks":[{"id":2,"qty":1}],"riser2":{"id":3},"riser3":null,"psu":{"id":3,"qty":2},"hbas":[],"optical_drives":[]}},{"id":3,"title":"ML110 Gen11 پیشرفته","description":"گزینه نسل جدیدتر با ظرفیت ذخیره‌سازی بسیار بالاتر، NVMe، رم مناسب و GPU برای رشد آینده و بارهای سنگین‌تر.","icon":"🚀","cpu_cores":32,"ram_gb":256,"usable_storage_gb":25600,"raw_storage_gb":51200,"gpu_memory_gb":24,"generation_rank":11,"expansion_score":85,"performance_score":32256,"bullets":["نسل جدیدتر و مناسب‌تر برای ارتقای آینده","ظرفیت ذخیره‌سازی چندبرابر نیازهای معمول","آماده برای GPU، AI سبک، VDI یا بارهای پیشرفته"],"selected_components":{"chassis_id":2,"cpu":{"id":37,"qty":1},"ram":{"id":14,"qty":4},"drives":[{"id":76,"qty":4,"raid":"10"}],"controller":{"id":12},"sas_expander":false,"gpu":{"id":7,"qty":1},"networks":[{"id":3,"qty":1}],"riser2":{"id":18},"riser3":null,"psu":{"id":11,"qty":2},"hbas":[],"optical_drives":[]}}]';

		return json_decode( $json, true ) ?: array();
	}

	/**
	 * Active offers from Prepared_Server_Offers with typed fields.
	 *
	 * @return array
	 */
	private static function catalog_offers() {
		global $wpdb;

		$table = Falnic_SC_Tables::table( 'offers' );
		$rows  = $wpdb->get_results( "SELECT * FROM `{$table}` WHERE is_active = 1 ORDER BY performance_score ASC, generation_rank ASC, id ASC", ARRAY_A );

		if ( ! $rows ) {
			return self::fallback_offers();
		}

		return array_map(
			static function ( $row ) {
				$row['cpu_cores']         = (int) $row['cpu_cores'];
				$row['ram_gb']            = (int) $row['ram_gb'];
				$row['usable_storage_gb'] = (int) $row['usable_storage_gb'];
				$row['raw_storage_gb']    = (int) $row['raw_storage_gb'];
				$row['gpu_memory_gb']     = (int) $row['gpu_memory_gb'];
				$row['generation_rank']   = (int) $row['generation_rank'];
				$row['expansion_score']   = (int) $row['expansion_score'];
				$row['performance_score'] = (int) $row['performance_score'];
				$row['stock_status']      = $row['stock_status'] ?? 'Available';
				$row['stock_qty']         = (int) ( $row['stock_qty'] ?? 1 );
				$row['lead_time_days']    = (int) ( $row['lead_time_days'] ?? 0 );
				$row['bullets']           = json_decode( $row['bullets'] ?? '[]', true ) ?: array();
				$row['selected_components'] = json_decode( $row['selected_components'] ?? '{}', true ) ?: array();
				return $row;
			},
			$rows
		);
	}

	/**
	 * Fetch one row by id with decoded JSON columns.
	 *
	 * @param string $table_key Schema key.
	 * @param mixed  $id        Row id.
	 * @return array|null
	 */
	private static function fetch_row( $table_key, $id ) {
		global $wpdb;

		if ( ! $id ) {
			return null;
		}

		$table = Falnic_SC_Tables::table( $table_key );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", (int) $id ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}

		$row = Falnic_SC_Tables::cast_rows( $table_key, $row );

		$fields = isset( self::$json_fields[ $table_key ] ) ? self::$json_fields[ $table_key ] : array();
		foreach ( $fields as $field ) {
			if ( array_key_exists( $field, $row ) && null !== $row[ $field ] && '' !== $row[ $field ] ) {
				$decoded      = json_decode( $row[ $field ], true );
				$row[ $field ] = null === $decoded ? $row[ $field ] : $decoded;
			}
		}

		return $row;
	}

	/**
	 * Offer availability check.
	 *
	 * @param array $offer Offer row.
	 * @return bool
	 */
	private static function is_available_offer( array $offer ) {
		return ( $offer['stock_status'] ?? 'Available' ) !== 'Unavailable' && (int) ( $offer['stock_qty'] ?? 1 ) > 0;
	}

	/**
	 * Soft fiber-network signal.
	 *
	 * @param array $offer Offer row.
	 * @return bool
	 */
	private static function offer_has_fiber_network( array $offer ) {
		$encoded = wp_json_encode( $offer['selected_components'] ?? array() );
		return false !== strpos( (string) $encoded, 'network' ) || false !== strpos( strtolower( $offer['title'] ?? '' ), 'fiber' );
	}

	/**
	 * Does the offer satisfy the (scaled) target?
	 *
	 * @param array $offer      Offer row.
	 * @param array $target     Normalized target.
	 * @param float $multiplier Scale factor.
	 * @return bool
	 */
	private static function meets_target( array $offer, array $target, $multiplier = 1.0 ) {
		if ( ! self::is_available_offer( $offer ) ) {
			return false;
		}
		if ( $offer['cpu_cores'] < ( $target['cores'] * $multiplier ) ) {
			return false;
		}
		if ( $offer['ram_gb'] < ( $target['ram'] * $multiplier ) ) {
			return false;
		}
		if ( $target['storage'] > 0 && $offer['usable_storage_gb'] < ( $target['storage'] * $multiplier ) ) {
			return false;
		}
		if ( ! empty( $target['gpu'] ) && $offer['gpu_memory_gb'] <= 0 ) {
			return false;
		}
		return true;
	}

	/**
	 * Workload / stock adjustments to the score.
	 *
	 * @param array $offer  Offer row.
	 * @param array $target Normalized target.
	 * @return float
	 */
	private static function workload_bonus( array $offer, array $target ) {
		$bonus    = 0.0;
		$services = $target['services'] ?? array();

		if ( in_array( 'ai', $services, true ) && (int) $offer['gpu_memory_gb'] > 0 ) { $bonus -= 120; }
		if ( in_array( 'storage', $services, true ) && (int) $offer['usable_storage_gb'] >= (int) $target['storage'] * 1.5 ) { $bonus -= 80; }
		if ( in_array( 'virt', $services, true ) && (int) $offer['ram_gb'] >= (int) $target['ram'] * 1.25 ) { $bonus -= 70; }
		if ( in_array( 'db', $services, true ) && (int) $offer['raw_storage_gb'] > 0 ) { $bonus -= 45; }
		if ( ( $target['network'] ?? 'any' ) === 'fiber' && self::offer_has_fiber_network( $offer ) ) { $bonus -= 25; }
		if ( ! empty( $target['wants_growth'] ) && (int) $offer['expansion_score'] >= 70 ) { $bonus -= 60; }

		$stock_status = $offer['stock_status'] ?? 'Available';
		if ( 'Limited' === $stock_status ) {
			$bonus += 20;
		}
		$bonus += min( 60, max( 0, (int) ( $offer['lead_time_days'] ?? 0 ) ) * 4 );

		return $bonus;
	}

	/**
	 * Distance between an offer and the scaled target.
	 *
	 * @param array $offer      Offer row.
	 * @param array $target     Normalized target.
	 * @param float $multiplier Scale factor.
	 * @return float
	 */
	private static function score_distance( array $offer, array $target, $multiplier ) {
		return abs( $offer['cpu_cores'] - ( $target['cores'] * $multiplier ) )
			+ abs( ( $offer['ram_gb'] - ( $target['ram'] * $multiplier ) ) / 4 )
			+ abs( ( $offer['usable_storage_gb'] - ( ( $target['storage'] ?: 1 ) * $multiplier ) ) / 250 )
			+ self::workload_bonus( $offer, $target );
	}

	/**
	 * Pick exactly eco / managed / advanced offers.
	 *
	 * @param array[] $offers Catalog.
	 * @param array   $target Normalized target.
	 * @return array[]
	 */
	private static function choose_recommendations( array $offers, array $target ) {
		$available = array_values( array_filter( $offers, static fn( $o ) => self::is_available_offer( $o ) ) );
		if ( ! $available ) {
			$available = $offers;
		}

		$eligible = array_values( array_filter( $available, static fn( $o ) => self::meets_target( $o, $target, 1.0 ) ) );
		if ( ! $eligible ) {
			$eligible = $available;
		}

		usort( $eligible, static fn( $a, $b ) => array( self::score_distance( $a, $target, 1.0 ), $a['performance_score'], $a['ram_gb'], $a['usable_storage_gb'] ) <=> array( self::score_distance( $b, $target, 1.0 ), $b['performance_score'], $b['ram_gb'], $b['usable_storage_gb'] ) );
		$eco = $eligible[0];

		$managed_pool = array_values( array_filter( $eligible, static fn( $o ) => $o['id'] != $eco['id'] && (int) $o['expansion_score'] >= 50 ) );
		if ( ! $managed_pool ) {
			$managed_pool = array_values( array_filter( $available, static fn( $o ) => $o['id'] != $eco['id'] ) );
		}
		usort( $managed_pool, static fn( $a, $b ) => self::score_distance( $a, $target, 1.35 ) <=> self::score_distance( $b, $target, 1.35 ) );
		$managed = $managed_pool[0] ?? $eco;

		$advanced_pool = array_values( array_filter( $available, static fn( $o ) => $o['id'] != $eco['id'] && $o['id'] != $managed['id'] && self::meets_target( $o, $target, 2.0 ) ) );
		if ( ! $advanced_pool ) {
			$advanced_pool = array_values( array_filter( $available, static fn( $o ) => $o['id'] != $eco['id'] && $o['id'] != $managed['id'] ) );
		}
		usort( $advanced_pool, static fn( $a, $b ) => array( self::score_distance( $a, $target, 2.0 ), -$a['generation_rank'], -$a['expansion_score'] ) <=> array( self::score_distance( $b, $target, 2.0 ), -$b['generation_rank'], -$b['expansion_score'] ) );
		$advanced = $advanced_pool[0] ?? $managed;

		$eco['recommendation_tier']   = 'eco';
		$eco['recommendation_label']  = 'اقتصادی';
		$eco['recommendation_reason'] = 'اولین سرور آماده‌ای که نیاز شما را با کمترین منابع اضافه و موجودی قابل سفارش پوشش می‌دهد.';

		$managed['recommendation_tier']   = 'managed';
		$managed['recommendation_label']  = 'مدیریت‌شده';
		$managed['recommendation_reason'] = 'انتخاب استاندارد با کارایی بهتر، ریسک کمتر و فضای توسعه منطقی.';

		$advanced['recommendation_tier']   = 'advanced';
		$advanced['recommendation_label']  = 'پیشرفته';
		$advanced['recommendation_reason'] = 'ظرفیت بالاتر، نسل جدیدتر و مناسب رشد آینده با کمترین نگرانی ارتقا.';

		return array( $eco, $managed, $advanced );
	}

	/**
	 * Hydrate an offer: fetch its components, rebuild config + display rows.
	 *
	 * @param array $offer  Offer row.
	 * @param array $target Normalized target.
	 * @return array
	 */
	private static function hydrate_offer( array $offer, array $target ) {
		$components = $offer['selected_components'] ?? array();

		$chassis    = self::fetch_row( 'chassis', $components['chassis_id'] ?? null );
		$cpu        = self::fetch_row( 'cpus', $components['cpu']['id'] ?? null );
		$ram        = self::fetch_row( 'rams', $components['ram']['id'] ?? null );
		$controller = self::fetch_row( 'controllers', $components['controller']['id'] ?? null );
		$gpu        = self::fetch_row( 'gpus', $components['gpu']['id'] ?? null );
		$riser2     = self::fetch_row( 'risers', $components['riser2']['id'] ?? null );
		$riser3     = self::fetch_row( 'risers', $components['riser3']['id'] ?? null );
		$psu        = self::fetch_row( 'psus', $components['psu']['id'] ?? null );

		$drives_config = array();
		$drives_db     = array();
		foreach ( ( $components['drives'] ?? array() ) as $drive ) {
			$row = self::fetch_row( 'drives', $drive['id'] ?? null );
			if ( ! $row ) { continue; }
			$drives_db[]     = $row;
			$drives_config[] = array(
				'driveId' => (string) $row['id'],
				'qty'     => (int) ( $drive['qty'] ?? 1 ),
				'raid'    => (string) ( $drive['raid'] ?? 'none' ),
			);
		}

		$networks_config = array();
		$networks_db     = array();
		foreach ( ( $components['networks'] ?? array() ) as $network ) {
			$row = self::fetch_row( 'networks', $network['id'] ?? null );
			if ( ! $row ) { continue; }
			$networks_db[]     = $row;
			$networks_config[] = array(
				'networkId' => (string) $row['id'],
				'qty'       => (int) ( $network['qty'] ?? 1 ),
			);
		}

		$hbas_config = array();
		$hbas_db     = array();
		foreach ( ( $components['hbas'] ?? array() ) as $hba ) {
			$row = self::fetch_row( 'hbas', $hba['id'] ?? null );
			if ( ! $row ) { continue; }
			$hbas_db[]     = $row;
			$hbas_config[] = array(
				'hbaId' => (string) $row['id'],
				'qty'   => (int) ( $hba['qty'] ?? 1 ),
			);
		}

		$opticals_config = array();
		$opticals_db     = array();
		foreach ( ( $components['optical_drives'] ?? array() ) as $optical ) {
			$row = self::fetch_row( 'optical_drives', $optical['id'] ?? null );
			if ( ! $row ) { continue; }
			$opticals_db[]     = $row;
			$opticals_config[] = array(
				'opticalId' => (string) $row['id'],
				'qty'       => (int) ( $optical['qty'] ?? 1 ),
			);
		}

		$total_watts = (int) ( $chassis['base_power_watts'] ?? 100 );
		if ( $cpu ) { $total_watts += (int) $cpu['tdp_watts'] * (int) ( $components['cpu']['qty'] ?? 1 ); }
		if ( $ram ) { $total_watts += (int) $ram['power_consumption_watts'] * (int) ( $components['ram']['qty'] ?? 1 ); }
		if ( $gpu ) { $total_watts += (int) $gpu['tdp_watts'] * (int) ( $components['gpu']['qty'] ?? 1 ); }
		foreach ( $drives_db as $idx => $drive_row ) { $total_watts += (int) $drive_row['power_consumption_watts'] * (int) $drives_config[ $idx ]['qty']; }
		foreach ( $opticals_db as $idx => $opt_row ) { $total_watts += (int) $opt_row['power_consumption_watts'] * (int) $opticals_config[ $idx ]['qty']; }

		$config = array(
			'chassis'         => $chassis,
			'cpu'             => $cpu,
			'cpuQty'          => (int) ( $components['cpu']['qty'] ?? 1 ),
			'ram'             => $ram,
			'ramQty'          => (int) ( $components['ram']['qty'] ?? 1 ),
			'drives'          => $drives_config,
			'opticalDrives'   => $opticals_config,
			'controller'      => $controller,
			'hbas'            => $hbas_config,
			'gpu'             => $gpu,
			'gpuQty'          => $gpu ? (int) ( $components['gpu']['qty'] ?? 1 ) : 0,
			'networks'        => $networks_config,
			'psu'             => $psu,
			'psuQty'          => (int) ( $components['psu']['qty'] ?? ( $chassis['max_psu_bays'] ?? 2 ) ),
			'riser2'          => $riser2,
			'riser3'          => $riser3,
			'sasExpander'     => ! empty( $components['sas_expander'] ),
			'totalWatts'      => $total_watts,
			'reqHighPerfFan'  => ! empty( $gpu ) || array_reduce( $drives_db, static fn( $carry, $d ) => $carry || ! empty( $d['requires_high_perf_fan'] ), false ),
		);

		$display_rows = array();
		if ( $chassis ) { $display_rows[] = array( 'title' => 'شاسی (Chassis)', 'desc' => "HPE ProLiant {$chassis['model']} {$chassis['generation']} ({$chassis['form_factor']})" ); }
		if ( $cpu ) { $display_rows[] = array( 'title' => 'پردازنده (CPU)', 'desc' => $config['cpuQty'] . 'X ' . $cpu['model_name'] . ' — ' . ( $cpu['cores'] * $config['cpuQty'] ) . ' Core' ); }
		if ( $ram ) { $display_rows[] = array( 'title' => 'حافظه رم (RAM)', 'desc' => $config['ramQty'] . 'X ' . $ram['model_name'] . ' — مجموع ' . $offer['ram_gb'] . 'GB' ); }
		foreach ( $drives_db as $idx => $drive_row ) { $display_rows[] = array( 'title' => 'فضای ذخیره‌سازی (Storage)', 'desc' => $drives_config[ $idx ]['qty'] . 'X ' . $drive_row['model_name'] . ' (RAID: ' . $drives_config[ $idx ]['raid'] . ')' ); }
		$display_rows[] = array( 'title' => 'ظرفیت قابل استفاده', 'desc' => round( $offer['usable_storage_gb'] / 1000, 1 ) . ' TB' );
		if ( $controller ) { $display_rows[] = array( 'title' => 'کنترلر رید (RAID)', 'desc' => $controller['model_name'] ); }
		elseif ( $chassis ) { $display_rows[] = array( 'title' => 'کنترلر رید (RAID)', 'desc' => $chassis['default_controller'] ?? 'پیش‌فرض مادربرد' ); }
		if ( $gpu ) { $display_rows[] = array( 'title' => 'کارت گرافیک (GPU)', 'desc' => $config['gpuQty'] . 'X ' . $gpu['model_name'] . ' — ' . $offer['gpu_memory_gb'] . 'GB VRAM' ); }
		foreach ( $networks_db as $idx => $net_row ) { $display_rows[] = array( 'title' => 'کارت شبکه (Network)', 'desc' => $networks_config[ $idx ]['qty'] . 'X ' . $net_row['model_name'] ); }
		if ( $riser2 ) { $display_rows[] = array( 'title' => 'رایزر دوم', 'desc' => $riser2['model_name'] ); }
		if ( $riser3 ) { $display_rows[] = array( 'title' => 'رایزر سوم', 'desc' => $riser3['model_name'] ); }
		if ( $psu ) { $display_rows[] = array( 'title' => 'منبع تغذیه (Power Supply)', 'desc' => $config['psuQty'] . 'X ' . $psu['model_name'] ); }
		$display_rows[] = array( 'title' => 'توان تقریبی', 'desc' => $total_watts . ' W' );
		$display_rows[] = array( 'title' => 'وضعیت موجودی', 'desc' => ( $offer['stock_status'] ?? 'Available' ) . ' — تعداد: ' . (int) ( $offer['stock_qty'] ?? 1 ) . ' — زمان تامین: ' . (int) ( $offer['lead_time_days'] ?? 0 ) . ' روز' );

		$offer['config']        = $config;
		$offer['display_rows']  = $display_rows;
		$offer['db']            = array(
			'chassis'        => array_values( array_filter( array( $chassis ) ) ),
			'cpus'           => array_values( array_filter( array( $cpu ) ) ),
			'rams'           => array_values( array_filter( array( $ram ) ) ),
			'drives'         => $drives_db,
			'controllers'    => array_values( array_filter( array( $controller ) ) ),
			'gpus'           => array_values( array_filter( array( $gpu ) ) ),
			'networks'       => $networks_db,
			'risers'         => array_values( array_filter( array( $riser2, $riser3 ) ) ),
			'hbas'           => $hbas_db,
			'optical_drives' => $opticals_db,
			'psus'           => array_values( array_filter( array( $psu ) ) ),
		);
		$offer['target']        = $target;

		unset( $offer['selected_components'] );
		return $offer;
	}

	// -------------------------------------------------------------------
	// submit_config
	// -------------------------------------------------------------------

	/**
	 * Normalize a compatibility id list that may arrive as a raw JSON string or
	 * as an already-decoded array (fetch_row decodes JSON columns).
	 *
	 * @param mixed $value Raw value.
	 * @return array|null Null when unrestricted.
	 */
	private static function id_list( $value ) {
		if ( null === $value || '' === $value || array() === $value ) {
			return null;
		}
		if ( is_array( $value ) ) {
			return $value;
		}
		$decoded = json_decode( (string) $value, true );
		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Compatibility list check for a JSON id list column.
	 *
	 * @param mixed $value      Raw JSON string or decoded array.
	 * @param int   $chassis_id Chassis id.
	 * @return bool
	 */
	private static function is_compatible_with_chassis( $value, $chassis_id ) {
		$ids = self::id_list( $value );
		if ( null === $ids || empty( $ids ) ) {
			return true;
		}
		return in_array( $chassis_id, $ids, false );
	}

	/**
	 * Usable capacity per RAID level.
	 *
	 * @param int    $capacity_gb Single disk capacity.
	 * @param int    $qty         Disk count.
	 * @param string $raid        RAID level.
	 * @return int
	 */
	private static function raid_usable_gb( $capacity_gb, $qty, $raid ) {
		switch ( $raid ) {
			case '1':  return 2 === $qty ? $capacity_gb : 0;
			case '5':  return $qty >= 3 ? ( $qty - 1 ) * $capacity_gb : 0;
			case '6':  return $qty >= 4 ? ( $qty - 2 ) * $capacity_gb : 0;
			case '10': return ( $qty >= 4 && 0 === $qty % 2 ) ? (int) ( ( $qty / 2 ) * $capacity_gb ) : 0;
			case '50': return $qty >= 6 ? ( $qty - 2 ) * $capacity_gb : 0;
			case '60': return $qty >= 8 ? ( $qty - 4 ) * $capacity_gb : 0;
			default:   return $qty * $capacity_gb;
		}
	}

	/**
	 * RAID/qty pair validity.
	 *
	 * @param string $raid RAID level.
	 * @param int    $qty  Disk count.
	 * @return bool
	 */
	private static function validate_raid_qty( $raid, $qty ) {
		switch ( $raid ) {
			case '0':  return $qty >= 2;
			case '1':  return 2 === $qty;
			case '5':  return $qty >= 3;
			case '6':  return $qty >= 4;
			case '10': return $qty >= 4 && 0 === $qty % 2;
			case '50': return $qty >= 6;
			case '60': return $qty >= 8;
			case 'none':
			case '':   return true;
			default:   return false;
		}
	}

	/**
	 * POST: server-side validation + price/power calc + persist request.
	 *
	 * @return void
	 */
	public static function submit_config() {
		global $wpdb;

		self::verify_nonce();

		if ( ! falnic_sc_rate_limit( 'submit', (int) falnic_sc_setting( 'submit_rate_limit', 20 ) ) ) {
			wp_send_json( array( 'status' => 'error', 'message' => 'تعداد درخواست‌های شما بیش از حد مجاز است؛ لطفاً کمی بعد دوباره تلاش کنید.' ), 429 );
		}

		$request = self::payload();

		if ( empty( $request['config']['chassis']['id'] ) ) {
			wp_send_json( array( 'status' => 'error', 'message' => 'اطلاعات نامعتبر است.' ), 400 );
		}

		$errors  = array();
		$target  = isset( $request['target'] ) && is_array( $request['target'] ) ? $request['target'] : array();
		$config  = $request['config'];

		try {
			// 1) Chassis.
			$chassis = self::fetch_row( 'chassis', $config['chassis']['id'] );
			if ( ! $chassis ) {
				$errors[] = 'شاسی انتخابی معتبر نیست.';
			}
			$chassis_id = $chassis ? (int) $chassis['id'] : null;

			// 2) CPU.
			$cpu     = null;
			$cpu_qty = (int) ( $config['cpuQty'] ?? 0 );
			if ( $chassis ) {
				$cpu = self::fetch_row( 'cpus', $config['cpu']['id'] ?? null );
				if ( ! $cpu ) {
					$errors[] = 'پردازنده انتخابی معتبر نیست.';
				} else {
					if ( $cpu['socket_type'] !== $chassis['cpu_socket_type'] ) {
						$errors[] = 'سوکت پردازنده با شاسی انتخابی سازگار نیست.';
					}
					if ( ! self::is_compatible_with_chassis( $cpu['compatible_chassis_ids'] ?? null, $chassis_id ) ) {
						$errors[] = 'پردازنده انتخابی برای این شاسی تاییدشده نیست.';
					}
					if ( $cpu_qty < 1 || $cpu_qty > (int) $chassis['max_cpus'] ) {
						$errors[] = "تعداد پردازنده مجاز نیست (حداکثر {$chassis['max_cpus']} عدد).";
					}
				}
			}

			// 3) RAM.
			$ram     = null;
			$ram_qty = (int) ( $config['ramQty'] ?? 0 );
			if ( $chassis ) {
				$ram = self::fetch_row( 'rams', $config['ram']['id'] ?? null );
				if ( ! $ram ) {
					$errors[] = 'رم انتخابی معتبر نیست.';
				} else {
					if ( $ram['memory_generation'] !== $chassis['ram_generation'] ) {
						$errors[] = 'نسل رم با شاسی انتخابی سازگار نیست.';
					}
					if ( ! self::is_compatible_with_chassis( $ram['compatible_chassis_ids'] ?? null, $chassis_id ) ) {
						$errors[] = 'رم انتخابی برای این شاسی تاییدشده نیست.';
					}
					if ( $cpu ) {
						$cpu_ids = self::id_list( $ram['compatible_cpu_ids'] ?? null );
						if ( is_array( $cpu_ids ) && ! empty( $cpu_ids ) && ! in_array( (int) $cpu['id'], $cpu_ids, false ) ) {
							$errors[] = 'رم انتخابی با پردازنده انتخابی سازگار نیست.';
						}
					}
					if ( $ram_qty < 1 || $ram_qty > (int) $chassis['max_ram_slots'] ) {
						$errors[] = "تعداد رم مجاز نیست (حداکثر {$chassis['max_ram_slots']} عدد).";
					}
				}
			}

			// 4) Optional single components.
			$optional_single = array(
				'controller' => array( 'controllers', 'کنترلر' ),
				'gpu'        => array( 'gpus', 'گرافیک' ),
				'riser2'     => array( 'risers', 'رایزر ۲' ),
				'riser3'     => array( 'risers', 'رایزر ۳' ),
				'psu'        => array( 'psus', 'پاور' ),
			);
			$fetched_optional = array();
			if ( $chassis ) {
				foreach ( $optional_single as $key => $info ) {
					if ( ! empty( $config[ $key ]['id'] ) ) {
						$row = self::fetch_row( $info[0], $config[ $key ]['id'] );
						if ( ! $row ) {
							$errors[] = "{$info[1]} انتخابی معتبر نیست.";
						} elseif ( ! self::is_compatible_with_chassis( $row['compatible_chassis_ids'] ?? null, $chassis_id ) ) {
							$errors[] = "{$info[1]} انتخابی برای این شاسی تاییدشده نیست.";
						} else {
							$fetched_optional[ $key ] = $row;
						}
					}
				}
			}

			// 4a) Optional multi components.
			$optional_arrays = array(
				'drives'        => array( 'drives', 'driveId', 'درایو' ),
				'networks'      => array( 'networks', 'networkId', 'کارت شبکه' ),
				'hbas'          => array( 'hbas', 'hbaId', 'HBA' ),
				'opticalDrives' => array( 'optical_drives', 'opticalId', 'دی‌وی‌دی درایو' ),
			);
			$fetched_arrays = array();
			if ( $chassis ) {
				foreach ( $optional_arrays as $key => $info ) {
					$fetched_arrays[ $key ] = array();
					$items = $config[ $key ] ?? array();
					if ( ! is_array( $items ) ) { continue; }
					foreach ( $items as $item ) {
						$item_id = is_array( $item ) ? ( $item[ $info[1] ] ?? $item['id'] ?? null ) : $item;
						if ( ! $item_id ) { continue; }
						$row = self::fetch_row( $info[0], $item_id );
						if ( ! $row ) {
							$errors[] = "{$info[2]} انتخابی معتبر نیست.";
						} elseif ( ! self::is_compatible_with_chassis( $row['compatible_chassis_ids'] ?? null, $chassis_id ) ) {
							$errors[] = "{$info[2]} انتخابی برای این شاسی تاییدشده نیست.";
						} else {
							$qty = is_array( $item ) ? (int) ( $item['qty'] ?? 1 ) : 1;
							$fetched_arrays[ $key ][] = array( 'row' => $row, 'qty' => max( 1, $qty ), 'item' => is_array( $item ) ? $item : array() );
						}
					}
				}
			}

			// 4b) Hardware architecture validation (same rules as front-end).
			if ( $chassis && $cpu && $ram ) {
				$storage_rules   = json_decode( (string) ( $chassis['storage_rules'] ?? '{}' ), true ) ?: array();
				$max_bays        = (int) ( $storage_rules['max_bays'] ?? 24 );
				$base_drive_type = $storage_rules['base_drive_type'] ?? null;
				$total_bays      = 0;
				$total_usable    = 0;
				$requires_hw     = false;

				foreach ( $fetched_arrays['drives'] as $drive_item ) {
					$drive = $drive_item['row'];
					$qty   = (int) $drive_item['qty'];
					$raid  = (string) ( $drive_item['item']['raid'] ?? 'none' );
					$total_bays += $qty;

					if ( $base_drive_type && $drive['form_factor'] !== $base_drive_type ) {
						$errors[] = "فرم‌فاکتور درایو {$drive['model_name']} با شاسی انتخابی سازگار نیست.";
					}
					if ( ! self::validate_raid_qty( $raid, $qty ) ) {
						$errors[] = "تعداد دیسک برای RAID {$raid} معتبر نیست.";
					}
					if ( in_array( $raid, array( '5', '6', '50', '60' ), true ) ) {
						$requires_hw = true;
					}
					$total_usable += self::raid_usable_gb( (int) $drive['capacity_gb'], $qty, $raid );
				}

				if ( $total_bays > $max_bays ) {
					$errors[] = "تعداد کل درایوها ({$total_bays}) از ظرفیت شاسی ({$max_bays}) بیشتر است.";
				}

				if ( ! empty( $target['storage'] ) && $total_usable < (int) $target['storage'] ) {
					$errors[] = 'ظرفیت قابل استفاده ذخیره‌سازی از هدف تعیین‌شده کمتر است.';
				}

				$active_controller     = $fetched_optional['controller'] ?? null;
				$active_controller_name = $active_controller['model_name'] ?? ( $chassis['default_controller'] ?? 'S100i' );
				$is_hardware_controller = preg_match( '/(P|E|MR)\d{3}/i', $active_controller_name ) === 1;
				if ( $requires_hw && ! $is_hardware_controller && empty( $config['sasExpander'] ) ) {
					$errors[] = 'برای RAID پیشرفته باید کنترلر سخت‌افزاری معتبر یا SAS Expander انتخاب شود.';
				}

				$controller_limit = $active_controller ? (int) ( $active_controller['max_drives'] ?? 8 ) : ( false !== strpos( $active_controller_name, 'S100i' ) ? 14 : 8 );
				if ( ! empty( $config['sasExpander'] ) ) { $controller_limit = 999; }
				if ( $controller_limit > 0 && $total_bays > $controller_limit ) {
					$errors[] = "تعداد درایوها از ظرفیت کنترلر فعال ({$controller_limit}) بیشتر است.";
				}

				if ( $ram_qty > ( (int) $cpu_qty * (int) ( $chassis['ram_slots_per_cpu'] ?? 12 ) ) ) {
					$errors[] = 'تعداد رم از ظرفیت اسلات‌های فعال بر اساس تعداد پردازنده بیشتر است.';
				}

				$gpu_qty = isset( $fetched_optional['gpu'] ) ? max( 1, (int) ( $config['gpuQty'] ?? 1 ) ) : 0;
				if ( ! empty( $target['gpu'] ) && $gpu_qty < 1 ) {
					$errors[] = 'بر اساس نیاز کاربر، انتخاب حداقل یک GPU الزامی است.';
				}

				if ( isset( $fetched_optional['riser3'] ) && $cpu_qty < 2 ) {
					$errors[] = 'رایزر سوم نیازمند پردازنده دوم است.';
				}

				$avail_x16   = 0;
				$avail_general = (int) ( $chassis['base_x8_slots'] ?? 0 ) + (int) ( $chassis['base_x16_slots'] ?? 0 );
				foreach ( array( 'riser2', 'riser3' ) as $riser_key ) {
					if ( ! isset( $fetched_optional[ $riser_key ] ) ) { continue; }
					if ( 'riser3' === $riser_key && $cpu_qty < 2 ) { continue; }
					$riser         = $fetched_optional[ $riser_key ];
					$x16           = (int) ( $riser['x16_slots'] ?? 0 );
					$total         = (int) ( $riser['total_slots'] ?? 0 );
					$avail_x16    += $x16;
					$avail_general += max( 0, $total - $x16 );
				}

				$req_general = ! empty( $config['sasExpander'] ) ? 1 : 0;
				if ( $active_controller && (int) ( $active_controller['pcie_slots_used'] ?? 0 ) > 0 ) { $req_general += (int) $active_controller['pcie_slots_used']; }
				foreach ( $fetched_arrays['hbas'] as $hba ) { $req_general += (int) ( $hba['row']['pcie_slots_used'] ?? 1 ) * (int) $hba['qty']; }

				$flr_count = 0;
				foreach ( $fetched_arrays['networks'] as $net ) {
					if ( ( $net['row']['form_factor'] ?? '' ) === 'FlexibleLOM' ) {
						$flr_count += (int) $net['qty'];
					} else {
						$req_general += (int) ( $net['row']['pcie_slots_used'] ?? 1 ) * (int) $net['qty'];
					}
				}
				if ( $flr_count > 1 ) {
					$errors[] = 'شاسی فقط یک جایگاه FlexibleLOM دارد.';
				}

				if ( isset( $fetched_optional['gpu'] ) ) {
					$gpu_slots_used = (int) ( $fetched_optional['gpu']['pcie_slots_used'] ?? 1 );
					if ( $gpu_qty > $avail_x16 ) {
						$errors[] = 'تعداد GPU از اسلات‌های x16 قابل استفاده بیشتر است.';
					}
					$req_general += max( 0, $gpu_slots_used - 1 ) * $gpu_qty;
				}
				$remaining_gpu_slots = max( 0, $avail_x16 - $gpu_qty );
				if ( $req_general > ( $avail_general + $remaining_gpu_slots ) ) {
					$errors[] = 'اسلات‌های PCIe برای کارت‌های جانبی کافی نیست.';
				}
			}

			if ( ! empty( $errors ) ) {
				wp_send_json( array( 'status' => 'error', 'message' => 'خطا در اعتبارسنجی پیکربندی.', 'errors' => $errors ), 400 );
			}

			// 5) Server-side power calculation.
			$total_watts = (int) ( $chassis['base_power_watts'] ?? 100 );
			$total_watts += (int) $cpu['tdp_watts'] * $cpu_qty;
			$total_watts += (int) $ram['power_consumption_watts'] * $ram_qty;
			if ( isset( $fetched_optional['gpu'] ) ) {
				$gpu_qty    = max( 1, (int) ( $config['gpuQty'] ?? 1 ) );
				$total_watts += (int) $fetched_optional['gpu']['tdp_watts'] * $gpu_qty;
			}
			foreach ( $fetched_arrays['drives'] as $d ) {
				$total_watts += (int) $d['row']['power_consumption_watts'] * $d['qty'];
			}
			foreach ( $fetched_arrays['opticalDrives'] as $o ) {
				$total_watts += (int) $o['row']['power_consumption_watts'] * $o['qty'];
			}

			if ( isset( $fetched_optional['psu'] ) ) {
				$psu_qty         = max( 1, (int) ( $config['psuQty'] ?? ( $chassis['max_psu_bays'] ?? 2 ) ) );
				$safe_watts      = (int) ceil( $total_watts * 1.20 );
				$available_watts = (int) $fetched_optional['psu']['wattage'] * $psu_qty;
				if ( $available_watts < $safe_watts ) {
					wp_send_json( array( 'status' => 'error', 'message' => 'خطا در اعتبارسنجی پیکربندی.', 'errors' => array( "توان پاور انتخابی ({$available_watts}W) کمتر از حد ایمن مورد نیاز ({$safe_watts}W) است." ) ), 400 );
				}
			}

			// 5b) Server-side price calculation.
			$total_price     = 0.0;
			$price_incomplete = false;
			$add_price        = static function ( $row, $qty = 1 ) use ( &$total_price, &$price_incomplete ) {
				if ( null === $row['price'] ) { $price_incomplete = true; return; }
				$total_price += (float) $row['price'] * $qty;
			};

			$add_price( $chassis );
			$add_price( $cpu, $cpu_qty );
			$add_price( $ram, $ram_qty );
			foreach ( array( 'controller', 'gpu', 'riser2', 'riser3', 'psu' ) as $key ) {
				if ( isset( $fetched_optional[ $key ] ) ) {
					$qty = 1;
					if ( 'gpu' === $key ) { $qty = max( 1, (int) ( $config['gpuQty'] ?? 1 ) ); }
					if ( 'psu' === $key ) { $qty = max( 1, (int) ( $config['psuQty'] ?? ( $chassis['max_psu_bays'] ?? 2 ) ) ); }
					$add_price( $fetched_optional[ $key ], $qty );
				}
			}
			foreach ( array( 'drives', 'networks', 'hbas', 'opticalDrives' ) as $key ) {
				foreach ( $fetched_arrays[ $key ] as $item ) { $add_price( $item['row'], $item['qty'] ); }
			}
			$total_price = $price_incomplete ? null : round( $total_price, 2 );

			// 6) Persist.
			$tracking_code = self::generate_tracking_code();

			$selected_components = array(
				'chassis_id'     => $chassis_id,
				'cpu'            => array( 'id' => (int) $cpu['id'], 'qty' => $cpu_qty ),
				'ram'            => array( 'id' => (int) $ram['id'], 'qty' => $ram_qty ),
				'drives'         => array_map( static fn( $d ) => array( 'id' => (int) $d['row']['id'], 'qty' => $d['qty'], 'raid' => (string) ( $d['item']['raid'] ?? 'none' ) ), $fetched_arrays['drives'] ),
				'controller'     => isset( $fetched_optional['controller'] ) ? array( 'id' => (int) $fetched_optional['controller']['id'] ) : null,
				'sas_expander'   => isset( $config['sasExpander'] ) && (bool) $config['sasExpander'],
				'gpu'            => isset( $fetched_optional['gpu'] ) ? array( 'id' => (int) $fetched_optional['gpu']['id'], 'qty' => max( 1, (int) ( $config['gpuQty'] ?? 1 ) ) ) : null,
				'networks'       => array_map( static fn( $n ) => array( 'id' => (int) $n['row']['id'], 'qty' => $n['qty'] ), $fetched_arrays['networks'] ),
				'riser2'         => isset( $fetched_optional['riser2'] ) ? array( 'id' => (int) $fetched_optional['riser2']['id'] ) : null,
				'riser3'         => isset( $fetched_optional['riser3'] ) ? array( 'id' => (int) $fetched_optional['riser3']['id'] ) : null,
				'psu'            => isset( $fetched_optional['psu'] ) ? array( 'id' => (int) $fetched_optional['psu']['id'], 'qty' => max( 1, (int) ( $config['psuQty'] ?? ( $chassis['max_psu_bays'] ?? 2 ) ) ) ) : null,
				'fan_type'       => ! empty( $config['reqHighPerfFan'] ) ? 'High Performance' : 'Standard',
				'hbas'           => array_map( static fn( $h ) => array( 'id' => (int) $h['row']['id'], 'qty' => $h['qty'] ), $fetched_arrays['hbas'] ),
				'optical_drives' => array_map( static fn( $o ) => array( 'id' => (int) $o['row']['id'], 'qty' => $o['qty'] ), $fetched_arrays['opticalDrives'] ),
			);

			$inserted = $wpdb->insert(
				Falnic_SC_Tables::table( 'requests' ),
				array(
					'session_id'          => $tracking_code,
					'user_id'             => get_current_user_id() ?: null,
					'workload_type'       => isset( $target['usecase'] ) ? sanitize_text_field( (string) $target['usecase'] ) : 'Custom',
					'form_factor_pref'    => isset( $target['formFactor'] ) ? sanitize_text_field( (string) $target['formFactor'] ) : null,
					'min_cores'           => isset( $target['cores'] ) ? (int) $target['cores'] : null,
					'min_ram_gb'          => isset( $target['ram'] ) ? (int) $target['ram'] : null,
					'min_storage_gb'      => isset( $target['storage'] ) ? (int) $target['storage'] : null,
					'selected_components' => wp_json_encode( $selected_components, JSON_UNESCAPED_UNICODE ),
					'total_power'         => $total_watts,
					'total_price'         => $total_price,
					'status'              => 'Completed',
				),
				array( '%s', '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%d', is_null( $total_price ) ? '%s' : '%f', '%s' )
			);

			if ( false === $inserted ) {
				wp_send_json( array( 'status' => 'error', 'message' => 'ثبت درخواست با خطا مواجه شد.' ), 500 );
			}

			wp_send_json( array(
				'status'           => 'success',
				'tracking_code'    => $tracking_code,
				'total_power'      => $total_watts,
				'total_price'      => $total_price,
				'price_incomplete' => $price_incomplete,
			) );
		} catch ( Exception $e ) {
			wp_send_json( array( 'status' => 'error', 'message' => 'خطای داخلی سرور.' ), 500 );
		}
	}

	/**
	 * Unique tracking code (HPE-XXXXXX), collision-checked against the table.
	 *
	 * @return string
	 */
	private static function generate_tracking_code() {
		global $wpdb;

		$table = Falnic_SC_Tables::table( 'requests' );

		for ( $attempt = 0; $attempt < 8; $attempt++ ) {
			$code = 'HPE-' . strtoupper( substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 6 ) );
			$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE session_id = %s", $code ) );
			if ( 0 === $exists ) {
				return $code;
			}
		}

		return 'HPE-' . strtoupper( wp_generate_password( 6, false, false ) );
	}

	// -------------------------------------------------------------------
	// ai_chat
	// -------------------------------------------------------------------

	/**
	 * POST: AI assistant gateway with whitelisted context only.
	 *
	 * @return void
	 */
	public static function ai_chat() {
		self::verify_nonce();

		if ( ! falnic_sc_rate_limit( 'ai_chat', (int) falnic_sc_setting( 'ai_rate_limit', 30 ) ) ) {
			wp_send_json( array( 'status' => 'error', 'message' => 'تعداد پیام‌های شما بیش از حد مجاز است؛ لطفاً کمی بعد دوباره تلاش کنید.' ), 429 );
		}

		$request = self::payload();

		$message = Falnic_SC_Ajax_Text::sanitize_text( $request['message'] ?? '', 1200 );
		$context = isset( $request['context'] ) && is_array( $request['context'] ) ? $request['context'] : array();
		$history = isset( $request['history'] ) && is_array( $request['history'] ) ? $request['history'] : array();

		if ( '' === $message ) {
			wp_send_json( array( 'status' => 'error', 'message' => 'پیام خالی است.' ), 400 );
		}
		if ( ! Falnic_SC_AI::is_configured() ) {
			wp_send_json( array( 'status' => 'error', 'message' => 'ارتباط با سرویس AI برقرار نشد یا تنظیمات آن کامل نیست.' ), 502 );
		}

		try {
			$compact = Falnic_SC_Ajax_Context::compact( $context );
			$signals = Falnic_SC_Ajax_Context::db_signals( $context );

			$system_prompt = implode( "\n", array(
				'تو دستیار تخصصی کانفیگ سرور HPE در وب‌سایت فالنیک هستی.',
				'خیلی مهم: هیچ جدول Markdown، تحلیل طولانی، thinking، مقدمه اضافه یا داده خام دیتابیس ننویس.',
				'پاسخ مشتری باید کوتاه، خوانا و عملی باشد: حداکثر ۴ بولت کوتاه، هر بولت زیر ۱۸ کلمه.',
				'همیشه با توجه به page.current_step بگو کاربر در کدام مرحله کمک خواسته و فقط همان مرحله را اولویت بده.',
				'حتماً در پایان یک سوال کوتاه از کاربر بپرس.',
				'اگر تغییر قابل اعمال وجود دارد، action امن بده؛ برای اصلاح CPU/RAM از set_cpu_ram استفاده کن. اگر مطمئن نیستی action نده.',
				'فقط بر اساس CONTEXT و DB_SIGNALS پاسخ بده؛ قیمت/موجودی/سازگاری ناموجود را حدس نزن.',
				'هیچ کلید API، مسیر سرور، SQL خام یا اطلاعات محرمانه‌ای را بازگو نکن.',
				'خروجی فقط JSON معتبر باشد؛ بدون ``` و بدون متن بیرون JSON.',
				'Schema دقیق: {"reply":"متن کوتاه با بولت‌های ساده","question":"سوال کوتاه","quick_replies":[{"label":"...","message":"...","action":{"label":"...","type":"set_cpu_ram","payload":{"cpu_id":1,"cpu_qty":2,"ram_id":5,"ram_qty":4}}}],"actions":[{"label":"...","type":"set_cpu|set_ram|set_cpu_ram|set_ram_qty|set_ram_total|set_cpu_qty|set_psu_qty|add_drive_raid10","payload":{"qty":4,"cpu_id":1,"ram_id":5}}]}',
			) );

			$user_question = ( '__context_init__' === $message )
				? 'کاربر پنجره AI را باز کرده است. با توجه به مرحله فعلی، یک خلاصه کوتاه و سوال بعدی مناسب بده.'
				: $message;

			$messages = array_merge(
				array( array( 'role' => 'system', 'content' => $system_prompt ) ),
				Falnic_SC_Ajax_Context::compact_history( $history, $message ),
				array( array(
					'role'    => 'user',
					'content' => "USER_QUESTION:\n" . $user_question . "\n\nCONTEXT:\n" . wp_json_encode( $compact, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n\nDB_SIGNALS:\n" . wp_json_encode( $signals, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				) )
			);

			$raw     = Falnic_SC_AI::chat( $messages );
			$payload = Falnic_SC_Ajax_Context::enrich_with_safe_actions(
				Falnic_SC_Ajax_Context::normalize_payload( $raw ),
				$compact,
				$signals,
				$user_question
			);

			wp_send_json( array(
				'status'        => 'success',
				'reply'         => $payload['reply'],
				'question'      => $payload['question'],
				'quick_replies' => $payload['quick_replies'],
				'actions'       => $payload['actions'],
			) );
		} catch ( Exception $e ) {
			wp_send_json( array( 'status' => 'error', 'message' => 'ارتباط با سرویس AI برقرار نشد یا تنظیمات آن کامل نیست.' ), 502 );
		}
	}
}
