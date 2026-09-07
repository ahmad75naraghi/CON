<?php
/**
 * Generic catalog CRUD engine.
 *
 * One class powers every catalog submenu (chassis, cpus, rams, ... offers):
 * searchable/sortable/paginated list + add/edit form + delete/bulk-delete,
 * all driven by the definitions in falnic-sc-catalog-defs.php and validated
 * against the real schema (nullability, defaults, unique keys).
 *
 * @package FalnicServerConfigurator
 */

defined( 'ABSPATH' ) || exit;

class Falnic_SC_CRUD {

	/**
	 * Table key.
	 *
	 * @var string
	 */
	private $key;

	/**
	 * Definition (label, fields, ...).
	 *
	 * @var array
	 */
	private $def;

	/**
	 * Physical table name.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Raw schema definition (columns with SQL defs).
	 *
	 * @var array
	 */
	private $schema;

	/**
	 * Items per page.
	 */
	const PER_PAGE = 20;

	/**
	 * Constructor.
	 *
	 * @param string $key Table key.
	 */
	public function __construct( $key ) {
		$defs    = falnic_sc_catalog_defs();
		$schema  = Falnic_SC_Tables::schema();

		if ( ! isset( $defs[ $key ], $schema[ $key ] ) ) {
			wp_die( 'تعریف جدول یافت نشد.' );
		}

		$this->key    = $key;
		$this->def    = $defs[ $key ];
		$this->schema = $schema[ $key ];
		$this->table  = Falnic_SC_Tables::table( $key );
	}

