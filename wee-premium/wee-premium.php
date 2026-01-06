<?php
/*
Plugin Name: The Wooee! Premium Upgrades with Storefront Polishes for WooCommerce
Description: Enhances WooCommerce UX with link styling, hover effects, keyboard focus, blog excerpts (Storefront only), reviews page, Facebook integration, and item specifics.
Version: 0.3.1
Author: Smoking Blends
Author URI: https://www.smokingblends.com/
Text Domain: wee-premium
Requires at least: 6.0
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/
if (!defined('ABSPATH')) exit;
define('WEE_PREMIUM_VER', '0.3.1');
define('WEE_PREMIUM_PLUGIN_FILE', __FILE__);
/** ==============================
 * Options (defaults + helpers)
 * ============================== */
const WEE_PREMIUM_DEFAULTS = [
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
  'enable_item_specifics' => false,
  'enable_custom_shop' => false,
  'category_per_page' => 0,
  'products_limit' => 4,
  'products_columns' => 4,
];
function wee_premium_opts() {
  $saved = get_option('wee_premium_options');
  if (!is_array($saved)) $saved = [];
  return array_merge(WEE_PREMIUM_DEFAULTS, $saved);
}
function wee_premium_opt($key) {
  $o = wee_premium_opts();
  return isset($o[$key]) ? $o[$key] : (WEE_PREMIUM_DEFAULTS[$key] ?? null);
}
// Include the blog adjustments file conditionally
$wee_premium_theme = wp_get_theme();
$wee_premium_is_storefront = ( 'storefront' === $wee_premium_theme->get_stylesheet() || 'storefront' === $wee_premium_theme->get_template() );
if (wee_premium_opt('blog_excerpts_enabled') && $wee_premium_is_storefront) {
  require_once plugin_dir_path(__FILE__) . 'includes/blog-adjustments.php';
}
// Include custom shop conditionally
if (wee_premium_opt('enable_custom_shop')) {
  require_once plugin_dir_path(__FILE__) . 'includes/custom-shop.php';
}
// Include the settings file
require_once plugin_dir_path(__FILE__) . 'includes/settings.php';
// Include the reviews file conditionally
if (wee_premium_opt('enable_reviews')) {
  require_once plugin_dir_path(__FILE__) . 'includes/reviews.php';
}
// Include the checkout-facebook file conditionally
if (wee_premium_opt('enable_facebook_cart')) {
  require_once plugin_dir_path(__FILE__) . 'includes/checkout-facebook.php';
}
// Include the item specifics file
require_once plugin_dir_path(__FILE__) . 'includes/item-specifics.php';
add_action('after_setup_theme', function() {
  global $wee_premium_is_storefront;
  if ($wee_premium_is_storefront && wee_premium_opt('footer_credit_text') && wee_premium_opt('footer_credit_url')) {
      remove_action('storefront_footer', 'storefront_credit', 20);
      add_action('storefront_footer', 'wee_premium_custom_storefront_credit', 20);
  }
});
function wee_premium_custom_storefront_credit() {
    $credit_text = wee_premium_opt('footer_credit_text');
    $credit_url = wee_premium_opt('footer_credit_url');
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
  if ( is_shop() && wee_premium_opt('enable_custom_shop') ) {
    $per_page = (int) wee_premium_opt('category_per_page');
    $paged = get_query_var( 'paged' ) ?: 1;
    if ( $per_page > 0 && $paged > 1 ) {
      $args = [
        'taxonomy'   => 'product_cat',
        'hide_empty' => true,
        'parent'     => 0,
        'fields'     => 'count',
      ];
      $total = get_terms( $args );
      $total_pages = ceil( $total / $per_page );
      if ( $paged > $total_pages ) {
        global $wp_query;
        $wp_query->set_404();
        status_header( 404 );
        nocache_headers();
      }
    }
  }
} );
/** ======================================
 * STOP empty paginated shop pages END
 * ====================================== */
