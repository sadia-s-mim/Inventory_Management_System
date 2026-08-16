
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.pc-confirm-delete').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!confirm('Are you sure you want to delete this record? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });


    document.querySelectorAll('.tree-toggle').forEach(function (el) {
        el.addEventListener('click', function () {
            const target = document.getElementById(this.dataset.target);
            if (target) {
                target.classList.toggle('d-none');
                const icon = this.querySelector('i');
                if (icon) {
                    icon.classList.toggle('bi-folder-plus');
                    icon.classList.toggle('bi-folder-minus');
                }
            }
        });
    });

    document.querySelectorAll('.pc-line-qty, .pc-line-price').forEach(function (el) {
        el.addEventListener('input', function () {
            const row = this.closest('tr');
            const qty = parseFloat(row.querySelector('.pc-line-qty').value) || 0;
            const price = parseFloat(row.querySelector('.pc-line-price').value) || 0;
            row.querySelector('.pc-line-subtotal').textContent = (qty * price).toFixed(2);
        });
    });
});
