<?php
/**
 * Schema installer / verifier.
 *
 * On plugin activation (and on every version upgrade, plus on demand from the
 * admin tools screen) this class walks the full schema definition and:
 *
 *   1. Verifies every table exists          → creates missing tables.
 *   2. Verifies every expected column exists → adds missing columns.
 *   3. Verifies primary/unique/normal keys   → adds missing indexes.
 *   4. Verifies `id` is AUTO_INCREMENT       → repairs when needed.
 *   5. Seeds empty catalog tables with the original data.
 *
 * The structure is byte-for-byte the one shipped in
 * falnicc1_server_configurator.sql, so the plugin and the standalone app can
 * share the same database without any code-level differences.
 *
 * @package FalnicServerConfigurator
 */

defined( 'ABSPATH' ) || exit;

require_once FALNIC_SC_DIR . 'includes/class-falnic-sc-tables.php';
require_once FALNIC_SC_DIR . 'includes/falnic-sc-functions.php';

class Falnic_SC_Installer {

	/**
	 * Run the full verification + repair + (optionally) seed routine.
	 *
	 * @param bool $seed Whether to seed empty catalog tables.
	 * @return array Report: status per table + created/altered items.
	 */
	public static function install( $seed = true ) {
		global $wpdb;

		$report = array(
			'created_tables'  => array(),
			'added_columns'   => array(),
			'added_indexes'   => array(),
			'repaired_ai'     => array(),
			'seeded'          => array(),
			'errors'          => array(),
			'tables_status'   => array(),
		);

		@set_time_limit( 300 );

		foreach ( Falnic_SC_Tables::schema() as $key => $def ) {
			$table = Falnic_SC_Tables::table( $key );

			// -------------------------------------------------------------
			// 1) Table existence
			// -------------------------------------------------------------
			$exists = self::table_exists( $table );
			if ( ! $exists ) {
				$created = self::create_table( $table, $def );
				if ( $created ) {
					$report['created_tables'][] = $table;
					$report['tables_status'][ $table ] = 'created';
				} else {
					$report['errors'][] = sprintf( 'ساخت جدول %s ناموفق بود: %s', $table, $wpdb->last_error );
					$report['tables_status'][ $table ] = 'error';
					continue;
				}
			} else {
				$report['tables_status'][ $table ] = 'existing';
			}

			// -------------------------------------------------------------
			// 2) Column verification / repair
			// -------------------------------------------------------------
			$existing_columns = self::existing_columns( $table );
			if ( null === $existing_columns ) {
				$report['errors'][] = sprintf( 'خواندن ستون‌های جدول %s ناموفق بود: %s', $table, $wpdb->last_error );
				continue;
			}

			foreach ( $def['columns'] as $column => $column_def ) {
				if ( in_array( $column, $existing_columns, true ) ) {
					continue;
				}
				// `id` is added without AUTO_INCREMENT first; repaired below.
				$add_def = ( 'id' === $column ) ? '`id` int(11) NOT NULL' : $column_def;
				$sql     = "ALTER TABLE `{$table}` ADD COLUMN {$add_def}";
				if ( false === self::query( $sql ) ) {
					// Retry without CHECK constraints (older servers).
					$retry = self::strip_check_constraints( $sql );
					if ( $retry !== $sql ) {
						$retry_ok = self::query( $retry );
					} else {
						$retry_ok = false;
					}
					if ( ! $retry_ok ) {
						$report['errors'][] = sprintf( 'افزودن ستون %s.%s ناموفق بود: %s', $table, $column, $wpdb->last_error );
						continue;
					}
				}
				$report['added_columns'][] = "{$table}.{$column}";
			}

			// -------------------------------------------------------------
			// 3) Index verification / repair
			// -------------------------------------------------------------
			$existing_indexes = self::existing_index_names( $table );
			if ( null !== $existing_indexes ) {
				foreach ( $def['indexes'] as $name => $index_def ) {
					$physical = ( 'PRIMARY' === $name ) ? 'PRIMARY' : $name;
					if ( in_array( $physical, $existing_indexes, true ) ) {
						continue;
					}
					$sql = "ALTER TABLE `{$table}` ADD {$index_def}";
					if ( false === self::query( $sql ) ) {
						$report['errors'][] = sprintf( 'افزودن ایندکس %s روی %s ناموفق بود: %s', $name, $table, $wpdb->last_error );
						continue;
					}
					$report['added_indexes'][] = "{$table}.{$name}";
				}
			}

			// -------------------------------------------------------------
			// 4) AUTO_INCREMENT on primary key
			// -------------------------------------------------------------
			$id_info = self::column_info( $table, 'id' );
			if ( $id_info && false === stripos( (string) $id_info['Extra'], 'auto_increment' ) ) {
				$type = isset( $id_info['Type'] ) ? $id_info['Type'] : 'int(11)';
				if ( false !== stripos( $type, 'int' ) ) {
					if ( false !== self::query( "ALTER TABLE `{$table}` MODIFY `id` {$type} NOT NULL AUTO_INCREMENT" ) ) {
						$report['repaired_ai'][] = $table;
					}
				}
			}
		}

		// -----------------------------------------------------------------
		// 5) Seed empty catalog tables
		// -----------------------------------------------------------------
		if ( $seed ) {
			$seed_report     = self::seed_catalog();
			$report['seeded'] = $seed_report['seeded'];
			foreach ( $seed_report['errors'] as $err ) {
				$report['errors'][] = $err;
			}
		}

		update_option( 'falnic_sc_db_version', FALNIC_SC_DB_VERSION, false );
		update_option( 'falnic_sc_last_schema_check', time(), false );
		set_transient( 'falnic_sc_install_report', $report, HOUR_IN_SECONDS );

		return $report;
	}

