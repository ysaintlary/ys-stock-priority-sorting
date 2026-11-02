<?php
/**
 * Nettoyage à la désinstallation du plugin.
 *
 * Supprime les métas _ys_sps_effective_step et les actions planifiées.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_ys_sps_effective_step' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'ys_sps_recalculate_all_batch', null, 'ys-sps' );
	as_unschedule_all_actions( 'ys_sps_recalculate_category_batch', null, 'ys-sps' );
}
