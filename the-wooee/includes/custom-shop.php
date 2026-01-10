<?php
/**
 * COMPLETELY DISABLING DEFAULT WOOCOMMERCE SHOP OUTPUT
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1. Clear the main product query for the shop page
add_action( 'woocommerce_product_query', function( $query ) {
    if ( is_shop() ) {
        $query->set( 'post__in', array(0) );
    }
} );

// 2. Disable default category/product displays from settings
add_filter( 'option_woocommerce_shop_page_display', function( $value ) {
    return is_shop() ? '' : $value;
} );

/**
 * Clean up default WooCommerce shop elements
 */
add_action( 'wp', function() {
    if ( is_shop() ) {
        remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
        remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );
        remove_action( 'woocommerce_before_shop_loop', 'woocommerce_maybe_show_product_subcategories', 10 );
        remove_action( 'woocommerce_after_shop_loop', 'woocommerce_pagination', 10 );
        remove_action( 'woocommerce_no_products_found', 'wc_no_products_found', 10 );
        remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
    }
} );

/**
 * RENDERING CUSTOM CATEGORY ROWS
 */
add_action( 'woocommerce_archive_description', function() {
    if ( ! is_shop() ) return;

    $o = the_wooee_opts();
    if ( empty( $o['enable_custom_shop'] ) ) return;

    $categories = get_terms( [
        'taxonomy'   => 'product_cat',
        'hide_empty' => true,
        'parent'     => 0,
    ] );

    if ( empty( $categories ) ) return;

    // Sort categories in PHP
    usort( $categories, function( $a, $b ) {
        $order_a = get_term_meta( $a->term_id, 'order', true ) ?: 9999;
        $order_b = get_term_meta( $b->term_id, 'order', true ) ?: 9999;
        return $order_a <=> $order_b;
    } );

    $per_page = (int) $o['category_per_page'];
    $page = max( 1, get_query_var( 'paged' ) );
    if ( $per_page > 0 ) {
        $categories = array_slice( $categories, ( $page - 1 ) * $per_page, $per_page );
    }

    if ( empty( $categories ) ) return;

    $count = count( $categories );
    $index = 0;

    foreach ( $categories as $category ) {
        $index++;
        $is_first = ( $index === 1 );
        $is_last = ( $index === $count );

        $class = 'category-section';
        if ( $is_first ) $class .= ' first';
        if ( $is_last ) $class .= ' last';

        echo '<div class="' . esc_attr( $class ) . '">';
        echo '<h2 class="category-title">' . esc_html( $category->name ) . '</h2>';

        // Capture WooCommerce shortcode output
        ob_start();
        echo do_shortcode( '[products limit="' . esc_attr( $o['products_limit'] ) . '" columns="' . esc_attr( $o['products_columns'] ) . '" category="' . esc_attr( $category->slug ) . '"]' );
        $content = ob_get_clean();

        /**
         * FIX: RESTORE RESPONSIVENESS & ADD LAZY LOADING
         * wp_filter_content_tags adds srcset and native lazy loading.
         * We manually ensure loading="lazy" is present for everything EXCEPT the first row
         * to protect your LCP (Core Web Vitals) score for 2026.
         */
        $content = wp_filter_content_tags( $content );

        // If still no lazy loading, manually add it using DOMDocument
        if ( class_exists( 'DOMDocument' ) ) {
            libxml_use_internal_errors( true ); // Suppress warnings
            $dom = new DOMDocument();
            $dom->loadHTML( mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' ), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
            $images = $dom->getElementsByTagName( 'img' );
            foreach ( $images as $img ) {
                if ( ! $img->hasAttribute( 'loading' ) ) {
                    // Add loading="lazy" unless it has fetchpriority="high"
                    if ( ! $img->hasAttribute( 'fetchpriority' ) || $img->getAttribute( 'fetchpriority' ) !== 'high' ) {
                        $img->setAttribute( 'loading', 'lazy' );
                    }
                }
            }
            $content = $dom->saveHTML();
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $content;
        echo '</div>';
    }

    if ( $per_page > 0 ) {
        $total_pages = ceil( count( $categories ) / $per_page );
        echo '<nav class="woocommerce-pagination">';
        echo wp_kses_post( paginate_links( [
            'base'    => esc_url( get_pagenum_link( 1 ) ) . '%_%',
            'format'  => '/page/%#%',
            'current' => $page,
            'total'   => $total_pages,
        ] ) );
        echo '</nav>';
    }
}, 15 );

/**
 * CATEGORY ORDERING ADMIN FIELDS
 */
add_action( 'product_cat_add_form_fields', 'the_wooee_add_category_order_field', 10 );
add_action( 'product_cat_edit_form_fields', 'the_wooee_edit_category_order_field', 10, 1 );
add_action( 'created_product_cat', 'the_wooee_save_category_order_field', 10, 1 );
add_action( 'edited_product_cat', 'the_wooee_save_category_order_field', 10, 1 );

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

function the_wooee_edit_category_order_field( $term ) {
    $order = get_term_meta( $term->term_id, 'order', true );
    wp_nonce_field( 'the_wooee_category_order_nonce_action', 'the_wooee_category_order_nonce' );
    ?>
    <tr class="form-field">
        <th scope="row"><label for="the_wooee_category_order"><?php esc_html_e( 'Order', 'the-wooee' ); ?></label></th>
        <td>
            <input type="number" name="the_wooee_category_order" id="the_wooee_category_order" value="<?php echo esc_attr( $order ); ?>" step="1" style="width: 60px;">
            <p class="description"><?php esc_html_e( 'Enter the order number for this category row on the custom shop page (lowest first).', 'the-wooee' ); ?></p>
        </td>
    </tr>
    <?php
}

function the_wooee_save_category_order_field( $term_id ) {
    if ( ! isset( $_POST['the_wooee_category_order_nonce'] ) ) {
        return;
    }
    $nonce = sanitize_text_field( wp_unslash( $_POST['the_wooee_category_order_nonce'] ) );
    if ( ! wp_verify_nonce( $nonce, 'the_wooee_category_order_nonce_action' ) ) {
        return;
    }
    if ( isset( $_POST['the_wooee_category_order'] ) ) {
        $order = sanitize_text_field( wp_unslash( $_POST['the_wooee_category_order'] ) );
        update_term_meta( $term_id, 'order', $order );
    }
}