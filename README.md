# Git Updater for WordPress

**Git Updater** allows you to easily install and update WordPress plugins directly from GitHub repositories, supporting both public and private repositories.

## Features
- **Private Repository Support**: Authenticate using a GitHub Personal Access Token (PAT).
- **Branch Selection**: Install plugins from any specific branch (defaults to `main`).
- **Auto-Updates**: Integrates with WordPress's native update system.
- **Robust Installation**: Handles filesystem permissions automatically with native PHP fallbacks.
- **Verbose Logging**: Debug installation issues with a built-in log viewer.

## Installation

1. Download the `git-updater` plugin zip.
2. Go to your WordPress Dashboard > **Plugins** > **Add New** > **Upload Plugin**.
3. Upload and activate the plugin.

## Configuration

### 1. Generate a GitHub Token
To access private repositories (and to increase API limits for public ones), you need a GitHub Personal Access Token.

1. Go to [GitHub Settings > Developer Settings > Personal Access Tokens > Tokens (classic)](https://github.com/settings/tokens).
2. Click **Generate new token (classic)**.
3. Give it a Note (e.g., "WordPress Git Updater").
4. Select the `repo` scope (for private repositories) or `public_repo` (for public only).
5. Click **Generate token**.
6. **Copy the token immediately**.

### 2. Configure the Plugin
1. In your WordPress Dashboard, go to **Settings** > **Git Updater**.
2. Paste your **GitHub Token** in the "GitHub Access Token" field.
3. Click **Save Settings**.

## Usage

### Adding a Repository
1. Go to **Settings** > **Git Updater**.
2. Under "Monitored Repositories", click **Add Repo**.
3. Enter the details:
    - **Plugin Slug**: The folder name of the plugin (e.g., `my-custom-plugin`).
    - **Repository**: The GitHub repository in `owner/repo` format (e.g., `chit112/wordpress-git-updater`).
    - **Branch**: (Optional) The branch to track (defaults to `main`).
4. Click **Save Settings**.

### Installing a New Plugin
1. On the same settings page, browse to the "Install New Plugin" section.
2. **Repository**: Enter the full URL (e.g., `https://github.com/Let-s-Roll/wordpress-admin-ui`) or `owner/repo`.
3. **Branch**: (Optional) Leave empty for `main` or specify a branch.
4. **Target Slug**: The folder name you want for the plugin (e.g., `lets-roll-admin`).
5. Click **Install Plugin**.
6. Check the **Logs** section at the bottom if you encounter any issues.

### Updating Plugins
Once a repository is added to the "Monitored Repositories" list:
1. The plugin will check GitHub for updates every 12 hours (standard WordPress interval).
2. If the `Version` header in the remote `main` branch is higher than your installed version, an update will appear in **Dashboard** > **Updates**.
3. Click update just like a normal plugin!

## Troubleshooting
- **Installation Failed?**: Check the "Installation Logs" text area at the bottom of the Settings page.
- **GitHub Token**: Ensure your token has the correct `repo` permissions.
- **File Permissions**: The plugin attempts multiple methods (WP Filesystem, Native PHP) to handle file moves. If it fails, ensure your `wp-content/plugins` folder is writable by the web server.

## Author
Rune Brimer
[https://github.com/chit112/wordpress-git-updater](https://github.com/chit112/wordpress-git-updater)
