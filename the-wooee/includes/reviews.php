<?php
/**
 * The Wooee - Reviews
 *
 * This file handles review submission and display shortcodes.
 *
 * @package The Wooee
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

add_action('woocommerce_new_product', 'the_wooee_clear_product_transient');
add_action('woocommerce_update_product', 'the_wooee_clear_product_transient');
add_action('before_delete_post', 'the_wooee_clear_product_transient_on_delete');
function the_wooee_clear_product_transient($product_id) {
    delete_transient('the_wooee_review_products');
}
function the_wooee_clear_product_transient_on_delete($post_id) {
    if (get_post_type($post_id) === 'product') {
        delete_transient('the_wooee_review_products');
    }
}

// Shortcode for review submission form
add_shortcode('submit_review_form', 'the_wooee_review_form_shortcode');
function the_wooee_review_form_shortcode() {
    // Define current URL early for use in processing/redirect
    global $post;
    $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '/';
    $current_url = is_a($post, 'WP_Post') ? get_permalink($post->ID) : home_url($request_uri);
    // Check only for POST with submit button (prevents Gutenberg preview issues)
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
        // Early nonce verification
        if (!isset($_POST['_wpnonce'])) {
            wc_add_notice(__('Security check failed. Please try again.', 'the-wooee'), 'error');
            wp_safe_redirect($current_url);
            exit;
        }
        $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce']));
        if (!wp_verify_nonce($nonce, 'submit_review_nonce')) {
            wc_add_notice(__('Security check failed. Please try again.', 'the-wooee'), 'error');
            wp_safe_redirect($current_url);
            exit;
        }
        // Honey pot check for basic spam prevention
        if (!empty($_POST['hp_email'])) {
            wc_add_notice(__('Error submitting review. Please try again.', 'the-wooee'), 'error'); // Generic error to not tip off bots
            wp_safe_redirect($current_url);
            exit;
        }
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
        $review_content = isset($_POST['comment']) ? sanitize_textarea_field(wp_unslash($_POST['comment'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $author_name = isset($_POST['author']) ? sanitize_text_field(wp_unslash($_POST['author'])) : '';
        if (!$product_id || !$review_content || (!$email && !is_user_logged_in()) || (wc_review_ratings_enabled() && !$rating)) {
            wc_add_notice(__('Please fill out all required fields.', 'the-wooee'), 'error');
            wp_safe_redirect($current_url);
            exit;
        }
        if (wc_review_ratings_enabled() && ($rating < 1 || $rating > 5)) {
            wc_add_notice(__('Rating must be between 1 and 5.', 'the-wooee'), 'error');
            wp_safe_redirect($current_url);
            exit;
        }
        if ($email && !is_email($email)) {
            wc_add_notice(__('Please provide a valid email address.', 'the-wooee'), 'error');
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
            wc_add_notice(__('You have already reviewed this product.', 'the-wooee'), 'error');
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
        $use_turnstile = the_wooee_opt('enable_turnstile') && is_plugin_active('login-security-pro/login-security-pro.php');
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
            wc_add_notice(__('Error submitting review. Please try again.', 'the-wooee'), 'error');
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
        $notice = (1 === $comment_approved) ? __('Review published successfully!', 'the-wooee') : __('Review submitted successfully! It will be reviewed before publishing.', 'the-wooee');
        wc_add_notice($notice, 'success');
        // Redirect after processing to prevent resubmission on refresh (PRG pattern)
        // Notices will show on the redirected page load
        wp_safe_redirect($current_url);
        exit;
    }
    return the_wooee_render_review_form();
}
// Render the review form
function the_wooee_render_review_form() {
    ob_start();
    global $post;
    $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '/';
    $current_url = is_a($post, 'WP_Post') ? get_permalink($post->ID) : home_url($request_uri);
    echo '<div class="woocommerce"><div id="reviews" class="woocommerce-Reviews">';
    echo '<h2 class="woocommerce-Reviews-title">' . esc_html__('Leave a Review', 'the-wooee') . '</h2>';
    echo '<div id="review_form_wrapper"><div id="review_form">';
    echo '<div id="respond" class="comment-respond">'; // Added to match WC JS selector (#respond p.stars a)
    echo '<form action="' . esc_url($current_url) . '" method="post" id="commentform" class="comment-form">';
    $nonce_html = wp_nonce_field('submit_review_nonce', '_wpnonce', true, false);
    echo wp_kses($nonce_html, array('input' => array('type' => array(), 'name' => array(), 'value' => array(), 'id' => array())));
    // Honey pot field (hidden; bots might fill it)
    echo '<input type="text" name="hp_email" value="" style="display:none;" tabindex="-1" autocomplete="off">';
    ?>
    <p class="comment-form-product form-row form-row-wide">
        <label for="product_id"><?php esc_html_e('Select Product', 'the-wooee'); ?> <span class="required">*</span></label>
        <select name="product_id" id="product_id" class="input-text" required>
            <option value=""><?php esc_html_e('Choose a product...', 'the-wooee'); ?></option>
            <?php
            $transient_key = 'the_wooee_review_products';
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
                echo '<option value="" disabled>' . esc_html__('No products available', 'the-wooee') . '</option>';
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
            <label for="author"><?php esc_html_e('Name', 'the-wooee'); ?><?php echo $req ? ' <span class="required">*</span>' : ''; ?></label>
            <input id="author" name="author" type="text" value="<?php echo esc_attr($commenter['comment_author']); ?>" size="30" class="input-text"<?php echo esc_html($aria_req); ?> />
        </p>
        <p class="comment-form-email form-row form-row-last">
            <label for="email"><?php esc_html_e('Email', 'the-wooee'); ?> <span class="required">*</span></label>
            <input id="email" name="email" <?php echo $html5 ? 'type="email"' : 'type="text"'; ?> value="<?php echo esc_attr($commenter['comment_author_email']); ?>" size="30" class="input-text"<?php echo esc_html($aria_req); ?> />
        </p>
        <?php
    }
    if (wc_review_ratings_enabled()) {
        ?>
        <p class="comment-form-rating form-row form-row-wide">
            <label for="rating"><?php esc_html_e('Your rating', 'the-wooee'); ?> <span class="required">*</span></label>
            <select name="rating" id="rating" required>
                <option value=""><?php esc_html_e('Rate&hellip;', 'the-wooee'); ?></option>
                <option value="5"><?php esc_html_e('Perfect', 'the-wooee'); ?></option>
                <option value="4"><?php esc_html_e('Good', 'the-wooee'); ?></option>
                <option value="3"><?php esc_html_e('Average', 'the-wooee'); ?></option>
                <option value="2"><?php esc_html_e('Not that bad', 'the-wooee'); ?></option>
                <option value="1"><?php esc_html_e('Very poor', 'the-wooee'); ?></option>
            </select>
        </p>
        <?php
    }
    ?>
    <p class="comment-form-comment form-row form-row-wide">
        <label for="comment"><?php esc_html_e('Your review', 'the-wooee'); ?> <span class="required">*</span></label>
        <textarea id="comment" name="comment" cols="45" rows="8" class="input-text" required></textarea>
    </p>
    <?php
    // Optional Turnstile CAPTCHA (if enabled and plugin active)
    $use_turnstile = the_wooee_opt('enable_turnstile') && is_plugin_active('login-security-pro/login-security-pro.php');
    if ($use_turnstile) {
        // Output Turnstile field (plugin handles rendering via filter)
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        do_action('comment_form_after_fields');
    }
    ?>
    <p class="form-submit">
        <input name="submit_review" type="submit" id="submit" class="submit button" value="<?php esc_attr_e('Submit', 'the-wooee'); ?>" />
    </p>
    </form>
    </div><!-- #respond -->
    </div><!-- #review_form -->
    </div><!-- #review_form_wrapper -->
    </div><!-- #reviews -->
    </div><!-- .woocommerce -->
    <?php
    return ob_get_clean();
}

// Shortcode for displaying all reviews
add_shortcode('display_all_reviews', 'the_wooee_display_all_reviews_shortcode');
function the_wooee_display_all_reviews_shortcode() {
    $reviews = the_wooee_opt('reviews');
    if (empty($reviews) || !is_array($reviews)) {
        return '<p>' . esc_html__('No reviews available.', 'the-wooee') . '</p>';
    }
    ob_start();
    echo '<div class="woocommerce"><div id="reviews" class="woocommerce-Reviews">';
    echo '<ol class="commentlist">';
    foreach ($reviews as $review) {
        $author = esc_html($review['author']);
        $rating = intval($review['rating']);
        $content = esc_html($review['content']);
        ?>
        <li class="woocommerce-review__item review">
            <div class="woocommerce-review__author">
                <p class="woocommerce-review__author-name"><?php echo esc_html($author); ?></p>
            </div>
            <div class="woocommerce-review__container">
                <div class="woocommerce-review__rating">
                    <div class="star-rating" role="img" aria-label="<?php echo esc_attr(sprintf(
                        /* translators: %d: rating number from 1 to 5 */
                        __('Rated %d out of 5', 'the-wooee'), $rating)); ?>">
                        <span style="width:<?php echo esc_attr(($rating / 5) * 100); ?>%;"><?php echo esc_html(sprintf(
                            /* translators: %d: rating number from 1 to 5 */
                            __('Rated %d out of 5', 'the-wooee'), $rating)); ?></span>
                    </div>
                </div>
                <div class="woocommerce-review__text">
                    <p><?php echo wp_kses_post(nl2br($content)); ?></p>
                </div>
            </div>
        </li>
        <?php
    }
    echo '</ol>';
    echo '</div></div>';
    return ob_get_clean();
}