<?php
class UG_Upload_Handler {
    
    private $github_api;
    private $settings;
    private $upload_dir;
    private $all_image_sizes = array();
    
    public function init() {
        add_filter('wp_handle_upload', array($this, 'handle_upload'), 10, 2);
        add_filter('wp_handle_sideload', array($this, 'handle_upload'), 10, 2);
        add_filter('wp_get_attachment_url', array($this, 'get_attachment_url'), 10, 2);
        add_filter('wp_get_attachment_image_src', array($this, 'get_attachment_image_src'), 10, 4);
        add_filter('wp_generate_attachment_metadata', array($this, 'handle_attachment_metadata'), 10, 2);
        add_action('delete_attachment', array($this, 'handle_attachment_delete'));
        
        // Disable image editing
        add_action('admin_menu', array($this, 'remove_image_edit_menu'), 999);
        add_filter('media_row_actions', array($this, 'remove_edit_action'), 10, 2);
        add_action('admin_init', array($this, 'disable_image_edit_page'));
        add_filter('attachment_fields_to_edit', array($this, 'remove_edit_fields'), 10, 2);
        add_filter('wp_ajax_imgedit_preview', array($this, 'disable_ajax_edit'), 1);
        add_filter('wp_ajax_imgedit', array($this, 'disable_ajax_edit'), 1);
        add_action('admin_head', array($this, 'remove_edit_scripts'));
        add_filter('wp_image_editors', array($this, 'disable_image_editors'));
        
        $this->settings = get_option('ug_settings', array());
        $this->upload_dir = wp_upload_dir();
        
        if (!empty($this->settings['github_token'])) {
            $this->github_api = new UG_GitHub_API(
                $this->settings['github_token'],
                $this->settings['github_username'],
                $this->settings['github_repo'],
                (isset($this->settings['repo_visibility']) && $this->settings['repo_visibility'] === 'private'),
                isset($this->settings['upload_path']) ? $this->settings['upload_path'] : ''
            );
        }
        
        $this->load_all_image_sizes();
    }
    
    public function remove_image_edit_menu() {
        remove_submenu_page('upload.php', 'post.php?post=%d&amp;action=edit');
    }
    
    public function remove_edit_action($actions, $post) {
        if ($post->post_type === 'attachment') {
            unset($actions['edit']);
            unset($actions['edit_image']);
        }
        return $actions;
    }
    
    public function disable_image_edit_page() {
        global $pagenow;
        
        if ($pagenow === 'post.php') {
            $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : '';
            if ($action === 'edit') {
                $post_id = isset($_GET['post']) ? intval(wp_unslash($_GET['post'])) : 0;
                if ($post_id && get_post_type($post_id) === 'attachment') {
                    wp_safe_redirect(admin_url('upload.php'));
                    exit;
                }
            }
        }
    }
    
    public function remove_edit_fields($fields, $post) {
        if (isset($fields['edit-image'])) {
            unset($fields['edit-image']);
        }
        return $fields;
    }
    
