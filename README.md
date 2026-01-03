# Git Updater for WordPress

**Git Updater** allows you to easily install and update WordPress plugins directly from GitHub repositories, supporting both public and private repositories.

## Features
- **Private Repository Support**: Authenticate using a GitHub Personal Access Token (PAT).
- **Branch Selection**: Install plugins from any specific branch (defaults to `main`).
- **Auto-Updates**: Integrates with WordPress's native update system.
- **Robust Installation**: Handles filesystem permissions automatically with native PHP fallbacks.
- **Verbose Logging**: Debug installation issues with a built-in log viewer.

## Installation

1. Download the `git-updater` plugin zip: [Download Latest Zip](https://github.com/chit112/wordpress-git-updater/archive/refs/heads/main.zip)
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

### Installing Plugins
1. Go to **Settings** > **Git Updater** > **Install New Plugin**.
2. **Repository**: Enter the full URL (e.g., `https://github.com/Let-s-Roll/wordpress-admin-ui`) or `owner/repo`.
3. **Branch**: (Optional) Leave empty for `main` or specify a branch.
4. **Target Slug**: The folder name you want for the plugin (e.g., `lets-roll-admin`).
5. Click **Install Plugin**.
6. The plugin will be installed and automatically added to the monitored list.

### Managing Monitored Plugins
The **Installed & Monitored Plugins** list is read-only to ensure safety and integrity.
- **Monitoring**: All installed plugins in this list are automatically checked for updates.
- **Changing Branch**: If you need to switch branches (e.g., test `develop`), **Remove** the plugin from the list and **Re-install** it with the desired branch.
- **Removing**: Click **Remove** to stop monitoring a plugin (this does not delete the plugin files).

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
