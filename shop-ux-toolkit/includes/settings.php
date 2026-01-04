<?php
/**
 * Shop UX Toolkit - Settings
 *
 * This file handles the plugin's settings page and options.
 *
 * @package Shop UX Toolkit
 * @version 0.2.2
 */
if (!defined('ABSPATH')) exit;
/** ===============================
 * Settings page (hidden from Settings menu; Plugins row link opens it)
 * =============================== */
add_action('admin_menu', function () {
  add_options_page(
    __('Shop UX Toolkit', 'shop-ux-toolkit'),
    __('Shop UX Toolkit', 'shop-ux-toolkit'),
    'manage_options',
    'shop_ux_toolkit',
    'shop_ux_toolkit_render_settings_page'
  );
});
// Hide from Settings menu; keep URL working for Plugins-row link
add_action('admin_menu', function () {
  remove_submenu_page('options-general.php', 'shop_ux_toolkit');
}, 99);
/** ===============================
 * Save handler (manual form)
 * =============================== */
add_action('admin_post_shop_ux_toolkit_save', function () {
  if (!current_user_can('manage_options')) {
    wp_die(
      esc_html__('Unauthorized', 'shop-ux-toolkit'),
      esc_html__('Error', 'shop-ux-toolkit'),
      ['response' => 403]
    );
  }
  check_admin_referer('shop_ux_toolkit_save');
  $keys = array_keys(SHOP_UX_TOOLKIT_DEFAULTS);
  $new = [];
  foreach ($keys as $k) {
    if ($k === 'reviews') {
      $reviews = [];
      for ($i = 0; $i < 10; $i++) { // Fixed 10 slots
        $author = isset($_POST['review_author'][$i]) ? sanitize_text_field(wp_unslash($_POST['review_author'][$i])) : '';
        $rating = isset($_POST['review_rating'][$i]) ? intval($_POST['review_rating'][$i]) : 0;
        $content = isset($_POST['review_content'][$i]) ? sanitize_textarea_field(wp_unslash($_POST['review_content'][$i])) : '';
        if (!empty($author) && !empty($content) && $rating >= 1 && $rating <= 5) {
          $reviews[] = [
            'author' => $author,
            'rating' => $rating,
            'content' => $content,
          ];
        }
      }
      $new['reviews'] = $reviews;
    } elseif ($k === 'reviews_page_slug' || $k === 'facebook_cart_page_slug') {
      $new[$k] = isset($_POST[$k]) ? sanitize_title(wp_unslash($_POST[$k])) : SHOP_UX_TOOLKIT_DEFAULTS[$k];
    } elseif ($k === 'footer_credit_text') {
      $new[$k] = isset($_POST[$k]) ? sanitize_text_field(wp_unslash($_POST[$k])) : SHOP_UX_TOOLKIT_DEFAULTS[$k];
    } elseif ($k === 'footer_credit_url') {
      $new[$k] = isset($_POST[$k]) ? esc_url_raw(wp_unslash($_POST[$k])) : SHOP_UX_TOOLKIT_DEFAULTS[$k];
    } else {
      $new[$k] = isset($_POST[$k]) ? true : false;
    }
  }
  update_option('shop_ux_toolkit_options', $new);
  wp_safe_redirect(add_query_arg(
    ['page' => 'shop_ux_toolkit', 'settings-updated' => '1'],
    admin_url('options-general.php')
  ));
  exit;
});
/** ===============================
 * Render settings page
 * =============================== */