    public function disable_ajax_edit() {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            wp_die('Image editing is disabled.');
        }
    }
    
    public function remove_edit_scripts() {
        global $post;
        
        if (is_admin() && isset($post) && $post->post_type === 'attachment') {
            wp_dequeue_script('image-edit');
            wp_dequeue_script('media-grid');
        }
        
        echo '<style>
            .edit-attachment, .attachment-edit-link, .imgedit-edit,
            .imgedit-edit-button, .edit-image-button, .image-editor,
            #image-editor, .attachment-info .actions .edit-attachment,
            .media-frame .attachment-detail .edit-attachment,
            .media-toolbar-secondary .edit-attachment,
            .attachment-actions .edit-attachment {
                display: none !important;
            }
        </style>';
    }
    
    public function disable_image_editors($editors) {
        return array('WP_Image_Editor_GD');
    }
    
    private function load_all_image_sizes() {
        global $_wp_additional_image_sizes;
        
        $default_sizes = array(
            'thumbnail' => array(
                'width' => get_option('thumbnail_size_w', 150),
                'height' => get_option('thumbnail_size_h', 150),
                'crop' => get_option('thumbnail_crop', true)
            ),
            'medium' => array(
                'width' => get_option('medium_size_w', 300),
                'height' => get_option('medium_size_h', 300),
                'crop' => false
            ),
            'medium_large' => array(
                'width' => get_option('medium_large_size_w', 768),
                'height' => get_option('medium_large_size_h', 0),
                'crop' => false
            ),
            'large' => array(
                'width' => get_option('large_size_w', 1024),
                'height' => get_option('large_size_h', 1024),
                'crop' => false
            ),
            'full' => array(
                'width' => 0,
                'height' => 0,
                'crop' => false
            )
        );
        
        $additional_sizes = array();
        if (isset($_wp_additional_image_sizes) && is_array($_wp_additional_image_sizes)) {
            foreach ($_wp_additional_image_sizes as $name => $size) {
                $additional_sizes[$name] = array(
                    'width' => isset($size['width']) ? $size['width'] : 0,
                    'height' => isset($size['height']) ? $size['height'] : 0,
                    'crop' => isset($size['crop']) ? $size['crop'] : false
                );
            }
        }
        
        $this->all_image_sizes = array_merge($default_sizes, $additional_sizes);
    }
    
    public function handle_upload($upload, $context) {
        if (!$this->is_github_ready()) {
            return $upload;
        }
        
        $file = $upload['file'];
        
        if (!file_exists($file)) {
            return $upload;
        }
        
        $file_content = file_get_contents($file);
        $relative_path = str_replace($this->upload_dir['basedir'] . '/', '', $file);
        
        $result = $this->github_api->upload_file($relative_path, $file_content);
        
        if ($result['success']) {
            $upload['github_url'] = $result['url'];
            $upload['github_sha'] = $result['sha'];
            $upload['url'] = $result['url'];
            $this->store_upload_metadata($upload);
        }
        
        return $upload;
    }
    
    public function handle_attachment_metadata($metadata, $attachment_id) {
        if (!$this->is_github_ready()) {
            return $metadata;
        }
        
        $file_path = get_attached_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) {
            return $metadata;
        }
        
        $github_url = get_post_meta($attachment_id, '_github_url', true);
        if (empty($github_url)) {
            $file_content = file_get_contents($file_path);
            $relative_path = str_replace($this->upload_dir['basedir'] . '/', '', $file_path);
            
            $result = $this->github_api->upload_file($relative_path, $file_content);
            if ($result['success']) {
                update_post_meta($attachment_id, '_github_url', $result['url']);
                update_post_meta($attachment_id, '_github_sha', $result['sha']);
                $metadata['github_url'] = $result['url'];
            }
        }
        
        if (!empty($metadata['sizes'])) {
            $dir = dirname($file_path);
            
            foreach ($metadata['sizes'] as $size_name => $size_data) {
                if (isset($size_data['file'])) {
                    $thumb_path = $dir . '/' . $size_data['file'];
                    
                    if (file_exists($thumb_path)) {
                        $thumb_content = file_get_contents($thumb_path);
                        $thumb_relative = str_replace($this->upload_dir['basedir'] . '/', '', $thumb_path);
                        
                        $result = $this->github_api->upload_file(
                            $thumb_relative, 
                            $thumb_content, 
                            'Thumbnail: ' . $size_name
                        );
                        
                        if ($result['success']) {
                            $meta_key = '_github_thumb_' . $size_name;
                            update_post_meta($attachment_id, $meta_key, $result['url']);
                            $metadata['sizes'][$size_name]['github_url'] = $result['url'];
                        }
                    }
                }
            }
        }
        
        return $metadata;
    }
    
    public function handle_attachment_delete($attachment_id) {
        if (!$this->is_github_ready()) {
            return;
        }
        
        $file_path = get_attached_file($attachment_id);
        $metadata = wp_get_attachment_metadata($attachment_id);
        
        if ($file_path) {
            $relative_path = str_replace($this->upload_dir['basedir'] . '/', '', $file_path);
            $this->github_api->delete_file($relative_path, 'Deleted from WordPress');
        }
        
        if (!empty($metadata['sizes'])) {
            $dir = dirname($file_path);
            
            foreach ($metadata['sizes'] as $size_name => $size_data) {
                if (isset($size_data['file'])) {
                    $thumb_path = $dir . '/' . $size_data['file'];
                    $thumb_relative = str_replace($this->upload_dir['basedir'] . '/', '', $thumb_path);
                    
                    $this->github_api->delete_file($thumb_relative, 'Deleted thumbnail: ' . $size_name);
                    delete_post_meta($attachment_id, '_github_thumb_' . $size_name);
                }
            }
        }
        
        delete_post_meta($attachment_id, '_github_url');
        delete_post_meta($attachment_id, '_github_sha');
        delete_post_meta($attachment_id, '_github_migrated');
        delete_post_meta($attachment_id, '_ug_all_sizes');
    }
    
    public function get_attachment_url($url, $attachment_id) {
        $github_url = get_post_meta($attachment_id, '_github_url', true);
        if ($github_url) {
            return $github_url;
        }
        return $url;
    }
    
    public function get_attachment_image_src($image, $attachment_id, $size, $icon) {
        if (!$image) {
            return $image;
        }
        
        $size_name = $this->get_size_name($size);
        
        if ($size_name !== 'full') {
            $github_thumb_url = get_post_meta($attachment_id, '_github_thumb_' . $size_name, true);
            if ($github_thumb_url) {
                $image[0] = $github_thumb_url;
                return $image;
            }
            
            if (is_array($size)) {
                $metadata = wp_get_attachment_metadata($attachment_id);
                if (!empty($metadata['sizes'])) {
                    foreach ($metadata['sizes'] as $s_name => $s_data) {
                        $github_thumb_url = get_post_meta($attachment_id, '_github_thumb_' . $s_name, true);
                        if ($github_thumb_url && isset($s_data['width']) && isset($s_data['height'])) {
                            if ($s_data['width'] == $size[0] && $s_data['height'] == $size[1]) {
                                $image[0] = $github_thumb_url;
                                return $image;
                            }
                        }
                    }
                }
            }
        }
        
        $github_url = get_post_meta($attachment_id, '_github_url', true);
        if ($github_url) {
            $image[0] = $github_url;
        }
        
        return $image;
    }
    
    private function get_size_name($size) {
        if (is_array($size)) {
            foreach ($this->all_image_sizes as $name => $data) {
                if ($data['width'] == $size[0] && $data['height'] == $size[1]) {
                    return $name;
                }
            }
            return 'custom_' . $size[0] . 'x' . $size[1];
        }
        return $size;
    }
    
    private function is_github_ready() {
        return !empty($this->settings['github_token']) && 
               !empty($this->settings['github_username']) && 
               !empty($this->settings['github_repo']) &&
               $this->github_api !== null;
    }
    
    private function store_upload_metadata($upload) {
        if (isset($upload['github_url'])) {
            add_action('add_attachment', function($attachment_id) use ($upload) {
                if (isset($upload['github_url'])) {
                    update_post_meta($attachment_id, '_github_url', $upload['github_url']);
                    if (isset($upload['github_sha'])) {
                        update_post_meta($attachment_id, '_github_sha', $upload['github_sha']);
                    }
                }
            });
        }
    }
}