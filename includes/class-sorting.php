<?php
/**
 * Tri des produits par priorité de stock via le filtre posts_clauses.
 *
 * Groupes :
 *   0 = en stock (défaut)
 *   1 = stock faible (onbackorder, ou instock avec quantité < palier effectif)
 *   2 = rupture de stock (outofstock)
 *
 * Conditions d'application (toutes requises) :
 * - Front uniquement (y compris REST API servant l'affichage front)
 * - Requête sur le type product
 * - ORDER BY contenant menu_order (= tri par défaut)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YS_SPS_Sorting {

	public static function init() {
		add_filter( 'posts_clauses', array( __CLASS__, 'modify_clauses' ), 9999, 2 );
	}

	/**
	 * @param array     $clauses
	 * @param \WP_Query $query
	 * @return array
	 */
	public static function modify_clauses( array $clauses, $query ): array {
		if ( is_admin() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $clauses;
		}

		$post_type = $query->get( 'post_type' );
		if ( is_array( $post_type ) ) {
			if ( ! in_array( 'product', $post_type, true ) ) {
				return $clauses;
			}
		} elseif ( 'product' !== $post_type ) {
			return $clauses;
		}

		if ( empty( $clauses['orderby'] ) || false === stripos( $clauses['orderby'], 'menu_order' ) ) {
			return $clauses;
		}

		if ( ! apply_filters( 'ys_sps_sorting_enabled', true, $query ) ) {
			return $clauses;
		}

		global $wpdb;

		$pml_table = esc_sql( $wpdb->prefix . 'wc_product_meta_lookup' );

		$clauses['join'] .= " LEFT JOIN {$pml_table} AS sps_pml ON ({$wpdb->posts}.ID = sps_pml.product_id)";
		$clauses['join'] .= $wpdb->prepare(
			" LEFT JOIN {$wpdb->postmeta} AS sps_pm ON ({$wpdb->posts}.ID = sps_pm.post_id AND sps_pm.meta_key = %s)",
			'_ys_sps_effective_step'
		);

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- expression SQL statique sans interpolation utilisateur
		$case = "CASE
			WHEN sps_pml.stock_status = 'outofstock' THEN 2
			WHEN sps_pml.stock_status = 'onbackorder' THEN 1
			WHEN sps_pml.stock_status = 'instock'
				AND sps_pml.stock_quantity IS NOT NULL
				AND CAST(COALESCE(sps_pm.meta_value, '0') AS UNSIGNED) > 1
				AND sps_pml.stock_quantity < CAST(sps_pm.meta_value AS UNSIGNED)
			THEN 1
			ELSE 0
		END ASC";
		// phpcs:enable

		$clauses['orderby'] = $case . ', ' . $clauses['orderby'];

		return $clauses;
	}
}
