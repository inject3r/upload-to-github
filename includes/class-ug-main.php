<?php
/**
 * Admin UI: settings page, asset loading, and notice management.
 *
 * @package Upload_To_GitHub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the plugin's admin menu, settings page, and related admin hooks.
 */
class UG_Main {

	/**
	 * Wire up the settings and upload handler modules plus admin hooks.
	 *
	 * @return void
	 */
	public function init() {
		$settings = new UG_Settings();
		$settings->init();

		$upload_handler = new UG_Upload_Handler();
		$upload_handler->init();

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_notices', array( $this, 'show_admin_notices' ) );
		add_action( 'admin_head', array( $this, 'hide_other_plugin_notices' ) );
	}

	/**
	 * Register the plugin's top-level admin menu page.
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		add_menu_page(
			esc_html__( 'Upload to GitHub Settings', 'upload-to-github' ),
			esc_html__( 'Upload to GitHub', 'upload-to-github' ),
			'manage_options',
			'upload-to-github',
			array( $this, 'render_admin_page' ),
			'dashicons-upload',
			30
		);
	}

	/**
	 * Enqueue admin CSS/JS on the plugin's own settings page only.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'toplevel_page_upload-to-github' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'ug-admin-style',
			UG_PLUGIN_URL . 'assets/css/admin-style.css',
			array(),
			UG_VERSION
		);

		wp_enqueue_script(
			'ug-admin-script',
			UG_PLUGIN_URL . 'assets/js/admin-script.js',
			array( 'jquery' ),
			UG_VERSION,
			true
		);

		wp_localize_script(
			'ug-admin-script',
			'ug_ajax',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'ug_ajax_nonce' ),
				'version'  => UG_VERSION,
				'strings'  => array(
					'username_required'        => esc_html__( 'GitHub username is required.', 'upload-to-github' ),
					'username_pattern'         => esc_html__( 'Username can only contain letters, numbers, hyphens, and underscores.', 'upload-to-github' ),
					'username_min'             => esc_html__( 'Username must be at least 1 character.', 'upload-to-github' ),
					'username_max'             => esc_html__( 'Username cannot exceed 39 characters.', 'upload-to-github' ),
					'repo_required'            => esc_html__( 'Repository name is required.', 'upload-to-github' ),
					'repo_pattern'             => esc_html__( 'Repository name can only contain letters, numbers, hyphens, underscores, and dots.', 'upload-to-github' ),
					'repo_min'                 => esc_html__( 'Repository name must be at least 1 character.', 'upload-to-github' ),
					'repo_max'                 => esc_html__( 'Repository name cannot exceed 100 characters.', 'upload-to-github' ),
					'token_required'           => esc_html__( 'GitHub token is required.', 'upload-to-github' ),
					'token_min'                => esc_html__( 'Token must be at least 40 characters long.', 'upload-to-github' ),
					'token_max'                => esc_html__( 'Token cannot exceed 255 characters.', 'upload-to-github' ),
					'token_warning'            => esc_html__( 'Token format might be invalid. GitHub tokens usually start with ghp_, gho_, etc.', 'upload-to-github' ),
					'valid'                    => esc_html__( 'Valid', 'upload-to-github' ),
					'fill_fields'              => esc_html__( 'Please fill in all fields.', 'upload-to-github' ),
					'fix_errors'               => esc_html__( 'Please fix all validation errors before saving.', 'upload-to-github' ),
					'configure_first'          => esc_html__( 'Please configure your GitHub settings first.', 'upload-to-github' ),
					'save_settings'            => esc_html__( 'Save Settings', 'upload-to-github' ),
					'test_connection'          => esc_html__( 'Test Connection', 'upload-to-github' ),
					'start_migration'          => esc_html__( 'Start Migration', 'upload-to-github' ),
					'clear_repo'               => esc_html__( 'Clear Repository', 'upload-to-github' ),
					'saving'                   => esc_html__( 'Saving...', 'upload-to-github' ),
					'testing'                  => esc_html__( 'Testing...', 'upload-to-github' ),
					'migrating'                => esc_html__( 'Migrating...', 'upload-to-github' ),
					'clearing'                 => esc_html__( 'Clearing...', 'upload-to-github' ),
					'check_auth'               => esc_html__( 'Authentication', 'upload-to-github' ),
					'check_repo'               => esc_html__( 'Repository Access', 'upload-to-github' ),
					'check_write'              => esc_html__( 'Write Permission', 'upload-to-github' ),
					'check_pages'              => esc_html__( 'GitHub Pages', 'upload-to-github' ),
					'all_checks_passed'        => esc_html__( 'All checks passed! Connection is working properly.', 'upload-to-github' ),
					'some_checks_failed'       => esc_html__( 'Some checks failed. Please review the errors above.', 'upload-to-github' ),
					'migration_complete'       => esc_html__( 'Migration completed successfully!', 'upload-to-github' ),
					'migration_start_error'    => esc_html__( 'An error occurred while starting migration.', 'upload-to-github' ),
					'migration_progress_error' => esc_html__( 'Error checking migration progress.', 'upload-to-github' ),
					'clear_confirm'            => esc_html__( 'Are you sure you want to delete ALL files from the repository?\n\nThis action cannot be undone!', 'upload-to-github' ),
					'clear_final'              => esc_html__( 'Final confirmation: Delete all files from the GitHub repository?', 'upload-to-github' ),
					'clear_error'              => esc_html__( 'An error occurred while clearing the repository.', 'upload-to-github' ),
					'save_error'               => esc_html__( 'An error occurred while saving settings.', 'upload-to-github' ),
					'connection_error'         => esc_html__( 'Connection test failed.', 'upload-to-github' ),
				),
			)
		);
	}

	/**
	 * Hide notices from other plugins on the plugin's own admin page.
	 *
	 * The CSS/JS payload is attached to the already-enqueued admin
	 * style/script handles instead of being echoed as raw markup.
	 *
	 * @return void
	 */
	public function hide_other_plugin_notices() {
		$screen = get_current_screen();
		if ( ! $screen || 'toplevel_page_upload-to-github' !== $screen->base ) {
			return;
		}

		$css = '
            #wpbody-content > .notice,
            #wpbody-content > .notice-wrap,
            #wpbody-content > .updated,
            #wpbody-content > .error,
            #wpbody-content > .warning,
            #wpbody-content > .info,
            #wpbody-content > .is-dismissible {
                display: none !important;
            }

