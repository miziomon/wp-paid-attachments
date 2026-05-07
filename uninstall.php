<?php
/**
 * Pulizia dati all'uninstall del plugin.
 *
 * La rimozione effettiva di tabelle e option avviene solo se l'admin
 * ha abilitato l'opzione "delete_data_on_uninstall" nelle impostazioni
 * (default: false). Senza il flag, la disinstallazione lascia i dati intatti
 * così da non perdere lo storico in caso di reinstallazione accidentale.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$wppa_settings = get_option( 'wppa_settings', array() );

if ( empty( $wppa_settings['delete_data_on_uninstall'] ) ) {
	return;
}

// ── Drop tabelle custom ───────────────────────────────────────────────────────

$wppa_tables = array(
	$wpdb->prefix . 'wppa_free_views',
	$wpdb->prefix . 'wppa_unlock_codes',
	$wpdb->prefix . 'wppa_payments',
	$wpdb->prefix . 'wppa_attachment_config',
);

foreach ( $wppa_tables as $wppa_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS `{$wppa_table}`" );
}

// ── Delete options ────────────────────────────────────────────────────────────

$wppa_options = array(
	'wppa_settings',
	'wppa_secret_key',
	'wppa_db_version',
	'wppa_stats_cache_version',
);

foreach ( $wppa_options as $wppa_option ) {
	delete_option( $wppa_option );
}

// ── Delete transients wppa_* ──────────────────────────────────────────────────

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM `{$wpdb->options}` WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_wppa_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_wppa_' ) . '%'
	)
);

// ── Delete postmeta wppa_* ────────────────────────────────────────────────────

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM `{$wpdb->postmeta}` WHERE meta_key LIKE %s",
		$wpdb->esc_like( 'wppa_' ) . '%'
	)
);
