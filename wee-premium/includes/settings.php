<?php
/**
 * Wee Premium - Settings
 *
 * This file handles the plugin's settings page and options.
 *
 * @package Wee Premium
 * @version 0.2.3
 */
if (!defined('ABSPATH')) exit;
/** ===============================
 * Settings page (hidden from Settings menu; Plugins row link opens it)
 * ============================== */
add_action('admin_menu', function () {
  add_options_page(
    __('The Wooee! Premium Upgrades with Storefront Polishes for WooCommerce', 'wee-premium'),
    __('The Wooee!', 'wee-premium'),
    'manage_options',
    'wee-premium',
    'wee_premium_render_settings_page'
  );
});
// Hide from Settings menu; keep URL working for Plugins-row link
add_action('admin_menu', function () {
  remove_submenu_page('options-general.php', 'wee-premium');
}, 99);
/** ===============================
 * Save handler (manual form)
 * ============================== */
add_action('admin_post_wee_premium_save', function () {
  if (!current_user_can('manage_options')) {
    wp_die(
      esc_html__('Unauthorized', 'wee-premium'),
      esc_html__('Error', 'wee-premium'),
      ['response' => 403]
    );
  }
  check_admin_referer('wee_premium_save');
  $keys = array_keys(WEE_PREMIUM_DEFAULTS);
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
      $new[$k] = isset($_POST[$k]) ? sanitize_title(wp_unslash($_POST[$k])) : WEE_PREMIUM_DEFAULTS[$k];
    } elseif ($k === 'footer_credit_text') {
      $new[$k] = isset($_POST[$k]) ? sanitize_text_field(wp_unslash($_POST[$k])) : WEE_PREMIUM_DEFAULTS[$k];
    } elseif ($k === 'footer_credit_url') {
      $new[$k] = isset($_POST[$k]) ? esc_url_raw(wp_unslash($_POST[$k])) : WEE_PREMIUM_DEFAULTS[$k];
    } elseif (in_array($k, ['category_per_page', 'products_limit', 'products_columns'])) {
      $new[$k] = isset($_POST[$k]) ? intval($_POST[$k]) : WEE_PREMIUM_DEFAULTS[$k];
    } else {
      $new[$k] = isset($_POST[$k]) ? true : false;
    }
  }
  update_option('wee_premium_options', $new);
  wp_safe_redirect(add_query_arg(
    ['page' => 'wee-premium', 'settings-updated' => '1'],
    admin_url('options-general.php')
  ));
  exit;
});
/** ===============================
 * Render settings page
 * ============================== */
