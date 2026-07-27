<p align="center">
  <img src="https://raw.githubusercontent.com/inject3r/upload-to-github/master/assets/icon-256x256.png" width="120" height="120" alt="Upload to GitHub">
</p>

# Upload to GitHub

Upload WordPress media files directly to a GitHub repository instead of the default uploads folder.

---

## Banner

![Plugin Banner](https://raw.githubusercontent.com/inject3r/upload-to-github/master/assets/banner-1544x500.png)

---

## Description

Upload to GitHub automatically uploads all your media files to a GitHub repository.

### Key Features

- **Direct GitHub Upload**: All media files are uploaded directly to your GitHub repository
- **Automatic Repository Creation**: If the repository doesn't exist, it will be automatically created
- **Support for All Image Sizes**: WordPress generates multiple image sizes - all are uploaded to GitHub
- **Private and Public Repositories**: Support for both public and private repositories
- **Custom Upload Path**: Define custom paths inside your repository
- **Migrate Existing Media**: Transfer all existing media files to GitHub
- **GitHub Pages Support**: For private repositories, media is served via GitHub Pages

### How It Works

1. Configure your GitHub credentials (username, repository name, and Personal Access Token)
2. Choose repository visibility (public or private)
3. Upload media files as usual - they'll be sent to GitHub
4. All media URLs are automatically updated to point to GitHub

---

## Installation

### From WordPress Repository

1. Go to **Plugins > Add New** in your WordPress admin
2. Search for "Upload to GitHub"
3. Click **Install Now** and then **Activate**

### Manual Installation

1. Upload the plugin files to `/wp-content/plugins/upload-to-github`
2. Activate the plugin through the Plugins screen
3. Go to **Upload to GitHub** in the admin menu and configure your settings
4. Enter your GitHub username, repository name, and Personal Access Token
5. Click **Save Settings** and **Test Connection**

---

## Frequently Asked Questions

### How do I get a GitHub Personal Access Token?

1. Log in to GitHub
2. Go to **Settings > Developer settings > Personal access tokens**
3. Click **Generate new token**
4. Select the `repo` scope
5. Copy the token and paste it into the plugin settings

### Can I use this with a private repository?

Yes! Select "Private (GitHub Pages)" as the repository visibility. The plugin will automatically create a GitHub Pages repository.

### What happens to my media URLs?

All media URLs are automatically updated to point to GitHub.

### Can I change the upload path?

Yes! Set a custom path in the plugin settings. Example: "test/test2" will upload to `/test/test2/uploads/year/month/`.

---

## Changelog

### 1.0.0

- Initial release
- Direct upload to GitHub repository
- Support for all WordPress image sizes
- Private repository support with GitHub Pages
- Media migration from WordPress uploads folder
- Custom upload path support
- Full translation support (Persian, Arabic, German, Spanish, French, Russian, Turkish)

---

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- GitHub account with Personal Access Token (repo scope)

---

## Support

For support, feature requests, or bug reports:  
[https://github.com/inject3r/upload-to-github/issues](https://github.com/inject3r/upload-to-github/issues)

---

## WordPress Plugin Repository

This plugin is also available on the official WordPress Plugin Directory:  
[https://wordpress.org/plugins/upload-to-github/](https://wordpress.org/plugins/upload-to-github/)

---

## License

GPLv2 or later
