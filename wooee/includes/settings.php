<?php
/**
 * Wooee - Settings
 *
 * This file handles the plugin's settings page and options.
 *
 * @package Wooee
 * @version 0.2.2
 */
if (!defined('ABSPATH')) exit;
/** ===============================
 * Settings page (hidden from Settings menu; Plugins row link opens it)
 * =============================== */
add_action('admin_menu', function () {
  add_options_page(
    __('Wooee', 'wooee'),
    __('Wooee', 'wooee'),
    'manage_options',
    'wooee',
    'wooee_render_settings_page'
  );
});
// Hide from Settings menu; keep URL working for Plugins-row link
add_action('admin_menu', function () {
  remove_submenu_page('options-general.php', 'wooee');
}, 99);
/** ===============================
 * Save handler (manual form)
 * =============================== */
add_action('admin_post_wooee_save', function () {
  if (!current_user_can('manage_options')) {
    wp_die(
      esc_html__('Unauthorized', 'wooee'),
      esc_html__('Error', 'wooee'),
      ['response' => 403]
    );
  }
  check_admin_referer('wooee_save');
  $keys = array_keys(WOOEE_DEFAULTS);
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
      $new[$k] = isset($_POST[$k]) ? sanitize_title(wp_unslash($_POST[$k])) : WOOEE_DEFAULTS[$k];
    } elseif ($k === 'footer_credit_text') {
      $new[$k] = isset($_POST[$k]) ? sanitize_text_field(wp_unslash($_POST[$k])) : WOOEE_DEFAULTS[$k];
    } elseif ($k === 'footer_credit_url') {
      $new[$k] = isset($_POST[$k]) ? esc_url_raw(wp_unslash($_POST[$k])) : WOOEE_DEFAULTS[$k];
    } else {
      $new[$k] = isset($_POST[$k]) ? true : false;
    }
  }
  update_option('wooee_options', $new);
  wp_safe_redirect(add_query_arg(
    ['page' => 'wooee', 'settings-updated' => '1'],
    admin_url('options-general.php')
  ));
  exit;
});
/** ===============================
 * Render settings page
 * =============================== */