function wee_premium_render_settings_page() {
  if (!current_user_can('manage_options')) return;
  $o = wee_premium_opts();
  $settings_updated = (bool) filter_input(INPUT_GET, 'settings-updated', FILTER_VALIDATE_BOOLEAN);
  $reviews = $o['reviews'] ?? [];
  $current_theme = wp_get_theme();
  $is_storefront = ( 'storefront' === $current_theme->get_stylesheet() || 'storefront' === $current_theme->get_template() );
  $has_login_security = is_plugin_active('login-security-pro/login-security-pro.php');
  ?>
  <div class="wrap">
    <h1><?php echo esc_html__('The Wooee! Premium Upgrades with Storefront Polishes for WooCommerce', 'wee-premium'); ?></h1>
    <?php if ($settings_updated): ?>
      <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Settings saved.', 'wee-premium'); ?></p></div>
    <?php endif; ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <?php wp_nonce_field('wee_premium_save'); ?>
      <input type="hidden" name="action" value="wee_premium_save" />
      <table class="form-table" role="presentation">
        <tbody>
          <tr>
            <th scope="row"><?php echo esc_html__('Keyboard-only focus outline (optional)', 'wee-premium'); ?></th>
            <td>
              <label>
                <input type="checkbox" name="keyboard_only_focus" value="1" <?php checked(!empty($o['keyboard_only_focus'])); ?>>
                <?php echo esc_html__('Visible focus ring for keyboard navigation; not shown for mouse/touch.', 'wee-premium'); ?>
              </label>
            </td>
          </tr>
          <tr>
            <th colspan="2"><h2 style="font-size: 2.0em;"><?php echo esc_html__('Storefront Theme Features', 'wee-premium'); ?></h2></th>
          </tr>
          <?php if ($is_storefront): ?>
          <tr>
            <th scope="row"><?php echo esc_html__('Blog excerpts with "Read More" button', 'wee-premium'); ?></th>
            <td>
              <label>
                <input type="checkbox" name="blog_excerpts_enabled" value="1" <?php checked(!empty($o['blog_excerpts_enabled'])); ?>>
                <?php echo esc_html__('Show excerpts on blog and archive pages with a styled "Read More" link.', 'wee-premium'); ?>
              </label>
            </td>
          </tr>
          <?php else: ?>
          <tr>
            <th scope="row"><?php echo esc_html__('Blog excerpts with "Read More" button', 'wee-premium'); ?></th>
            <td>
              <p class="description"><?php echo esc_html__('Requires Storefront theme activated.', 'wee-premium'); ?></p>
            </td>
          </tr>
          <?php endif; ?>
          <?php if ($is_storefront): ?>
          <tr>
              <th scope="row"><?php echo esc_html__('Footer Credit Text', 'wee-premium'); ?></th>
              <td>
                  <input type="text" name="footer_credit_text" value="<?php echo esc_attr($o['footer_credit_text']); ?>">
                  <p class="description"><?php echo esc_html__('Custom text for footer credit link.', 'wee-premium'); ?></p>
              </td>
          </tr>
          <tr>
              <th scope="row"><?php echo esc_html__('Footer Credit URL', 'wee-premium'); ?></th>
              <td>
                  <input type="url" name="footer_credit_url" value="<?php echo esc_attr($o['footer_credit_url']); ?>">
                  <p class="description"><?php echo esc_html__('URL for footer credit link.', 'wee-premium'); ?></p>
              </td>
          </tr>
          <?php else: ?>
          <tr>
              <th scope="row"><?php echo esc_html__('Custom Footer Credit', 'wee-premium'); ?></th>
              <td>
                  <p class="description"><?php echo esc_html__('Requires Storefront theme.', 'wee-premium'); ?></p>
              </td>
          </tr>
          <?php endif; ?>
          <tr>
            <th colspan="2"><h2 style="font-size: 2.0em;"><?php echo esc_html__('WooCommerce Features', 'wee-premium'); ?></h2></th>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Links in shop & category descriptions display properly', 'wee-premium'); ?></th>
            <td>
              <label>
                <input type="checkbox" name="load_links_css" value="1" <?php checked(!empty($o['load_links_css'])); ?>>
                <?php echo esc_html__('Links are underlined; underline not displayed on hover.', 'wee-premium'); ?>
              </label>
            </td>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Hover effect on product & category boxes', 'wee-premium'); ?></th>
            <td>
              <label>
                <input type="checkbox" name="load_lift_css" value="1" <?php checked(!empty($o['load_lift_css'])); ?>>
                <?php echo esc_html__('Adds a soft shadow on hover for depth.', 'wee-premium'); ?>
              </label>
            </td>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Enable Item Specifics', 'wee-premium'); ?></th>
            <td>
              <label>
                <input type="checkbox" name="enable_item_specifics" value="1" <?php checked(!empty($o['enable_item_specifics'])); ?>>
                <?php echo esc_html__('Renames Additional Information tab on product pages to Item Specifics and adds custom editable meta box for product details.', 'wee-premium'); ?>
              </label>
            </td>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Enable Custom Shop Page', 'wee-premium'); ?></th>
            <td>
              <label>
                <input type="checkbox" name="enable_custom_shop" value="1" <?php checked(!empty($o['enable_custom_shop'])); ?>>
                <?php echo esc_html__('Displays custom category rows on the shop page, ordered by category order meta.', 'wee-premium'); ?>
              </label>
            </td>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Categories Per Page (0 for unlimited)', 'wee-premium'); ?></th>
            <td>
              <input type="number" name="category_per_page" value="<?php echo esc_attr($o['category_per_page']); ?>" min="0">
              <p class="description"><?php echo esc_html__('Set to a number greater than 0 to enable pagination.', 'wee-premium'); ?></p>
            </td>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Products Per Category', 'wee-premium'); ?></th>
            <td>
              <input type="number" name="products_limit" value="<?php echo esc_attr($o['products_limit']); ?>" min="1">
            </td>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Product Columns', 'wee-premium'); ?></th>
            <td>
              <input type="number" name="products_columns" value="<?php echo esc_attr($o['products_columns']); ?>" min="1" max="6">
            </td>
          </tr>
          <tr>
            <th colspan="2"><h2 style="font-size: 2.0em;"><?php echo esc_html__('Facebook Cart Integration', 'wee-premium'); ?></h2></th>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Enable Facebook Cart Integration', 'wee-premium'); ?></th>
            <td>
              <label>
                <input type="checkbox" name="enable_facebook_cart" value="1" <?php checked(!empty($o['enable_facebook_cart'])); ?>>
                <?php echo esc_html__('Enable handling of Facebook/Instagram cart redirects.', 'wee-premium'); ?>
              </label>
            </td>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Facebook Cart Page Slug', 'wee-premium'); ?></th>
            <td>
              <input type="text" name="facebook_cart_page_slug" value="<?php echo esc_attr($o['facebook_cart_page_slug']); ?>">
              <p class="description"><?php echo esc_html__('Enter custom slug for the Facebook cart page; page created on activation if missing.', 'wee-premium'); ?></p>
            </td>
          </tr>
          <tr>
            <th colspan="2"><h2 style="font-size: 2.0em;"><?php echo esc_html__('Reviews Page', 'wee-premium'); ?></h2></th>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Enable Reviews Page', 'wee-premium'); ?></th>
            <td>
              <label>
                <input type="checkbox" name="enable_reviews" value="1" <?php checked(!empty($o['enable_reviews'])); ?>>
                <?php echo esc_html__('This is a page that allows your customers to leave reviews and lists up to 10 reviews of your choice. Example: https://www.smokingblends.com/reviews/', 'wee-premium'); ?>
              </label>
              <p class="description"><?php echo esc_html__('Note: Sites with over 5000 products might experience some slowdown on the reviews page due to loading all products in the dropdown.', 'wee-premium'); ?></p>
            </td>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html__('Reviews Page Slug', 'wee-premium'); ?></th>
            <td>
              <input type="text" name="reviews_page_slug" value="<?php echo esc_attr($o['reviews_page_slug']); ?>">
              <p class="description"><?php echo esc_html__('Enter custom slug for the reviews page; page created on activation if missing.', 'wee-premium'); ?></p>
            </td>
          </tr>
          <?php if ($has_login_security): ?>
          <tr>
            <th scope="row"><?php echo esc_html__('Enable Turnstile/Google Captcha', 'wee-premium'); ?></th>
            <td>
              <label>
                <input type="checkbox" name="enable_turnstile" value="1" <?php checked(!empty($o['enable_turnstile'])); ?>>
                <?php echo esc_html__('Enable Turnstile or Google Captcha for review submission (requires Login Security Pro plugin installed and configured).', 'wee-premium'); ?>
              </label>
            </td>
          </tr>
          <?php else: ?>
          <tr>
            <th scope="row"><?php echo esc_html__('Enable Turnstile/Google Captcha', 'wee-premium'); ?></th>
            <td>
              <p class="description"><?php echo esc_html__('Requires Login Security Pro plugin installed and activated.', 'wee-premium'); ?></p>
            </td>
          </tr>
          <?php endif; ?>
          <tr>
            <th scope="row"><?php echo esc_html__('Manage Reviews', 'wee-premium'); ?></th>
            <td>
              <h3><?php echo esc_html__('Review Fields', 'wee-premium'); ?></h3>
              <?php wee_premium_render_reviews_repeater($o); ?>
            </td>
          </tr>
        </tbody>
      </table>
      <?php submit_button(__('Save Changes', 'wee-premium')); ?>
    </form>
    <p style="margin-top:1rem;color:#555;">
      <?php echo esc_html__('Privacy: This plugin does not collect, store, or transmit any personal data and makes no external requests.', 'wee-premium'); ?>
    </p>
  </div>
  <?php
}

