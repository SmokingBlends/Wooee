<?php
/**
 * Shop UX Toolkit - Blog Adjustments
 *
 * This file handles the blog excerpts, "Continue Reading" button, and related features for the Storefront theme.
 *
 * @package Shop UX Toolkit
 * @version 0.3.0
 */

// Prevent direct access to this file
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define the content filter callback
function shop_ux_toolkit_use_excerpt_on_blog_pages_with_read_more( $content ) {
    if ( is_home() || is_archive() ) {
        global $post;
        // Temporarily remove this filter to prevent recursion when calling get_the_excerpt()
        remove_filter( 'the_content', 'shop_ux_toolkit_use_excerpt_on_blog_pages_with_read_more' );
        $excerpt = get_the_excerpt();
        // Add the filter back
        add_filter( 'the_content', 'shop_ux_toolkit_use_excerpt_on_blog_pages_with_read_more' );
        // Use "button" class to inherit Storefront/WooCommerce styling; add custom class for tweaks if needed
        $read_more = '<p class="read-more-wrapper"><a class="button read-more-button" href="' . esc_url( get_permalink( $post->ID ) ) . '">Continue Reading</a></p>';
        return $excerpt . $read_more;
    }
    return $content;
}

// Only add the hooks if the feature is enabled
if ( shop_ux_toolkit_opt( 'blog_excerpts_enabled' ) ) {
    add_action( 'init', 'shop_ux_toolkit_blog_excerpts_custom_init' );
}

function shop_ux_toolkit_blog_excerpts_custom_init() {
    // Show excerpts on blog and archive pages with a Read More link
    add_filter( 'the_content', 'shop_ux_toolkit_use_excerpt_on_blog_pages_with_read_more' );
    // Customize excerpt length (increased to 55 for a few more readable lines; adjust as needed)
    add_filter( 'excerpt_length', function( $length ) {
        return 55; // Adjust this number as needed
    }, 999 );
    // Customize the excerpt ending text
    add_filter( 'excerpt_more', function() {
        return '...'; // Or change to '' for no ellipsis
    });
}

function shop_ux_toolkit_add_blog_page_header() {
    if ( is_home() && ! is_front_page() ) {
        $blog_page_id = get_option( 'page_for_posts' );
        if ( $blog_page_id ) {
            $blog_page = get_post( $blog_page_id );
           
            // Setup postdata to properly render the_content()
            $original_post = $GLOBALS['post']; // Backup current post
            $GLOBALS['post'] = $blog_page; // Temporarily set as current post
            setup_postdata( $blog_page );
            ?>
            <header class="page-header">
                <h1 class="page-title"><?php echo esc_html( $blog_page->post_title ); ?></h1>
                <?php if ( has_post_thumbnail( $blog_page_id ) ) {
                    echo get_the_post_thumbnail( $blog_page_id, 'full', array( 'class' => 'page-featured-image' ) );
                } ?>
            </header>
            <div class="taxonomy-description">
                <?php
                // Temporarily remove the excerpt filter to get full content
                remove_filter( 'the_content', 'shop_ux_toolkit_use_excerpt_on_blog_pages_with_read_more' );
               
                // Force full content if needed (uncomment if issues persist after filter removal)
                // global $more; $more = -1;
                the_content();
               
                // Add the filter back for the post loop below
                add_filter( 'the_content', 'shop_ux_toolkit_use_excerpt_on_blog_pages_with_read_more' );
                ?>
            </div>
            <?php
            // Reset to original
            $GLOBALS['post'] = $original_post;
            wp_reset_postdata();
        }
    }
}
add_action( 'storefront_loop_before', 'shop_ux_toolkit_add_blog_page_header' );

// Remove archive title prefixes like "Category:", "Tag:", etc.
add_filter( 'get_the_archive_title', function( $title ) {
    return preg_replace( '/^\w+:\s*/', '', $title );
} );