	/**
	 * Read-only schema verification (no writes) — used by the dashboard.
	 *
	 * @return array Per-table health snapshot.
	 */
	public static function verify() {
		global $wpdb;

		$status = array();

		foreach ( Falnic_SC_Tables::schema() as $key => $def ) {
			$table   = Falnic_SC_Tables::table( $key );
			$entry   = array(
				'key'             => $key,
				'name'            => $table,
				'exists'          => false,
				'missing_columns' => array(),
				'missing_indexes' => array(),
				'rows'            => null,
				'healthy'         => false,
			);

			if ( self::table_exists( $table ) ) {
				$entry['exists'] = true;

				$existing_columns = self::existing_columns( $table );
				if ( null !== $existing_columns ) {
					$entry['missing_columns'] = array_values( array_diff( array_keys( $def['columns'] ), $existing_columns ) );
				}

				$existing_indexes = self::existing_index_names( $table );
				if ( null !== $existing_indexes ) {
					$missing = array();
					foreach ( array_keys( $def['indexes'] ) as $name ) {
						$physical = ( 'PRIMARY' === $name ) ? 'PRIMARY' : $name;
						if ( ! in_array( $physical, $existing_indexes, true ) ) {
							$missing[] = $name;
						}
					}
					$entry['missing_indexes'] = $missing;
				}

				$entry['rows']    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
				$entry['healthy'] = $entry['exists'] && empty( $entry['missing_columns'] ) && empty( $entry['missing_indexes'] );
			}

			$status[ $key ] = $entry;
		}

		return $status;
	}

	/**
	 * Seed catalog tables that are empty. Existing data is never touched.
	 *
	 * @param bool $force Re-seed offers table even when not empty (default false).
	 * @return array Report.
	 */
	public static function seed_catalog( $force = false ) {
		global $wpdb;

		$report = array(
			'seeded' => array(),
			'errors' => array(),
		);

		$file = FALNIC_SC_DIR . 'seed/catalog-seed.sql';
		if ( ! is_readable( $file ) ) {
			$report['errors'][] = 'فایل seed/catalog-seed.sql پیدا نشد.';
			return $report;
		}

		$statements = self::parse_seed_file( $file );
		if ( empty( $statements ) ) {
			$report['errors'][] = 'فایل seed خالی است.';
			return $report;
		}

		$name_to_key = array();
		foreach ( Falnic_SC_Tables::tables() as $key => $name ) {
			$name_to_key[ $name ] = $key;
		}

		foreach ( $statements as $group ) {
			$table = $group['table'];
			if ( ! isset( $name_to_key[ $table ] ) ) {
				continue;
			}

			$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
			if ( $count > 0 && ! ( $force && 'offers' === $name_to_key[ $table ] ) ) {
				continue;
			}

			$applied = 0;
			foreach ( $group['statements'] as $sql ) {
				if ( false !== self::query( $sql ) ) {
					$applied++;
				} else {
					$report['errors'][] = sprintf( 'درج داده اولیه در %s ناموفق بود: %s', $table, $wpdb->last_error );
				}
			}

			if ( $applied > 0 ) {
				$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
				$report['seeded'][ $table ] = $total;
			}
		}

		if ( ! empty( $report['seeded'] ) ) {
			falnic_sc_purge_data_cache();
		}

		return $report;
	}

