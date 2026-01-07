<?php
/**
 * The Wooee - Settings
 *
 * This file handles the plugin's settings page and options.
 *
 * @package The Wooee
 * @version 0.2.3
 */
if (!defined('ABSPATH')) exit;
/** =============================== 
 * Settings page (hidden from Settings menu; Plugins row link opens it)
 * ============================== */
add_action('admin_menu', function () {
  add_options_page(
    __('The Wooee', 'the-wooee'),
    __('The Wooee!', 'the-wooee'),
    'manage_options',
    'the-wooee',
    'the_wooee_render_settings_page'
  );
});
// Hide from Settings menu; keep URL working for Plugins-row link
add_action('admin_menu', function () {
  remove_submenu_page('options-general.php', 'the-wooee');
}, 99);
/** =============================== 
 * Save handler (manual form)
 * ============================== */
add_action('admin_post_the_wooee_save', function () {
  if (!current_user_can('manage_options')) {
    wp_die(
      esc_html__('Unauthorized', 'the-wooee'),
      esc_html__('Error', 'the-wooee'),
      ['response' => 403]
    );
  }
  check_admin_referer('the_wooee_save');
  $keys = array_keys(THE_WOOEE_DEFAULTS);
  $new = [];
  foreach ($keys as $k) {
    if ($k === 'reviews') {
      $reviews = [];
      // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
      $reviews_post = wp_unslash($_POST['reviews'] ?? []);
      if (is_array($reviews_post)) {
        foreach ($reviews_post as $review) {
          $author = isset($review['author']) ? sanitize_text_field($review['author']) : '';
          $rating = isset($review['rating']) ? intval($review['rating']) : 0;
          $content = isset($review['content']) ? sanitize_textarea_field($review['content']) : '';
          if (!empty($author) && !empty($content) && $rating >= 1 && $rating <= 5) {
            $reviews[] = [
              'author' => $author,
              'rating' => $rating,
              'content' => $content,
            ];
          }
          if (count($reviews) >= 10) break;
        }
      }
      $new['reviews'] = $reviews;
    } elseif ($k === 'reviews_page_slug' || $k === 'facebook_cart_page_slug') {
      $new[$k] = isset($_POST[$k]) ? sanitize_title(wp_unslash($_POST[$k])) : THE_WOOEE_DEFAULTS[$k];
    } elseif ($k === 'footer_credit_text') {
      $new[$k] = isset($_POST[$k]) ? sanitize_text_field(wp_unslash($_POST[$k])) : THE_WOOEE_DEFAULTS[$k];
    } elseif ($k === 'footer_credit_url') {
      $new[$k] = isset($_POST[$k]) ? esc_url_raw(wp_unslash($_POST[$k])) : THE_WOOEE_DEFAULTS[$k];
    } elseif (in_array($k, ['category_per_page', 'products_limit', 'products_columns'])) {
      $new[$k] = isset($_POST[$k]) ? intval($_POST[$k]) : THE_WOOEE_DEFAULTS[$k];
    } else {
      $new[$k] = isset($_POST[$k]) ? true : false;
    }
  }
  update_option('the_wooee_options', $new);
  wp_safe_redirect(add_query_arg(
    ['page' => 'the-wooee', 'settings-updated' => '1'],
    admin_url('options-general.php')
  ));
  exit;
});
/** =============================== 
 * Render settings page
 * ============================== */
