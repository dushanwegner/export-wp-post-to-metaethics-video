<?php
/**
 * Plugin Name: Video Exporter
 * Description: Adds a button to the post edit page to send post data to a specified URL.
 * Version: 1.0
 * Author: Your Name
 */

class Video_Exporter {
    private $api_endpoint;
    private $api_token;
    private $max_retries = 3;

    public function __construct() {
        // Add button to post edit screen
        add_action('edit_form_after_title', array($this, 'add_export_button'));
        // Add admin menu page
        add_action('admin_menu', array($this, 'add_admin_menu'));
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        // Set API endpoint from WordPress options
        $this->api_endpoint = get_option('video_exporter_api_endpoint', '');
        // Set API token from WordPress options
        $this->api_token = get_option('video_exporter_api_token', '');
        
        // Add admin notice if API URL is not configured
        if (empty($this->api_endpoint) || empty($this->api_token)) {
            add_action('admin_notices', array($this, 'show_api_missing_notice'));
        }
    }

    public function show_api_missing_notice() {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><?php _e('Video Exporter: Please configure the API endpoint URL and API token in the settings to enable video generation.', 'video-exporter'); ?></p>
        </div>
        <?php
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

        add_submenu_page(
            'video-exporter',
            'Settings',
            'Settings',
            'manage_options',
            'video-exporter-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting(
            'video_exporter_settings',
            'video_exporter_api_endpoint',
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_api_endpoint'),
                'default' => ''
            )
        );

        register_setting(
            'video_exporter_settings',
            'video_exporter_api_token',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            )
        );

        add_settings_section(
            'video_exporter_main_section',
            'API Configuration',
            array($this, 'settings_section_callback'),
            'video-exporter-settings'
        );

        add_settings_field(
            'video_exporter_api_endpoint',
            'API Endpoint URL',
            array($this, 'api_endpoint_callback'),
            'video-exporter-settings',
            'video_exporter_main_section'
        );

        add_settings_field(
            'video_exporter_api_token',
            'API Token',
            array($this, 'api_token_callback'),
            'video-exporter-settings',
            'video_exporter_main_section'
        );
    }

    public function settings_section_callback() {
        echo '<p>Configure the endpoint URL and API token for your video generation API.</p>';
    }

    public function api_endpoint_callback() {
        $api_endpoint = get_option('video_exporter_api_endpoint', '');
        ?>
        <input type="url" 
               name="video_exporter_api_endpoint" 
               value="<?php echo esc_attr($api_endpoint); ?>" 
               class="regular-text"
               placeholder="https://your-api-endpoint.com/generate-video"
        />
        <p class="description">Enter the full URL of your video generation API endpoint.</p>
        <?php
    }

    public function api_token_callback() {
        $api_token = get_option('video_exporter_api_token', '');
        ?>
        <input type="password" 
               name="video_exporter_api_token" 
               value="<?php echo esc_attr($api_token); ?>" 
               class="regular-text"
               autocomplete="new-password"
        />
        <p class="description">Enter your API token for authentication.</p>
        <?php
    }

    public function sanitize_api_endpoint($input) {
        $sanitized = esc_url_raw(trim($input));
        
        if (empty($sanitized) && !empty($input)) {
            add_settings_error(
                'video_exporter_api_endpoint',
                'invalid_url',
                'Please enter a valid URL for the API endpoint.',
                'error'
            );
            return get_option('video_exporter_api_endpoint', '');
        }
        
        return $sanitized;
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('video_exporter_settings');
                do_settings_sections('video-exporter-settings');
                submit_button('Save Settings');
                ?>
            </form>
        </div>
        <?php
    }

    public function render_exporter_page() {
        // Verify nonce and get post data
        if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'export_post_to_video')) {
            $post_id = intval($_GET['post_id']);
            $post = get_post($post_id);
            
            if ($post) {
                // Check if API URL is configured
                if (empty($this->api_endpoint) || empty($this->api_token)) {
                    ?>
                    <div class="wrap">
                        <h1>Video Export Error</h1>
                        <div class="notice notice-error">
                            <p><?php _e('The API endpoint URL and API token are not configured. Please configure them in the settings to enable video generation.', 'video-exporter'); ?></p>
                        </div>
                    </div>
                    <?php
                    return;
                }

                $post_data = array(
                    'title' => $post->post_title,
                    'content' => $post->post_content,
                    'excerpt' => $post->post_excerpt,
                    'featured_image' => get_the_post_thumbnail_url($post_id, 'full'),
                    'post_id' => $post_id,
                    'site_url' => get_site_url(),
                    'timestamp' => current_time('mysql')
                );

                // Send data to API with retry mechanism
                $response = $this->send_to_api($post_data);

                if (is_wp_error($response)) {
                    $error_message = $response->get_error_message();
                    $this->log_error($error_message, $post_id);
                    ?>
                    <div class="wrap">
                        <h1>Video Export Error</h1>
                        <div class="notice notice-error">
                            <p><?php echo esc_html($error_message); ?></p>
                        </div>
                    </div>
                    <?php
                } else {
                    $response_body = wp_remote_retrieve_body($response);
                    $response_data = json_decode($response_body, true);
                    
                    // Store the API response in post meta
                    update_post_meta($post_id, '_video_export_status', $response_data['status'] ?? 'pending');
                    update_post_meta($post_id, '_video_export_id', $response_data['export_id'] ?? '');
                    
                    ?>
                    <div class="wrap">
                        <h1>Video Export Initiated</h1>
                        <div class="notice notice-success">
                            <p>Your post has been successfully sent for video generation. Export ID: <?php echo esc_html($response_data['export_id'] ?? 'N/A'); ?></p>
                        </div>
                    </div>
                    <?php
                }
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

    private function send_to_api($post_data) {
        $retry_count = 0;
        
        // Format the data according to API expectations
        $api_data = array(
            'title' => $post_data['title'],
            'script' => wp_strip_all_tags($post_data['content']),
            'tagline' => $post_data['excerpt'],
            'website' => 1  // This might need to be configurable
        );

        while ($retry_count < $this->max_retries) {
            $response = wp_remote_post($this->api_endpoint, array(
                'timeout' => 45,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Token ' . $this->api_token,
                ),
                'body' => wp_json_encode($api_data),
            ));

            if (!is_wp_error($response) && 
                wp_remote_retrieve_response_code($response) >= 200 && 
                wp_remote_retrieve_response_code($response) < 300) {
                return $response;
            }

            $retry_count++;
            if ($retry_count < $this->max_retries) {
                sleep(2 * $retry_count); // Exponential backoff
            }
        }

        return is_wp_error($response) ? $response : new WP_Error(
            'api_error',
            'Failed to send data to API after ' . $this->max_retries . ' attempts'
        );
    }

    private function log_error($message, $post_id) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'post_id' => $post_id,
            'message' => $message
        );
        
        $existing_logs = get_option('video_exporter_error_logs', array());
        array_unshift($existing_logs, $log_entry);
        $existing_logs = array_slice($existing_logs, 0, 100); // Keep only last 100 logs
        
        update_option('video_exporter_error_logs', $existing_logs);
    }
}

new Video_Exporter();