function wooee_render_settings_page() {
  if (!current_user_can('manage_options')) return;
  $o = wooee_opts();
  $settings_updated = (bool) filter_input(INPUT_GET, 'settings-updated', FILTER_VALIDATE_BOOLEAN);
  $reviews = $o['reviews'] ?? [];
  $current_theme = wp_get_theme();
  $is_storefront = ( 'storefront' === $current_theme->get_stylesheet() || 'storefront' === $current_theme->get_template() );
  $has_login_security = is_plugin_active('login-security-pro/login-security-pro.php');
  ?>
  <div class="wrap">
    <h1><?php echo esc_html__('Wooee', 'wooee'); ?></h1>
    <?php if ($settings_updated): ?>
      <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Settings saved.', 'wooee'); ?></p></div>
    <?php endif; ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <?php wp_nonce_field('wooee_save'); ?>
      <input type="hidden" name="action" value="wooee_save" />
      <table class="form-table" role="presentation">
        <tbody>
          <tr>
            <th scope="row"><?php echo esc_html__('Keyboard-only focus outline (optional)', 'wooee'); ?></th>
            <td>
              <label>
                <input type="checkbox" name="keyboard_only_focus" value="1" <?php checked(!empty($o['keyboard_only_focus'])); ?>>
                <?php echo esc_html__('Visible focus ring for keyboard navigation; not shown for mouse/touch.', 'wooee'); ?>
              </label>
            </td>
          </tr>
          <tr>
            <th colspan="2"><h2 style="font-size: 2.0em;"><?php echo esc_html__('Storefront Theme Features', 'wooee'); ?></h2></th>
          </tr>
          <?php if ($is_storefront): ?>
          <tr>
            <th scope="row"><?php echo esc_html__('Blog excerpts with "Read More" button', 'wooee'); ?></th>
            <td>
              <label>
                <input type="checkbox" name="blog_excerpts_enabled" value="1" <?php checked(!empty($o['blog_excerpts_enabled'])); ?>>
                <?php echo esc_html__('Show excerpts on blog and archive pages with a styled "Read More" link.', 'wooee'); ?>
              </label>
            </td>
          </tr>
          <?php else: ?>
          <tr>
            <th scope="row"><?php echo esc_html__('Blog excerpts with "Read More" button', 'wooee'); ?></th>
            <td>
              <p class="description"><?php echo esc_html__('Requires Storefront theme activated.', 'wooee'); ?></p>
            </td>
          </tr>
          <?php endif; ?>
          <?php if ($is_storefront): ?>
          <tr>
              <th scope="row"><?php echo esc_html__('Footer Credit Text', 'wooee'); ?></th>
              <td>
                  <input type="text" name="footer_credit_text" value="<?php echo esc_attr($o['footer_credit_text']); ?>">
                  <p class="description"><?php echo esc_html__('Custom text for footer credit link.', 'wooee'); ?></p>
              </td>
          </tr>
          <tr>
              <th scope="row"><?php echo esc_html__('Footer Credit URL', 'wooee'); ?></th>
              <td>
                  <input type="url" name="footer_credit_url" value="<?php echo esc_attr($o['footer_credit_url']); ?>">
                  <p class="description"><?php echo esc_html__('URL for footer credit link.', 'wooee'); ?></p>
              </td>
          </tr>
          <?php else: ?>
          <tr>
              <th scope="row"><?php echo esc_html__('Custom Footer Credit', 'wooee'); ?></th>
              <td>
                  <p class="description"><?php echo esc_html__('Requires Storefront theme.', 'wooee'); ?></p>
              </td>
          </tr>
          <?php endif; ?>
          <tr>
            <th colspan="2"><h2><?php echo esc_html__('WooCommerce Features', 'wooee'); ?></h2></th>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Links in shop & category descriptions display properly', 'wooee'); ?></th>
            <td>
              <label>
                <input type="checkbox" name="load_links_css" value="1" <?php checked(!empty($o['load_links_css'])); ?>>
                <?php echo esc_html__('Links are underlined; underline not displayed on hover.', 'wooee'); ?>
              </label>
            </td>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Hover effect on product & category boxes', 'wooee'); ?></th>
            <td>
              <label>
                <input type="checkbox" name="load_lift_css" value="1" <?php checked(!empty($o['load_lift_css'])); ?>>
                <?php echo esc_html__('Adds a soft shadow on hover for depth.', 'wooee'); ?>
              </label>
            </td>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Enable Item Specifics', 'wooee'); ?></th>
            <td>
              <label>
                <input type="checkbox" name="enable_item_specifics" value="1" <?php checked(!empty($o['enable_item_specifics'])); ?>>
                <?php echo esc_html__('Renames Additional Information tab on product pages to Item Specifics and adds custom editable meta box for product details.', 'wooee'); ?>
              </label>
            </td>
          </tr>
          <tr>
            <th colspan="2"><h2><?php echo esc_html__('Facebook Cart Integration', 'wooee'); ?></h2></th>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Enable Facebook Cart Integration', 'wooee'); ?></th>
            <td>
              <label>
                <input type="checkbox" name="enable_facebook_cart" value="1" <?php checked(!empty($o['enable_facebook_cart'])); ?>>
                <?php echo esc_html__('Enable handling of Facebook/Instagram cart redirects.', 'wooee'); ?>
              </label>
            </td>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Facebook Cart Page Slug', 'wooee'); ?></th>
            <td>
              <input type="text" name="facebook_cart_page_slug" value="<?php echo esc_attr($o['facebook_cart_page_slug']); ?>">
              <p class="description"><?php echo esc_html__('Enter custom slug for the Facebook cart page; page created on activation if missing.', 'wooee'); ?></p>
            </td>
          </tr>
          <tr>
            <th colspan="2"><h2><?php echo esc_html__('Reviews Page', 'wooee'); ?></h2></th>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Enable Reviews Page', 'wooee'); ?></th>
            <td>
              <label>
                <input type="checkbox" name="enable_reviews" value="1" <?php checked(!empty($o['enable_reviews'])); ?>>
                <?php echo esc_html__('This is a page that allows your customers to leave reviews and lists up to 10 reviews of your choice. Example: https://www.smokingblends.com/reviews/', 'wooee'); ?>
              </label>
              <p class="description"><?php echo esc_html__('Note: Sites with over 5000 products might experience some slowdown on the reviews page due to loading all products in the dropdown.', 'wooee'); ?></p>
            </td>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Reviews Page Slug', 'wooee'); ?></th>
            <td>
              <input type="text" name="reviews_page_slug" value="<?php echo esc_attr($o['reviews_page_slug']); ?>">
              <p class="description"><?php echo esc_html__('Enter custom slug for the reviews page; page created on activation if missing.', 'wooee'); ?></p>
            </td>
          </tr>
          <?php if ($has_login_security): ?>
          <tr>
            <th scope="row"><?php echo esc_html__('Enable Turnstile/Google Captcha', 'wooee'); ?></th>
            <td>
              <label>
                <input type="checkbox" name="enable_turnstile" value="1" <?php checked(!empty($o['enable_turnstile'])); ?>>
                <?php echo esc_html__('Enable Turnstile or Google Captcha for review submission (requires Login Security Pro plugin installed and configured).', 'wooee'); ?>
              </label>
            </td>
          </tr>
          <?php else: ?>
          <tr>
            <th scope="row"><?php echo esc_html__('Enable Turnstile/Google Captcha', 'wooee'); ?></th>
            <td>
              <p class="description"><?php echo esc_html__('Requires Login Security Pro plugin installed and activated.', 'wooee'); ?></p>
            </td>
          </tr>
          <?php endif; ?>
          <tr>
            <th scope="row"><?php echo esc_html__('Manage Reviews', 'wooee'); ?></th>
            <td>
              <h3><?php echo esc_html__('Review Fields', 'wooee'); ?></h3>
              <?php
              for ($i = 0; $i < 10; $i++) { // Fixed 10
                $r = $reviews[$i] ?? ['author' => '', 'rating' => 0, 'content' => ''];
              ?>
                <div class="review-field">
                  <p>
                    <label><?php echo esc_html__('Author', 'wooee'); ?></label>
                    <input type="text" name="review_author[<?php echo $i; ?>]" value="<?php echo esc_attr($r['author']); ?>">
                  </p>
                  <p>
                    <label><?php echo esc_html__('Rating', 'wooee'); ?></label>
                    <select name="review_rating[<?php echo $i; ?>]">
                      <?php for ($j = 1; $j <= 5; $j++) : ?>
                        <option value="<?php echo $j; ?>" <?php selected($r['rating'], $j); ?>><?php echo $j; ?></option>
                      <?php endfor; ?>
                    </select>
                  </p>
                  <p>
                    <label><?php echo esc_html__('Content', 'wooee'); ?></label>
                    <textarea name="review_content[<?php echo $i; ?>]" rows="3"><?php echo esc_textarea($r['content']); ?></textarea>
                  </p>
                </div>
              <?php } ?>
              <p class="description"><?php echo esc_html__('Add up to 10 reviews to display on the reviews page.', 'wooee'); ?></p>
            </td>
          </tr>
        </tbody>
      </table>
      <?php submit_button(); ?>
    </form>
  </div>
  <?php
}