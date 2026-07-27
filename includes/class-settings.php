<?php
class UG_Settings {
    
    public function init() {
        add_action('wp_ajax_ug_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_ug_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_ug_start_migration', array($this, 'ajax_start_migration'));
        add_action('wp_ajax_ug_migration_progress', array($this, 'ajax_migration_progress'));
        add_action('wp_ajax_ug_clear_repository', array($this, 'ajax_clear_repository'));
        add_action('wp_ajax_ug_check_repo_status', array($this, 'ajax_check_repo_status'));
        add_action('wp_ajax_ug_check_pages_status', array($this, 'ajax_check_pages_status'));
    }
    
    public function ajax_save_settings() {
        if (!$this->verify_request() || !current_user_can('manage_options')) {
            $this->send_error(esc_html__('Unauthorized access.', 'upload-to-github'));
            return;
        }
        
        $data = array(
            'github_username' => isset($_POST['github_username']) ? sanitize_text_field(wp_unslash($_POST['github_username'])) : '',
            'github_repo' => isset($_POST['github_repo']) ? sanitize_text_field(wp_unslash($_POST['github_repo'])) : '',
            'github_token' => isset($_POST['github_token']) ? sanitize_text_field(wp_unslash($_POST['github_token'])) : '',
            'repo_visibility' => isset($_POST['repo_visibility']) ? sanitize_text_field(wp_unslash($_POST['repo_visibility'])) : 'public',
            'upload_path' => isset($_POST['upload_path']) ? sanitize_text_field(wp_unslash($_POST['upload_path'])) : ''
        );
        
        if (empty($data['github_username']) || empty($data['github_repo']) || empty($data['github_token'])) {
            $this->send_error(esc_html__('All fields are required.', 'upload-to-github'));
            return;
        }
        
        if ($data['repo_visibility'] === 'private') {
            $data['github_repo'] = $data['github_username'] . '.github.io';
        }
        
        $data['upload_path'] = trim($data['upload_path'], '/');
        
        $github_api = new UG_GitHub_API(
            $data['github_token'],
            $data['github_username'],
            $data['github_repo'],
            ($data['repo_visibility'] === 'private'),
            $data['upload_path']
        );
        
        $test_result = $github_api->test_connection();
        if (!$test_result['success']) {
            $this->send_error(esc_html__('GitHub authentication failed. Please check your credentials.', 'upload-to-github'));
            return;
        }
        
        $repo_exists = $github_api->repository_exists();
        
        if (!$repo_exists) {
            $description = sprintf(
                '[upload-to-github] v%s - This repository was created by the Upload to GitHub WordPress plugin',
                UG_VERSION
            );
            
            $created = $github_api->create_repository($data['repo_visibility'], $description);
            if (!$created) {
                $this->send_error(esc_html__('Failed to create repository. Please check your credentials.', 'upload-to-github'));
                return;
            }
        } else {
            $repo_info = $github_api->get_repository_info();
            
            if ($repo_info && isset($repo_info['description'])) {
                if (strpos($repo_info['description'], '[upload-to-github]') === false) {
                    $update_url = 'https://api.github.com/repos/' . $data['github_username'] . '/' . $data['github_repo'];
                    $update_data = array(
                        'description' => $repo_info['description'] . ' | [upload-to-github] v' . UG_VERSION
                    );
                    $github_api->make_request('PATCH', $update_url, $update_data);
                }
            }
            
            if ($repo_info && $repo_info['visibility'] !== $data['repo_visibility']) {
                $updated = $github_api->update_repository_visibility($data['repo_visibility']);
                if (!$updated) {
                    $this->send_error(esc_html__('Failed to update repository visibility.', 'upload-to-github'));
                    return;
                }
            }
        }
        
        update_option('ug_settings', $data);
        $this->send_success(esc_html__('Settings saved successfully!', 'upload-to-github'));
    }
    
