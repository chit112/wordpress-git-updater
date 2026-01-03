document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('git-updater-repos-wrapper');
    const addButton = document.getElementById('git-updater-add-repo');

    if (!container || !addButton) return;

    // Delegate click for remove buttons
    container.addEventListener('click', function (e) {
        if (e.target.classList.contains('git-updater-remove-repo')) {
            e.preventDefault();
            const row = e.target.closest('.repo-row');
            if (row) {
                row.remove();
            }
        }
    });

    addButton.addEventListener('click', function (e) {
        e.preventDefault();

        // Count existing rows to generate index (optional, but empty [] works for PHP)
        // Using empty brackets [] allows PHP to auto-index, which is safer for delete/re-add cycles.

        const newRow = document.createElement('div');
        newRow.classList.add('repo-row');
        newRow.style.marginBottom = '10px';
        newRow.innerHTML = `
            <input type="text" name="git_updater_repos[][plugin]" placeholder="Plugin Directory Name" value="" class="regular-text" style="width: 250px;"/>
            <input type="text" name="git_updater_repos[][repo]" placeholder="owner/repo" value="" class="regular-text" style="width: 250px;"/>
            <input type="text" name="git_updater_repos[][branch]" placeholder="Branch (default: main)" value="" class="regular-text" style="width: 150px;"/>
            <button class="button git-updater-remove-repo">Remove</button>
        `;

        // Insert before the instructions/add button
        container.appendChild(newRow);
    });
});
