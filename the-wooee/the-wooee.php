<?php
/*
Plugin Name: The Wooee
Description: Enhances WooCommerce UX with link styling, hover effects, keyboard focus, blog excerpts (Storefront only), reviews page, Facebook integration, and item specifics.
Version: 0.3.1
Author: Smoking Blends
Author URI: https://www.smokingblends.com/
Text Domain: the-wooee
Requires at least: 6.0
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/
if (!defined('ABSPATH')) exit;
define('THE_WOOEE_VER', '0.3.1');
define('THE_WOOEE_PLUGIN_FILE', __FILE__);
/** ==============================
 * Options (defaults + helpers)
 * ============================== */
const THE_WOOEE_DEFAULTS = [
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
function the_wooee_opts() {
  $saved = get_option('the_wooee_options');
  if (!is_array($saved)) $saved = [];
  return array_merge(THE_WOOEE_DEFAULTS, $saved);
}
function the_wooee_opt($key) {
  $o = the_wooee_opts();
  return isset($o[$key]) ? $o[$key] : (THE_WOOEE_DEFAULTS[$key] ?? null);
}
// Include the blog adjustments file conditionally
$the_wooee_theme = wp_get_theme();
$the_wooee_is_storefront = ( 'storefront' === $the_wooee_theme->get_stylesheet() || 'storefront' === $the_wooee_theme->get_template() );
if (the_wooee_opt('blog_excerpts_enabled') && $the_wooee_is_storefront) {
  require_once plugin_dir_path(__FILE__) . 'includes/blog-adjustments.php';
}
// Include custom shop conditionally
if (the_wooee_opt('enable_custom_shop')) {
  require_once plugin_dir_path(__FILE__) . 'includes/custom-shop.php';
}
// Include the settings file
require_once plugin_dir_path(__FILE__) . 'includes/settings.php';
// Include the reviews file conditionally
if (the_wooee_opt('enable_reviews')) {
  require_once plugin_dir_path(__FILE__) . 'includes/reviews.php';
}
// Include the checkout-facebook file conditionally
if (the_wooee_opt('enable_facebook_cart')) {
  require_once plugin_dir_path(__FILE__) . 'includes/checkout-facebook.php';
}
// Include the item specifics file
require_once plugin_dir_path(__FILE__) . 'includes/item-specifics.php';
add_action('after_setup_theme', function() {
  global $the_wooee_is_storefront;
  if ($the_wooee_is_storefront && the_wooee_opt('footer_credit_text') && the_wooee_opt('footer_credit_url')) {
      remove_action('storefront_footer', 'storefront_credit', 20);
      add_action('storefront_footer', 'the_wooee_custom_storefront_credit', 20);
  }
});
function the_wooee_custom_storefront_credit() {
    $credit_text = the_wooee_opt('footer_credit_text');
    $credit_url = the_wooee_opt('footer_credit_url');
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
  if ( is_shop() && the_wooee_opt('enable_custom_shop') ) {
    $per_page = (int) the_wooee_opt('category_per_page');
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
add_action('wp_enqueue_scripts', 'the_wooee_enqueue_wc_styles_for_review_shortcode');
function the_wooee_enqueue_wc_styles_for_review_shortcode() {
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
  if (the_wooee_opt('load_links_css')) {
    $rel  = 'assets/css/links.css';
    $file = plugin_dir_path(__FILE__) . $rel;
    $url  = plugins_url($rel, __FILE__);
    $ver  = file_exists($file) ? (string) filemtime($file) : THE_WOOEE_VER;
    wp_enqueue_style('the-wooee-links', $url, $deps, $ver);
  }
  // 2) Hover highlight on product & category boxes
  if (the_wooee_opt('load_lift_css')) {
    $rel  = 'assets/css/lift.css';
    $file = plugin_dir_path(__FILE__) . $rel;
    $url  = plugins_url($rel, __FILE__);
    $ver  = file_exists($file) ? (string) filemtime($file) : THE_WOOEE_VER;
    wp_enqueue_style('the-wooee-lift', $url, $deps, $ver);
  }
  // 3) Keyboard-only focus rings (CSS only)
  if (the_wooee_opt('keyboard_only_focus')) {
    $rel  = 'assets/css/focus.css';
    $file = plugin_dir_path(__FILE__) . $rel;
    $url  = plugins_url($rel, __FILE__);
    $ver  = file_exists($file) ? (string) filemtime($file) : THE_WOOEE_VER;
    wp_enqueue_style('the-wooee-focus', $url, [], $ver);
  }
  // 4) Blog excerpts "Read More" button (new feature)
  if (the_wooee_opt('blog_excerpts_enabled') && (is_home() || is_archive())) {
    $rel  = 'assets/css/snippet-articles.css';
    $file = plugin_dir_path(__FILE__) . $rel;
    $url  = plugins_url($rel, __FILE__);
    $ver  = file_exists($file) ? (string) filemtime($file) : THE_WOOEE_VER;
    wp_enqueue_style('the-wooee-blog-excerpts', $url, $deps, $ver);
  }
  // 5) Reviews-specific styles (conditionally if enabled)
  if (the_wooee_opt('enable_reviews')) {
    $rel  = 'assets/css/reviews.css';
    $file = plugin_dir_path(__FILE__) . $rel;
    if (file_exists($file)) {
      $url  = plugins_url($rel, __FILE__);
      $ver  = (string) filemtime($file);
      wp_enqueue_style('the-wooee-reviews', $url, $deps, $ver);
    }
  }
  // 6) Item specifics
  $rel = 'assets/css/item-specifics.css';
  $file = plugin_dir_path(__FILE__) . $rel;
  if (file_exists($file)) {
      $url = plugins_url($rel, __FILE__);
      $ver = (string) filemtime($file);
      wp_enqueue_style('the-wooee-item-specifics', $url, $deps, $ver);
  }
  // 7) Custom shop styles
  if (the_wooee_opt('enable_custom_shop') && is_shop()) {
    $rel = 'assets/css/custom-shop.css';
    $file = plugin_dir_path(__FILE__) . $rel;
    if (file_exists($file)) {
      $url = plugins_url($rel, __FILE__);
      $ver = (string) filemtime($file);
      wp_enqueue_style('the-wooee-custom-shop', $url, $deps, $ver);
    }
  }
}, 999);
// Create secure-checkout page on activation if not exists
register_activation_hook(__FILE__, 'the_wooee_create_secure_checkout_page');
function the_wooee_create_secure_checkout_page() {
  $slug = the_wooee_opt('facebook_cart_page_slug');
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
register_activation_hook(__FILE__, 'the_wooee_create_reviews_page');
function the_wooee_create_reviews_page() {
  $slug = the_wooee_opt('reviews_page_slug');
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
if (the_wooee_opt('enable_item_specifics')) {
    add_filter('woocommerce_product_tabs', function($tabs) {
        if (isset($tabs['additional_information'])) {
            $tabs['additional_information']['title'] = __('Item Specifics', 'the-wooee');
        }
        return $tabs;
    }, 20);
    add_filter( 'woocommerce_product_additional_information_heading', function() { return __('Item Specifics', 'the-wooee'); } );
}
// Enqueue admin CSS on settings page
add_action('admin_enqueue_scripts', 'the_wooee_admin_scripts');
function the_wooee_admin_scripts($hook) {
  if ($hook !== 'settings_page_the-wooee') return;
  wp_enqueue_style('the-wooee-admin', plugins_url('assets/css/admin.css', __FILE__), [], THE_WOOEE_VER);
}