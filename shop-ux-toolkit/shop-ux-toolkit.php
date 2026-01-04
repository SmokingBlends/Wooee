<?php
/*
Plugin Name: Shop UX Toolkit
Description: Enhances WooCommerce UX with link styling, hover effects, keyboard focus, blog excerpts (Storefront only), reviews, category ordering, and more.
Version: 0.3.1
Author: Smoking Blends
Author URI: https://www.smokingblends.com/
Text Domain: shop-ux-toolkit
Requires at least: 6.0
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/
if (!defined('ABSPATH')) exit;
define('SHOP_UX_TOOLKIT_VER', '0.3.1');
define('SHOP_UX_TOOLKIT_PLUGIN_FILE', __FILE__);
/** ==============================
 * Options (defaults + helpers)
 * ============================== */
const SHOP_UX_TOOLKIT_DEFAULTS = [
  'load_links_css'       => false,
  'load_lift_css'        => false,
  'keyboard_only_focus' => false,
  'blog_excerpts_enabled' => false,
  'enable_facebook_cart' => false,
  'facebook_cart_page_slug' => 'secure-checkout',
  'enable_reviews' => false,
  'reviews' => [],
  'enable_turnstile' => false,
  'reviews_page_slug' => 'reviews',
  'footer_credit_text' => '',
  'footer_credit_url' => '',
  'enable_item_specifics' => true,
];
function shop_ux_toolkit_opts() {
  $saved = get_option('shop_ux_toolkit_options');
  if (!is_array($saved)) $saved = [];
  return array_merge(SHOP_UX_TOOLKIT_DEFAULTS, $saved);
}
function shop_ux_toolkit_opt($key) {
  $o = shop_ux_toolkit_opts();
  return isset($o[$key]) ? $o[$key] : (SHOP_UX_TOOLKIT_DEFAULTS[$key] ?? null);
}
// Include the blog adjustments file conditionally
$shop_ux_toolkit_theme = wp_get_theme();
$shop_ux_toolkit_is_storefront = ( 'storefront' === $shop_ux_toolkit_theme->get_stylesheet() || 'storefront' === $shop_ux_toolkit_theme->get_template() );
if (shop_ux_toolkit_opt('blog_excerpts_enabled') && $shop_ux_toolkit_is_storefront) {
  require_once plugin_dir_path(__FILE__) . 'includes/blog-adjustments.php';
}
// Include the settings file
require_once plugin_dir_path(__FILE__) . 'includes/settings.php';
// Include the reviews file conditionally
if (shop_ux_toolkit_opt('enable_reviews')) {
  require_once plugin_dir_path(__FILE__) . 'includes/reviews.php';
}
// Include the category order file
require_once plugin_dir_path(__FILE__) . 'includes/category-order.php';
// Include the checkout-facebook file conditionally
if (shop_ux_toolkit_opt('enable_facebook_cart')) {
  require_once plugin_dir_path(__FILE__) . 'includes/checkout-facebook.php';
}
// Include the item specifics file
require_once plugin_dir_path(__FILE__) . 'includes/item-specifics.php';
add_action('after_setup_theme', function() {
    global $shop_ux_toolkit_is_storefront;
    if ($shop_ux_toolkit_is_storefront) {
        remove_action('storefront_footer', 'storefront_credit', 20);
        add_action('storefront_footer', 'shop_ux_toolkit_custom_storefront_credit', 20);
    }
});
function shop_ux_toolkit_custom_storefront_credit() {
    $credit_text = shop_ux_toolkit_opt('footer_credit_text');
    $credit_url = shop_ux_toolkit_opt('footer_credit_url');
    $links_output = '';
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
    if (apply_filters('storefront_credit_link', true) && !empty($credit_text) && !empty($credit_url)) {
        $links_output .= '<a href="' . esc_url($credit_url) . '" target="_blank" title="' . esc_attr($credit_text) . '" rel="author">' . esc_html($credit_text) . '</a>.';
    }
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
    if (apply_filters('storefront_privacy_policy_link', true) && function_exists('the_privacy_policy_link')) {
        $separator = '<span role="separator" aria-hidden="true"></span>';
        $links_output = get_the_privacy_policy_link('', (!empty($links_output) ? $separator : '')) . $links_output;
    }
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
    $links_output = apply_filters('storefront_credit_links_output', $links_output);
    $output = '<div class="site-info">';
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
    $output .= esc_html(apply_filters('storefront_copyright_text', '&copy; ' . get_bloginfo('name') . ' ' . gmdate('Y')));
    if (!empty($links_output)) {
        $output .= '<br />' . wp_kses_post($links_output);
    }
    $output .= '</div><!-- .site-info -->';
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo $output;
}
/** ======================================
 * STOP empty paginated shop pages
 * ====================================== */
add_action( 'template_redirect', function() {
  if ( is_shop() && get_query_var( 'paged' ) > 1 && get_option( 'woocommerce_shop_page_display' ) === 'subcategories' ) {
      global $wp_query;
      $wp_query->set_404();
      status_header( 404 );
      nocache_headers();
  }
} );
/** ======================================
 * STOP empty paginated shop pages END
 * ====================================== */
// Enqueue WooCommerce styles and scripts (consider moving this add_action to your main plugin file for better organization)
add_action('wp_enqueue_scripts', 'shop_ux_toolkit_enqueue_wc_styles_for_review_shortcode');
function shop_ux_toolkit_enqueue_wc_styles_for_review_shortcode() {
    global $post;
    if (is_a($post, 'WP_Post') && (has_shortcode($post->post_content, 'submit_review_form') || has_shortcode($post->post_content, 'display_all_reviews'))) {
        wp_enqueue_style('woocommerce-layout');
        wp_enqueue_style('woocommerce-smallscreen');
        wp_enqueue_style('woocommerce-general');
        wp_enqueue_script('jquery'); // Ensure jQuery is loaded
        wp_enqueue_script('wc-single-product'); // Includes JS for star rating handling
      //  wp_enqueue_script('wc-add-to-cart');
    }
}
// Enqueue WooCommerce styles and scripts (consider moving this add_action to your main plugin file for better organization) END
/** ======================================
 * Enqueue CSS (each toggle loads a file)
 * ====================================== */
