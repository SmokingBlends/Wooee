<?php
/**
 * Shop UX Toolkit - Reviews
 *
 * This file handles review submission and display shortcodes.
 *
 * @package Shop UX Toolkit
 * @version 0.2.2
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
// Check if WooCommerce is active
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return; // Exit if WooCommerce is not active
}

add_action('woocommerce_new_product', 'shop_ux_toolkit_clear_product_transient');
add_action('woocommerce_update_product', 'shop_ux_toolkit_clear_product_transient');
add_action('before_delete_post', 'shop_ux_toolkit_clear_product_transient_on_delete');
function shop_ux_toolkit_clear_product_transient($product_id) {
    delete_transient('shop_ux_toolkit_review_products');
}
function shop_ux_toolkit_clear_product_transient_on_delete($post_id) {
    if (get_post_type($post_id) === 'product') {
        delete_transient('shop_ux_toolkit_review_products');
    }
}

// Shortcode for review submission form
add_shortcode('submit_review_form', 'shop_ux_toolkit_review_form_shortcode');
function shop_ux_toolkit_review_form_shortcode() {
    // Define current URL early for use in processing/redirect
    global $post;
    $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '/';
    $current_url = is_a($post, 'WP_Post') ? get_permalink($post->ID) : home_url($request_uri);
    // Check only for POST with submit button (prevents Gutenberg preview issues)
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
        // Early nonce verification
        if (!isset($_POST['_wpnonce'])) {
            wc_add_notice(__('Security check failed. Please try again.', 'shop-ux-toolkit'), 'error');
            wp_safe_redirect($current_url);
            exit;
        }
        $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce']));
        if (!wp_verify_nonce($nonce, 'submit_review_nonce')) {
            wc_add_notice(__('Security check failed. Please try again.', 'shop-ux-toolkit'), 'error');
            wp_safe_redirect($current_url);
            exit;
        }
        // Honey pot check for basic spam prevention
        if (!empty($_POST['hp_email'])) {
            wc_add_notice(__('Error submitting review. Please try again.', 'shop-ux-toolkit'), 'error'); // Generic error to not tip off bots
            wp_safe_redirect($current_url);
            exit;
        }
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
        $review_content = isset($_POST['comment']) ? sanitize_textarea_field(wp_unslash($_POST['comment'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $author_name = isset($_POST['author']) ? sanitize_text_field(wp_unslash($_POST['author'])) : '';
        if (!$product_id || !$review_content || (!$email && !is_user_logged_in()) || (wc_review_ratings_enabled() && !$rating)) {
            wc_add_notice(__('Please fill out all required fields.', 'shop-ux-toolkit'), 'error');
            wp_safe_redirect($current_url);
            exit;
        }
        if (wc_review_ratings_enabled() && ($rating < 1 || $rating > 5)) {
            wc_add_notice(__('Rating must be between 1 and 5.', 'shop-ux-toolkit'), 'error');
            wp_safe_redirect($current_url);
            exit;
        }
        if ($email && !is_email($email)) {
            wc_add_notice(__('Please provide a valid email address.', 'shop-ux-toolkit'), 'error');
            wp_safe_redirect($current_url);
            exit;
        }
        $user_id = is_user_logged_in() ? get_current_user_id() : 0;
        $submit_email = is_user_logged_in() ? wp_get_current_user()->user_email : $email;
        $submit_author = is_user_logged_in() ? wp_get_current_user()->display_name : ($author_name ?: 'Guest');
        $existing_args = array(
            'post_id' => $product_id,
            'type' => 'review',
            'status' => 'all', // Changed to 'all' to prevent duplicates even if pending
            'number' => 1,
            'fields' => 'ids',
        );
        $existing_args['author_email'] = $submit_email;
        if ($user_id) {
            $existing_args['user_id'] = $user_id;
        }
        $existing_reviews = get_comments($existing_args);
        if (!empty($existing_reviews)) {
            wc_add_notice(__('You have already reviewed this product.', 'shop-ux-toolkit'), 'error');
            wp_safe_redirect($current_url);
            exit;
        }
        $comment_approved = get_option('comment_moderation') ? 0 : 1; // Respect admin moderation setting
        $data = array(
            'comment_post_ID' => $product_id,
            'comment_author' => $submit_author,
            'comment_author_email' => $submit_email,
            'comment_author_url' => '',
            'comment_content' => $review_content,
            'comment_type' => 'review',
            'comment_parent' => 0,
            'user_ID' => $user_id,
            'comment_approved' => $comment_approved,
        );
        $use_turnstile = shop_ux_toolkit_opt('enable_turnstile') && is_plugin_active('login-security-pro/login-security-pro.php');
        if ($use_turnstile) {
            // NEW: Integrate Login Security Pro Turnstile verification via WP's preprocess_comment filter
            // This triggers the plugin's token check; returns WP_Error on failure (e.g., invalid captcha)
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
            $data = apply_filters( 'preprocess_comment', $data );
            if ( is_wp_error( $data ) ) {
                wc_add_notice( $data->get_error_message(), 'error' );
                wp_safe_redirect( $current_url );
                exit;
            }
        }
        $comment_id = wp_insert_comment($data);
        if (!$comment_id) {
            wc_add_notice(__('Error submitting review. Please try again.', 'shop-ux-toolkit'), 'error');
            wp_safe_redirect($current_url);
            exit;
        }
        if ($rating) {
            add_comment_meta($comment_id, 'rating', $rating);
        }
        $verified = 0;
        if ($user_id && wc_customer_bought_product($submit_email, $user_id, $product_id)) {
            $verified = 1;
        } elseif (!$user_id) {
            $orders = wc_get_orders(array(
                'billing_email' => $submit_email,
                'status' => array('wc-completed', 'wc-processing'),
                'limit' => -1, // Changed to -1 for full accuracy (no perf hit expected for reviews)
                'date_created' => '>=' . (time() - YEAR_IN_SECONDS * 2), // Last 2 years
            ));
            foreach ($orders as $order) {
                foreach ($order->get_items() as $item) {
                    if ($item->get_product_id() == $product_id) {
                        $verified = 1;
                        break 2;
                    }
                }
            }
        }
        add_comment_meta($comment_id, 'verified', $verified);
        // If auto-approved, update product rating caches (matches WC core)
        if (1 === $comment_approved) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
            do_action('woocommerce_new_product_review', $comment_id);
        }
        // Dynamic notice based on approval status
        $notice = (1 === $comment_approved) ? __('Review published successfully!', 'shop-ux-toolkit') : __('Review submitted successfully! It will be reviewed before publishing.', 'shop-ux-toolkit');
        wc_add_notice($notice, 'success');
        // Redirect after processing to prevent resubmission on refresh (PRG pattern)
        // Notices will show on the redirected page load
        wp_safe_redirect($current_url);
        exit;
    }
    return shop_ux_toolkit_render_review_form();
}
// Render the review form
function shop_ux_toolkit_render_review_form() {
    ob_start();
    global $post;
    $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '/';
    $current_url = is_a($post, 'WP_Post') ? get_permalink($post->ID) : home_url($request_uri);
    echo '<div class="woocommerce"><div id="reviews" class="woocommerce-Reviews">';
    echo '<h2 class="woocommerce-Reviews-title">' . esc_html__('Leave a Review', 'shop-ux-toolkit') . '</h2>';
    echo '<div id="review_form_wrapper"><div id="review_form">';
    echo '<div id="respond" class="comment-respond">'; // Added to match WC JS selector (#respond p.stars a)
    echo '<form action="' . esc_url($current_url) . '" method="post" id="commentform" class="comment-form">';
    $nonce_html = wp_nonce_field('submit_review_nonce', '_wpnonce', true, false);
    echo wp_kses($nonce_html, array('input' => array('type' => array(), 'name' => array(), 'value' => array(), 'id' => array())));
    // Honey pot field (hidden; bots might fill it)
    echo '<input type="text" name="hp_email" value="" style="display:none;" tabindex="-1" autocomplete="off">';
    ?>
    <p class="comment-form-product form-row form-row-wide">
        <label for="product_id"><?php esc_html_e('Select Product', 'shop-ux-toolkit'); ?> <span class="required">*</span></label>
        <select name="product_id" id="product_id" class="input-text" required>
            <option value=""><?php esc_html_e('Choose a product...', 'shop-ux-toolkit'); ?></option>
            <?php
            $transient_key = 'shop_ux_toolkit_review_products';
            $products = get_transient($transient_key);
            if (false === $products) {
                $products = wc_get_products(array(
                    'limit' => -1,
                    'status' => 'publish',
                    'orderby' => 'name',
                    'order' => 'ASC',
                ));
                set_transient($transient_key, $products, 12 * HOUR_IN_SECONDS);
            }
            if (empty($products)) {
                echo '<option value="" disabled>' . esc_html__('No products available', 'shop-ux-toolkit') . '</option>';
            } else {
                foreach ($products as $product) {
                    echo '<option value="' . esc_attr($product->get_id()) . '">' . esc_html($product->get_name()) . '</option>';
                }
            }
            ?>
        </select>
    </p>
    <?php
    $commenter = wp_get_current_commenter();
    $req = get_option('require_name_email');
    $aria_req = ($req ? ' aria-required="true"' : '');
    $html5 = current_theme_supports('html5', 'comment-form') ? 'html5' : '';
    if (!is_user_logged_in()) {
        ?>
        <p class="comment-form-author form-row form-row-first">
            <label for="author"><?php esc_html_e('Name', 'shop-ux-toolkit'); ?><?php echo $req ? ' <span class="required">*</span>' : ''; ?></label>
            <input id="author" name="author" type="text" value="<?php echo esc_attr($commenter['comment_author']); ?>" size="30" class="input-text"<?php echo esc_html($aria_req); ?> />
        </p>
        <p class="comment-form-email form-row form-row-last">
            <label for="email"><?php esc_html_e('Email', 'shop-ux-toolkit'); ?> <span class="required">*</span></label>
            <input id="email" name="email" <?php echo $html5 ? 'type="email"' : 'type="text"'; ?> value="<?php echo esc_attr($commenter['comment_author_email']); ?>" size="30" class="input-text"<?php echo esc_html($aria_req); ?> />
        </p>
        <?php
    }
    if (wc_review_ratings_enabled()) {
        ?>
        <p class="comment-form-rating form-row form-row-wide">
            <label for="rating"><?php esc_html_e('Your rating', 'shop-ux-toolkit'); ?> <span class="required">*</span></label>
            <select name="rating" id="rating" required>
                <option value=""><?php esc_html_e('Rate&hellip;', 'shop-ux-toolkit'); ?></option>
                <option value="5"><?php esc_html_e('Perfect', 'shop-ux-toolkit'); ?></option>
                <option value="4"><?php esc_html_e('Good', 'shop-ux-toolkit'); ?></option>
                <option value="3"><?php esc_html_e('Average', 'shop-ux-toolkit'); ?></option>
                <option value="2"><?php esc_html_e('Not that bad', 'shop-ux-toolkit'); ?></option>
                <option value="1"><?php esc_html_e('Very poor', 'shop-ux-toolkit'); ?></option>
            </select>
        </p>
        <?php
    }
    ?>
    <p class="comment-form-comment form-row form-row-wide">
        <label for="comment"><?php esc_html_e('Your review', 'shop-ux-toolkit'); ?> <span class="required">*</span></label>
        <textarea id="comment" name="comment" cols="45" rows="8" class="input-text" required></textarea>
    </p>
    <?php
    $use_turnstile = shop_ux_toolkit_opt('enable_turnstile') && is_plugin_active('login-security-pro/login-security-pro.php');
    if ($use_turnstile) {
        // NEW: Display Turnstile captcha markup via plugin hooks
        // Place after fields (before submit); uses correct hook based on login status
        if ( is_user_logged_in() ) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
            do_action( 'comment_form_logged_in_after' );
        } else {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
            do_action( 'comment_form_after_fields' );
        }
    }
    ?>
    <p class="form-submit form-row form-row-wide">
        <input name="submit_review" type="submit" id="submit" class="submit button" value="<?php esc_attr_e('Submit', 'shop-ux-toolkit'); ?>" />
    </p>
    <?php
    echo '</form>';
    echo '</div>'; // Close #respond
    echo '</div></div></div></div>';
    return ob_get_clean();
}
// Display selected reviews as static HTML
add_shortcode('display_all_reviews', 'shop_ux_toolkit_all_reviews_shortcode');
function shop_ux_toolkit_all_reviews_shortcode($atts) {
  $o = shop_ux_toolkit_opts();
  $reviews = $o['reviews'] ?? [];
  if (empty($reviews)) return '<p>No reviews added yet.</p>';
  $html = '<div class="reviews-grid" style="margin-bottom: 50px; grid-template-columns: 1fr; font-size: 1.1em;">';
  foreach ($reviews as $review) {
    $stars = str_repeat('★', $review['rating']) . str_repeat('☆', 5 - $review['rating']);
    $html .= '<div class="review-card">
      <div class="stars">' . esc_html($stars) . '</div>
      <blockquote>' . esc_html($review['content']) . '</blockquote>
      <p class="author">- ' . esc_html($review['author']) . '</p>
    </div>';
  }
  $html .= '</div>';
  return $html;
}