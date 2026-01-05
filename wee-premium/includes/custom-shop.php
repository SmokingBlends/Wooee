<?php
/**
 * COMPLETELY DISABLING DEFAULT WOOCOMMERCE SHOP OUTPUT
 */
add_action( 'woocommerce_product_query', function( $query ) {
    if ( is_shop() ) {
        $query->set( 'post__in', array(0) );
    }
} );

/**
 * Additional removals
 */
add_action( 'wp', function() {
    if ( is_shop() ) {
        remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
        remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );
        remove_action( 'woocommerce_before_shop_loop', 'woocommerce_maybe_show_product_subcategories' );
        remove_action( 'woocommerce_after_shop_loop', 'woocommerce_pagination', 10 );
        remove_action( 'woocommerce_no_products_found', 'wc_no_products_found', 10 );
    }
} );

/**
 * RENDERING CUSTOM CATEGORY ROWS WITH SHORTCODES
 */
add_action( 'woocommerce_archive_description', function() {
    if ( ! is_shop() ) return;

    $categories = get_terms([
        'taxonomy'   => 'product_cat',
        'hide_empty' => true,
        'parent'     => 0,
    ]);

    foreach ( $categories as $category ) {
        echo '<div class="category-section" style="margin-bottom: 40px; clear: both;">';
        echo '<h2 class="category-title">' . esc_html( $category->name ) . '</h2>';
        echo do_shortcode( '[products limit="4" columns="4" category="' . esc_attr( $category->slug ) . '"]' );
        echo '</div>';
    }
}, 15 );