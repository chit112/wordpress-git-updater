document.addEventListener('DOMContentLoaded', function () {
    // 1. Handle "Installed & Monitored Plugins" (Table) - READ ONLY LIST essentially, but has remove button
    const listBody = document.getElementById('git-updater-repos-list');
    if (listBody) {
        listBody.addEventListener('click', function (e) {
            if (e.target.classList.contains('git-updater-remove-repo')) {
                e.preventDefault();
                if (confirm('Are you sure you want to stop monitoring this plugin?')) {
                    const row = e.target.closest('tr');
                    if (row) {
                        row.remove();
                        // If we are in the form, we should probably submit or at least visual remove is done.
                        // But wait, the table is inside a form that posts to admin-post.php
                        // So removing the row means it won't be sent in $_POST['git_updater_repos'].
                    }
                }
            }
        });
    }

    // 2. Handle "Repositories Map" (Div List) - The Raw Settings Field
    const reposWrapper = document.getElementById('git-updater-repos-wrapper');
    const addRepoBtn = document.getElementById('git-updater-add-repo');

    if (reposWrapper) {
        // Delegate remove event
        reposWrapper.addEventListener('click', function (e) {
            if (e.target.classList.contains('git-updater-remove-repo')) {
                e.preventDefault();
                if (confirm('Remove this repository mapping?')) {
                    const row = e.target.closest('.repo-row');
                    if (row) {
                        row.remove();
                    }
                }
            }
        });
    }

    // Handle "Add Repository" button
    if (addRepoBtn && reposWrapper) {
        addRepoBtn.addEventListener('click', function (e) {
            e.preventDefault();
            
            const newRow = document.createElement('div');
            newRow.className = 'repo-row';
            newRow.style.marginBottom = '10px';
            
            // Create inputs matching the PHP structure
            // We use simple string concatenation for brevity, could be cleaner with DOM methods
            newRow.innerHTML = `
                <input type="text" name="git_updater_repos[][plugin]" placeholder="Plugin Directory Name" value="" class="regular-text" style="width: 250px;" />
                <input type="text" name="git_updater_repos[][repo]" placeholder="owner/repo" value="" class="regular-text" style="width: 250px;" />
                <input type="text" name="git_updater_repos[][branch]" placeholder="Branch (default: main)" value="" class="regular-text" style="width: 150px;" />
                <button type="button" class="button git-updater-remove-repo">Remove</button>
            `;
            
            reposWrapper.appendChild(newRow);
        });
    }
});