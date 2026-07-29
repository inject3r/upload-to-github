<?php
/**
 * GitHub REST API client used by the plugin to store and manage media files.
 *
 * @package Upload_To_GitHub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin wrapper around the GitHub Contents/Repos REST API.
 */
class UG_GitHub_API {

	/**
	 * Personal access token used to authenticate requests.
	 *
	 * @var string
	 */
	private $token;

	/**
	 * GitHub username or organization that owns the repository.
	 *
	 * @var string
	 */
	private $username;

	/**
	 * Repository name.
	 *
	 * @var string
	 */
	private $repo;

	/**
	 * Base URL for the GitHub REST API.
	 *
	 * @var string
	 */
	private $api_url = 'https://api.github.com';

	/**
	 * Branch used for all content operations.
	 *
	 * @var string
	 */
	private $branch = 'main';

	/**
	 * Whether the configured repository is private.
	 *
	 * @var bool
	 */
	private $is_private = false;

	/**
	 * Base URL for GitHub Pages when the repository is private.
	 *
	 * @var string
	 */
	private $pages_url = '';

	/**
	 * Optional custom path prefix inside the repository.
	 *
	 * @var string
	 */
	private $upload_path = '';

	/**
	 * Set up the API client for a specific repository.
	 *
	 * @param string $token       GitHub personal access token.
	 * @param string $username   GitHub username or organization.
	 * @param string $repo       Repository name.
	 * @param bool   $is_private Whether the repository is private.
	 * @param string $upload_path Optional custom upload path prefix.
	 */
	public function __construct( $token, $username, $repo, $is_private = false, $upload_path = '' ) {
		$this->token       = $token;
		$this->username    = $username;
		$this->repo        = $repo;
		$this->is_private  = $is_private;
		$this->upload_path = trim( $upload_path, '/' );
		$this->pages_url   = 'https://' . rawurlencode( $username ) . '.github.io';
	}

	/**
	 * Verify the configured token can authenticate against the GitHub API.
	 *
	 * @return array{success: bool, message: string}
	 */
	public function test_connection() {
		$url      = $this->api_url . '/user';
		$response = $this->make_request( 'GET', $url );

		if ( $response && isset( $response['login'] ) ) {
			return array(
				'success' => true,
				'message' => $response['login'],
			);
		}

		return array(
			'success' => false,
			'message' => isset( $response['message'] ) ? $response['message'] : __( 'Authentication failed', 'upload-to-github' ),
		);
	}

	/**
	 * Upload and immediately delete a throwaway file to confirm write access.
	 *
	 * @return array{success: bool, message: string}
	 */
	public function test_write_permission() {
		$test_file = 'test-wp-upload-' . time() . '.txt';
		$content   = 'Test write permission at ' . current_time( 'mysql' );

		$result = $this->upload_file( $test_file, $content, 'Test write permission' );

		if ( $result['success'] ) {
			$this->delete_file( $test_file, 'Delete test file' );
			return array(
				'success' => true,
				'message' => __( 'Write permission verified', 'upload-to-github' ),
			);
		}

		return array(
			'success' => false,
			'message' => isset( $result['message'] ) ? $result['message'] : __( 'Write permission denied', 'upload-to-github' ),
		);
	}

	/**
	 * Check whether the configured repository already exists.
	 *
	 * @return bool
	 */
	public function repository_exists() {
		$url      = $this->api_url . '/repos/' . $this->encoded_repo_segments();
		$response = $this->make_request( 'GET', $url );
		return $response && ! isset( $response['message'] );
	}

	/**
	 * Fetch basic metadata about the configured repository.
	 *
	 * @return array|false
	 */
	public function get_repository_info() {
		$url      = $this->api_url . '/repos/' . $this->encoded_repo_segments();
		$response = $this->make_request( 'GET', $url );

		if ( $response && isset( $response['private'] ) ) {
			return array(
				'visibility'     => $response['private'] ? 'private' : 'public',
				'name'           => $response['name'],
				'full_name'      => $response['full_name'],
				'description'    => isset( $response['description'] ) ? $response['description'] : '',
				'created_at'     => $response['created_at'],
				'updated_at'     => $response['updated_at'],
				'default_branch' => isset( $response['default_branch'] ) ? $response['default_branch'] : 'main',
				'has_pages'      => isset( $response['has_pages'] ) ? $response['has_pages'] : false,
			);
		}

		return false;
	}

