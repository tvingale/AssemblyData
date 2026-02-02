/**
 * Downtime Entry page logic
 */
document.addEventListener('DOMContentLoaded', () => {
    const dateInput = document.getElementById('dt-date');
    const groupSelect = document.getElementById('dt-group');
    const form = document.getElementById('downtime-form');
    const tbody = document.querySelector('#downtime-table tbody');

    // Load downtimes on page load and on filter change
    loadDowntimes();
    dateInput.addEventListener('change', loadDowntimes);
    groupSelect.addEventListener('change', loadDowntimes);

    // Add downtime form
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const startTime = document.getElementById('dt-start').value;
        const endTime = document.getElementById('dt-end').value;
        const category = document.getElementById('dt-category').value;
        const reason = document.getElementById('dt-reason').value;

        if (!startTime) {
            App.toast('Start time is required.', 'error');
            return;
        }

        App.post(`${DT_CONFIG.apiBase}/save_downtime.php`, {
            action: 'add',
            date: dateInput.value,
            group_id: parseInt(groupSelect.value),
            start_time: startTime,
            end_time: endTime || null,
            category: category,
            reason: reason,
        }).then(result => {
            if (result.success) {
                App.toast('Downtime added.');
                form.reset();
                loadDowntimes();
            } else {
                App.toast(result.error || 'Failed to add.', 'error');
            }
        }).catch(() => App.toast('Network error.', 'error'));
    });

    function loadDowntimes() {
        App.post(`${DT_CONFIG.apiBase}/save_downtime.php`, {
            action: 'list',
            date: dateInput.value,
            group_id: parseInt(groupSelect.value),
        }).then(result => {
            renderDowntimes(result.downtimes || []);
            document.getElementById('total-downtime').textContent =
                `Total: ${App.formatDecimal(result.total_minutes || 0)} min`;
        });
    }

    function renderDowntimes(downtimes) {
        tbody.innerHTML = '';
        if (downtimes.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6">No downtime events recorded.</td></tr>';
            return;
        }

        downtimes.forEach(d => {
            const tr = document.createElement('tr');
            const endDisplay = d.end_time ? d.end_time.substring(0, 5) : '<em>Ongoing</em>';
            const durDisplay = d.duration_minutes ? App.formatDecimal(d.duration_minutes) : '-';

            tr.innerHTML = `
                <td>${d.start_time.substring(0, 5)}</td>
                <td>${endDisplay}</td>
                <td class="num">${durDisplay}</td>
                <td><span class="badge badge-warning">${escHtml(d.category)}</span></td>
                <td>${escHtml(d.reason || '')}</td>
                <td>
                    <button class="btn btn-sm btn-danger delete-dt" data-id="${d.id}">Delete</button>
                </td>
            `;
            tbody.appendChild(tr);
        });

        // Bind delete
        tbody.querySelectorAll('.delete-dt').forEach(btn => {
            btn.addEventListener('click', () => {
                if (!confirm('Delete this downtime event?')) return;
                App.post(`${DT_CONFIG.apiBase}/delete_downtime.php`, {
                    id: parseInt(btn.dataset.id),
                }).then(result => {
                    if (result.success) {
                        App.toast('Downtime deleted.');
                        loadDowntimes();
                    } else {
                        App.toast(result.error || 'Delete failed.', 'error');
                    }
                });
            });
        });
    }

    function escHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
});
