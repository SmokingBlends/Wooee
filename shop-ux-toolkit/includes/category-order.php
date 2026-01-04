<?php
/**
 * Plugin Name: Custom Category Order for WooCommerce
 * Description: Allows custom ordering of product categories via meta field.
 * Version: 1.5
 * Author: Grok
 */
// Add field to new category form
if ( ! defined( 'ABSPATH' ) ) exit;
add_action('product_cat_add_form_fields', 'shop_ux_toolkit_category_order_add_field');
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
function shop_ux_toolkit_category_order_add_field() {
    wp_nonce_field('shop_ux_toolkit_category_order', 'shop_ux_toolkit_category_order_nonce');
    ?>
    <div class="form-field">
        <label for="category_order">Category Order</label>
        <input type="number" name="category_order" id="category_order" value="0" />
        <p>Enter a number for custom order (lower first).</p>
    </div>
    <?php
}
// Add field to edit category form
add_action('product_cat_edit_form_fields', 'shop_ux_toolkit_category_order_edit_field', 10, 2);
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
function shop_ux_toolkit_category_order_edit_field($term, $taxonomy) {
    wp_nonce_field('shop_ux_toolkit_category_order', 'shop_ux_toolkit_category_order_nonce');
    $category_order = get_term_meta($term->term_id, 'category_order', true);
    ?>
    <tr class="form-field">
        <th><label for="category_order">Category Order</label></th>
        <td>
            <input type="number" name="category_order" id="category_order" value="<?php echo esc_attr($category_order ? $category_order : 0); ?>" />
            <p class="description">Enter a number for custom order (lower first).</p>
        </td>
    </tr>
    <?php
}
// Save the field
add_action('created_product_cat', 'shop_ux_toolkit_category_order_save');
add_action('edited_product_cat', 'shop_ux_toolkit_category_order_save');
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
function shop_ux_toolkit_category_order_save($term_id) {
    $nonce = isset($_POST['shop_ux_toolkit_category_order_nonce']) ? sanitize_text_field(wp_unslash($_POST['shop_ux_toolkit_category_order_nonce'])) : '';
    if (!$nonce || !wp_verify_nonce($nonce, 'shop_ux_toolkit_category_order')) {
        return;
    }
    if (isset($_POST['category_order'])) {
        update_term_meta($term_id, 'category_order', intval($_POST['category_order']));
    }
}
// Filter category args for shop/homepage (set a flag for custom sorting)
add_filter('woocommerce_product_subcategories_args', 'shop_ux_toolkit_category_order_args');
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
function shop_ux_toolkit_category_order_args($args) {
    $args['shop_ux_toolkit_category_order'] = true;
    $args['orderby'] = 'name'; // Use a fast default DB orderby to avoid any meta JOIN
    $args['order'] = 'ASC';
    return $args;
}
// Sort the terms in PHP after retrieval (only for flagged queries)
add_filter('get_terms', 'shop_ux_toolkit_sort_product_categories', 10, 3);
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
function shop_ux_toolkit_sort_product_categories($terms, $taxonomies, $args) {
    if (!in_array('product_cat', (array) $taxonomies) || empty($args['shop_ux_toolkit_category_order'])) {
        return $terms;
    }
    usort($terms, function($a, $b) {
        $order_a = (int) get_term_meta($a->term_id, 'category_order', true);
        $order_b = (int) get_term_meta($b->term_id, 'category_order', true);
        if ($order_a === $order_b) {
            return strcmp($a->name, $b->name); // Fallback to name if orders match
        }
        return $order_a - $order_b; // ASC sort (lower numbers first)
    });
    return $terms;
}