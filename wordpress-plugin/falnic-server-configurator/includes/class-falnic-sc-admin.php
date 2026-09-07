<?php
/**
 * Admin layer: menus, dashboard, requests inbox, settings and tools.
 *
 * @package FalnicServerConfigurator
 */

defined( 'ABSPATH' ) || exit;

require_once FALNIC_SC_DIR . 'includes/class-falnic-sc-install.php';
require_once FALNIC_SC_DIR . 'includes/class-falnic-sc-crud.php';

class Falnic_SC_Admin {

	/**
	 * Hook everything (admin only).
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_falnic_sc_repair_db', array( __CLASS__, 'tool_repair_db' ) );
		add_action( 'admin_post_falnic_sc_seed', array( __CLASS__, 'tool_seed' ) );
		add_action( 'admin_post_falnic_sc_purge_cache', array( __CLASS__, 'tool_purge_cache' ) );
		add_action( 'admin_post_falnic_sc_request_status', array( __CLASS__, 'handle_request_status' ) );
		add_action( 'admin_post_falnic_sc_request_delete', array( __CLASS__, 'handle_request_delete' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_notices', array( __CLASS__, 'activation_notice' ) );
	}

	/**
	 * Register the top-level menu and all submenus.
	 *
	 * @return void
	 */
	public static function register_menus() {
		add_menu_page(
			'کانفیگوراتور سرور',
			'کانفیگوراتور سرور',
			'manage_options',
			'falnic-sc',
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-server',
			58
		);

		add_submenu_page(
			'falnic-sc',
			'پیشخوان کانفیگوراتور',
			'پیشخوان',
			'manage_options',
			'falnic-sc',
			array( __CLASS__, 'render_dashboard' )
		);

		add_submenu_page(
			'falnic-sc',
			'درخواست‌های پیش‌فاکتور',
			'درخواست‌های پیش‌فاکتور',
			'manage_options',
			'falnic-sc-requests',
			array( __CLASS__, 'render_requests' )
		);

		foreach ( falnic_sc_catalog_defs() as $key => $def ) {
			add_submenu_page(
				'falnic-sc',
				$def['label'],
				$def['label'],
				'manage_options',
				$def['slug'],
				static function () use ( $key ) {
					( new Falnic_SC_CRUD( $key ) )->dispatch();
				}
			);
		}

		add_submenu_page(
			'falnic-sc',
			'تنظیمات کانفیگوراتور',
			'تنظیمات',
			'manage_options',
			'falnic-sc-settings',
			array( __CLASS__, 'render_settings' )
		);
	}

	/**
	 * Enqueue admin CSS/JS only on plugin screens.
	 *
	 * @param string $hook Current screen hook.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		if ( false === strpos( (string) $hook, 'falnic-sc' ) ) {
			return;
		}

		wp_enqueue_style(
			'falnic-sc-admin',
			FALNIC_SC_URL . 'admin/css/admin.css',
			array(),
			(string) filemtime( FALNIC_SC_DIR . 'admin/css/admin.css' )
		);
		wp_enqueue_script(
			'falnic-sc-admin',
			FALNIC_SC_URL . 'admin/js/admin.js',
			array(),
			(string) filemtime( FALNIC_SC_DIR . 'admin/js/admin.js' ),
			true
		);
	}

	/**
	 * Show the activation/schema report once after activation.
	 *
	 * @return void
	 */
	public static function activation_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$report = get_transient( 'falnic_sc_install_report' );
		if ( ! is_array( $report ) ) {
			return;
		}
		delete_transient( 'falnic_sc_install_report' );