	// -------------------------------------------------------------------
	// Low-level helpers
	// -------------------------------------------------------------------

	/**
	 * Execute a DDL/DML statement using $wpdb while suppressing WP's default
	 * error suppression behavior so failures are detectable.
	 *
	 * @param string $sql Statement.
	 * @return mixed
	 */
	private static function query( $sql ) {
		global $wpdb;

		$suppress = $wpdb->suppress_errors( true );
		$result   = $wpdb->query( $sql );
		$wpdb->suppress_errors( $suppress );

		return $result;
	}

	/**
	 * Does the table exist?
	 *
	 * @param string $table Physical table name.
	 * @return bool
	 */
	private static function table_exists( $table ) {
		global $wpdb;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return $found === $table;
	}

	/**
	 * Existing column names.
	 *
	 * @param string $table Physical table name.
	 * @return string[]|null Null on failure.
	 */
	private static function existing_columns( $table ) {
		global $wpdb;
		$rows = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`", ARRAY_A );
		if ( null === $rows ) {
			return null;
		}
		$columns = array();
		foreach ( (array) $rows as $row ) {
			$columns[] = $row['Field'];
		}
		return $columns;
	}

	/**
	 * Full info for one column.
	 *
	 * @param string $table  Physical table name.
	 * @param string $column Column name.
	 * @return array|null
	 */
	private static function column_info( $table, $column ) {
		global $wpdb;
		$row = $wpdb->get_row( "SHOW COLUMNS FROM `{$table}` LIKE '{$column}'", ARRAY_A );
		return $row ?: null;
	}

	/**
	 * Existing index names (including PRIMARY).
	 *
	 * @param string $table Physical table name.
	 * @return string[]|null Null on failure.
	 */
	private static function existing_index_names( $table ) {
		global $wpdb;
		$rows = $wpdb->get_results( "SHOW INDEXES FROM `{$table}`", ARRAY_A );
		if ( null === $rows ) {
			return null;
		}
		$names = array();
		foreach ( (array) $rows as $row ) {
			if ( ! empty( $row['Key_name'] ) && ! in_array( $row['Key_name'], $names, true ) ) {
				$names[] = $row['Key_name'];
			}
		}
		return $names;
	}

	/**
	 * Build + run the CREATE TABLE statement for one table.
	 *
	 * @param string $table Physical table name.
	 * @param array  $def   Schema definition.
	 * @return bool
	 */
	private static function create_table( $table, $def ) {
		$parts = array_values( $def['columns'] );
		foreach ( $def['indexes'] as $index_def ) {
			$parts[] = $index_def;
		}

		$sql = "CREATE TABLE IF NOT EXISTS `{$table}` (\n  " . implode( ",\n  ", $parts ) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

		if ( false !== self::query( $sql ) ) {
			return true;
		}

		// Retry once without CHECK constraints for servers that reject them.
		$stripped = self::strip_check_constraints( $sql );
		if ( $stripped !== $sql ) {
			return false !== self::query( $stripped );
		}

		return false;
	}

	/**
	 * Remove inline CHECK(...) clauses from a DDL statement (fallback for
	 * exotic/old database servers). Handles one nesting level of parentheses.
	 *
	 * @param string $sql Statement.
	 * @return string
	 */
	private static function strip_check_constraints( $sql ) {
		$result = '';
		$len    = strlen( $sql );
		for ( $i = 0; $i < $len; $i++ ) {
			$rest = substr( $sql, $i );
			if ( preg_match( '/^\s*CHECK\s*\(/i', $rest, $m ) ) {
				// Skip the whole CHECK (...) group, tracking nesting.
				$i += strlen( $m[0] ) - 1;
				$depth = 1;
				while ( $i + 1 < $len && $depth > 0 ) {
					$i++;
					$char = $sql[ $i ];
					if ( '(' === $char ) {
						$depth++;
					} elseif ( ')' === $char ) {
						$depth--;
					}
				}
				// Drop a trailing comma that would now dangle.
				$j = $i + 1;
				while ( $j < $len && ( ' ' === $sql[ $j ] || "\n" === $sql[ $j ] || "\t" === $sql[ $j ] || "\r" === $sql[ $j ] ) ) {
					$j++;
				}
				if ( isset( $sql[ $j ] ) && ',' === $sql[ $j ] ) {
					$i = $j;
				}
				continue;
			}
			$result .= $sql[ $i ];
		}
		return $result;
	}

	/**
	 * Parse the seed file into groups keyed by the `-- @table:` markers.
	 * Statements are split with a quote-aware scanner (same rules as MySQL:
	 * single/double quoted strings, backslash escapes, backtick identifiers),
	 * so semicolons inside data values can never break the split.
	 *
	 * @param string $file Absolute path.
	 * @return array[] Each: [ 'table' => name, 'statements' => [] ]
	 */
	private static function parse_seed_file( $file ) {
		$content = (string) file_get_contents( $file );
		if ( '' === $content ) {
			return array();
		}

		$len      = strlen( $content );
		$table    = '';
		$buffer   = '';
		$result   = array();
		$in_single = $in_double = $in_backtick = $in_block_comment = false;

		for ( $i = 0; $i < $len; $i++ ) {
			$c   = $content[ $i ];
			$nxt = ( $i + 1 < $len ) ? $content[ $i + 1 ] : '';

			if ( $in_block_comment ) {
				if ( '*' === $c && '/' === $nxt ) {
					$in_block_comment = false;
					$i++;
				}
				continue;
			}

			if ( ! $in_single && ! $in_double && ! $in_backtick ) {
				// Comment or marker line: `-- ...` or `# ...` up to EOL.
				if ( ( '-' === $c && '-' === $nxt ) || '#' === $c ) {
					$comment = '';
					$i++;
					while ( $i < $len && "\n" !== $content[ $i ] ) {
						$comment .= $content[ $i ];
						$i++;
					}
					if ( preg_match( '/^\s*-{0,2}\s*@table:(\w+)/', $comment, $m ) ) {
						$table = $m[1];
					}
					continue;
				}
				if ( '/' === $c && '*' === $nxt ) {
					$in_block_comment = true;
					$i++;
					continue;
				}
				if ( "'" === $c ) {
					$in_single = true;
				} elseif ( '"' === $c ) {
					$in_double = true;
				} elseif ( '`' === $c ) {
					$in_backtick = true;
				} elseif ( ';' === $c ) {
					$stmt = trim( $buffer );
					$buffer = '';
					if ( '' !== $stmt && '' !== $table ) {
						if ( ! isset( $result[ $table ] ) ) {
							$result[ $table ] = array();
						}
						$result[ $table ][] = $stmt;
					}
					continue;
				}
				$buffer .= $c;
				continue;
			}

			// Inside a quoted context.
			if ( $in_single && '\\' === $c && '' !== $nxt ) {
				$buffer .= $c . $nxt;
				$i++;
				continue;
			}
			if ( $in_single && "'" === $c ) {
				if ( "'" === $nxt ) { // doubled quote escape
					$buffer .= "''";
					$i++;
					continue;
				}
				$in_single = false;
			} elseif ( $in_double && '"' === $c ) {
				if ( '"' === $nxt ) {
					$buffer .= '""';
					$i++;
					continue;
				}
				$in_double = false;
			} elseif ( $in_backtick && '`' === $c ) {
				$in_backtick = false;
			}
			$buffer .= $c;
		}

		$final = array();
		foreach ( $result as $tbl => $sqls ) {
			$final[] = array(
				'table'      => $tbl,
				'statements' => $sqls,
			);
		}

		return $final;
	}
}
