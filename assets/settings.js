document.addEventListener('DOMContentLoaded', function () {
    const listBody = document.getElementById('git-updater-repos-list');

    if (!listBody) return;

    // Delegate click for remove buttons
    listBody.addEventListener('click', function (e) {
        if (e.target.classList.contains('git-updater-remove-repo')) {
            e.preventDefault();
            // Confirm deletion
            if (confirm('Are you sure you want to stop monitoring this plugin?')) {
                const row = e.target.closest('tr');
                if (row) {
                    row.remove();
                }
            }
        }
    });
});