function wee_premium_render_reviews_repeater($options) {
  $reviews = $options['reviews'] ?? [];
  ?>
  <div class="wee-reviews-repeater" role="region" aria-label="<?php esc_attr_e('Reviews Repeater', 'wee-premium'); ?>">
    <?php foreach ($reviews as $i => $review): ?>
      <div class="wee-reviews-repeater-item">
        <p><label><?php esc_html_e('Author', 'wee-premium'); ?></label><br>
        <input type="text" name="reviews[<?php echo esc_attr($i); ?>][author]" value="<?php echo esc_attr($review['author'] ?? ''); ?>" class="regular-text"></p>
        <p><label><?php esc_html_e('Rating', 'wee-premium'); ?></label><br>
        <select name="reviews[<?php echo esc_attr($i); ?>][rating]">
          <option value="5" <?php selected(5, $review['rating'] ?? 5); ?>>★★★★★</option>
          <option value="4" <?php selected(4, $review['rating'] ?? 5); ?>>★★★★☆</option>
          <option value="3" <?php selected(3, $review['rating'] ?? 5); ?>>★★★☆☆</option>
          <option value="2" <?php selected(2, $review['rating'] ?? 5); ?>>★★☆☆☆</option>
          <option value="1" <?php selected(1, $review['rating'] ?? 5); ?>>★☆☆☆☆</option>
        </select></p>
        <p><label><?php esc_html_e('Review Text', 'wee-premium'); ?></label><br>
        <textarea name="reviews[<?php echo esc_attr($i); ?>][content]" rows="5" class="regular-text"><?php echo esc_textarea($review['content'] ?? ''); ?></textarea></p>
        <button type="button" class="button wee-reviews-remove-item"><?php esc_html_e('Remove Review', 'wee-premium'); ?></button>
      </div>
    <?php endforeach; ?>
    <button type="button" class="button wee-reviews-add-item"><?php esc_html_e('Add Review', 'wee-premium'); ?></button>
  </div>
  <?php wee_premium_inline_reviews_repeater_js(); ?>
  <?php
}

