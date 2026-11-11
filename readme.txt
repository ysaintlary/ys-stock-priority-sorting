=== YS | Stock priority sorting ===
Contributors: ysaintlary
Tags: woocommerce, stock, sorting, product-collection, blocks
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.2
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Trie les produits WooCommerce par priorité de stock : en stock, stock faible, rupture.

== Description ==

Modifie le tri par défaut des listes de produits WooCommerce (Boutique, catégories, étiquettes, attributs, recherche) pour afficher les produits dans cet ordre :

1. Produits en stock (stock normal)
2. Produits en stock faible
3. Produits en rupture de stock

À l'intérieur de chaque groupe, le tri par défaut existant est conservé (ordre personnalisé menu_order, puis titre).

= Définition du stock faible =

Un produit est en stock faible si sa quantité en stock est inférieure à son palier effectif (step) configuré via Barn2 WooCommerce Quantity Manager. Un produit en backorder est également classé en stock faible.

La hiérarchie de résolution du palier (le niveau le plus précis l'emporte) :

1. Méta produit `_wqm_product_step`
2. Méta de catégorie `quantity_manager_category_step` (sous-catégorie prioritaire)
3. Option globale `quantity_manager_global_step`

Si aucun palier n'est défini (vide, 0 ou 1), le produit n'est jamais considéré en stock faible.

= Commande WP-CLI =

    wp stock-sort recalculate          # planification via Action Scheduler
    wp stock-sort recalculate --sync   # exécution immédiate

== Changelog ==

= 1.0.2 =
* Fix - fix: add WORDPRESS_TEST_PLUGINS for Plugin Check WooCommerce dependency.
* Update - Release 1.0.1.
* Update - chore: enable readiness/quality/security packs with PUC exclusions.


= 1.0.1 =
* Update - chore: enable readiness/quality/security packs with PUC exclusions.


= 1.0.0 =
* Version initiale.
