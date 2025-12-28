# Git Updater

A WordPress plugin to keep your plugins up to date directly from GitHub repositories. Supports both public and private repositories.

## Features

- **Public & Private Repos**: Update plugins from any GitHub repository.
- **Private Repo Support**: Authenticates using a Personal Access Token (PAT).
- **Branch Selection**: Choose which branch to pull updates from (default: `main`).

## Installation

1.  Download the repository as a ZIP file.
2.  Upload it to your WordPress site via **Plugins > Add New > Upload Plugin**.
3.  Activate **Git Updater**.

## Configuration

Go to **Settings > Git Updater** to configure the plugin.

### 1. GitHub Token (Optional but Recommended)
A Personal Access Token is **required** for private repositories and recommended for public ones to avoid API rate limits.

1.  Go to [GitHub Settings > Developer settings > Personal access tokens](https://github.com/settings/tokens).
2.  Generate a new token (Classic).
3.  **Scopes**:
    *   **For Private Repositories**: Check `repo` (Full control of private repositories). This is the only way to access private plugin code.
    *   **For Public Repositories Only**: Check `public_repo` (Access public repositories).
4.  Copy the token and paste it into the **GitHub Personal Access Token** field in the plugin settings.

### 2. Repositories Map
Map your installed plugins to their GitHub repositories.

*   **Plugin Directory Name**: The folder name of the plugin in `wp-content/plugins` (e.g., `my-custom-plugin`).
*   **Repository**: The `owner/repo` string from GitHub (e.g., `antigravity/my-custom-plugin`).
*   **Branch**: The branch to track (default: `main`).

## Usage

1.  **Tag a Release**: On GitHub, create a new Release (e.g., `v1.0.1`).
2.  **Check for Updates**: In WordPress, go to **Dashboard > Updates** and click **Check Again**.
3.  **Update**: If a new version is detected, it will appear in the list of available updates. Click **Update Now**.

## Requirements

*   WordPress 5.0+
*   PHP 7.4+
