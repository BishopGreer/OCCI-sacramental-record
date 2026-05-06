/* OCCI Sacramental Records - Admin JS */
jQuery(document).ready(function($) {
    // Confirm delete actions
    $('.occi-delete').on('click', function(e) {
        if (!confirm('This will permanently delete this sacramental record. This action cannot be undone. Are you sure?')) {
            e.preventDefault();
        }
    });
});