function wee_premium_inline_reviews_repeater_js() {
  $template = wee_premium_get_reviews_template();
  ?>
  <script type="text/javascript">
    jQuery(document).ready(function($) {
      var template = <?php echo wp_json_encode($template); ?>;
      var $repeater = $('.wee-reviews-repeater');
      $repeater.on('click', '.wee-reviews-add-item', function() {
        var $lastItem = $repeater.find('.wee-reviews-repeater-item').last();
        var $newItem = $lastItem.length === 0 ? $(template) : $lastItem.clone(true);
        if ($lastItem.length > 0) {
          $newItem.find('input[type="text"], textarea').val('');
          $newItem.find('select').val(5);
        }
        var index = $repeater.find('.wee-reviews-repeater-item').length;
        $newItem.find('[name]').each(function() {
          var name = $(this).attr('name').replace(/\[\d+\]/, '[' + index + ']');
          $(this).attr('name', name);
        });
        if (index < 10) {
          $repeater.find('.wee-reviews-add-item').before($newItem);
        } else {
          alert('<?php echo esc_js(__('Maximum of 10 reviews allowed.', 'wee-premium')); ?>');
        }
      });
      $repeater.on('click', '.wee-reviews-remove-item', function() {
        $(this).closest('.wee-reviews-repeater-item').remove();
      });
    });
  </script>
  <?php
}

function wee_premium_get_reviews_template() {
  return '<div class="wee-reviews-repeater-item">'
    . '<p><label>' . esc_html__('Author', 'wee-premium') . '</label><br>'
    . '<input type="text" name="reviews[0][author]" class="regular-text"></p>'
    . '<p><label>' . esc_html__('Rating', 'wee-premium') . '</label><br>'
    . '<select name="reviews[0][rating]">'
    . '<option value="5">★★★★★</option>'
    . '<option value="4">★★★★☆</option>'
    . '<option value="3">★★★☆☆</option>'
    . '<option value="2">★★☆☆☆</option>'
    . '<option value="1">★☆☆☆☆</option>'
    . '</select></p>'
    . '<p><label>' . esc_html__('Review Text', 'wee-premium') . '</label><br>'
    . '<textarea name="reviews[0][content]" rows="5" class="regular-text"></textarea></p>'
    . '<button type="button" class="button wee-reviews-remove-item">' . esc_html__('Remove Review', 'wee-premium') . '</button>'
    . '</div>';
}
/** ===============================
 * Plugins screen links
 * ============================== */
add_filter('plugin_action_links_' . plugin_basename(WEE_PREMIUM_PLUGIN_FILE), function ($links) {
  $url = admin_url('options-general.php?page=wee-premium');
  array_unshift($links, '<a href="' . esc_url($url) . '">' . esc_html__('Settings', 'wee-premium') . '</a>');
  return $links;
});
add_filter('plugin_row_meta', function ($links, $file) {
  if ($file !== plugin_basename(WEE_PREMIUM_PLUGIN_FILE)) return $links;
  $links[] = '<a href="https://wordpress.org/plugins/shop-ux-toolkit/#faq" target="_blank" rel="noopener">' . esc_html__('FAQ', 'wee-premium') . '</a>';
  $links[] = '<a href="https://wordpress.org/support/plugin/shop-ux-toolkit/" target="_blank" rel="noopener">' . esc_html__('Support', 'wee-premium') . '</a>';
  return $links;
}, 10, 2);