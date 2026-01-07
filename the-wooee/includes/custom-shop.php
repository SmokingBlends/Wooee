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

add_filter( 'option_woocommerce_shop_page_display', function( $value ) {
    if ( is_shop() ) {
        return '';
    }
    return $value;
} );

/**
 * Additional removals
 */
add_action( 'wp', function() {
    if ( is_shop() ) {
        remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
        remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );
        remove_action( 'woocommerce_before_shop_loop', 'woocommerce_maybe_show_product_subcategories', 10 );
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

    $categories = get_terms( [
        'taxonomy'   => 'product_cat',
        'hide_empty' => true,
        'parent'     => 0,
        'orderby'    => 'meta_value_num',
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
        'meta_key'   => 'order',
        'order'      => 'ASC',
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
}, 15 );

// Add order field to product category add form
function the_wooee_add_category_order_field() {
    wp_nonce_field( 'the_wooee_category_order_nonce_action', 'the_wooee_category_order_nonce' );
    ?>
    <div class="form-field">
        <label for="the_wooee_category_order"><?php esc_html_e( 'Order', 'the-wooee' ); ?></label>
        <input type="number" name="the_wooee_category_order" id="the_wooee_category_order" value="" step="1" style="width: 60px;">
        <p class="description"><?php esc_html_e( 'Enter the order number for this category row on the custom shop page (lowest first).', 'the-wooee' ); ?></p>
    </div>
    <?php
}
add_action( 'product_cat_add_form_fields', 'the_wooee_add_category_order_field', 10 );

// Add order field to product category edit form
function the_wooee_edit_category_order_field( $term ) {
    $order = get_term_meta( $term->term_id, 'order', true );
    wp_nonce_field( 'the_wooee_category_order_nonce_action', 'the_wooee_category_order_nonce' );
    ?>
    <tr class="form-field">
        <th scope="row" valign="top"><label for="the_wooee_category_order"><?php esc_html_e( 'Order', 'the-wooee' ); ?></label></th>
        <td>
            <input type="number" name="the_wooee_category_order" id="the_wooee_category_order" value="<?php echo esc_attr( $order ); ?>" step="1" style="width: 60px;">
            <p class="description"><?php esc_html_e( 'Enter the order number for this category row on the custom shop page (lowest first).', 'the-wooee' ); ?></p>
        </td>
    </tr>
    <?php
}
add_action( 'product_cat_edit_form_fields', 'the_wooee_edit_category_order_field', 10, 1 );

// Save order field on create or edit
function the_wooee_save_category_order_field( $term_id ) {
    if ( ! isset( $_POST['the_wooee_category_order_nonce'] ) ) {
        return;
    }

    $nonce = sanitize_text_field( wp_unslash( $_POST['the_wooee_category_order_nonce'] ) );

    if ( ! wp_verify_nonce( $nonce, 'the_wooee_category_order_nonce_action' ) ) {
        return;
    }

    if ( isset( $_POST['the_wooee_category_order'] ) ) {
        $order_value = sanitize_text_field( wp_unslash( $_POST['the_wooee_category_order'] ) );
        update_term_meta( $term_id, 'order', $order_value );
    }
}
add_action( 'created_product_cat', 'the_wooee_save_category_order_field', 10, 1 );
add_action( 'edited_product_cat', 'the_wooee_save_category_order_field', 10, 1 );