    public function ajax_test_connection() {
        if (!$this->verify_request() || !current_user_can('manage_options')) {
            $this->send_error(esc_html__('Unauthorized access.', 'upload-to-github'));
            return;
        }
        
        $github_username = isset($_POST['github_username']) ? sanitize_text_field(wp_unslash($_POST['github_username'])) : '';
        $github_repo = isset($_POST['github_repo']) ? sanitize_text_field(wp_unslash($_POST['github_repo'])) : '';
        $github_token = isset($_POST['github_token']) ? sanitize_text_field(wp_unslash($_POST['github_token'])) : '';
        $repo_visibility = isset($_POST['repo_visibility']) ? sanitize_text_field(wp_unslash($_POST['repo_visibility'])) : 'public';
        $upload_path = isset($_POST['upload_path']) ? sanitize_text_field(wp_unslash($_POST['upload_path'])) : '';
        
        if (empty($github_username) || empty($github_repo) || empty($github_token)) {
            $this->send_error(esc_html__('All fields are required for testing.', 'upload-to-github'));
            return;
        }
        
        if ($repo_visibility === 'private') {
            $github_repo = $github_username . '.github.io';
        }
        
        $results = array();
        $github_api = new UG_GitHub_API(
            $github_token,
            $github_username,
            $github_repo,
            ($repo_visibility === 'private'),
            $upload_path
        );
        
        $results['auth'] = array(
            'label' => esc_html__('Authentication', 'upload-to-github'),
            'status' => false
        );
        
        $auth_test = $github_api->test_connection();
        if ($auth_test['success']) {
            $results['auth']['status'] = true;
            /* translators: %s: GitHub username */
            $results['auth']['message'] = sprintf(esc_html__('Authenticated as: %s', 'upload-to-github'), $auth_test['message']);
        } else {
            $results['auth']['message'] = $auth_test['message'];
        }
        
        $results['repo'] = array(
            'label' => esc_html__('Repository Access', 'upload-to-github'),
            'status' => false
        );
        
        if ($results['auth']['status']) {
            $repo_exists = $github_api->repository_exists();
            if ($repo_exists) {
                $repo_info = $github_api->get_repository_info();
                $has_signature = false;
                if ($repo_info && isset($repo_info['description'])) {
                    $has_signature = strpos($repo_info['description'], '[upload-to-github]') !== false;
                }
                $results['repo']['status'] = true;
                /* translators: 1: repository name, 2: additional info */
                $results['repo']['message'] = sprintf(
                    esc_html__('Repository "%1$s" exists %2$s', 'upload-to-github'),
                    $github_repo,
                    $has_signature ? esc_html__('(Created by this plugin)', 'upload-to-github') : ''
                );
            } else {
                $results['repo']['message'] = esc_html__('Repository does not exist (will be created)', 'upload-to-github');
                $results['repo']['status'] = true;
            }
        } else {
            $results['repo']['message'] = esc_html__('Cannot access repository (authentication failed)', 'upload-to-github');
        }
        
        $results['write'] = array(
            'label' => esc_html__('Write Permission', 'upload-to-github'),
            'status' => false
        );
        
        if ($results['auth']['status']) {
            $test_result = $github_api->test_write_permission();
            if ($test_result['success']) {
                $results['write']['status'] = true;
                $results['write']['message'] = esc_html__('Write permission confirmed', 'upload-to-github');
            } else {
                $results['write']['message'] = $test_result['message'];
            }
        } else {
            $results['write']['message'] = esc_html__('Cannot test write permission (authentication failed)', 'upload-to-github');
        }
        
        if ($repo_visibility === 'private') {
            $results['pages'] = array(
                'label' => esc_html__('GitHub Pages', 'upload-to-github'),
                'status' => false
            );
            
            if ($results['auth']['status']) {
                $github_api->enable_github_pages();
                $results['pages']['status'] = true;
                $results['pages']['message'] = esc_html__('GitHub Pages enabled', 'upload-to-github');
            } else {
                $results['pages']['message'] = esc_html__('Cannot enable Pages (authentication failed)', 'upload-to-github');
            }
        }
        
        $this->send_success('', array('results' => $results));
    }
    