		$created = ! empty( $report['created_tables'] ) ? $report['created_tables'] : array();
		$altered = array_merge(
			(array) $report['added_columns'],
			(array) $report['added_indexes']
		);
		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<strong>کانفیگوراتور سرور فالنیک:</strong>
				بررسی ساختار دیتابیس انجام شد؛
				<?php if ( $created ) : ?>
					جدول‌های <?php echo esc_html( implode( '، ', $created ) ); ?> ساخته شدند.
				<?php else : ?>
					همه جدول‌ها و ستون‌ها موجود و سالم بودند.
				<?php endif; ?>
				<?php if ( $altered ) : ?>
					(<?php echo esc_html( count( $altered ) ); ?> مورد ستون/ایندکس تکمیل شد.)
				<?php endif; ?>
			</p>
		</div>
		<?php
	}

	// -------------------------------------------------------------------
	// Dashboard
	// -------------------------------------------------------------------

	/**
	 * Dashboard screen.
	 *
	 * @return void
	 */
	public static function render_dashboard() {
		global $wpdb;

		$verify  = Falnic_SC_Installer::verify();
		$healthy = true;
		foreach ( $verify as $entry ) {
			if ( ! $entry['healthy'] ) {
				$healthy = false;
				break;
			}
		}

		$requests_table = Falnic_SC_Tables::table( 'requests' );
		$requests_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$requests_table}`" );
		$requests_new   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$requests_table}` WHERE status = %s", 'Completed' ) );

		$last_check = (int) get_option( 'falnic_sc_last_schema_check', 0 );

		$repair_url = wp_nonce_url( admin_url( 'admin-post.php?action=falnic_sc_repair_db' ), 'falnic_sc_tool' );
		$seed_url   = wp_nonce_url( admin_url( 'admin-post.php?action=falnic_sc_seed' ), 'falnic_sc_tool' );
		$cache_url  = wp_nonce_url( admin_url( 'admin-post.php?action=falnic_sc_purge_cache' ), 'falnic_sc_tool' );
		?>
		<div class="wrap falnic-admin-wrap">
			<h1 class="falnic-admin-title">پیشخوان کانفیگوراتور سرور</h1>

			<div class="falnic-hero">
				<div class="falnic-hero-main">
					<h2>شورت‌کد کانفیگوراتور</h2>
					<p>شورت‌کد زیر را در هر برگه یا نوشته‌ای قرار دهید؛ اپ کامل کانفیگوراتور فقط در همان صفحه اجرا می‌شود و فایل CSS/JS آن در هیچ صفحه دیگری بارگذاری نمی‌شود.</p>
					<div class="falnic-shortcode-box">
						<code dir="ltr">[falnic_server_configurator]</code>
						<button type="button" class="button" data-falnic-copy>[falnic_server_configurator]</button>
					</div>
					<p class="description">معادل PHP: <code dir="ltr">&lt;?php echo do_shortcode( '[falnic_server_configurator]' ); ?&gt;</code></p>
				</div>
				<div class="falnic-hero-side">
					<div class="falnic-stat">
						<span class="falnic-stat-num"><?php echo esc_html( number_format_i18n( $requests_total ) ); ?></span>
						<span class="falnic-stat-label">کل درخواست‌ها</span>
					</div>
					<div class="falnic-stat">
						<span class="falnic-stat-num"><?php echo esc_html( number_format_i18n( $requests_new ) ); ?></span>
						<span class="falnic-stat-label">ثبت‌شده (Completed)</span>
					</div>
				</div>
			</div>

			<div class="falnic-tools">
				<h2>ابزارهای دیتابیس</h2>
				<p class="description">
					وضعیت ساختار:
					<?php if ( $healthy ) : ?>
						<span class="falnic-badge falnic-badge-green">همه جدول‌ها سالم</span>
					<?php else : ?>
						<span class="falnic-badge falnic-badge-red">نیازمند بازسازی</span>
					<?php endif; ?>
					<?php if ( $last_check ) : ?>
						— آخرین بررسی: <?php echo esc_html( date_i18n( 'Y/m/d H:i', $last_check ) ); ?>
					<?php endif; ?>
				</p>
				<p>
					<a href="<?php echo esc_url( $repair_url ); ?>" class="button button-primary" data-falnic-confirm="ساختار دیتابیس بررسی و موارد ناقص بازسازی شوند؟">بررسی و بازسازی ساختار</a>
					<a href="<?php echo esc_url( $seed_url ); ?>" class="button" data-falnic-confirm="داده‌های اولیه کاتالوگ فقط در جدول‌های خالی درج شوند؟">ورود داده‌های اولیه (جدول‌های خالی)</a>
					<a href="<?php echo esc_url( $cache_url ); ?>" class="button">پاک کردن کش قطعات</a>
				</p>
			</div>

			<h2>سلامت جداول دیتابیس</h2>
			<table class="wp-list-table widefat fixed striped falnic-table">
				<thead>
					<tr>
						<th>جدول</th>
						<th>وضعیت</th>
						<th>ستون‌ها</th>
						<th>ایندکس‌ها</th>
						<th>رکوردها</th>
						<th>مدیریت</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $verify as $key => $entry ) : ?>
						<tr>
							<td dir="ltr"><code><?php echo esc_html( $entry['name'] ); ?></code></td>
							<td>
								<?php if ( ! $entry['exists'] ) : ?>
									<span class="falnic-badge falnic-badge-red">ناموجود</span>
								<?php elseif ( $entry['healthy'] ) : ?>
									<span class="falnic-badge falnic-badge-green">سالم</span>
								<?php else : ?>
									<span class="falnic-badge falnic-badge-red">ناقص</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( empty( $entry['missing_columns'] ) ) : ?>
									<span class="falnic-badge falnic-badge-green">کامل</span>
								<?php else : ?>
									<span class="falnic-badge falnic-badge-red" title="<?php echo esc_attr( implode( ', ', $entry['missing_columns'] ) ); ?>">ناقص</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( empty( $entry['missing_indexes'] ) ) : ?>
									<span class="falnic-badge falnic-badge-green">کامل</span>
								<?php else : ?>
									<span class="falnic-badge falnic-badge-red" title="<?php echo esc_attr( implode( ', ', $entry['missing_indexes'] ) ); ?>">ناقص</span>
								<?php endif; ?>
							</td>
							<td><?php echo null === $entry['rows'] ? '—' : esc_html( number_format_i18n( $entry['rows'] ) ); ?></td>
							<td>
								<?php
								$defs     = falnic_sc_catalog_defs();
								$edit_url = isset( $defs[ $key ] ) ? admin_url( 'admin.php?page=' . $defs[ $key ]['slug'] ) : admin_url( 'admin.php?page=falnic-sc-requests' );
								?>
								<a href="<?php echo esc_url( $edit_url ); ?>">مدیریت</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	// -------------------------------------------------------------------
	// Tools (admin-post handlers)
	// -------------------------------------------------------------------

	/**
	 * Tool: re-run schema verification + repair.
	 *
	 * @return void
	 */
	public static function tool_repair_db() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی غیرمجاز.' );
		}
		check_admin_referer( 'falnic_sc_tool' );

		Falnic_SC_Installer::install( false ); // stores its own report transient.

		wp_safe_redirect( admin_url( 'admin.php?page=falnic-sc' ) );
		exit;
	}

	/**
	 * Tool: seed empty catalog tables.
	 *
	 * @return void
	 */
	public static function tool_seed() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی غیرمجاز.' );
		}
		check_admin_referer( 'falnic_sc_tool' );

		Falnic_SC_Installer::seed_catalog();

		wp_safe_redirect( admin_url( 'admin.php?page=falnic-sc' ) );
		exit;
	}

	/**
	 * Tool: purge the component cache.
	 *
	 * @return void
	 */
	public static function tool_purge_cache() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی غیرمجاز.' );
		}
		check_admin_referer( 'falnic_sc_tool' );

		falnic_sc_purge_data_cache();

		wp_safe_redirect( admin_url( 'admin.php?page=falnic-sc' ) );
		exit;
	}

	// -------------------------------------------------------------------
	// Requests (User_Configurations)
	// -------------------------------------------------------------------

	/**
	 * Requests inbox screen.
	 *
	 * @return void
	 */
	public static function render_requests() {
		global $wpdb;

		$table = Falnic_SC_Tables::table( 'requests' );
		$view  = isset( $_GET['view'] ) ? absint( $_GET['view'] ) : 0;

		if ( $view ) {
			self::render_request_detail( $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $view ), ARRAY_A ) );
			return;
		}

		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged  = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
		$per_page = 20;
		$offset = ( $paged - 1 ) * $per_page;

		$where  = '1=1';
		$params = array();
		if ( '' !== $search ) {
			$where    = '( session_id LIKE %s OR workload_type LIKE %s )';
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$params   = array( $like, $like );
		}

		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE {$where}", $params ) );
		$rows  = (array) $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d", array_merge( $params, array( $per_page, $offset ) ) ),
			ARRAY_A
		);
		$pages = (int) ceil( $total / $per_page );

		$status_labels = array(
			'Draft'     => 'پیش‌نویس',
			'Completed' => 'ثبت‌شده',
			'Ordered'   => 'سفارش‌شده',
		);
		?>
		<div class="wrap falnic-admin-wrap">
			<h1 class="falnic-admin-title">درخواست‌های پیش‌فاکتور</h1>

			<form method="get" class="falnic-search-form">
				<input type="hidden" name="page" value="falnic-sc-requests" />
				<p class="search-box">
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="کد رهگیری یا نوع بار کاری..." />
					<button class="button" type="submit">جستجو</button>
				</p>
			</form>

			<table class="wp-list-table widefat fixed striped falnic-table">
				<thead>
					<tr>
						<th>ID</th>
						<th>کد رهگیری</th>
						<th>بار کاری</th>
						<th>حداقلها (Core/RAM/Storage)</th>
						<th>توان</th>
						<th>قیمت</th>
						<th>وضعیت</th>
						<th>تاریخ</th>
						<th>عملیات</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="9">درخواستی یافت نشد.</td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['id'] ); ?></td>
								<td dir="ltr"><code><?php echo esc_html( $row['session_id'] ); ?></code></td>
								<td><?php echo esc_html( $row['workload_type'] ?: '—' ); ?></td>
								<td dir="ltr"><?php echo esc_html( sprintf( '%s / %s / %s', $row['min_cores'] ?? '—', $row['min_ram_gb'] ?? '—', $row['min_storage_gb'] ?? '—' ) ); ?></td>
								<td><?php echo esc_html( $row['total_power'] ); ?> W</td>
								<td><?php echo esc_html( null === $row['total_price'] ? '—' : $row['total_price'] ); ?></td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="falnic-inline-form">
										<?php wp_nonce_field( 'falnic_sc_request_' . $row['id'] ); ?>
										<input type="hidden" name="action" value="falnic_sc_request_status" />
										<input type="hidden" name="id" value="<?php echo esc_attr( $row['id'] ); ?>" />
										<select name="status">
											<?php foreach ( $status_labels as $value => $label ) : ?>
												<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $row['status'] ); ?>><?php echo esc_html( $label ); ?></option>
											<?php endforeach; ?>
										</select>
										<button class="button button-small" type="submit">ثبت</button>
									</form>
								</td>
								<td><?php echo esc_html( mysql2date( 'Y/m/d H:i', $row['created_at'] ) ); ?></td>
								<td class="falnic-row-actions">
									<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'falnic-sc-requests', 'view' => $row['id'] ), admin_url( 'admin.php' ) ) ); ?>">مشاهده</a> |
									<a class="falnic-danger" data-falnic-confirm="درخواست <?php echo esc_attr( $row['session_id'] ); ?> حذف شود؟" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=falnic_sc_request_delete&id=' . $row['id'] ), 'falnic_sc_request_' . $row['id'] ) ); ?>">حذف</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $pages > 1 ) : ?>
				<div class="tablenav"><div class="tablenav-pages">
					<?php for ( $i = 1; $i <= $pages; $i++ ) : ?>
						<?php if ( $i === $paged ) : ?>
							<span class="tablenav-pages-navspan button-disabled"><?php echo esc_html( (string) $i ); ?></span>
						<?php else : ?>
							<a class="tablenav-pages-navspan button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'falnic-sc-requests', 's' => $search, 'paged' => $i ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( (string) $i ); ?></a>
						<?php endif; ?>
					<?php endfor; ?>
				</div></div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Single request detail view.
	 *
	 * @param array|null $row Request row.
	 * @return void
	 */
	private static function render_request_detail( $row ) {
		if ( ! $row ) {
			echo '<div class="wrap falnic-admin-wrap"><div class="notice notice-error"><p>درخواست یافت نشد.</p></div></div>';
			return;
		}

		$components = json_decode( (string) $row['selected_components'], true );
		?>
		<div class="wrap falnic-admin-wrap">
			<h1 class="falnic-admin-title">
				درخواست <?php echo esc_html( $row['session_id'] ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=falnic-sc-requests' ) ); ?>" class="page-title-action">← بازگشت به فهرست</a>
			</h1>

			<table class="form-table falnic-form-table" role="presentation">
				<tr><th>کد رهگیری</th><td dir="ltr"><code><?php echo esc_html( $row['session_id'] ); ?></code></td></tr>
				<tr><th>کاربر وردپرس</th><td><?php echo $row['user_id'] ? esc_html( (string) $row['user_id'] ) : 'مهمان'; ?></td></tr>
				<tr><th>بار کاری</th><td><?php echo esc_html( $row['workload_type'] ?: '—' ); ?></td></tr>
				<tr><th>حداقلها</th><td dir="ltr"><?php echo esc_html( sprintf( 'Cores: %s — RAM: %s GB — Storage: %s GB', $row['min_cores'] ?: '—', $row['min_ram_gb'] ?: '—', $row['min_storage_gb'] ?: '—' ) ); ?></td></tr>
				<tr><th>توان کل</th><td><?php echo esc_html( $row['total_power'] ); ?> وات</td></tr>
				<tr><th>قیمت کل</th><td><?php echo esc_html( null === $row['total_price'] ? 'قیمت‌گذاری نشده' : $row['total_price'] ); ?></td></tr>
				<tr><th>وضعیت</th><td><?php echo esc_html( $row['status'] ); ?></td></tr>
				<tr><th>تاریخ ایجاد</th><td><?php echo esc_html( mysql2date( 'Y/m/d H:i:s', $row['created_at'] ) ); ?></td></tr>
			</table>

			<h2>قطعات انتخاب‌شده</h2>
			<?php if ( empty( $components ) ) : ?>
				<p>—</p>
			<?php else : ?>
				<pre dir="ltr" class="falnic-json-view"><?php echo esc_html( wp_json_encode( $components, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) ); ?></pre>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handle status change for a request.
	 *
	 * @return void
	 */
	public static function handle_request_status() {
		global $wpdb;

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی غیرمجاز.' );
		}

		$id     = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';

		check_admin_referer( 'falnic_sc_request_' . $id );

		// sanitize_key() lowercases, so match case-insensitively and store the
		// canonical label.
		$allowed_statuses = array(
			'draft'     => 'Draft',
			'completed' => 'Completed',
			'ordered'   => 'Ordered',
		);
		if ( $id && isset( $allowed_statuses[ $status ] ) ) {
			$wpdb->update(
				Falnic_SC_Tables::table( 'requests' ),
				array( 'status' => $allowed_statuses[ $status ] ),
				array( 'id' => $id ),
				array( '%s' ),
				array( '%d' )
			);
		}

		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=falnic-sc-requests' ) );
		exit;
	}

	/**
	 * Handle request deletion.
	 *
	 * @return void
	 */
	public static function handle_request_delete() {
		global $wpdb;

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی غیرمجاز.' );
		}

		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'falnic_sc_request_' . $id );

		if ( $id ) {
			$wpdb->delete( Falnic_SC_Tables::table( 'requests' ), array( 'id' => $id ), array( '%d' ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=falnic-sc-requests' ) );
		exit;
	}

	// -------------------------------------------------------------------
	// Settings
	// -------------------------------------------------------------------

	/**
	 * Register the settings + fields.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			'falnic_sc_settings',
			'falnic_sc_settings',
			array( 'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ) )
		);
	}

	/**
	 * Sanitize the settings payload.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize_settings( $input ) {
		$old     = falnic_sc_settings();
		$clean   = falnic_sc_default_settings();
		$input   = is_array( $input ) ? $input : array();

		$clean['ai_enabled']    = empty( $input['ai_enabled'] ) ? 0 : 1;
		$clean['ai_endpoint']   = isset( $input['ai_endpoint'] ) ? esc_url_raw( trim( (string) $input['ai_endpoint'] ) ) : '';
		$clean['ai_api_key']    = isset( $input['ai_api_key'] ) ? trim( (string) $input['ai_api_key'] ) : '';
		if ( '' === $clean['ai_api_key'] ) {
			$clean['ai_api_key'] = (string) $old['ai_api_key']; // keep stored key when left blank
		}
		$clean['ai_model']      = isset( $input['ai_model'] ) ? sanitize_text_field( trim( (string) $input['ai_model'] ) ) : $clean['ai_model'];
		$clean['ai_timeout']    = max( 5, min( 120, (int) ( $input['ai_timeout'] ?? 25 ) ) );
		$clean['ai_max_tokens'] = max( 64, min( 4096, (int) ( $input['ai_max_tokens'] ?? 900 ) ) );
		$clean['ai_temperature'] = max( 0, min( 2, (float) ( $input['ai_temperature'] ?? 0.25 ) ) );

		$clean['cache_ttl']         = max( 0, min( 86400, (int) ( $input['cache_ttl'] ?? 3600 ) ) );
		$clean['ai_rate_limit']     = max( 0, min( 1000, (int) ( $input['ai_rate_limit'] ?? 30 ) ) );
		$clean['submit_rate_limit'] = max( 0, min( 1000, (int) ( $input['submit_rate_limit'] ?? 20 ) ) );

		$clean['delete_data_on_uninstall'] = empty( $input['delete_data_on_uninstall'] ) ? 0 : 1;

		falnic_sc_purge_data_cache();

		return $clean;
	}

	/**
	 * Settings screen.
	 *
	 * @return void
	 */
	public static function render_settings() {
		$settings = falnic_sc_settings();
		?>
		<div class="wrap falnic-admin-wrap">
			<h1 class="falnic-admin-title">تنظیمات کانفیگوراتور سرور</h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'falnic_sc_settings' ); ?>

				<h2 class="falnic-settings-section">دستیار هوشمند (AI)</h2>
				<table class="form-table falnic-form-table" role="presentation">
					<tr>
						<th scope="row"><label for="falnic-ai-enabled">فعال‌سازی دستیار هوشمند</label></th>
						<td>
							<label>
								<input type="checkbox" name="falnic_sc_settings[ai_enabled]" id="falnic-ai-enabled" value="1" <?php checked( 1, (int) $settings['ai_enabled'] ); ?> />
								نمایش دکمه «راهنمایی هوشمند» و اتصال به سرویس AI
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="falnic-ai-endpoint">آدرس Endpoint سرویس AI</label></th>
						<td>
							<input type="url" class="regular-text" dir="ltr" name="falnic_sc_settings[ai_endpoint]" id="falnic-ai-endpoint" value="<?php echo esc_attr( $settings['ai_endpoint'] ); ?>" placeholder="https://api.example.com/v1/chat/completions" />
							<p class="description">سرویس سازگار با OpenAI (Chat Completions).</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="falnic-ai-key">کلید API</label></th>
						<td>
							<input type="password" class="regular-text" dir="ltr" autocomplete="new-password" name="falnic_sc_settings[ai_api_key]" id="falnic-ai-key" value="" placeholder="<?php echo esc_attr( '' !== $settings['ai_api_key'] ? '•••••••••••• (ذخیره شده — برای تغییر وارد کنید)' : '' ); ?>" />
							<p class="description">کلید فقط در دیتابیس وردپرس ذخیره می‌شود و هرگز به مرورگر ارسال نمی‌شود.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="falnic-ai-model">مدل</label></th>
						<td><input type="text" class="regular-text" dir="ltr" name="falnic_sc_settings[ai_model]" id="falnic-ai-model" value="<?php echo esc_attr( $settings['ai_model'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="falnic-ai-timeout">Timeout (ثانیه)</label></th>
						<td><input type="number" min="5" max="120" dir="ltr" name="falnic_sc_settings[ai_timeout]" id="falnic-ai-timeout" value="<?php echo esc_attr( (string) $settings['ai_timeout'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="falnic-ai-tokens">حداکثر توکن پاسخ</label></th>
						<td><input type="number" min="64" max="4096" dir="ltr" name="falnic_sc_settings[ai_max_tokens]" id="falnic-ai-tokens" value="<?php echo esc_attr( (string) $settings['ai_max_tokens'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="falnic-ai-temp">Temperature</label></th>
						<td><input type="number" min="0" max="2" step="0.05" dir="ltr" name="falnic_sc_settings[ai_temperature]" id="falnic-ai-temp" value="<?php echo esc_attr( (string) $settings['ai_temperature'] ); ?>" /></td>
					</tr>
				</table>

				<h2 class="falnic-settings-section">کارایی</h2>
				<table class="form-table falnic-form-table" role="presentation">
					<tr>
						<th scope="row"><label for="falnic-cache-ttl">مدت کش داده قطعات (ثانیه)</label></th>
						<td>
							<input type="number" min="0" max="86400" dir="ltr" name="falnic_sc_settings[cache_ttl]" id="falnic-cache-ttl" value="<?php echo esc_attr( (string) $settings['cache_ttl'] ); ?>" />
							<p class="description">پاسخ get_data کش می‌شود و با هر تغییر در پنل مدیریت به‌صورت خودکار پاک می‌شود. 0 = بدون کش.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="falnic-ai-rate">سقف پیام‌های AI در ساعت (هر IP)</label></th>
						<td><input type="number" min="0" max="1000" dir="ltr" name="falnic_sc_settings[ai_rate_limit]" id="falnic-ai-rate" value="<?php echo esc_attr( (string) $settings['ai_rate_limit'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="falnic-submit-rate">سقف ثبت پیش‌فاکتور در ساعت (هر IP)</label></th>
						<td><input type="number" min="0" max="1000" dir="ltr" name="falnic_sc_settings[submit_rate_limit]" id="falnic-submit-rate" value="<?php echo esc_attr( (string) $settings['submit_rate_limit'] ); ?>" /></td>
					</tr>
				</table>

				<h2 class="falnic-settings-section">داده‌ها</h2>
				<table class="form-table falnic-form-table" role="presentation">
					<tr>
						<th scope="row"><label for="falnic-uninstall">حذف کامل داده‌ها هنگام حذف پلاگین</label></th>
						<td>
							<label>
								<input type="checkbox" name="falnic_sc_settings[delete_data_on_uninstall]" id="falnic-uninstall" value="1" <?php checked( 1, (int) $settings['delete_data_on_uninstall'] ); ?> />
								جدول‌های کاتالوگ و درخواست‌ها هنگام Uninstall حذف شوند (غیرقابل بازگشت)
							</label>
							<p class="description">در حالت پیش‌فرض داده‌ها هنگام حذف پلاگین حفظ می‌شوند.</p>
						</td>
					</tr>
				</table>

				<?php submit_button( 'ذخیره تنظیمات' ); ?>
			</form>
		</div>
		<?php
	}
}
