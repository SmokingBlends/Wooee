<?php
/**
 * Wee Premium - Item Specifics
 *
 * @package Wee Premium
 * @version 0.3.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const WEE_PREMIUM_ITEM_SPECIFICS_META_CONTENT = '_wee_premium_item_specifics';

function wee_premium_item_specifics_init() {
    add_action( 'add_meta_boxes_product', 'wee_premium_item_specifics_add_meta_box' );
    add_action( 'save_post_product', 'wee_premium_item_specifics_save_meta', 10, 2 );
    add_action( 'woocommerce_product_additional_information', 'wee_premium_item_specifics_inject_table', 5, 1 );
}

function wee_premium_item_specifics_add_meta_box() {
    add_meta_box(
        'wee_premium_item_specifics',
        __( 'Item Specifics', 'wee-premium' ),
        'wee_premium_item_specifics_render_meta_box',
        'product',
        'normal',
        'default'
    );
}

function wee_premium_item_specifics_render_meta_box( $post ) {
    wp_nonce_field( 'wee_premium_item_specifics_nonce', 'wee_premium_item_specifics_nonce' );
    $content = get_post_meta( $post->ID, WEE_PREMIUM_ITEM_SPECIFICS_META_CONTENT, true );
    echo '<p><label for="wee_premium_item_specifics_content"><strong>' . esc_html__( 'Item Specifics content', 'wee-premium' ) . '</strong></label></p>';
    echo '<textarea id="wee_premium_item_specifics_content" name="wee_premium_item_specifics_content" rows="10" style="width:100%;">' . esc_textarea( $content ) . '</textarea>';
    echo '<p class="description">' . esc_html__( 'One per line: Label: Value. Lists comma-separated. Basic HTML allowed in values.', 'wee-premium' ) . '</p>';
}

function wee_premium_item_specifics_save_meta( $post_id, $post ) {
    if ( ! isset( $_POST['wee_premium_item_specifics_nonce'] ) ) {
        return;
    }
    $nonce = sanitize_text_field( wp_unslash( $_POST['wee_premium_item_specifics_nonce'] ) );
    if ( ! wp_verify_nonce( $nonce, 'wee_premium_item_specifics_nonce' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( 'product' !== $post->post_type ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    $content = isset( $_POST['wee_premium_item_specifics_content'] )
        ? wp_kses_post( wp_unslash( $_POST['wee_premium_item_specifics_content'] ) )
        : '';
    if ( $content ) {
        update_post_meta( $post_id, WEE_PREMIUM_ITEM_SPECIFICS_META_CONTENT, $content );
    } else {
        delete_post_meta( $post_id, WEE_PREMIUM_ITEM_SPECIFICS_META_CONTENT );
    }
}

function wee_premium_item_specifics_inject_table( $product ) {
    if ( ! $product instanceof WC_Product ) {
        return;
    }
    $product_id = $product->get_id();
    $content    = get_post_meta( $product_id, WEE_PREMIUM_ITEM_SPECIFICS_META_CONTENT, true );
    if ( empty( $content ) ) {
        return; // Keep native attributes.
    }
    $pairs = wee_premium_item_specifics_parse_label_value_lines( $content );
    if ( empty( $pairs ) ) {
        return; // Keep native attributes.
    }
    // Replace native attributes for this render.
    remove_action( 'woocommerce_product_additional_information', 'wc_display_product_attributes', 10 );
    // Output table; styling comes from assets/css/item-specifics.css.
    echo '<table class="item-specs-table"><tbody>';
    foreach ( $pairs as $pair ) {
        echo '<tr><th>' . esc_html( $pair['label'] ) . '</th><td>' . wp_kses_post( $pair['value'] ) . '</td></tr>';
    }
    echo '</tbody></table>';
}

function wee_premium_item_specifics_parse_label_value_lines( string $content ) : array {
    $lines = preg_split( '/\r\n|\r|\n/', $content );
    $out   = [];
    foreach ( $lines as $line ) {
        $line = trim( $line );
        if ( $line === '' ) {
            continue;
        }
        $parts = explode( ':', $line, 2 );
        if ( count( $parts ) !== 2 ) {
            continue;
        }
        $label = trim( $parts[0] );
        $value = trim( $parts[1] );
        if ( $label === '' || $value === '' ) {
            continue;
        }
        $out[] = [ 'label' => $label, 'value' => $value ];
    }
    return $out;
}

if (wee_premium_opt('enable_item_specifics')) {
    wee_premium_item_specifics_init();
}