    public function ajax_check_pages_status() {
        if (!$this->verify_request() || !current_user_can('manage_options')) {
            $this->send_error(esc_html__('Unauthorized access.', 'upload-to-github'));
            return;
        }
        
        $settings = get_option('ug_settings', array());
        if (empty($settings['github_token']) || empty($settings['github_username'])) {
            $this->send_error(esc_html__('Please configure settings first.', 'upload-to-github'));
            return;
        }
        
        $pages_repo = $settings['github_username'] . '.github.io';
        $github_api = new UG_GitHub_API(
            $settings['github_token'],
            $settings['github_username'],
            $pages_repo,
            false
        );
        
        $exists = $github_api->repository_exists();
        
        $this->send_success('', array(
            'exists' => $exists,
            'url' => 'https://' . $settings['github_username'] . '.github.io'
        ));
    }
    
    public function ajax_check_repo_status() {
        if (!$this->verify_request() || !current_user_can('manage_options')) {
            $this->send_error(esc_html__('Unauthorized access.', 'upload-to-github'));
            return;
        }
        
        $settings = get_option('ug_settings', array());
        if (empty($settings['github_token']) || empty($settings['github_username']) || empty($settings['github_repo'])) {
            $this->send_error(esc_html__('Please configure settings first.', 'upload-to-github'));
            return;
        }
        
        $github_api = new UG_GitHub_API(
            $settings['github_token'],
            $settings['github_username'],
            $settings['github_repo'],
            ($settings['repo_visibility'] === 'private'),
            isset($settings['upload_path']) ? $settings['upload_path'] : ''
        );
        
        $repo_info = $github_api->get_repository_info();
        if (!$repo_info) {
            $this->send_error(esc_html__('Repository not found.', 'upload-to-github'));
            return;
        }
        
        $has_signature = strpos($repo_info['description'], '[upload-to-github]') !== false;
        
        $this->send_success('', array(
            'repo' => $repo_info,
            'has_signature' => $has_signature,
            'show_clear' => $has_signature
        ));
    }
    
    public function ajax_clear_repository() {
        if (!$this->verify_request() || !current_user_can('manage_options')) {
            $this->send_error(esc_html__('Unauthorized access.', 'upload-to-github'));
            return;
        }
        
        $settings = get_option('ug_settings', array());
        if (empty($settings['github_token']) || empty($settings['github_username']) || empty($settings['github_repo'])) {
            $this->send_error(esc_html__('Please configure settings first.', 'upload-to-github'));
            return;
        }
        
        $github_api = new UG_GitHub_API(
            $settings['github_token'],
            $settings['github_username'],
            $settings['github_repo'],
            ($settings['repo_visibility'] === 'private'),
            isset($settings['upload_path']) ? $settings['upload_path'] : ''
        );
        
        $result = $github_api->clear_repository();
        
        if ($result['success']) {
            $this->send_success($result['message']);
        } else {
            $this->send_error($result['message']);
        }
    }
    
    public function ajax_start_migration() {
        if (!$this->verify_request() || !current_user_can('manage_options')) {
            $this->send_error(esc_html__('Unauthorized access.', 'upload-to-github'));
            return;
        }
        
        $settings = get_option('ug_settings', array());
        if (empty($settings['github_token']) || empty($settings['github_username']) || empty($settings['github_repo'])) {
            $this->send_error(esc_html__('Please configure GitHub settings first.', 'upload-to-github'));
            return;
        }
        
        $delete_local = isset($_POST['delete_local']) ? filter_var(wp_unslash($_POST['delete_local']), FILTER_VALIDATE_BOOLEAN) : true;
        
        $this->migrate_media_files($settings, $delete_local);
        
        $this->send_success(esc_html__('Migration started successfully!', 'upload-to-github'));
    }
    
