<?php
/**
 * Plugin Name: Video Exporter
 * Description: Adds a button to the post edit page to send post data to a specified URL.
 * Version: 1.0
 * Author: Your Name
 */

class Video_Exporter {
    public function __construct() {
        // Add button to post edit screen
        add_action('edit_form_after_title', array($this, 'add_export_button'));
        // Add admin menu page
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }

    public function add_export_button() {
        global $post;
        $nonce = wp_create_nonce('export_post_to_video');
        $export_url = admin_url(sprintf(
            'admin.php?page=video-exporter&post_id=%d&_wpnonce=%s',
            $post->ID,
            $nonce
        ));
        echo '<a href="' . esc_url($export_url) . '" class="button">Export this post to Video</a>';
    }

    public function add_admin_menu() {
        add_menu_page(
            'Video Exporter',
            'Video Exporter',
            'edit_posts',
            'video-exporter',
            array($this, 'render_exporter_page'),
            'dashicons-video-alt3'
        );
    }

    public function render_exporter_page() {
        // Verify nonce and get post data
        if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'export_post_to_video')) {
            $post_id = intval($_GET['post_id']);
            // Rest of your code remains the same, just change $_POST to $_GET
            $post = get_post($post_id);
            
            if ($post) {
                $post_data = array(
                    'title' => $post->post_title,
                    'content' => $post->post_content,
                    'excerpt' => $post->post_excerpt,
                    'featured_image' => get_the_post_thumbnail_url($post_id, 'full')
                );
                
                // Store the data in a transient for 1 hour
                set_transient('video_export_data_' . $post_id, $post_data, HOUR_IN_SECONDS);
                
                // Display the data
                ?>
                <div class="wrap">
                    <h1>Video Export Data</h1>
                    <h2>Post Title: <?php echo esc_html($post_data['title']); ?></h2>
                    <div>
                        <h3>Content:</h3>
                        <?php echo wpautop($post_data['content']); ?>
                    </div>
                    <?php if ($post_data['featured_image']) : ?>
                        <div>
                            <h3>Featured Image:</h3>
                            <img src="<?php echo esc_url($post_data['featured_image']); ?>" style="max-width: 300px;">
                        </div>
                    <?php endif; ?>
                </div>
                <?php
            }
        } else {
            ?>
            <div class="wrap">
                <h1>Video Exporter</h1>
                <p>Please use the "Export to Video" button from a post edit page to export content.</p>
            </div>
            <?php
        }
    }
}

new Video_Exporter();