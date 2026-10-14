document.addEventListener('DOMContentLoaded', function () {
    const targetDialog = document.getElementById('targetDialog');
    const openTarget = document.getElementById('openTarget');
    const closeTarget = document.getElementById('closeTarget');
    const cancelTarget = document.getElementById('cancelTarget');

    function closeGoalDialog() {
        if (targetDialog && targetDialog.open) targetDialog.close();
    }

    if (openTarget && targetDialog) {
        openTarget.addEventListener('click', function () {
            if (typeof targetDialog.showModal === 'function') targetDialog.showModal();
        });
    }
    if (closeTarget) closeTarget.addEventListener('click', closeGoalDialog);
    if (cancelTarget) cancelTarget.addEventListener('click', closeGoalDialog);

    if (targetDialog) {
        targetDialog.addEventListener('click', function (event) {
            if (event.target === targetDialog) closeGoalDialog();
        });
    }

    const typeSelect = document.getElementById('tipe');
    const typeHint = document.getElementById('typeHint');
    function updateTypeHint() {
        if (!typeSelect || !typeHint) return;
        const hints = {
            pemasukan: 'Pemasukan akan menambah saldo kas.',
            pengeluaran: 'Pengeluaran akan mengurangi saldo kas.',
            setor_tabungan: 'Setor tabungan mengurangi kas dan menambah saldo tabungan.'
        };
        typeHint.textContent = hints[typeSelect.value] || '';
    }
    if (typeSelect) {
        typeSelect.addEventListener('change', updateTypeHint);
        updateTypeHint();
    }

    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            const question = form.getAttribute('data-confirm') || 'Lanjutkan tindakan ini?';
            if (!window.confirm(question)) event.preventDefault();
        });
    });
});