	/**
	 * Check whether a README.md already exists in the repository.
	 *
	 * @return bool
	 */
	public function readme_exists() {
		$url      = $this->api_url . '/repos/' . $this->encoded_repo_segments() . '/contents/README.md';
		$response = $this->make_request( 'GET', $url );

		return $response && ! isset( $response['message'] );
	}

	/**
	 * Create (or update) a README.md describing the plugin-managed repository.
	 *
	 * @return bool
	 */
	public function create_readme() {
		$url = $this->api_url . '/repos/' . $this->encoded_repo_segments() . '/contents/README.md';

		$readme_content  = "# Upload to GitHub - WordPress Plugin\n\n";
		$readme_content .= "This repository is automatically managed by the **[Upload to GitHub](https://github.com/inject3r/upload-to-github)** WordPress plugin.\n\n";
		$readme_content .= "## About This Repository\n\n";
		$readme_content .= '- **Created by:** [Upload to GitHub](https://github.com/inject3r/upload-to-github) WordPress Plugin v' . UG_VERSION . "\n";
		$readme_content .= "- **Purpose:** Stores media files uploaded from WordPress\n";
		$readme_content .= "- **Auto-generated:** Do not edit manually\n\n";
		$readme_content .= "## Links\n\n";
		$readme_content .= "- **Plugin Repository:** [https://github.com/inject3r/upload-to-github](https://github.com/inject3r/upload-to-github)\n";
		$readme_content .= "- **WordPress Plugin:** [Upload to GitHub](https://wordpress.org/plugins/upload-to-github)\n\n";
		$readme_content .= "## Important\n\n";
		$readme_content .= "> **Do not modify files in this repository manually!**\n\n";
		$readme_content .= "All files are managed by the Upload to GitHub WordPress plugin. Manual changes may be overwritten.\n\n";
		$readme_content .= "---\n\n";
		$readme_content .= '*Generated by [Upload to GitHub](https://github.com/inject3r/upload-to-github) v' . UG_VERSION . ' on ' . current_time( 'Y-m-d H:i:s' ) . "*\n";

		$existing = $this->make_request( 'GET', $url );
		$sha      = isset( $existing['sha'] ) ? $existing['sha'] : null;

		$data = array(
			'message' => 'Add README.md via Upload to GitHub plugin v' . UG_VERSION,
			'content' => base64_encode( $readme_content ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required by the GitHub Contents API, which stores file content as Base64.
			'branch'  => $this->branch,
		);

		if ( $sha ) {
			$data['sha'] = $sha;
		}

		$response = $this->make_request( 'PUT', $url, $data );

		return $response && isset( $response['content'] );
	}

	/**
	 * Create the configured repository on GitHub.
	 *
	 * @param string $visibility  Either 'public' or 'private'.
	 * @param string $description Repository description.
	 * @return bool
	 */
	public function create_repository( $visibility = 'public', $description = '' ) {
		$url  = $this->api_url . '/user/repos';
		$data = array(
			'name'        => $this->repo,
			'private'     => ( 'private' === $visibility ),
			'auto_init'   => true,
			'description' => $description ? $description : sprintf(
				/* translators: %s: plugin version number. */
				'[upload-to-github] v%s - This repository was created by the Upload to GitHub WordPress plugin',
				UG_VERSION
			),
		);

		$response = $this->make_request( 'POST', $url, $data );

		if ( $response && isset( $response['name'] ) ) {
			$this->create_readme();

			if ( 'private' === $visibility ) {
				$this->enable_github_pages();
			}
			return true;
		}

		return false;
	}

	/**
	 * Update the description of the configured repository.
	 *
	 * @param string $description New repository description.
	 * @return bool
	 */
	public function update_repository_description( $description ) {
		$url  = $this->api_url . '/repos/' . $this->encoded_repo_segments();
		$data = array(
			'description' => $description,
		);

		$response = $this->make_request( 'PATCH', $url, $data );

		return $response && isset( $response['name'] );
	}

	/**
	 * Update the visibility of the configured repository.
	 *
	 * @param string $visibility Either 'public' or 'private'.
	 * @return bool
	 */
	public function update_repository_visibility( $visibility ) {
		$url  = $this->api_url . '/repos/' . $this->encoded_repo_segments();
		$data = array(
			'private' => ( 'private' === $visibility ),
		);

		$response = $this->make_request( 'PATCH', $url, $data );

		if ( $response && isset( $response['name'] ) ) {
			$this->is_private = ( 'private' === $visibility );

			if ( $this->is_private ) {
				$this->enable_github_pages();
			}
			return true;
		}

		return false;
	}

	/**
	 * Ensure the user's GitHub Pages repository exists and serve the media repo through it.
	 *
	 * @return true
	 */
	public function enable_github_pages() {
		$pages_repo = $this->username . '.github.io';
		$url        = $this->api_url . '/repos/' . rawurlencode( $this->username ) . '/' . rawurlencode( $pages_repo );
		$response   = $this->make_request( 'GET', $url );

		if ( ! $response || isset( $response['message'] ) ) {
			$create_url = $this->api_url . '/user/repos';
			$data       = array(
				'name'        => $pages_repo,
				'private'     => false,
				'auto_init'   => true,
				'description' => sprintf(
					/* translators: %s: plugin version number. */
					'[upload-to-github] v%s - GitHub Pages repository for media files',
					UG_VERSION
				),
			);

			$this->make_request( 'POST', $create_url, $data );
		}

		$pages_url  = $this->api_url . '/repos/' . $this->encoded_repo_segments() . '/pages';
		$pages_data = array(
			'source' => array(
				'branch' => $this->branch,
				'path'   => '/',
			),
		);

		$this->make_request( 'POST', $pages_url, $pages_data );

		return true;
	}

	/**
	 * Upload (create or update) a file in the repository.
	 *
	 * @param string $file_path Path of the file relative to the WordPress uploads directory.
	 * @param string $content   Raw file content.
	 * @param string $message   Commit message.
	 * @return array{success: bool, url?: string, sha?: string, path?: string, message?: string}
	 */
	public function upload_file( $file_path, $content, $message = 'Upload via WordPress' ) {
		$path = $this->build_repo_path( $file_path );
		$url  = $this->api_url . '/repos/' . $this->encoded_repo_segments() . '/contents/' . $this->encode_path( $path );

		$existing = $this->make_request( 'GET', $url );
		$sha      = isset( $existing['sha'] ) ? $existing['sha'] : null;

		$data = array(
			'message' => $message . ' - ' . basename( $file_path ),
			'content' => base64_encode( $content ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required by the GitHub Contents API, which stores file content as Base64.
			'branch'  => $this->branch,
		);

		if ( $sha ) {
			$data['sha'] = $sha;
		}

		$response = $this->make_request( 'PUT', $url, $data );

		if ( $response && isset( $response['content'] ) ) {
			$file_url = $this->get_file_url( $response['content']['path'] );

			return array(
				'success' => true,
				'url'     => $file_url,
				'sha'     => $response['content']['sha'],
				'path'    => $response['content']['path'],
			);
		}

		return array(
			'success' => false,
			'message' => isset( $response['message'] ) ? $response['message'] : __( 'Upload failed', 'upload-to-github' ),
		);
	}

	/**
	 * Delete a file from the repository.
	 *
	 * @param string $file_path Path of the file relative to the WordPress uploads directory.
	 * @param string $message   Commit message.
	 * @return array{success: bool, message: string}
	 */
	public function delete_file( $file_path, $message = 'Delete via WordPress' ) {
		$path = $this->build_repo_path( $file_path );
		$url  = $this->api_url . '/repos/' . $this->encoded_repo_segments() . '/contents/' . $this->encode_path( $path );

		$existing = $this->make_request( 'GET', $url );
		if ( ! $existing || ! isset( $existing['sha'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'File not found on GitHub', 'upload-to-github' ),
			);
		}

		$data = array(
			'message' => $message . ' - ' . basename( $file_path ),
			'sha'     => $existing['sha'],
			'branch'  => $this->branch,
		);

		$response = $this->make_request( 'DELETE', $url, $data );

		if ( $response && ! isset( $response['message'] ) ) {
			return array(
				'success' => true,
				'message' => __( 'File deleted successfully', 'upload-to-github' ),
			);
		}

		return array(
			'success' => false,
			'message' => isset( $response['message'] ) ? $response['message'] : __( 'Delete failed', 'upload-to-github' ),
		);
	}

	/**
	 * Delete every file tracked under the plugin's uploads path in the repository.
	 *
	 * @return array{success: bool, message: string, deleted: int, errors: array}
	 */
	public function clear_repository() {
		$path = ! empty( $this->upload_path ) ? $this->upload_path . '/uploads' : 'uploads';

		$url      = $this->api_url . '/repos/' . $this->encoded_repo_segments() . '/contents/' . $this->encode_path( $path );
		$response = $this->make_request( 'GET', $url );

		if ( ! $response || isset( $response['message'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'No uploads folder found or repository is empty.', 'upload-to-github' ),
			);
		}

		$deleted = 0;
		$errors  = array();

		if ( isset( $response['tree'] ) && is_array( $response['tree'] ) ) {
			foreach ( $response['tree'] as $item ) {
				if ( 'blob' === $item['type'] ) {
					$result = $this->delete_file( $item['path'], 'Clear repository via plugin' );
					if ( $result['success'] ) {
						++$deleted;
					} else {
						$errors[] = $item['path'] . ': ' . $result['message'];
					}
				}
			}
		}

		return array(
			'success' => $deleted > 0 || empty( $errors ),
			'message' => sprintf(
				/* translators: 1: number of deleted files, 2: error messages if any. */
				__( 'Cleared %1$d files from repository. %2$s', 'upload-to-github' ),
				$deleted,
				! empty( $errors ) ? 'Errors: ' . implode( ', ', $errors ) : ''
			),
			'deleted' => $deleted,
			'errors'  => $errors,
		);
	}

	/**
	 * Build the public URL for a file already uploaded to the repository.
	 *
	 * @param string $path Path of the file inside the repository.
	 * @return string
	 */
	private function get_file_url( $path ) {
		if ( $this->is_private ) {
			return $this->pages_url . '/' . $path;
		}

		return 'https://raw.githubusercontent.com/' .
			$this->username . '/' .
			$this->repo . '/' .
			$this->branch . '/' .
			$path;
	}

	/**
	 * Prefix a WordPress-relative file path with the configured repository upload path.
	 *
	 * @param string $file_path Path of the file relative to the WordPress uploads directory.
	 * @return string
	 */
	private function build_repo_path( $file_path ) {
		if ( ! empty( $this->upload_path ) ) {
			return $this->upload_path . '/uploads/' . $file_path;
		}

		return 'uploads/' . $file_path;
	}

	/**
	 * URL-encode each segment of a repository-relative path, preserving slashes.
	 *
	 * @param string $path Repository-relative path.
	 * @return string
	 */
	private function encode_path( $path ) {
		return implode( '/', array_map( 'rawurlencode', explode( '/', $path ) ) );
	}

	/**
	 * Build the URL-encoded "owner/repo" segment used by most GitHub API endpoints.
	 *
	 * @return string
	 */
	private function encoded_repo_segments() {
		return rawurlencode( $this->username ) . '/' . rawurlencode( $this->repo );
	}

	/**
	 * Perform an authenticated request against the GitHub REST API.
	 *
	 * @param string     $method HTTP method.
	 * @param string     $url    Fully qualified request URL.
	 * @param array|null $data   Optional request body, sent as JSON.
	 * @return array|false Decoded JSON response, or false on transport error.
	 */
	public function make_request( $method, $url, $data = null ) {
		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'token ' . $this->token,
				'Accept'        => 'application/vnd.github.v3+json',
				'User-Agent'    => 'WordPress-Upload-To-GitHub',
			),
			'timeout' => 30,
		);

		if ( $data ) {
			$args['body']                    = wp_json_encode( $data );
			$args['headers']['Content-Type'] = 'application/json';
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		return json_decode( $body, true );
	}
}
