<?php
/**
 * Gestione dello schema database del plugin.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\Database;

/**
 * Crea e aggiorna le tabelle custom del plugin tramite dbDelta().
 *
 * Versioning: la versione dello schema è salvata nell'option `wppa_db_version`.
 * Se la versione installata è inferiore a DB_VERSION, le tabelle vengono
 * aggiornate in modo idempotente (dbDelta esegue solo le modifiche necessarie).
 */
final class Schema {

	/**
	 * Versione corrente dello schema. Incrementare ad ogni modifica strutturale.
	 */
	const DB_VERSION = '1.0';

	/**
	 * Nome dell'option WordPress che persiste la versione schema installata.
	 */
	const DB_VERSION_OPTION = 'wppa_db_version';

	/**
	 * Installa o aggiorna le tabelle DB.
	 *
	 * Chiamato da Activator::activate() e, in futuro, da un hook `plugins_loaded`
	 * per gestire aggiornamenti automatici dello schema.
	 *
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		foreach ( self::get_table_definitions( $charset_collate ) as $sql ) {
			dbDelta( $sql );
		}

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, 'no' );
	}

	/**
	 * Verifica se lo schema installato è aggiornato.
	 *
	 * @return bool True se la versione DB corrisponde a DB_VERSION.
	 */
	public static function is_up_to_date(): bool {
		return get_option( self::DB_VERSION_OPTION ) === self::DB_VERSION;
	}

	/**
	 * Rimuove tutte le tabelle custom del plugin.
	 *
	 * Usato da uninstall.php quando l'opzione `wppa_delete_data_on_uninstall` è attiva.
	 *
	 * @return void
	 */
	public static function drop_tables(): void {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'wppa_free_views',
			$wpdb->prefix . 'wppa_unlock_codes',
			$wpdb->prefix . 'wppa_payments',
			$wpdb->prefix . 'wppa_attachment_config',
		);

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
		}

		delete_option( self::DB_VERSION_OPTION );
	}

	/**
	 * Restituisce le definizioni SQL delle tabelle per dbDelta().
	 *
	 * Formato rigoroso richiesto da dbDelta:
	 * - ogni colonna su riga propria
	 * - due spazi tra PRIMARY KEY e la parentesi
	 * - charset collate in coda
	 *
	 * @param string $charset_collate Charset/collation del database WP.
	 * @return string[]
	 */
	private static function get_table_definitions( string $charset_collate ): array {
		global $wpdb;

		$tables = array();

		// Tabella configurazione attachment.
		$tables[] = "CREATE TABLE {$wpdb->prefix}wppa_attachment_config (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  attachment_id bigint(20) unsigned NOT NULL,
  enabled tinyint(1) NOT NULL DEFAULT 1,
  payment_mode varchar(32) NOT NULL DEFAULT 'paypal_donate',
  suggested_amounts longtext DEFAULT NULL,
  min_amount decimal(10,2) NOT NULL DEFAULT 1.00,
  currency varchar(3) NOT NULL DEFAULT 'EUR',
  code_validity_days int(11) NOT NULL DEFAULT 30,
  code_max_uses int(11) NOT NULL DEFAULT 0,
  custom_text longtext DEFAULT NULL,
  custom_email_subject varchar(255) DEFAULT NULL,
  custom_email_body longtext DEFAULT NULL,
  allow_free_view tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY attachment_id (attachment_id)
) {$charset_collate};";

		// Tabella pagamenti.
		$tables[] = "CREATE TABLE {$wpdb->prefix}wppa_payments (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  attachment_id bigint(20) unsigned NOT NULL,
  provider varchar(32) NOT NULL DEFAULT '',
  provider_transaction_id varchar(128) NOT NULL DEFAULT '',
  amount decimal(10,2) NOT NULL DEFAULT 0.00,
  currency varchar(3) NOT NULL DEFAULT 'EUR',
  status varchar(32) NOT NULL DEFAULT 'pending',
  donor_email varchar(255) NOT NULL DEFAULT '',
  donor_name varchar(255) DEFAULT NULL,
  unlock_code_id bigint(20) unsigned DEFAULT NULL,
  ip_address varchar(45) NOT NULL DEFAULT '',
  user_agent varchar(500) DEFAULT NULL,
  metadata longtext DEFAULT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY provider_transaction_id (provider_transaction_id),
  KEY attachment_id (attachment_id),
  KEY donor_email (donor_email),
  KEY status (status)
) {$charset_collate};";

		// Tabella codici di sblocco.
		$tables[] = "CREATE TABLE {$wpdb->prefix}wppa_unlock_codes (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  attachment_id bigint(20) unsigned NOT NULL,
  code_hash varchar(255) NOT NULL DEFAULT '',
  code_prefix varchar(8) NOT NULL DEFAULT '',
  payment_id bigint(20) unsigned DEFAULT NULL,
  email varchar(255) NOT NULL DEFAULT '',
  expires_at datetime NOT NULL,
  max_uses int(11) NOT NULL DEFAULT 0,
  used_count int(11) NOT NULL DEFAULT 0,
  last_used_at datetime DEFAULT NULL,
  last_used_ip varchar(45) DEFAULT NULL,
  revoked tinyint(1) NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY attachment_id (attachment_id),
  KEY email (email),
  KEY code_prefix (code_prefix),
  KEY expires_at (expires_at)
) {$charset_collate};";

		// Tabella visualizzazioni gratuite (log statistico).
		$tables[] = "CREATE TABLE {$wpdb->prefix}wppa_free_views (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  attachment_id bigint(20) unsigned NOT NULL,
  ip_address varchar(45) NOT NULL DEFAULT '',
  user_agent varchar(500) DEFAULT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY attachment_id (attachment_id),
  KEY created_at (created_at)
) {$charset_collate};";

		return $tables;
	}
}