// Enqueue WooCommerce styles and scripts (consider moving this add_action to your main plugin file for better organization)
add_action('wp_enqueue_scripts', 'wee_premium_enqueue_wc_styles_for_review_shortcode');
function wee_premium_enqueue_wc_styles_for_review_shortcode() {
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
  if (wee_premium_opt('load_links_css')) {
    $rel  = 'assets/css/links.css';
    $file = plugin_dir_path(__FILE__) . $rel;
    $url  = plugins_url($rel, __FILE__);
    $ver  = file_exists($file) ? (string) filemtime($file) : WEE_PREMIUM_VER;
    wp_enqueue_style('wee-premium-links', $url, $deps, $ver);
  }
  // 2) Hover highlight on product & category boxes
  if (wee_premium_opt('load_lift_css')) {
    $rel  = 'assets/css/lift.css';
    $file = plugin_dir_path(__FILE__) . $rel;
    $url  = plugins_url($rel, __FILE__);
    $ver  = file_exists($file) ? (string) filemtime($file) : WEE_PREMIUM_VER;
    wp_enqueue_style('wee-premium-lift', $url, $deps, $ver);
  }
  // 3) Keyboard-only focus rings (CSS only)
  if (wee_premium_opt('keyboard_only_focus')) {
    $rel  = 'assets/css/focus.css';
    $file = plugin_dir_path(__FILE__) . $rel;
    $url  = plugins_url($rel, __FILE__);
    $ver  = file_exists($file) ? (string) filemtime($file) : WEE_PREMIUM_VER;
    wp_enqueue_style('wee-premium-focus', $url, [], $ver);
  }
  // 4) Blog excerpts "Read More" button (new feature)
  if (wee_premium_opt('blog_excerpts_enabled') && (is_home() || is_archive())) {
    $rel  = 'assets/css/snippet-articles.css';
    $file = plugin_dir_path(__FILE__) . $rel;
    $url  = plugins_url($rel, __FILE__);
    $ver  = file_exists($file) ? (string) filemtime($file) : WEE_PREMIUM_VER;
    wp_enqueue_style('wee-premium-blog-excerpts', $url, $deps, $ver);
  }
  // 5) Reviews-specific styles (conditionally if enabled)
  if (wee_premium_opt('enable_reviews')) {
    $rel  = 'assets/css/reviews.css';
    $file = plugin_dir_path(__FILE__) . $rel;
    if (file_exists($file)) {
      $url  = plugins_url($rel, __FILE__);
      $ver  = (string) filemtime($file);
      wp_enqueue_style('wee-premium-reviews', $url, $deps, $ver);
    }
  }
  // 6) Item specifics
  $rel = 'assets/css/item-specifics.css';
  $file = plugin_dir_path(__FILE__) . $rel;
  if (file_exists($file)) {
      $url = plugins_url($rel, __FILE__);
      $ver = (string) filemtime($file);
      wp_enqueue_style('wee-premium-item-specifics', $url, $deps, $ver);
  }
  // 7) Custom shop styles
  if (wee_premium_opt('enable_custom_shop') && is_shop()) {
    $rel = 'assets/css/custom-shop.css';
    $file = plugin_dir_path(__FILE__) . $rel;
    if (file_exists($file)) {
      $url = plugins_url($rel, __FILE__);
      $ver = (string) filemtime($file);
      wp_enqueue_style('wee-premium-custom-shop', $url, $deps, $ver);
    }
  }
}, 999);
// Create secure-checkout page on activation if not exists
register_activation_hook(__FILE__, 'wee_premium_create_secure_checkout_page');
function wee_premium_create_secure_checkout_page() {
  $slug = wee_premium_opt('facebook_cart_page_slug');
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
register_activation_hook(__FILE__, 'wee_premium_create_reviews_page');
function wee_premium_create_reviews_page() {
  $slug = wee_premium_opt('reviews_page_slug');
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
if (wee_premium_opt('enable_item_specifics')) {
    add_filter('woocommerce_product_tabs', function($tabs) {
        if (isset($tabs['additional_information'])) {
            $tabs['additional_information']['title'] = __('Item Specifics', 'wee-premium');
        }
        return $tabs;
    }, 20);
    add_filter( 'woocommerce_product_additional_information_heading', function() { return __('Item Specifics', 'wee-premium'); } );
}
// Enqueue admin CSS on settings page
add_action('admin_enqueue_scripts', 'wee_premium_admin_scripts');
function wee_premium_admin_scripts($hook) {
  if ($hook !== 'settings_page_wee-premium') return;
  wp_enqueue_style('wee-premium-admin', plugins_url('assets/css/admin.css', __FILE__), [], WEE_PREMIUM_VER);
}