            #wpbody-content > .notice.ug-custom-notice,
            #wpbody-content > .notice.ug-custom-notice-wrap {
                display: block !important;
            }

            .ug-header .notice,
            .ug-header .notice-wrap,
            .ug-header .updated,
            .ug-header .error,
            .ug-header .warning,
            .ug-header .info,
            .ug-header .is-dismissible,
            .ug-header .dokan-admin-notices-wrap {
                display: none !important;
            }

            .notice-global,
            .notice-global-wrap {
                display: none !important;
            }
        ';
		wp_add_inline_style( 'ug-admin-style', $css );

		$js = "
        jQuery(document).ready(function($) {
            $('#wpbody-content').find('.notice, .notice-wrap, .updated, .error, .warning, .info, .is-dismissible').each(function() {
                var \$this = $(this);
                if (!\$this.hasClass('ug-custom-notice') && !\$this.hasClass('ug-custom-notice-wrap')) {
                    \$this.remove();
                }
            });

            $('.ug-header').find('.notice, .notice-wrap, .updated, .error, .warning, .info, .is-dismissible').remove();
        });
        ";
		wp_add_inline_script( 'ug-admin-script', $js );
	}

	/**
	 * Display a success notice after a completed media migration.
	 *
	 * @return void
	 */
	public function show_admin_notices() {
		$screen = get_current_screen();
		if ( ! $screen || 'toplevel_page_upload-to-github' !== $screen->base ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display flag from the migration redirect URL; triggers no state change.
		$ug_migrated = isset( $_GET['ug-migrated'] ) ? sanitize_text_field( wp_unslash( $_GET['ug-migrated'] ) ) : '';
		if ( 'success' === $ug_migrated ) {
			echo '<div class="notice notice-success is-dismissible ug-custom-notice"><p>' .
				esc_html__( 'All media files have been successfully migrated to GitHub!', 'upload-to-github' ) .
				'</p></div>';
		}
	}

	/**
	 * Render the plugin's admin settings page.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		?>
		<div class="wrap ug-wrap">
			<div class="ug-header">
				<div class="ug-header-left">
					<div class="ug-header-icon">
						<span class="dashicons dashicons-upload"></span>
					</div>
					<div>
						<h1><?php esc_html_e( 'Upload to GitHub', 'upload-to-github' ); ?></h1>
						<span class="ug-header-version">v<?php echo esc_html( UG_VERSION ); ?></span>
					</div>
				</div>
				<div class="ug-header-badge">
					<span class="dashicons dashicons-yes" style="color:#28a745;font-size:14px;width:14px;height:14px;line-height:14px;"></span>
					<?php esc_html_e( 'Connected to GitHub API', 'upload-to-github' ); ?>
				</div>
			</div>
			
			<div class="ug-tabs-wrapper">
				<ul class="ug-tabs" role="tablist">
					<li>
						<a href="#" class="ug-tab-link active" data-tab="settings">
							<span class="tab-icon dashicons dashicons-admin-generic"></span>
							<?php esc_html_e( 'Settings', 'upload-to-github' ); ?>
						</a>
					</li>
					<li>
						<a href="#" class="ug-tab-link" data-tab="migrate">
							<span class="tab-icon dashicons dashicons-migrate"></span>
							<?php esc_html_e( 'Migrate Media', 'upload-to-github' ); ?>
						</a>
					</li>
				</ul>
			</div>
			
			<div class="ug-tab-content">
				<div class="ug-tab-pane active" id="ug-tab-settings">
					<?php $this->render_settings_tab(); ?>
				</div>
				<div class="ug-tab-pane" id="ug-tab-migrate">
					<?php $this->render_migrate_tab(); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the "Settings" tab of the admin page.
	 *
	 * @return void
	 */
	private function render_settings_tab() {
		$settings = get_option(
			'ug_settings',
			array(
				'github_username' => '',
				'github_repo'     => '',
				'github_token'    => '',
				'repo_visibility' => 'public',
				'upload_path'     => '',
			)
		);

		$repo_display = $settings['github_repo'];
		$is_private   = ( 'private' === $settings['repo_visibility'] );
		if ( $is_private && ! empty( $settings['github_username'] ) ) {
			$repo_display = $settings['github_username'] . '.github.io';
		}
		?>
		<h2 class="ug-section-title"><?php esc_html_e( 'GitHub Configuration', 'upload-to-github' ); ?></h2>
		<p class="ug-section-desc"><?php esc_html_e( 'Configure your GitHub credentials to enable uploading media files directly to your repository.', 'upload-to-github' ); ?></p>
		
		<form id="ug-settings-form" novalidate>
			<div class="ug-form-group">
				<label class="ug-form-label" for="github_username">
					<?php esc_html_e( 'GitHub Username', 'upload-to-github' ); ?>
					<span class="required">*</span>
					<span class="label-hint"><?php esc_html_e( 'or organization name', 'upload-to-github' ); ?></span>
				</label>
				<input type="text" 
						id="github_username" 
						name="github_username" 
						value="<?php echo esc_attr( $settings['github_username'] ); ?>" 
						class="ug-form-control" 
						placeholder="<?php esc_attr_e( 'e.g., octocat', 'upload-to-github' ); ?>"
						autocomplete="off"
						data-validate="username"
						data-required="true">
				<div class="ug-validation-message" data-for="github_username"></div>
				<span class="ug-help-text">
					<?php esc_html_e( 'Your GitHub username or organization name where the repository exists.', 'upload-to-github' ); ?>
				</span>
			</div>
			
			<div class="ug-form-group">
				<label class="ug-form-label" for="github_repo">
					<?php esc_html_e( 'Repository Name', 'upload-to-github' ); ?>
					<span class="required">*</span>
				</label>
				<input type="text" 
						id="github_repo" 
						name="github_repo" 
						value="<?php echo esc_attr( $repo_display ); ?>" 
						class="ug-form-control" 
						placeholder="<?php esc_attr_e( 'e.g., my-repository', 'upload-to-github' ); ?>"
						autocomplete="off"
						data-validate="repo"
						data-required="true"
						<?php disabled( $is_private ); ?>>
				<div class="ug-validation-message" data-for="github_repo"></div>
				<span class="ug-help-text">
					<?php esc_html_e( 'The name of the repository where files will be uploaded.', 'upload-to-github' ); ?>
					<?php if ( $is_private ) : ?>
						<strong><?php esc_html_e( '(For private repositories, the repository is automatically set to username.github.io)', 'upload-to-github' ); ?></strong>
					<?php endif; ?>
				</span>
			</div>
			
			<div class="ug-form-group">
				<label class="ug-form-label" for="github_token">
					<?php esc_html_e( 'GitHub Token', 'upload-to-github' ); ?>
					<span class="required">*</span>
					<span class="label-hint"><?php esc_html_e( 'Personal Access Token', 'upload-to-github' ); ?></span>
				</label>
				<input type="password" 
						id="github_token" 
						name="github_token" 
						value="<?php echo esc_attr( $settings['github_token'] ); ?>" 
						class="ug-form-control" 
						placeholder="<?php esc_attr_e( 'Enter your GitHub Personal Access Token', 'upload-to-github' ); ?>"
						autocomplete="off"
						data-validate="token"
						data-required="true">
				<div class="ug-validation-message" data-for="github_token"></div>
				<span class="ug-help-text">
					<?php esc_html_e( 'Generate a token with <code>repo</code> scope.', 'upload-to-github' ); ?>
					<a href="https://github.com/settings/tokens" target="_blank" style="color:#0366d6;text-decoration:none;">
						<?php esc_html_e( 'Generate a new token →', 'upload-to-github' ); ?>
					</a>
				</span>
			</div>
			
			<div class="ug-form-group">
				<label class="ug-form-label" for="repo_visibility">
					<?php esc_html_e( 'Repository Visibility', 'upload-to-github' ); ?>
				</label>
				<select id="repo_visibility" name="repo_visibility" class="ug-form-control ug-form-select">
					<option value="public" <?php selected( $settings['repo_visibility'], 'public' ); ?>>
						<?php esc_html_e( 'Public', 'upload-to-github' ); ?>
					</option>
					<option value="private" <?php selected( $settings['repo_visibility'], 'private' ); ?>>
						<?php esc_html_e( 'Private (GitHub Pages)', 'upload-to-github' ); ?>
					</option>
				</select>
				<span class="ug-help-text">
					<?php esc_html_e( 'Visibility of the repository. Private repositories use GitHub Pages for serving media.', 'upload-to-github' ); ?>
				</span>
			</div>
			
			<div class="ug-form-group">
				<label class="ug-form-label" for="upload_path">
					<?php esc_html_e( 'Upload Path', 'upload-to-github' ); ?>
					<span class="label-hint"><?php esc_html_e( 'optional', 'upload-to-github' ); ?></span>
				</label>
				<input type="text" 
						id="upload_path" 
						name="upload_path" 
						value="<?php echo esc_attr( isset( $settings['upload_path'] ) ? $settings['upload_path'] : '' ); ?>" 
						class="ug-form-control" 
						placeholder="<?php esc_attr_e( 'e.g., test/test2', 'upload-to-github' ); ?>"
						autocomplete="off">
				<div class="ug-validation-message" data-for="upload_path"></div>
				<span class="ug-help-text">
					<?php esc_html_e( 'Custom path inside the repository for uploads. Example: "test/test2" will upload to /test/test2/', 'upload-to-github' ); ?>
				</span>
			</div>
			
			<div class="ug-btn-group">
				<button type="submit" id="ug-save-settings" class="ug-btn ug-btn-primary">
					<span class="dashicons dashicons-saved" style="font-size:16px;width:16px;height:16px;line-height:1.4;"></span>
					<?php esc_html_e( 'Save Settings', 'upload-to-github' ); ?>
				</button>
				<button type="button" id="ug-test-connection" class="ug-btn ug-btn-secondary">
					<span class="dashicons dashicons-networking" style="font-size:16px;width:16px;height:16px;line-height:1.4;"></span>
					<?php esc_html_e( 'Test Connection', 'upload-to-github' ); ?>
				</button>
				<button type="button" id="ug-clear-repo" class="ug-btn ug-btn-danger" style="display:none;">
					<span class="dashicons dashicons-trash" style="font-size:16px;width:16px;height:16px;line-height:1.4;"></span>
					<?php esc_html_e( 'Clear Repository', 'upload-to-github' ); ?>
				</button>
			</div>
		</form>
		
		<div id="ug-test-results" style="display:none; margin-top: 20px;">
			<div id="ug-test-checklist" class="ug-checklist"></div>
		</div>
		
		<div id="ug-message" class="ug-status-box"></div>
		<div id="ug-connection-status" class="ug-connection-status"></div>
		<?php
	}

	/**
	 * Render the "Migrate Media" tab of the admin page.
	 *
	 * @return void
	 */
	private function render_migrate_tab() {
		$settings     = get_option( 'ug_settings', array() );
		$has_settings = ! empty( $settings['github_token'] ) && ! empty( $settings['github_username'] ) && ! empty( $settings['github_repo'] );

		$total_media = wp_count_attachments();
		$total_count = array_sum( (array) $total_media );
		?>
		<h2 class="ug-section-title"><?php esc_html_e( 'Migrate Existing Media', 'upload-to-github' ); ?></h2>
		<p class="ug-section-desc"><?php esc_html_e( 'Transfer all existing media files from WordPress uploads folder to your GitHub repository.', 'upload-to-github' ); ?></p>
		
		<?php if ( ! $has_settings ) : ?>
			<div class="ug-notice ug-notice-warning">
				<span class="dashicons dashicons-warning" style="margin-right:8px;"></span>
				<?php esc_html_e( 'Please configure your GitHub settings first before migrating media files.', 'upload-to-github' ); ?>
			</div>
		<?php else : ?>
			<div class="ug-stats-grid">
				<div class="ug-stat-card">
					<div class="ug-stat-number"><?php echo esc_html( number_format_i18n( $total_count ) ); ?></div>
					<span class="ug-stat-label"><?php esc_html_e( 'Total Media Files', 'upload-to-github' ); ?></span>
				</div>
				<div class="ug-stat-card">
					<div class="ug-stat-number" id="ug-migrated-count">0</div>
					<span class="ug-stat-label"><?php esc_html_e( 'Migrated', 'upload-to-github' ); ?></span>
				</div>
				<div class="ug-stat-card">
					<div class="ug-stat-number" id="ug-remaining-count"><?php echo esc_html( number_format_i18n( $total_count ) ); ?></div>
					<span class="ug-stat-label"><?php esc_html_e( 'Remaining', 'upload-to-github' ); ?></span>
				</div>
			</div>
			
			<div class="ug-option-box">
				<label>
					<input type="checkbox" id="ug-delete-local" value="1" checked>
					<span><?php esc_html_e( 'Delete local files after migration', 'upload-to-github' ); ?></span>
				</label>
				<div class="ug-help-text">
					<?php esc_html_e( 'If checked, local files will be removed from WordPress uploads folder after successful migration to GitHub.', 'upload-to-github' ); ?>
				</div>
			</div>
			
			<div id="ug-migration-progress" class="ug-progress-wrapper" style="display:none;">
				<div class="ug-progress-bar">
					<div class="ug-progress-fill" id="ug-progress-fill" style="width:0%;"></div>
				</div>
				<span class="ug-progress-text" id="ug-progress-percent">0%</span>
				<span class="ug-progress-detail" id="ug-progress-detail">0 / 0</span>
			</div>
			
			<div class="ug-btn-group">
				<button type="button" id="ug-start-migration" class="ug-btn ug-btn-success">
					<span class="dashicons dashicons-upload" style="font-size:16px;width:16px;height:16px;line-height:1.4;"></span>
					<?php esc_html_e( 'Start Migration', 'upload-to-github' ); ?>
				</button>
			</div>
			
			<div id="ug-migration-status" class="ug-status-box"></div>
		<?php endif; ?>
		<?php
	}
}