add_action('wp_enqueue_scripts', function () {
  $deps = [];
  if (wp_style_is('storefront-style', 'registered')) $deps[] = 'storefront-style';
  if (wp_style_is('wc-blocks-style',  'registered')) $deps[] = 'wc-blocks-style';
  // 1) Intro paragraph links
  if (shop_ux_toolkit_opt('load_links_css')) {
    $rel  = 'assets/css/sb-links.css';
    $file = plugin_dir_path(__FILE__) . $rel;
    $url  = plugins_url($rel, __FILE__);
    $ver  = file_exists($file) ? (string) filemtime($file) : SHOP_UX_TOOLKIT_VER;
    wp_enqueue_style('shop-ux-toolkit-links', $url, $deps, $ver);
  }
  // 2) Hover highlight on product & category boxes
  if (shop_ux_toolkit_opt('load_lift_css')) {
    $rel  = 'assets/css/sb-lift.css';
    $file = plugin_dir_path(__FILE__) . $rel;
    $url  = plugins_url($rel, __FILE__);
    $ver  = file_exists($file) ? (string) filemtime($file) : SHOP_UX_TOOLKIT_VER;
    wp_enqueue_style('shop-ux-toolkit-lift', $url, $deps, $ver);
  }
  // 3) Keyboard-only focus rings (CSS only)
  if (shop_ux_toolkit_opt('keyboard_only_focus')) {
    $rel  = 'assets/css/sb-focus.css';
    $file = plugin_dir_path(__FILE__) . $rel;
    $url  = plugins_url($rel, __FILE__);
    $ver  = file_exists($file) ? (string) filemtime($file) : SHOP_UX_TOOLKIT_VER;
    wp_enqueue_style('shop-ux-toolkit-focus', $url, [], $ver);
  }
  // 4) Blog excerpts "Read More" button (new feature)
  if (shop_ux_toolkit_opt('blog_excerpts_enabled') && (is_home() || is_archive())) {
    $rel  = 'assets/css/sb-snippet-articles.css';
    $file = plugin_dir_path(__FILE__) . $rel;
    $url  = plugins_url($rel, __FILE__);
    $ver  = file_exists($file) ? (string) filemtime($file) : SHOP_UX_TOOLKIT_VER;
    wp_enqueue_style('shop-ux-toolkit-blog-excerpts', $url, $deps, $ver);
  }
  // 5) Reviews-specific styles (conditionally if enabled)
  if (shop_ux_toolkit_opt('enable_reviews')) {
    $rel  = 'assets/css/sb-reviews.css';
    $file = plugin_dir_path(__FILE__) . $rel;
    if (file_exists($file)) {
      $url  = plugins_url($rel, __FILE__);
      $ver  = (string) filemtime($file);
      wp_enqueue_style('shop-ux-toolkit-reviews', $url, $deps, $ver);
    }
  }
  // 6) Item specifics
  $rel = 'assets/css/item-specifics.css';
  $file = plugin_dir_path(__FILE__) . $rel;
  if (file_exists($file)) {
      $url = plugins_url($rel, __FILE__);
      $ver = (string) filemtime($file);
      wp_enqueue_style('shop-ux-toolkit-item-specifics', $url, $deps, $ver);
  }
}, 999);
// Create secure-checkout page on activation if not exists
register_activation_hook(__FILE__, 'shop_ux_toolkit_create_secure_checkout_page');
function shop_ux_toolkit_create_secure_checkout_page() {
  $slug = shop_ux_toolkit_opt('facebook_cart_page_slug');
  if ($slug && !get_page_by_path($slug)) {
    $page = [
      'post_title'   => 'Secure Checkout',
      'post_name'    => $slug,
      'post_status'  => 'publish',
      'post_type'    => 'page',
      'post_content' => '',
    ];
    wp_insert_post($page);
  }
}
// Create reviews page on activation if not exists
register_activation_hook(__FILE__, 'shop_ux_toolkit_create_reviews_page');
function shop_ux_toolkit_create_reviews_page() {
  $slug = shop_ux_toolkit_opt('reviews_page_slug');
  if ($slug && !get_page_by_path($slug)) {
    $page = [
      'post_title'   => 'Reviews',
      'post_name'    => $slug,
      'post_status'  => 'publish',
      'post_type'    => 'page',
      'post_content' => '[display_all_reviews][submit_review_form]',
    ];
    wp_insert_post($page);
  }
}
if (shop_ux_toolkit_opt('enable_item_specifics')) {
    add_filter('woocommerce_product_tabs', function($tabs) {
        if (isset($tabs['additional_information'])) {
            $tabs['additional_information']['title'] = __('Item Specifics', 'shop-ux-toolkit');
        }
        return $tabs;
    }, 20);
    add_filter( 'woocommerce_product_additional_information_heading', function() { return __('Item Specifics', 'shop-ux-toolkit'); } );
}
// Enqueue admin CSS on settings page
add_action('admin_enqueue_scripts', 'shop_ux_toolkit_admin_scripts');
function shop_ux_toolkit_admin_scripts($hook) {
  if ($hook !== 'settings_page_shop_ux_toolkit') return;
  wp_enqueue_style('shop-ux-toolkit-admin', plugins_url('assets/css/admin.css', __FILE__), [], SHOP_UX_TOOLKIT_VER);
}