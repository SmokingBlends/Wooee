<?php
/**
 * Plugin Name: Meta Checkout Handler
 * Description: Handles Meta Commerce checkout parameters for WooCommerce.
 * Version: 1.4.2
 * Author: Your Name
 */
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// Hook into template_redirect
add_action( 'template_redirect', 'shop_ux_toolkit_handle_meta_checkout' );
function shop_ux_toolkit_handle_meta_checkout() {
    if (!shop_ux_toolkit_opt('enable_facebook_cart')) {
        return;
    }
    $slug = shop_ux_toolkit_opt('facebook_cart_page_slug');
    // Only process on specific page with 'products' param
    if ( ! is_page($slug) || ! isset( $_GET['products'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return;
    }
    // Now safe to check WooCommerce/cart
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
        return;
    }
    // Validate cart_origin
    $valid_origins = [ 'facebook', 'instagram', 'meta_shops' ];
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $cart_origin = isset( $_GET['cart_origin'] ) ? sanitize_text_field( wp_unslash( $_GET['cart_origin'] ) ) : '';
    if ( ! in_array( $cart_origin, $valid_origins, true ) ) {
        // Redirect on failure, e.g., to cart
        wp_safe_redirect( wc_get_cart_url() );
        exit;
    }
    // Force session if new
    if ( ! WC()->session->has_session() ) {
        WC()->session->set_customer_session_cookie( true );
    }
    // Parse expected products from param
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $products_param = sanitize_text_field( wp_unslash( $_GET['products'] ) );
    $items = array_filter( array_map( 'trim', explode( ',', $products_param ) ) );
    $expected = [];
    foreach ( $items as $item ) {
        if ( preg_match( '/(\d+):(\d+)/', $item, $m ) ) {
            $product_id = absint( $m[1] );
            $qty = min( absint( $m[2] ), 99 );
            if ( $product_id > 0 && $qty > 0 ) {
                $expected[$product_id] = (isset($expected[$product_id]) ? $expected[$product_id] : 0) + $qty;
            }
        }
    }
    // Get current cart items
    $current = [];
    foreach ( WC()->cart->get_cart() as $cart_item ) {
        $product_id = $cart_item['product_id'];
        $current[$product_id] = (isset($current[$product_id]) ? $current[$product_id] : 0) + $cart_item['quantity'];
    }
    // Check coupon
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $coupon_code = isset( $_GET['coupon'] ) ? sanitize_text_field( wp_unslash( $_GET['coupon'] ) ) : '';
    $has_coupon = $coupon_code ? WC()->cart->has_discount( $coupon_code ) : true;
    // Whitelist extra params for tracking (removed clear_cart, products)
    $extra_params = [];
    $whitelist = [ 'cart_origin', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'fbclid' ];
    foreach ( $whitelist as $param ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( isset( $_GET[ $param ] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $extra_params[ $param ] = sanitize_text_field( wp_unslash( $_GET[ $param ] ) );
        }
    }
    $redirect_url = add_query_arg( $extra_params, wc_get_checkout_url() );
    // If current matches expected and coupon applied, redirect without changes
    if ( $expected === $current && $has_coupon ) {
        wp_safe_redirect( $redirect_url );
        exit;
    }
    // Handle clear_cart if present
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $clear_cart = isset( $_GET['clear_cart'] ) ? strtolower( sanitize_text_field( wp_unslash( $_GET['clear_cart'] ) ) ) : 'no';
    if ( in_array( $clear_cart, [ 'yes', 'true' ], true ) ) {
        WC()->cart->empty_cart();
    }
    // Validate and add products
    $errors = [];
    foreach ( $expected as $product_id => $qty ) {
        $product = wc_get_product( $product_id );
        if ( ! $product || ! $product->is_purchasable() ) {
            $errors[] = sprintf( 'Product %d is invalid or not purchasable.', $product_id );
            continue;
        }
        if ( $product->managing_stock() && ! $product->has_enough_stock( $qty ) ) {
            $errors[] = sprintf( 'Insufficient stock for product %d.', $product_id );
            continue;
        }
        // For variable products, add_to_cart needs variation_id; assume default or skip if required
        if ( $product->is_type( 'variable' ) ) {
            // If no variation specified, perhaps skip or add notice
            $errors[] = sprintf( 'Variable product %d requires variation selection.', $product_id );
            continue;
        }
        $added = WC()->cart->add_to_cart( $product_id, $qty );
        if ( ! $added ) {
            $errors[] = sprintf( 'Failed to add product %d to cart.', $product_id );
        }
    }
    // Apply coupon if present and valid
    if ( $coupon_code ) {
        $coupon = new WC_Coupon( $coupon_code );
        if ( $coupon->get_id() && ( ! $coupon->get_date_expires() || $coupon->get_date_expires() > time() ) ) {
            WC()->cart->apply_coupon( $coupon_code );
        } else {
            $errors[] = 'Invalid or expired coupon.';
        }
    }
    // Calculate totals to update hashes
    WC()->cart->calculate_totals();
    // Add errors as notices
    if ( ! empty( $errors ) ) {
        foreach ( $errors as $error ) {
            wc_add_notice( $error, 'error' );
        }
        // Redirect to cart on errors
        wp_safe_redirect( wc_get_cart_url() );
        exit;
    }
    // Redirect to clean checkout URL
    wp_safe_redirect( $redirect_url );
    exit;
}