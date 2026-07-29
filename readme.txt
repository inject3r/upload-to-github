=== Upload to GitHub ===
Contributors: inject3r, alkesh7
Tags: github, media, uploads, offload, storage
Requires at least: 5.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Upload WordPress media files directly to a GitHub repository instead of the default uploads folder.

== Description ==

Upload to GitHub automatically uploads all your media files to a GitHub repository.

= Key Features =

* **Direct GitHub Upload**: All media files are uploaded directly to your GitHub repository.
* **Automatic Repository Creation**: If the repository doesn't exist, it will be automatically created.
* **Support for All Image Sizes**: WordPress generates multiple image sizes - all are uploaded to GitHub.
* **Private and Public Repositories**: Support for both public and private repositories.
* **Custom Upload Path**: Define custom paths inside your repository.
* **Migrate Existing Media**: Transfer all existing media files to GitHub.
* **GitHub Pages Support**: For private repositories, media is served via GitHub Pages.

= How It Works =

1. Configure your GitHub credentials (username, repository name, and Personal Access Token).
2. Choose repository visibility (public or private).
3. Upload media files as usual - they'll be sent to GitHub.
4. All media URLs are automatically updated to point to GitHub.

= Third-Party Services =

This plugin connects to the GitHub REST API (api.github.com) to create repositories and upload/delete media files on your behalf, using the Personal Access Token you provide in the plugin settings. No data is sent to GitHub unless you configure and use this plugin.

* [GitHub REST API](https://docs.github.com/en/rest)
* [GitHub Terms of Service](https://docs.github.com/en/site-policy/github-terms/github-terms-of-service)
* [GitHub Privacy Statement](https://docs.github.com/en/site-policy/privacy-policies/github-privacy-statement)

== Installation ==

= Manual Installation =

1. Upload the plugin files to `/wp-content/plugins/upload-to-github`.
2. Activate the plugin through the Plugins screen.
3. Go to **Upload to GitHub** in the admin menu and configure your settings.
4. Enter your GitHub username, repository name, and Personal Access Token.
5. Click **Save Settings** and **Test Connection**.

== Frequently Asked Questions ==

= How do I get a GitHub Personal Access Token? =

1. Log in to GitHub.
2. Go to **Settings > Developer settings > Personal access tokens**.
3. Click **Generate new token**.
4. Select the `repo` scope.
5. Copy the token and paste it into the plugin settings.

= Can I use this with a private repository? =

Yes! Select "Private (GitHub Pages)" as the repository visibility. The plugin will automatically create a GitHub Pages repository.

= What happens to my media URLs? =

All media URLs are automatically updated to point to GitHub.

= Can I change the upload path? =

Yes! Set a custom path in the plugin settings. Example: "test/test2" will upload to `/test/test2/uploads/year/month/`.

== Changelog ==

= 1.0.1 =
* Security: encoded GitHub API path segments (username, repository, file paths) to prevent malformed requests from special characters.
* Security: replaced raw inline `<script>`/`<style>` output with `wp_add_inline_style()`/`wp_add_inline_script()`.
* Fix: guarded `get_current_screen()` usage against a null return value.
* Fix: replaced `json_encode()` with `wp_json_encode()`.
* Fix: escaped remaining unescaped admin page output.
* Compatibility: confirmed compatibility up to WordPress 7.0.
* Housekeeping: full PHPCS/WordPress Coding Standards pass and added inline documentation.

= 1.0.0 =
* Initial release
* Direct upload to GitHub repository
* Support for all WordPress image sizes
* Private repository support with GitHub Pages
* Media migration from WordPress uploads folder
* Custom upload path support
* Full translation support

== Requirements ==

* WordPress 5.0 or higher
* PHP 7.4 or higher
* GitHub account with Personal Access Token (repo scope)

== Upgrade Notice ==

= 1.0.1 =
Security and coding-standards hardening release. Upgrade recommended for all users.

== Support ==

For support, feature requests, or bug reports:
https://github.com/inject3r/upload-to-github/issues

== License ==

GPLv2 or later