	/**
	 * Capability check.
	 *
	 * @return void
	 */
	private function check_caps() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی غیرمجاز.' );
		}
	}

	/**
	 * Main dispatcher for the admin page.
	 *
	 * @return void
	 */
	public function dispatch() {
		$this->check_caps();

		$action = isset( $_GET['crud_action'] ) ? sanitize_key( wp_unslash( $_GET['crud_action'] ) ) : 'list';

		if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			$post_action = isset( $_POST['crud_action'] ) ? sanitize_key( wp_unslash( $_POST['crud_action'] ) ) : '';
			if ( 'save' === $post_action ) {
				$this->handle_save();
				return;
			}
			if ( 'bulk' === $post_action ) {
				$this->handle_bulk();
				return;
			}
		}

		switch ( $action ) {
			case 'new':
				$this->render_form( null );
				break;
			case 'edit':
				$this->render_form( $this->get_row() );
				break;
			case 'delete':
				$this->handle_delete();
				break;
			default:
				$this->render_list();
				break;
		}
	}

	/**
	 * Page base URL (keeps current filters).
	 *
	 * @param array $args Extra query args.
	 * @return string
	 */
	private function url( array $args = array() ) {
		$base = array(
			'page' => $this->def['slug'],
		);
		return add_query_arg( array_merge( $base, $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Redirect helper.
	 *
	 * @param array $args Query args.
	 * @return void
	 */
	private function redirect( array $args ) {
		wp_safe_redirect( $this->url( $args ) );
		exit;
	}

	// -------------------------------------------------------------------
	// Reading
	// -------------------------------------------------------------------

	/**
	 * Fetch row for editing.
	 *
	 * @return array|null
	 */
	private function get_row() {
		global $wpdb;

		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( ! $id ) {
			return null;
		}

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE id = %d", $id ), ARRAY_A );
		return $row ?: null;
	}

	/**
	 * Is the column nullable (per schema)?
	 *
	 * @param string $column Column name.
	 * @return bool
	 */
	private function is_nullable( $column ) {
		$def = $this->schema['columns'][ $column ] ?? '';
		return false === strpos( $def, 'NOT NULL' );
	}

	/**
	 * Default value from the schema definition.
	 *
	 * @param string $column Column name.
	 * @return mixed
	 */
	private function schema_default( $column ) {
		$def = $this->schema['columns'][ $column ] ?? '';
		if ( preg_match( "/DEFAULT ('(?:[^'\\\\]|\\\\.|'')+'|\\d+(?:\\.\\d+)?)/", $def, $m ) ) {
			$value = $m[1];
			if ( "'" === $value[0] ) {
				return stripslashes( substr( $value, 1, -1 ) );
			}
			return false === strpos( $value, '.' ) ? (int) $value : (float) $value;
		}
		return null;
	}

	// -------------------------------------------------------------------
	// List
	// -------------------------------------------------------------------

	/**
	 * Render the list view.
	 *
	 * @return void
	 */
	private function render_list() {
		global $wpdb;

		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$orderby  = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'id';
		$order    = ( isset( $_GET['order'] ) && 'asc' === strtolower( (string) $_GET['order'] ) ) ? 'ASC' : 'DESC';
		$paged    = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
		$offset   = ( $paged - 1 ) * self::PER_PAGE;

		$sortable = array_merge( array( 'id' ), array_keys( $this->def['fields'] ) );
		if ( ! in_array( $orderby, $sortable, true ) ) {
			$orderby = 'id';
		}

		$where  = '1=1';
		$params = array();
		if ( '' !== $search && ! empty( $this->def['search'] ) ) {
			$likes = array();
			foreach ( $this->def['search'] as $column ) {
				$likes[]  = "`{$column}` LIKE %s";
				$params[] = '%' . $wpdb->esc_like( $search ) . '%';
			}
			$where = '( ' . implode( ' OR ', $likes ) . ' )';
		}

		if ( $params ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$this->table}` WHERE {$where}", $params ) );
			$sql   = "SELECT * FROM `{$this->table}` WHERE {$where} ORDER BY `{$orderby}` {$order} LIMIT %d OFFSET %d";
			$rows  = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $params, array( self::PER_PAGE, $offset ) ) ), ARRAY_A );
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$this->table}`" );
			$sql   = "SELECT * FROM `{$this->table}` ORDER BY `{$orderby}` {$order} LIMIT %d OFFSET %d";
			$rows  = (array) $wpdb->get_results( $wpdb->prepare( $sql, self::PER_PAGE, $offset ), ARRAY_A );
		}

		$list_fields = array();
		foreach ( $this->def['fields'] as $name => $field ) {
			if ( ! empty( $field['list'] ) ) {
				$list_fields[ $name ] = $field;
				if ( count( $list_fields ) >= 6 ) {
					break;
				}
			}
		}

		$pages   = (int) ceil( $total / self::PER_PAGE );
		$message = $this->list_message();
		?>
		<div class="wrap falnic-admin-wrap">
			<h1 class="falnic-admin-title">
				<?php echo esc_html( $this->def['label'] ); ?>
				<a href="<?php echo esc_url( $this->url( array( 'crud_action' => 'new' ) ) ); ?>" class="page-title-action">افزودن <?php echo esc_html( $this->def['singular'] ); ?></a>
			</h1>

			<?php if ( ! empty( $this->def['description'] ) ) : ?>
				<p class="falnic-admin-desc"><?php echo esc_html( $this->def['description'] ); ?></p>
			<?php endif; ?>

			<?php if ( $message ) : ?>
				<div class="notice notice-<?php echo esc_attr( $message['type'] ); ?> is-dismissible"><p><?php echo esc_html( $message['text'] ); ?></p></div>
			<?php endif; ?>

			<form method="get" class="falnic-search-form">
				<input type="hidden" name="page" value="<?php echo esc_attr( $this->def['slug'] ); ?>" />
				<p class="search-box">
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="جستجو..." />
					<button class="button" type="submit">جستجو</button>
				</p>
			</form>

			<form method="post" class="falnic-bulk-form">
				<?php wp_nonce_field( 'falnic_sc_crud_' . $this->key ); ?>
				<input type="hidden" name="crud_action" value="bulk" />

				<div class="tablenav top">
					<div class="alignleft actions bulkactions">
						<select name="bulk_operation">
							<option value="delete">حذف</option>
						</select>
						<button type="submit" class="button action" data-falnic-confirm="رکوردهای انتخاب‌شده حذف شوند؟">اعمال</button>
					</div>
					<div class="tablenav-pages">
						<span class="displaying-num"><?php echo esc_html( number_format_i18n( $total ) ); ?> مورد</span>
						<?php $this->pagination( $paged, $pages, $search, $orderby, $order ); ?>
					</div>
				</div>

				<table class="wp-list-table widefat fixed striped falnic-table">
					<thead>
						<tr>
							<td class="manage-column column-cb check-column"><input type="checkbox" data-falnic-check-all /></td>
							<?php $this->header_cell( 'id', 'ID', $search, $orderby, $order ); ?>
							<?php foreach ( $list_fields as $name => $field ) : ?>
								<?php $this->header_cell( $name, $field['label'], $search, $orderby, $order ); ?>
							<?php endforeach; ?>
							<th class="manage-column column-actions">عملیات</th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $rows ) ) : ?>
							<tr><td colspan="<?php echo esc_attr( count( $list_fields ) + 3 ); ?>">موردی یافت نشد.</td></tr>
						<?php else : ?>
							<?php foreach ( $rows as $row ) : ?>
								<tr>
									<th scope="row" class="check-column">
										<input type="checkbox" name="bulk_ids[]" value="<?php echo esc_attr( $row['id'] ); ?>" />
									</th>
									<td class="falnic-col-id"><?php echo esc_html( $row['id'] ); ?></td>
									<?php foreach ( $list_fields as $name => $field ) : ?>
										<td class="<?php echo ! empty( $field['ltr'] ) || 'json' === $field['type'] ? 'falnic-ltr' : ''; ?>">
											<?php $this->cell_value( $row, $name, $field ); ?>
										</td>
									<?php endforeach; ?>
									<td class="falnic-row-actions">
										<?php
										$edit_url   = wp_nonce_url( $this->url( array( 'crud_action' => 'edit', 'id' => $row['id'] ) ), 'falnic_sc_crud_' . $this->key );
										$delete_url = wp_nonce_url( $this->url( array( 'crud_action' => 'delete', 'id' => $row['id'] ) ), 'falnic_sc_crud_' . $this->key );
										?>
										<a href="<?php echo esc_url( $edit_url ); ?>">ویرایش</a> |
										<a href="<?php echo esc_url( $delete_url ); ?>" class="falnic-danger" data-falnic-confirm="«<?php echo esc_attr( $this->row_title( $row ) ); ?>» حذف شود؟">حذف</a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</form>
		</div>
		<?php
	}

	/**
	 * Sortable header cell.
	 *
	 * @param string $column  Column key.
	 * @param string $label   Label.
	 * @param string $search  Active search.
	 * @param string $orderby Active orderby.
	 * @param string $order   Active order.
	 * @return void
	 */
	private function header_cell( $column, $label, $search, $orderby, $order ) {
		$next  = ( 'ASC' === $order ) ? 'desc' : 'asc';
		$arrow = '';
		if ( $orderby === $column ) {
			$arrow = 'ASC' === $order ? ' ▲' : ' ▼';
		}
		printf(
			'<th class="manage-column column-%1$s"><a href="%2$s"><span>%3$s%4$s</span><span class="sorting-indicator"></span></a></th>',
			esc_attr( $column ),
			esc_url( $this->url( array( 's' => $search, 'orderby' => $column, 'order' => $next ) ) ),
			esc_html( $label ),
			esc_html( $arrow )
		);
	}

	/**
	 * Render one list cell.
	 *
	 * @param array  $row   Row.
	 * @param string $name  Column name.
	 * @param array  $field Field def.
	 * @return void
	 */
	private function cell_value( array $row, $name, array $field ) {
		$value = $row[ $name ] ?? '';

		if ( 'bool' === $field['type'] ) {
			echo $value ? '<span class="falnic-badge falnic-badge-green">فعال</span>' : '<span class="falnic-badge falnic-badge-gray">غیرفعال</span>';
			return;
		}

		if ( 'json' === $field['type'] ) {
			$decoded = json_decode( (string) $value, true );
			$text    = is_array( $decoded ) ? wp_json_encode( $decoded, JSON_UNESCAPED_UNICODE ) : (string) $value;
			echo '<code class="falnic-json-cell">' . esc_html( falnic_mb_truncate( $text, 60 ) ) . '</code>';
			return;
		}

		if ( 'select' === $field['type'] && isset( $field['options'][ $value ] ) ) {
			echo esc_html( $field['options'][ $value ] );
			return;
		}

		if ( 'decimal' === $field['type'] ) {
			echo esc_html( null === $value || '' === $value ? '—' : $value );
			return;
		}

		echo esc_html( falnic_mb_truncate( (string) $value, 60 ) );
	}

	/**
	 * Best display title for a row (used in confirm dialogs).
	 *
	 * @param array $row Row.
	 * @return string
	 */
	private function row_title( array $row ) {
		foreach ( array( 'title', 'model_name', 'model', 'part_number' ) as $candidate ) {
			if ( ! empty( $row[ $candidate ] ) ) {
				return (string) $row[ $candidate ];
			}
		}
		return '#' . $row['id'];
	}

	/**
	 * Pagination links.
	 *
	 * @param int    $paged   Current page.
	 * @param int    $pages   Total pages.
	 * @param string $search  Search.
	 * @param string $orderby Orderby.
	 * @param string $order   Order.
	 * @return void
	 */
	private function pagination( $paged, $pages, $search, $orderby, $order ) {
		if ( $pages <= 1 ) {
			return;
		}

		$links = array();
		for ( $i = 1; $i <= $pages; $i++ ) {
			if ( $i === $paged ) {
				$links[] = '<span class="tablenav-pages-navspan button-disabled" aria-current="page">' . esc_html( (string) $i ) . '</span>';
			} else {
				$links[] = '<a class="tablenav-pages-navspan button" href="' . esc_url( $this->url( array( 's' => $search, 'orderby' => $orderby, 'order' => $order, 'paged' => $i ) ) ) . '">' . esc_html( (string) $i ) . '</a>';
			}
		}
		echo implode( "\n", $links );
	}

	/**
	 * Flash message from redirect query args.
	 *
	 * @return array|null
	 */
	private function list_message() {
		$code = isset( $_GET['falnic_msg'] ) ? sanitize_key( wp_unslash( $_GET['falnic_msg'] ) ) : '';
		$map  = array(
			'saved'  => array( 'type' => 'success', 'text' => 'تغییرات با موفقیت ذخیره شد.' ),
			'deleted' => array( 'type' => 'success', 'text' => 'رکورد(ها) حذف شدند.' ),
		);
		return $map[ $code ] ?? null;
	}

	// -------------------------------------------------------------------
	// Form + save
	// -------------------------------------------------------------------

	/**
	 * Render add/edit form.
	 *
	 * @param array|null $row           Existing row (edit) or null (add).
	 * @param array      $extra_errors  Validation errors to display (when the
	 *                                  save handler delegates back to the form).
	 * @return void
	 */
	private function render_form( $row = null, array $extra_errors = array() ) {
		$errors = $extra_errors;
		$values = array();
		$is_post = ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['crud_action'] ) && 'save' === $_POST['crud_action'] );

		if ( $is_post ) {
			$editing_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
			$values     = isset( $_POST['falnic'] ) && is_array( $_POST['falnic'] ) ? wp_unslash( $_POST['falnic'] ) : array();
		} else {
			$editing_id = $row['id'] ?? 0;
		}

		$is_new = empty( $editing_id );
		?>
		<div class="wrap falnic-admin-wrap">
			<h1 class="falnic-admin-title">
				<?php echo $is_new ? 'افزودن' : 'ویرایش'; ?>
				<?php echo esc_html( $this->def['singular'] ); ?>
				<a href="<?php echo esc_url( $this->url() ); ?>" class="page-title-action">← بازگشت به فهرست</a>
			</h1>

			<?php foreach ( $errors as $error ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endforeach; ?>

			<form method="post" class="falnic-form">
				<?php wp_nonce_field( 'falnic_sc_crud_' . $this->key ); ?>
				<input type="hidden" name="crud_action" value="save" />
				<input type="hidden" name="id" value="<?php echo esc_attr( (string) $editing_id ); ?>" />

				<table class="form-table falnic-form-table" role="presentation">
					<?php foreach ( $this->def['fields'] as $name => $field ) : ?>
						<?php
						if ( $is_post && array_key_exists( $name, $values ) ) {
							$raw = $values[ $name ];
						} elseif ( $is_post && 'bool' === $field['type'] ) {
							$raw = 0; // unchecked checkbox missing from POST.
						} else {
							$raw = $row[ $name ] ?? ( $field['default'] ?? '' );
						}
						?>
						<tr class="<?php echo ! empty( $field['ltr'] ) ? 'falnic-ltr-row' : ''; ?>">
							<th scope="row">
								<label for="falnic-<?php echo esc_attr( $name ); ?>">
									<?php echo esc_html( $field['label'] ); ?>
									<?php if ( ! empty( $field['required'] ) ) : ?><span class="falnic-req">*</span><?php endif; ?>
								</label>
							</th>
							<td>
								<?php $this->render_field( $name, $field, $raw ); ?>
								<?php if ( ! empty( $field['help'] ) ) : ?>
									<p class="description"><?php echo esc_html( $field['help'] ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary button-large">ذخیره</button>
					<a class="button" href="<?php echo esc_url( $this->url() ); ?>">انصراف</a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render a single form field control.
	 *
	 * @param string $name  Field name.
	 * @param array  $field Field def.
	 * @param mixed  $raw   Current value.
	 * @return void
	 */
	private function render_field( $name, array $field, $raw ) {
		$id    = 'falnic-' . esc_attr( $name );
		$attrs = 'name="falnic[' . esc_attr( $name ) . ']" id="' . $id . '"';
		$value = (string) $raw;
		$ltr   = ! empty( $field['ltr'] ) ? ' dir="ltr"' : '';

		switch ( $field['type'] ) {
			case 'textarea':
				printf(
					'<textarea %s rows="%d" class="large-text code"%s>%s</textarea>',
					$attrs, // phpcs:ignore WordPress.Security.EscapeOutput
					esc_attr( $field['rows'] ?? 3 ),
					$ltr, // phpcs:ignore WordPress.Security.EscapeOutput
					esc_textarea( $value )
				);
				break;

			case 'json':
				printf(
					'<textarea %s rows="%d" class="large-text code falnic-json-input"%s spellcheck="false">%s</textarea>',
					$attrs, // phpcs:ignore WordPress.Security.EscapeOutput
					esc_attr( $field['rows'] ?? 5 ),
					$ltr, // phpcs:ignore WordPress.Security.EscapeOutput
					esc_textarea( $value )
				);
				break;

			case 'select':
				echo '<select ' . $attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
				foreach ( $field['options'] as $opt_value => $opt_label ) {
					printf(
						'<option value="%s"%s>%s</option>',
						esc_attr( $opt_value ),
						selected( (string) $opt_value, $value, false ),
						esc_html( $opt_label )
					);
				}
				echo '</select>';
				break;

			case 'bool':
				printf(
					'<input type="checkbox" %s value="1"%s /> <label for="%s">بله</label>',
					$attrs, // phpcs:ignore WordPress.Security.EscapeOutput
					checked( '1', (string) $raw, false ),
					$id
				);
				break;

			case 'number':
				printf(
					'<input type="number" %s value="%s" class="regular-text" step="any" dir="ltr" />',
					$attrs, // phpcs:ignore WordPress.Security.EscapeOutput
					esc_attr( $value )
				);
				break;

			case 'decimal':
				printf(
					'<input type="number" %s value="%s" class="regular-text" step="%s" dir="ltr" />',
					$attrs, // phpcs:ignore WordPress.Security.EscapeOutput
					esc_attr( $value ),
					esc_attr( $field['step'] ?? '0.01' )
				);
				break;

			default:
				printf(
					'<input type="text" %s value="%s" class="regular-text"%s />',
					$attrs, // phpcs:ignore WordPress.Security.EscapeOutput
					esc_attr( $value ),
					$ltr // phpcs:ignore WordPress.Security.EscapeOutput
				);
				break;
		}
	}

	/**
	 * Validate + sanitize the submitted fields.
	 *
	 * @param array $post Unslashed $_POST.
	 * @return array|WP_Error Clean data keyed by column.
	 */
	private function validate_and_sanitize( array $post ) {
		$input = isset( $post['falnic'] ) && is_array( $post['falnic'] ) ? $post['falnic'] : array();
		$clean = array();
		$errs  = array();

		foreach ( $this->def['fields'] as $name => $field ) {
			$type  = $field['type'];
			$raw   = $input[ $name ] ?? null;

			if ( 'bool' === $type ) {
				$clean[ $name ] = empty( $raw ) ? 0 : 1;
				continue;
			}

			if ( null === $raw || '' === trim( (string) $raw ) ) {
				if ( ! empty( $field['required'] ) ) {
					$default = isset( $field['default'] ) ? (string) $field['default'] : '';
					if ( '' === $default ) {
						$errs[] = sprintf( 'فیلد «%s» الزامی است.', $field['label'] );
						continue;
					}
					$raw = $default;
				} else {
					$schema_default = $this->schema_default( $name );
					$clean[ $name ] = $this->is_nullable( $name ) ? null : ( null !== $schema_default ? $schema_default : '' );
					continue;
				}
			}

			switch ( $type ) {
				case 'number':
					$clean[ $name ] = (int) $raw;
					break;
				case 'decimal':
					$clean[ $name ] = (float) $raw;
					break;
				case 'select':
					$value = sanitize_text_field( (string) $raw );
					if ( ! isset( $field['options'][ $value ] ) ) {
						$errs[] = sprintf( 'مقدار فیلد «%s» معتبر نیست.', $field['label'] );
					} else {
						$clean[ $name ] = $value;
					}
					break;
				case 'json':
					try {
						$clean[ $name ] = falnic_sc_validate_json_field( $raw, $field['label'] );
					} catch ( Exception $e ) {
						$errs[] = $e->getMessage();
					}
					break;
				case 'textarea':
					$clean[ $name ] = sanitize_textarea_field( (string) $raw );
					break;
				default:
					$clean[ $name ] = sanitize_text_field( (string) $raw );
					break;
			}
		}

		if ( $errs ) {
			return new WP_Error( 'falnic_crud_validation', implode( ' ', $errs ) );
		}

		return $clean;
	}

	/**
	 * Handle save (insert/update) with unique-key friendliness.
	 *
	 * @return void
	 */
	private function handle_save() {
		global $wpdb;

		check_admin_referer( 'falnic_sc_crud_' . $this->key );

		$id    = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$post  = isset( $_POST ) ? wp_unslash( $_POST ) : array();
		$clean = $this->validate_and_sanitize( is_array( $post ) ? $post : array() );

		if ( is_wp_error( $clean ) ) {
			$errors = array();
			foreach ( $clean->get_error_messages() as $message ) {
				$errors[] = $message;
			}
			$this->render_form( null, $errors );
			return;
		}

		// Friendly unique-key check (nicer than a raw SQL duplicate error).
		$unique_column = $this->def['unique'] ?? '';
		if ( '' !== $unique_column && isset( $clean[ $unique_column ] ) && null !== $clean[ $unique_column ] && '' !== $clean[ $unique_column ] ) {
			$dupe = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id FROM `{$this->table}` WHERE `{$unique_column}` = %s AND id != %d LIMIT 1",
					$clean[ $unique_column ],
					$id
				)
			);
			if ( $dupe ) {
				$this->render_form( null, array( sprintf( 'مقدار «%s» تکراری است؛ فیلد %s باید یکتا باشد.', $clean[ $unique_column ], $this->def['fields'][ $unique_column ]['label'] ) ) );
				return;
			}
		}

		$data    = array();
		$formats = array();
		foreach ( $clean as $name => $value ) {
			if ( 'id' === $name ) {
				continue;
			}
			$data[ $name ] = $value;
			switch ( $this->def['fields'][ $name ]['type'] ) {
				case 'number':
				case 'bool':
					$formats[] = '%d';
					break;
				case 'decimal':
					$formats[] = '%f';
					break;
				default:
					$formats[] = '%s';
					break;
			}
		}

		if ( $id ) {
			$result = $wpdb->update( $this->table, $data, array( 'id' => $id ), $formats, array( '%d' ) );
		} else {
			$result = $wpdb->insert( $this->table, $data, $formats );
		}

		if ( false === $result ) {
			wp_die( 'ذخیره‌سازی ناموفق بود: ' . esc_html( $wpdb->last_error ) );
		}

		falnic_sc_purge_data_cache();
		$this->redirect( array( 'falnic_msg' => 'saved' ) );
	}

	/**
	 * Handle single delete.
	 *
	 * @return void
	 */
	private function handle_delete() {
		global $wpdb;

		check_admin_referer( 'falnic_sc_crud_' . $this->key );

		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( $id ) {
			$wpdb->delete( $this->table, array( 'id' => $id ), array( '%d' ) );
			falnic_sc_purge_data_cache();
		}

		$this->redirect( array( 'falnic_msg' => 'deleted' ) );
	}

	/**
	 * Handle bulk delete.
	 *
	 * @return void
	 */
	private function handle_bulk() {
		global $wpdb;

		check_admin_referer( 'falnic_sc_crud_' . $this->key );

		$operation = isset( $_POST['bulk_operation'] ) ? sanitize_key( wp_unslash( $_POST['bulk_operation'] ) ) : '';
		$ids       = isset( $_POST['bulk_ids'] ) && is_array( $_POST['bulk_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['bulk_ids'] ) ) : array();

		if ( 'delete' === $operation && $ids ) {
			foreach ( $ids as $id ) {
				$wpdb->delete( $this->table, array( 'id' => $id ), array( '%d' ) );
			}
			falnic_sc_purge_data_cache();
		}

		$this->redirect( array( 'falnic_msg' => 'deleted' ) );
	}
}