    private function migrate_media_files($settings, $delete_local) {
        $upload_dir = wp_upload_dir();
        $github_api = new UG_GitHub_API(
            $settings['github_token'],
            $settings['github_username'],
            $settings['github_repo'],
            ($settings['repo_visibility'] === 'private'),
            isset($settings['upload_path']) ? $settings['upload_path'] : ''
        );
        
        $attachments = get_posts(array(
            'post_type' => 'attachment',
            'posts_per_page' => -1,
            'post_status' => 'any'
        ));
        
        $total = count($attachments);
        $migrated = 0;
        $failed = 0;
        $errors = array();
        
        update_option('ug_migration_progress', array(
            'total' => $total,
            'migrated' => 0,
            'failed' => 0,
            'progress' => 0,
            'errors' => array()
        ));
        
        foreach ($attachments as $attachment) {
            $file_path = get_attached_file($attachment->ID);
            
            if (!$file_path || !file_exists($file_path)) {
                $failed++;
                continue;
            }
            
            $file_content = file_get_contents($file_path);
            $relative_path = str_replace($upload_dir['basedir'] . '/', '', $file_path);
            
            $result = $github_api->upload_file($relative_path, $file_content);
            
            if ($result['success']) {
                update_post_meta($attachment->ID, '_github_url', $result['url']);
                update_post_meta($attachment->ID, '_github_sha', $result['sha']);
                update_post_meta($attachment->ID, '_github_migrated', 'yes');
                update_post_meta($attachment->ID, '_github_migration_date', current_time('mysql'));
                
                $metadata = wp_get_attachment_metadata($attachment->ID);
                if (!empty($metadata['sizes'])) {
                    $dir = dirname($file_path);
                    foreach ($metadata['sizes'] as $size => $size_data) {
                        if (isset($size_data['file'])) {
                            $thumb_path = $dir . '/' . $size_data['file'];
                            if (file_exists($thumb_path)) {
                                $thumb_content = file_get_contents($thumb_path);
                                $thumb_relative = str_replace($upload_dir['basedir'] . '/', '', $thumb_path);
                                $github_api->upload_file($thumb_relative, $thumb_content, 'Thumbnail: ' . $size);
                            }
                        }
                    }
                }
                
                if ($delete_local && file_exists($file_path)) {
                    wp_delete_file($file_path);
                    
                    $metadata = wp_get_attachment_metadata($attachment->ID);
                    if (!empty($metadata['sizes'])) {
                        $dir = dirname($file_path);
                        foreach ($metadata['sizes'] as $size) {
                            if (isset($size['file'])) {
                                $thumb_path = $dir . '/' . $size['file'];
                                if (file_exists($thumb_path)) {
                                    wp_delete_file($thumb_path);
                                }
                            }
                        }
                    }
                }
                
                $migrated++;
            } else {
                $failed++;
                /* translators: 1: filename, 2: error message */
                $errors[] = sprintf(
                    esc_html__('Failed to migrate %1$s: %2$s', 'upload-to-github'),
                    basename($file_path),
                    $result['message']
                );
            }
            
            $progress = round((($migrated + $failed) / $total) * 100);
            update_option('ug_migration_progress', array(
                'total' => $total,
                'migrated' => $migrated,
                'failed' => $failed,
                'progress' => min($progress, 100),
                'errors' => $errors
            ));
            
            // set_time_limit is discouraged but needed for large migrations
            // @codingStandardsIgnoreStart
            set_time_limit(30);
            // @codingStandardsIgnoreEnd
        }
        
        update_option('ug_migration_progress', array(
            'total' => $total,
            'migrated' => $migrated,
            'failed' => $failed,
            'progress' => 100,
            'errors' => $errors,
            'completed' => true
        ));
        
        update_option('ug_migration_completed', true);
    }
    
    public function ajax_migration_progress() {
        if (!$this->verify_request() || !current_user_can('manage_options')) {
            $this->send_error(esc_html__('Unauthorized access.', 'upload-to-github'));
            return;
        }
        
        $progress = get_option('ug_migration_progress', array(
            'total' => 0,
            'migrated' => 0,
            'failed' => 0,
            'progress' => 0,
            'errors' => array(),
            'completed' => false
        ));
        
        $this->send_success('', $progress);
    }
    
    private function verify_request() {
        if (!isset($_POST['nonce'])) {
            return false;
        }
        return wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ug_ajax_nonce');
    }
    
    private function send_success($message = '', $data = array()) {
        wp_send_json(array(
            'success' => true,
            'data' => array_merge(array(
                'message' => $message
            ), $data)
        ));
    }
    
    private function send_error($message = '') {
        wp_send_json(array(
            'success' => false,
            'data' => array(
                'message' => $message
            )
        ));
    }
}