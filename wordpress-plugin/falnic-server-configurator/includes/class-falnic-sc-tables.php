<?php
/**
 * Table registry + schema access.
 *
 * The plugin keeps the exact same table names and column structure as the
 * standalone app (falnicc1_server_configurator.sql) so an existing dump can be
 * imported into the WordPress database — or vice versa — without any changes.
 *
 * @package FalnicServerConfigurator
 */

defined( 'ABSPATH' ) || exit;

class Falnic_SC_Tables {

	/**
	 * Cached table map: key => real table name (with optional prefix).
	 *
	 * @var array|null
	 */
	private static $tables = null;

	/**
	 * Cached schema definition (generated file).
	 *
	 * @var array|null
	 */
	private static $schema = null;

	/**
	 * Full table map. Names are intentionally NOT wp-prefixed by default to stay
	 * 1:1 compatible with the original database; a filter is available for sites
	 * that prefer a prefix.
	 *
	 * @return array key => table name
	 */
	public static function tables() {
		if ( null === self::$tables ) {
			$schema = self::schema();
			$map    = array();
			foreach ( $schema as $key => $def ) {
				$name = $def['name'];
				/**
				 * Filters the physical table name for each plugin table.
				 *
				 * @param string $name Default name (identical to the standalone app).
				 * @param string $key  Table key (chassis, cpus, ...).
				 */
				$map[ $key ] = apply_filters( 'falnic_sc_table_name', $name, $key );
			}
			self::$tables = $map;
		}

		return self::$tables;
	}

	/**
	 * Schema definitions (tables, columns, indexes) — generated from the
	 * original dump, must remain identical.
	 *
	 * @return array
	 */
	public static function schema() {
		if ( null === self::$schema ) {
			self::$schema = require FALNIC_SC_DIR . 'includes/falnic-sc-schema.php';
		}

		return self::$schema;
	}

	/**
	 * Real table name for a key.
	 *
	 * @param string $key Table key (chassis, cpus, rams, drives, controllers,
	 *                    gpus, networks, risers, hbas, optical_drives, psus,
	 *                    offers, requests).
	 * @return string
	 */
	public static function table( $key ) {
		$tables = self::tables();
		if ( ! isset( $tables[ $key ] ) ) {
			return '';
		}

		return $tables[ $key ];
	}

	/**
	 * Resolve a physical table name back to its key.
	 *
	 * @param string $name Physical table name.
	 * @return string Empty string when unknown.
	 */
	public static function key_for_table( $name ) {
		$tables = self::tables();
		$key    = array_search( $name, $tables, true );

		return $key ? (string) $key : '';
	}

	/**
	 * Cached column types per table key.
	 *
	 * @var array
	 */
	private static $column_types = array();

	/**
	 * Map every column of a table to a simple PHP type: int|float|string.
	 *
	 * Mirrors how the original PDO + mysqlnd stack returned native types for
	 * numeric columns while keeping varchar/text values as strings.
	 *
	 * @param string $key Table key.
	 * @return array column => int|float|string
	 */
	public static function column_types( $key ) {
		if ( isset( self::$column_types[ $key ] ) ) {
			return self::$column_types[ $key ];
		}

		$schema = self::schema();
		$types  = array();
		if ( isset( $schema[ $key ]['columns'] ) ) {
			foreach ( array_keys( $schema[ $key ]['columns'] ) as $column ) {
				$def = $schema[ $key ]['columns'][ $column ];
				if ( preg_match( '/`\s+(tinyint|smallint|mediumint|int|bigint|bit|bool|boolean)/i', $def . ' ', $m ) ) {
					$types[ $column ] = 'int';
				} elseif ( preg_match( '/`\s+(decimal|numeric|float|double)/i', $def . ' ', $m ) ) {
					$types[ $column ] = 'float';
				} else {
					$types[ $column ] = 'string';
				}
			}
		}

		self::$column_types[ $key ] = $types;

		return $types;
	}

	/**
	 * Cast one database row (or list of rows) to native PHP types using the
	 * schema column types. Keeps front-end JSON contracts identical to the
	 * standalone app.
	 *
	 * @param string       $key  Table key.
	 * @param array[]|array $rows Row or rows.
	 * @return array[]|array
	 */
	public static function cast_rows( $key, $rows ) {
		if ( empty( $rows ) || ! is_array( $rows ) ) {
			return $rows;
		}

		$types = self::column_types( $key );

		// Single row detection: assoc array with schema columns.
		$single = isset( $rows['id'] ) || isset( $rows[ key( $types ) ] );
		if ( $single ) {
			return self::cast_row( $rows, $types );
		}

		foreach ( $rows as $index => $row ) {
			if ( is_array( $row ) ) {
				$rows[ $index ] = self::cast_row( $row, $types );
			}
		}

		return $rows;
	}

	/**
	 * Cast a single row.
	 *
	 * @param array $row   Row values.
	 * @param array $types column => type map.
	 * @return array
	 */
	private static function cast_row( array $row, array $types ) {
		foreach ( $types as $column => $type ) {
			if ( ! array_key_exists( $column, $row ) || null === $row[ $column ] ) {
				continue;
			}
			if ( 'int' === $type ) {
				$row[ $column ] = (int) $row[ $column ];
			} elseif ( 'float' === $type ) {
				$row[ $column ] = (float) $row[ $column ];
			}
		}

		return $row;
	}
}