function shop_ux_toolkit_render_settings_page() {
  if (!current_user_can('manage_options')) return;
  $o = shop_ux_toolkit_opts();
  $settings_updated = (bool) filter_input(INPUT_GET, 'settings-updated', FILTER_VALIDATE_BOOLEAN);
  $reviews = $o['reviews'] ?? [];
  $current_theme = wp_get_theme();
  $is_storefront = ( 'storefront' === $current_theme->get_stylesheet() || 'storefront' === $current_theme->get_template() );
  $has_login_security = is_plugin_active('login-security-pro/login-security-pro.php');
  ?>
  <div class="wrap">
    <h1><?php echo esc_html__('Shop UX Toolkit', 'shop-ux-toolkit'); ?></h1>
    <?php if ($settings_updated): ?>
      <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Settings saved.', 'shop-ux-toolkit'); ?></p></div>
    <?php endif; ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <?php wp_nonce_field('shop_ux_toolkit_save'); ?>
      <input type="hidden" name="action" value="shop_ux_toolkit_save" />
      <table class="form-table" role="presentation">
        <tbody>
          <tr>
            <th scope="row"><?php echo esc_html__('Keyboard-only focus outline (optional)', 'shop-ux-toolkit'); ?></th>
            <td>
              <label>
                <input type="checkbox" name="keyboard_only_focus" value="1" <?php checked(!empty($o['keyboard_only_focus'])); ?>>
                <?php echo esc_html__('Visible focus ring for keyboard navigation; not shown for mouse/touch.', 'shop-ux-toolkit'); ?>
              </label>
            </td>
          </tr>
          <tr>
            <th colspan="2"><h2 style="font-size: 2.0em;"><?php echo esc_html__('Storefront Theme Features', 'shop-ux-toolkit'); ?></h2></th>
          </tr>
          <?php if ($is_storefront): ?>
          <tr>
            <th scope="row"><?php echo esc_html__('Blog excerpts with "Read More" button', 'shop-ux-toolkit'); ?></th>
            <td>
              <label>
                <input type="checkbox" name="blog_excerpts_enabled" value="1" <?php checked(!empty($o['blog_excerpts_enabled'])); ?>>
                <?php echo esc_html__('Show excerpts on blog and archive pages with a styled "Read More" link.', 'shop-ux-toolkit'); ?>
              </label>
            </td>
          </tr>
          <?php else: ?>
          <tr>
            <th scope="row"><?php echo esc_html__('Blog excerpts with "Read More" button', 'shop-ux-toolkit'); ?></th>
            <td>
              <p class="description"><?php echo esc_html__('Requires Storefront theme activated.', 'shop-ux-toolkit'); ?></p>
            </td>
          </tr>
          <?php endif; ?>
          <?php if ($is_storefront): ?>
          <tr>
              <th scope="row"><?php echo esc_html__('Footer Credit Text', 'shop-ux-toolkit'); ?></th>
              <td>
                  <input type="text" name="footer_credit_text" value="<?php echo esc_attr($o['footer_credit_text']); ?>">
                  <p class="description"><?php echo esc_html__('Custom text for footer credit link.', 'shop-ux-toolkit'); ?></p>
              </td>
          </tr>
          <tr>
              <th scope="row"><?php echo esc_html__('Footer Credit URL', 'shop-ux-toolkit'); ?></th>
              <td>
                  <input type="url" name="footer_credit_url" value="<?php echo esc_attr($o['footer_credit_url']); ?>">
                  <p class="description"><?php echo esc_html__('URL for footer credit link.', 'shop-ux-toolkit'); ?></p>
              </td>
          </tr>
          <?php else: ?>
          <tr>
              <th scope="row"><?php echo esc_html__('Custom Footer Credit', 'shop-ux-toolkit'); ?></th>
              <td>
                  <p class="description"><?php echo esc_html__('Requires Storefront theme.', 'shop-ux-toolkit'); ?></p>
              </td>
          </tr>
          <?php endif; ?>
          <tr>
            <th colspan="2"><h2 style="font-size: 2.0em;"><?php echo esc_html__('WooCommerce Features', 'shop-ux-toolkit'); ?></h2></th>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Links in shop & category descriptions display properly', 'shop-ux-toolkit'); ?></th>
            <td>
              <label>
                <input type="checkbox" name="load_links_css" value="1" <?php checked(!empty($o['load_links_css'])); ?>>
                <?php echo esc_html__('Links are underlined; underline not displayed on hover.', 'shop-ux-toolkit'); ?>
              </label>
            </td>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Hover effect on product & category boxes', 'shop-ux-toolkit'); ?></th>
            <td>
              <label>
                <input type="checkbox" name="load_lift_css" value="1" <?php checked(!empty($o['load_lift_css'])); ?>>
                <?php echo esc_html__('Adds a soft shadow on hover for depth.', 'shop-ux-toolkit'); ?>
              </label>
            </td>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Enable Item Specifics', 'shop-ux-toolkit'); ?></th>
            <td>
              <label>
                <input type="checkbox" name="enable_item_specifics" value="1" <?php checked(!empty($o['enable_item_specifics'])); ?>>
                <?php echo esc_html__('Renames Additional Information tab on product pages to Item Specifics and adds custom editable meta box for product details.', 'shop-ux-toolkit'); ?>
              </label>
            </td>
          </tr>
          <tr>
            <th colspan="2"><h2><?php echo esc_html__('Facebook Cart Integration', 'shop-ux-toolkit'); ?></h2></th>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Enable Facebook Cart Integration', 'shop-ux-toolkit'); ?></th>
            <td>
              <label>
                <input type="checkbox" name="enable_facebook_cart" value="1" <?php checked(!empty($o['enable_facebook_cart'])); ?>>
                <?php echo esc_html__('Enable handling of Facebook/Instagram cart redirects.', 'shop-ux-toolkit'); ?>
              </label>
            </td>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Facebook Cart Page Slug', 'shop-ux-toolkit'); ?></th>
            <td>
              <input type="text" name="facebook_cart_page_slug" value="<?php echo esc_attr($o['facebook_cart_page_slug']); ?>">
              <p class="description"><?php echo esc_html__('Enter custom slug for the Facebook cart page; page created on activation if missing.', 'shop-ux-toolkit'); ?></p>
            </td>
          </tr>
          <tr>
            <th colspan="2"><h2><?php echo esc_html__('Reviews Page', 'shop-ux-toolkit'); ?></h2></th>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Enable Reviews Page', 'shop-ux-toolkit'); ?></th>
            <td>
              <label>
                <input type="checkbox" name="enable_reviews" value="1" <?php checked(!empty($o['enable_reviews'])); ?>>
                <?php echo esc_html__('This is a page that allows your customers to leave reviews and lists up to 10 reviews of your choice. Example: https://www.smokingblends.com/reviews/', 'shop-ux-toolkit'); ?>
              </label>
              <p class="description"><?php echo esc_html__('Note: Sites with over 5000 products might experience some slowdown on the reviews page due to loading all products in the dropdown.', 'shop-ux-toolkit'); ?></p>
            </td>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Reviews Page Slug', 'shop-ux-toolkit'); ?></th>
            <td>
              <input type="text" name="reviews_page_slug" value="<?php echo esc_attr($o['reviews_page_slug']); ?>">
              <p class="description"><?php echo esc_html__('Enter custom slug for the reviews page; page created on activation if missing.', 'shop-ux-toolkit'); ?></p>
            </td>
          </tr>
          <?php if ($has_login_security): ?>
          <tr>
            <th scope="row"><?php echo esc_html__('Enable Turnstile/Google Captcha', 'shop-ux-toolkit'); ?></th>
            <td>
              <label>
                <input type="checkbox" name="enable_turnstile" value="1" <?php checked(!empty($o['enable_turnstile'])); ?>>
                <?php echo esc_html__('Enable Turnstile or Google Captcha for review submission (requires Login Security Pro plugin installed and configured).', 'shop-ux-toolkit'); ?>
              </label>
            </td>
          </tr>
          <?php else: ?>
          <tr>
            <th scope="row"><?php echo esc_html__('Enable Turnstile/Google Captcha', 'shop-ux-toolkit'); ?></th>
            <td>
              <p class="description"><?php echo esc_html__('Requires Login Security Pro plugin installed and activated.', 'shop-ux-toolkit'); ?></p>
            </td>
          </tr>
          <?php endif; ?>
          <tr>
            <th scope="row"><?php echo esc_html__('Manage Reviews', 'shop-ux-toolkit'); ?></th>
            <td>
              <h3><?php echo esc_html__('Review Fields', 'shop-ux-toolkit'); ?></h3>
              <?php
              for ($i = 0; $i < 10; $i++) { // Fixed 10 slots
                $review = isset($reviews[$i]) ? $reviews[$i] : ['author' => '', 'rating' => 5, 'content' => ''];
                ?>
                <div class="review-row">
                  <p>
                    <label><?php echo esc_html__('Author', 'shop-ux-toolkit') . ' ' . esc_html($i + 1); ?>:</label>
                    <input type="text" name="review_author[<?php echo esc_attr($i); ?>]" value="<?php echo esc_attr($review['author']); ?>">
                  </p>
                  <p>
                    <label><?php echo esc_html__('Rating', 'shop-ux-toolkit') . ' ' . esc_html($i + 1); ?>:</label>
                    <select name="review_rating[<?php echo esc_attr($i); ?>]">
                      <option value="5" <?php selected(5, $review['rating']); ?>>★★★★★</option>
                      <option value="4" <?php selected(4, $review['rating']); ?>>★★★★☆</option>
                      <option value="3" <?php selected(3, $review['rating']); ?>>★★★☆☆</option>
                      <option value="2" <?php selected(2, $review['rating']); ?>>★★☆☆☆</option>
                      <option value="1" <?php selected(1, $review['rating']); ?>>★☆☆☆☆</option>
                    </select>
                  </p>
                  <p>
                    <label><?php echo esc_html__('Review Text', 'shop-ux-toolkit') . ' ' . esc_html($i + 1); ?>:</label>
                    <textarea name="review_content[<?php echo esc_attr($i); ?>]"><?php echo esc_textarea($review['content']); ?></textarea>
                  </p>
                  <hr>
                </div>
                <?php
              }
              ?>
            </td>
          </tr>
        </tbody>
      </table>
      <?php submit_button(__('Save Changes', 'shop-ux-toolkit')); ?>
    </form>
    <p style="margin-top:1rem;color:#555;">
      <?php echo esc_html__('Privacy: This plugin does not collect, store, or transmit any personal data and makes no external requests.', 'shop-ux-toolkit'); ?>
    </p>
  </div>
  <?php
}
/** ===============================
 * Plugins screen links
 * =============================== */
add_filter('plugin_action_links_' . plugin_basename(SHOP_UX_TOOLKIT_PLUGIN_FILE), function ($links) {
  $url = admin_url('options-general.php?page=shop_ux_toolkit');
  array_unshift($links, '<a href="' . esc_url($url) . '">' . esc_html__('Settings', 'shop-ux-toolkit') . '</a>');
  return $links;
});
add_filter('plugin_row_meta', function ($links, $file) {
  if ($file !== plugin_basename(SHOP_UX_TOOLKIT_PLUGIN_FILE)) return $links;
  $links[] = '<a href="https://wordpress.org/plugins/shop-ux-toolkit/#faq" target="_blank" rel="noopener">' . esc_html__('FAQ', 'shop-ux-toolkit') . '</a>';
  $links[] = '<a href="https://wordpress.org/support/plugin/shop-ux-toolkit/" target="_blank" rel="noopener">' . esc_html__('Support', 'shop-ux-toolkit') . '</a>';
  return $links;
}, 10, 2);