function the_wooee_render_settings_page() {
  if (!current_user_can('manage_options')) return;
  $o = the_wooee_opts();
  $settings_updated = (bool) filter_input(INPUT_GET, 'settings-updated', FILTER_VALIDATE_BOOLEAN);
  $reviews = $o['reviews'] ?? [];
  $current_theme = wp_get_theme();
  $is_storefront = ( 'storefront' === $current_theme->get_stylesheet() || 'storefront' === $current_theme->get_template() );
  $has_login_security = is_plugin_active('login-security-pro/login-security-pro.php');
  ?>
  <div class="wrap">
    <h1><?php echo esc_html__('The Wooee', 'the-wooee'); ?></h1>
    <?php if ($settings_updated): ?>
      <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Settings saved.', 'the-wooee'); ?></p></div>
    <?php endif; ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <?php wp_nonce_field('the_wooee_save'); ?>
      <input type="hidden" name="action" value="the_wooee_save" />
      <table class="form-table" role="presentation">
        <tbody>
          <tr>
            <th scope="row"><?php echo esc_html__('Keyboard-only focus outline (optional)', 'the-wooee'); ?></th>
            <td>
              <label>
                <input type="checkbox" name="keyboard_only_focus" value="1" <?php checked(!empty($o['keyboard_only_focus'])); ?>>
                <?php echo esc_html__('Visible focus ring for keyboard navigation; not shown for mouse/touch.', 'the-wooee'); ?>
              </label>
            </td>
          </tr>
          <tr>
            <th colspan="2"><h2 style="font-size: 2.0em;"><?php echo esc_html__('Storefront Theme Features', 'the-wooee'); ?></h2></th>
          </tr>
          <?php if ($is_storefront): ?>
          <tr>
            <th scope="row"><?php echo esc_html__('Blog excerpts with "Read More" button', 'the-wooee'); ?></th>
            <td>
              <label>
                <input type="checkbox" name="blog_excerpts_enabled" value="1" <?php checked(!empty($o['blog_excerpts_enabled'])); ?>>
                <?php echo esc_html__('Show excerpts on blog and archive pages with a styled "Read More" link.', 'the-wooee'); ?>
              </label>
            </td>
          </tr>
          <?php else: ?>
          <tr>
            <th scope="row"><?php echo esc_html__('Blog excerpts with "Read More" button', 'the-wooee'); ?></th>
            <td>
              <p class="description"><?php echo esc_html__('Requires Storefront theme activated.', 'the-wooee'); ?></p>
            </td>
          </tr>
          <?php endif; ?>
          <?php if ($is_storefront): ?>
          <tr>
              <th scope="row"><?php echo esc_html__('Footer Credit Text', 'the-wooee'); ?></th>
              <td>
                  <input type="text" name="footer_credit_text" value="<?php echo esc_attr($o['footer_credit_text']); ?>">
                  <p class="description"><?php echo esc_html__('Custom text for footer credit link.', 'the-wooee'); ?></p>
              </td>
          </tr>
          <tr>
              <th scope="row"><?php echo esc_html__('Footer Credit URL', 'the-wooee'); ?></th>
              <td>
                  <input type="url" name="footer_credit_url" value="<?php echo esc_attr($o['footer_credit_url']); ?>">
                  <p class="description"><?php echo esc_html__('URL for footer credit link.', 'the-wooee'); ?></p>
              </td>
          </tr>
          <?php else: ?>
          <tr>
              <th scope="row"><?php echo esc_html__('Custom Footer Credit', 'the-wooee'); ?></th>
              <td>
                  <p class="description"><?php echo esc_html__('Requires Storefront theme.', 'the-wooee'); ?></p>
              </td>
          </tr>
          <?php endif; ?>
          <tr>
            <th colspan="2"><h2 style="font-size: 2.0em;"><?php echo esc_html__('WooCommerce Features', 'the-wooee'); ?></h2></th>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Links in shop & category descriptions display properly', 'the-wooee'); ?></th>
            <td>
              <label>
                <input type="checkbox" name="load_links_css" value="1" <?php checked(!empty($o['load_links_css'])); ?>>
                <?php echo esc_html__('Links are underlined; underline not displayed on hover.', 'the-wooee'); ?>
              </label>
            </td>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Hover effect on product & category boxes', 'the-wooee'); ?></th>
            <td>
              <label>
                <input type="checkbox" name="load_lift_css" value="1" <?php checked(!empty($o['load_lift_css'])); ?>>
                <?php echo esc_html__('Adds a soft shadow on hover for depth.', 'the-wooee'); ?>
              </label>
            </td>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Enable Item Specifics', 'the-wooee'); ?></th>
            <td>
              <label>
                <input type="checkbox" name="enable_item_specifics" value="1" <?php checked(!empty($o['enable_item_specifics'])); ?>>
                <?php echo esc_html__('Renames Additional Information tab on product pages to Item Specifics and adds custom editable meta box for product details.', 'the-wooee'); ?>
              </label>
            </td>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Enable Custom Shop Page', 'the-wooee'); ?></th>
            <td>
              <label>
                <input type="checkbox" name="enable_custom_shop" value="1" <?php checked(!empty($o['enable_custom_shop'])); ?>>
                <?php echo esc_html__('Displays custom category rows on the shop page, ordered by category order meta.', 'the-wooee'); ?>
              </label>
            </td>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Categories Per Page (0 for unlimited)', 'the-wooee'); ?></th>
            <td>
              <input type="number" name="category_per_page" value="<?php echo esc_attr($o['category_per_page']); ?>" min="0">
              <p class="description"><?php echo esc_html__('Set to a number greater than 0 to enable pagination.', 'the-wooee'); ?></p>
            </td>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Products Per Category', 'the-wooee'); ?></th>
            <td>
              <input type="number" name="products_limit" value="<?php echo esc_attr($o['products_limit']); ?>" min="1">
            </td>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Product Columns', 'the-wooee'); ?></th>
            <td>
              <input type="number" name="products_columns" value="<?php echo esc_attr($o['products_columns']); ?>" min="1" max="6">
            </td>
          </tr>
          <tr>
            <th colspan="2"><h2 style="font-size: 2.0em;"><?php echo esc_html__('Facebook Cart Integration', 'the-wooee'); ?></h2></th>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Enable Facebook Cart Integration', 'the-wooee'); ?></th>
            <td>
              <label>
                <input type="checkbox" name="enable_facebook_cart" value="1" <?php checked(!empty($o['enable_facebook_cart'])); ?>>
                <?php echo esc_html__('Enable handling of Facebook/Instagram cart redirects.', 'the-wooee'); ?>
              </label>
            </td>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Facebook Cart Page Slug', 'the-wooee'); ?></th>
            <td>
              <input type="text" name="facebook_cart_page_slug" value="<?php echo esc_attr($o['facebook_cart_page_slug']); ?>">
              <p class="description"><?php echo esc_html__('Enter custom slug for the Facebook cart page; page created on activation if missing.', 'the-wooee'); ?></p>
            </td>
          </tr>
          <tr>
            <th colspan="2"><h2 style="font-size: 2.0em;"><?php echo esc_html__('Reviews Page', 'the-wooee'); ?></h2></th>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Enable Reviews Page', 'the-wooee'); ?></th>
            <td>
              <label>
                <input type="checkbox" name="enable_reviews" value="1" <?php checked(!empty($o['enable_reviews'])); ?>>
                <?php echo esc_html__('This is a page that allows your customers to leave reviews and lists up to 10 reviews of your choice. Example: https://www.smokingblends.com/reviews/', 'the-wooee'); ?>
              </label>
              <p class="description"><?php echo esc_html__('Note: Sites with over 5000 products might experience some slowdown on the reviews page due to loading all products in the dropdown.', 'the-wooee'); ?></p>
            </td>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Reviews Page Slug', 'the-wooee'); ?></th>
            <td>
              <input type="text" name="reviews_page_slug" value="<?php echo esc_attr($o['reviews_page_slug']); ?>">
              <p class="description"><?php echo esc_html__('Enter custom slug for the reviews page; page created on activation if missing.', 'the-wooee'); ?></p>
            </td>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Enable Turnstile CAPTCHA', 'the-wooee'); ?></th>
            <td>
              <label>
                <input type="checkbox" name="enable_turnstile" value="1" <?php checked(!empty($o['enable_turnstile'])); ?> <?php disabled(!$has_login_security); ?>>
                <?php echo esc_html__('Add Turnstile CAPTCHA to review form (requires Login Security Pro plugin).', 'the-wooee'); ?>
              </label>
              <?php if (!$has_login_security): ?>
                <p class="description"><?php echo esc_html__('Install and activate Login Security Pro to enable.', 'the-wooee'); ?></p>
              <?php endif; ?>
            </td>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Manage Reviews (up to 10)', 'the-wooee'); ?></th>
            <td>
              <?php for ($i = 0; $i < 10; $i++): ?>
                <div class="review-row" style="margin-bottom: 20px; border-bottom: 1px solid #ddd; padding-bottom: 20px;">
                  <p>
                    <label><?php echo esc_html__('Author', 'the-wooee'); ?></label><br>
                    <input type="text" name="reviews[<?php echo esc_html($i); ?>][author]" value="<?php echo esc_attr($reviews[$i]['author'] ?? ''); ?>">
                  </p>
                  <p>
                    <label><?php echo esc_html__('Rating', 'the-wooee'); ?></label><br>
                    <select name="reviews[<?php echo esc_html($i); ?>][rating]">
                      <?php for ($r = 1; $r <= 5; $r++): ?>
                        <option value="<?php echo esc_attr($r); ?>" <?php selected($reviews[$i]['rating'] ?? 0, $r); ?>><?php echo esc_html($r); ?></option>
                      <?php endfor; ?>
                    </select>
                  </p>
                  <p>
                    <label><?php echo esc_html__('Content', 'the-wooee'); ?></label><br>
                    <textarea name="reviews[<?php echo esc_html($i); ?>][content]"><?php echo esc_textarea($reviews[$i]['content'] ?? ''); ?></textarea>
                  </p>
                </div>
              <?php endfor; ?>
              <p class="description"><?php echo esc_html__('Fill in up to 10 reviews to display on the reviews page.', 'the-wooee'); ?></p>
            </td>
          </tr>
        </tbody>
      </table>
      <?php submit_button(); ?>
    </form>
  </div>
  <?php
}

// Add settings link on plugins page
add_filter('plugin_action_links_' . plugin_basename(THE_WOOEE_PLUGIN_FILE), function ($links) {
  $settings_link = '<a href="' . admin_url('options-general.php?page=the-wooee') . '">' . esc_html__('Settings', 'the-wooee') . '</a>';
  array_unshift($links, $settings_link);
  return $links;
});