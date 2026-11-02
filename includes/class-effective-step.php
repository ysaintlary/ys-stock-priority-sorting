<?php
/**
 * Calcul et stockage du palier effectif pour chaque produit.
 *
 * Résout la hiérarchie Barn2 WooCommerce Quantity Manager :
 * 1. Méta produit _wqm_product_step
 * 2. Méta de terme quantity_manager_category_step (sous-catégorie prioritaire)
 * 3. Option globale quantity_manager_global_step
 *
 * Cas d'un produit dans plusieurs catégories de même niveau de profondeur :
 * le palier le plus élevé l'emporte (approche conservative qui favorise
 * la visibilité du stock faible).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YS_SPS_Effective_Step {

	const BATCH_SIZE = 100;

	public static function init() {
		add_action( 'woocommerce_update_product', array( __CLASS__, 'on_product_save' ) );
		add_action( 'woocommerce_new_product', array( __CLASS__, 'on_product_save' ) );

		add_action( 'added_term_meta', array( __CLASS__, 'on_term_meta_change' ), 10, 4 );
		add_action( 'updated_term_meta', array( __CLASS__, 'on_term_meta_change' ), 10, 4 );
		add_action( 'deleted_term_meta', array( __CLASS__, 'on_term_meta_change' ), 10, 4 );

		add_action( 'update_option_quantity_manager_global_step', array( __CLASS__, 'on_global_step_change' ) );
		add_action( 'add_option_quantity_manager_global_step', array( __CLASS__, 'on_global_step_change' ) );

		add_action( 'ys_sps_recalculate_all_batch', array( __CLASS__, 'process_all_batch' ) );
		add_action( 'ys_sps_recalculate_category_batch', array( __CLASS__, 'process_category_batch' ), 10, 2 );
	}

	/**
	 * Résout le palier effectif d'un produit selon la hiérarchie Barn2.
	 *
	 * Gère les valeurs stockées comme entier simple ou tableau sérialisé.
	 */
	public static function resolve_step( int $product_id ): int {
		// 1. Méta produit (priorité maximale)
		$step = self::parse_step_value( get_post_meta( $product_id, '_wqm_product_step', true ) );
		if ( $step > 1 ) {
			return $step;
		}

		// 2. Méta de catégorie (la sous-catégorie la plus profonde l'emporte)
		$terms = get_the_terms( $product_id, 'product_cat' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$best_step  = 0;
			$best_depth = -1;

			foreach ( $terms as $term ) {
				$cat_step = self::parse_step_value(
					get_term_meta( $term->term_id, 'quantity_manager_category_step', true )
				);
				if ( $cat_step <= 1 ) {
					continue;
				}

				$depth = self::get_term_depth( $term->term_id );
				if ( $depth > $best_depth || ( $depth === $best_depth && $cat_step > $best_step ) ) {
					$best_step  = $cat_step;
					$best_depth = $depth;
				}
			}

			if ( $best_step > 1 ) {
				return $best_step;
			}
		}

		// 3. Option globale
		$global = self::parse_step_value( get_option( 'quantity_manager_global_step', 0 ) );
		if ( $global > 1 ) {
			return $global;
		}

		return 0;
	}

	/**
	 * Parse une valeur de palier (entier simple ou tableau sérialisé).
	 */
	private static function parse_step_value( $value ): int {
		if ( is_array( $value ) ) {
			$value = reset( $value );
		}
		return is_numeric( $value ) ? absint( $value ) : 0;
	}

	/**
	 * Profondeur d'un terme dans la taxonomie product_cat (0 = racine).
	 */
	private static function get_term_depth( int $term_id ): int {
		$depth = 0;
		$term  = get_term( $term_id, 'product_cat' );
		while ( $term && ! is_wp_error( $term ) && $term->parent > 0 ) {
			$depth++;
			$term = get_term( $term->parent, 'product_cat' );
		}
		return $depth;
	}

	/**
	 * Calcule et stocke le palier effectif d'un produit dans _ys_sps_effective_step.
	 */
	public static function update_product_step( int $product_id ): void {
		$step = self::resolve_step( $product_id );
		$step = (int) apply_filters( 'ys_sps_effective_step', $step, $product_id );
		update_post_meta( $product_id, '_ys_sps_effective_step', $step );
	}

	public static function on_product_save( int $product_id ): void {
		self::update_product_step( $product_id );
	}

	/**
	 * Hook sur added/updated/deleted_term_meta.
	 *
	 * Le premier paramètre est $meta_id (ajout/modification) ou $meta_ids (suppression) ;
	 * on ne l'utilise pas.
	 */
	public static function on_term_meta_change( $meta_id_or_ids, int $term_id, string $meta_key, $meta_value ): void {
		if ( $meta_key !== 'quantity_manager_category_step' ) {
			return;
		}

		$term = get_term( $term_id );
		if ( ! $term || is_wp_error( $term ) || $term->taxonomy !== 'product_cat' ) {
			return;
		}

		self::schedule_category_recalculate( $term_id );
	}

	public static function on_global_step_change(): void {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}

		as_unschedule_all_actions( 'ys_sps_recalculate_all_batch', null, 'ys-sps' );
		as_schedule_single_action( time(), 'ys_sps_recalculate_all_batch', array( 0 ), 'ys-sps' );
	}

	public static function schedule_category_recalculate( int $term_id ): void {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}

		$term_ids   = get_term_children( $term_id, 'product_cat' );
		$term_ids[] = $term_id;

		as_schedule_single_action(
			time(),
			'ys_sps_recalculate_category_batch',
			array( $term_ids, 0 ),
			'ys-sps'
		);
	}

	/**
	 * Action Scheduler : lot de recalcul global (tous les produits).
	 */
	public static function process_all_batch( int $offset = 0 ): void {
		$product_ids = wc_get_products( array(
			'limit'  => self::BATCH_SIZE,
			'offset' => $offset,
			'return' => 'ids',
			'status' => array( 'publish', 'draft', 'pending', 'private' ),
		) );

		foreach ( $product_ids as $product_id ) {
			self::update_product_step( $product_id );
		}

		if ( count( $product_ids ) >= self::BATCH_SIZE ) {
			as_schedule_single_action(
				time(),
				'ys_sps_recalculate_all_batch',
				array( $offset + self::BATCH_SIZE ),
				'ys-sps'
			);
		}
	}

	/**
	 * Action Scheduler : lot de recalcul pour une catégorie et ses sous-catégories.
	 */
	public static function process_category_batch( array $term_ids, int $offset = 0 ): void {
		$product_ids = get_posts( array(
			'post_type'      => 'product',
			'posts_per_page' => self::BATCH_SIZE,
			'offset'         => $offset,
			'fields'         => 'ids',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => array_map( 'absint', $term_ids ),
				),
			),
		) );

		foreach ( $product_ids as $product_id ) {
			self::update_product_step( $product_id );
		}

		if ( count( $product_ids ) >= self::BATCH_SIZE ) {
			as_schedule_single_action(
				time(),
				'ys_sps_recalculate_category_batch',
				array( $term_ids, $offset + self::BATCH_SIZE ),
				'ys-sps'
			);
		}
	}
}
