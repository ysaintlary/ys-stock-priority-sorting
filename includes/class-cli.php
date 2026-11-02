<?php
/**
 * Commande WP-CLI pour le recalcul des paliers effectifs.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YS_SPS_CLI {

	public static function register() {
		WP_CLI::add_command( 'stock-sort', __CLASS__ );
	}

	/**
	 * Recalcule le palier effectif (_ys_sps_effective_step) de tous les produits.
	 *
	 * Par défaut, le recalcul est planifié via Action Scheduler (par lots de 100).
	 * Avec --sync, l'exécution est immédiate.
	 *
	 * ## OPTIONS
	 *
	 * [--sync]
	 * : Exécution synchrone (sans Action Scheduler).
	 *
	 * ## EXAMPLES
	 *
	 *     wp stock-sort recalculate
	 *     wp stock-sort recalculate --sync
	 *
	 * @subcommand recalculate
	 */
	public function recalculate( $args, $assoc_args ) {
		$sync = \WP_CLI\Utils\get_flag_value( $assoc_args, 'sync', false );

		if ( $sync ) {
			$count      = 0;
			$batch_size = 100;
			$offset     = 0;

			do {
				$product_ids = wc_get_products( array(
					'limit'  => $batch_size,
					'offset' => $offset,
					'return' => 'ids',
					'status' => array( 'publish', 'draft', 'pending', 'private' ),
				) );

				foreach ( $product_ids as $product_id ) {
					YS_SPS_Effective_Step::update_product_step( $product_id );
					$count++;
				}

				$offset += $batch_size;
			} while ( count( $product_ids ) >= $batch_size );

			WP_CLI::success( sprintf( '%d produits recalculés.', $count ) );
			return;
		}

		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			WP_CLI::error( 'Action Scheduler non disponible.' );
		}

		as_unschedule_all_actions( 'ys_sps_recalculate_all_batch', null, 'ys-sps' );
		as_schedule_single_action( time(), 'ys_sps_recalculate_all_batch', array( 0 ), 'ys-sps' );
		WP_CLI::success( 'Recalcul planifié via Action Scheduler.' );
	}
}
