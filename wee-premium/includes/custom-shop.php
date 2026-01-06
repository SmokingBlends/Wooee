<?php
/**
 * COMPLETELY DISABLING DEFAULT WOOCOMMERCE SHOP OUTPUT
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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

        // Compact display: remove buttons only
        remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
    }
} );

/**
 * RENDERING CUSTOM CATEGORY ROWS WITH SHORTCODES
 */
add_action( 'woocommerce_archive_description', function() {
    if ( ! is_shop() ) return;

    $paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
    $per_page = 5;
    $offset = ( $paged - 1 ) * $per_page;

    $categories = get_terms( [
        'taxonomy'   => 'product_cat',
        'hide_empty' => true,
        'parent'     => 0,
        'orderby'    => 'meta_value_num',
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
        'meta_key'   => 'order',
        'order'      => 'ASC',
        'number'     => $per_page,
        'offset'     => $offset,
    ] );

    $count = count( $categories );
    if ( $count === 0 ) return;

    echo '<style>
        .woocommerce ul.products { margin: 0 !important; padding: 0 !important; }
        .woocommerce ul.products li.product { margin-bottom: 10px !important; }
        .woocommerce-loop-product__title { margin-bottom: 5px !important; }
        .price { margin-bottom: 0 !important; }
        .category-title { margin-top: 0 !important; margin-bottom: 10px !important; }
        .content-area, .site-main { margin-bottom: 0 !important; padding-bottom: 0 !important; }
    </style>';

    $index = 0;
    foreach ( $categories as $category ) {
        $index++;
        $is_first = ( $index === 1 );
        $is_last = ( $index === $count );
        $top = $is_first ? 90 : 0;
        $bottom = $is_last ? 0 : 15;
        $style = "margin: {$top}px 0 {$bottom}px 0; clear: both;";
        echo '<div class="category-section" style="' . esc_attr( $style ) . '">';
        echo '<h2 class="category-title" style="text-align: left; font-size: 1.5em;">' . esc_html( $category->name ) . '</h2>';
        echo do_shortcode( '[products limit="4" columns="4" category="' . esc_attr( $category->slug ) . '"]' );
        echo '</div>';
    }

    $total = wp_count_terms( [
        'taxonomy' => 'product_cat',
        'hide_empty' => true,
        'parent' => 0
    ] );
    $pagination = paginate_links( [
        'base' => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ),
        'format' => '?paged=%#%',
        'current' => $paged,
        'total' => ceil( $total / $per_page ),
        'prev_text' => __( 'Previous', 'wee-premium' ),
        'next_text' => __( 'Next', 'wee-premium' ),
    ] );

    if ( $pagination ) {
        echo '<div class="pagination">' . wp_kses_post( $pagination ) . '</div>';
    }
}, 15 );