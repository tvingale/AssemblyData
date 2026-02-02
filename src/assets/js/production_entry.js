/**
 * Production Entry page logic - Mobile-first single slot entry
 */
document.addEventListener('DOMContentLoaded', () => {
    const dateInput = document.getElementById('prod-date');
    const groupTabs = document.getElementById('group-tabs');
    if (!groupTabs) return;

    App.initTabs(document.querySelector('.container'));

    // Store data per group
    const groupData = {};

    // Load all data on page load
    loadAllGroups();

    // Date change - reload everything
    dateInput.addEventListener('change', () => {
        loadAllGroups();
        updateShiftInfo();
    });

    function getDate() {
        return dateInput.value;
    }

    function updateShiftInfo() {
        App.get(`${PE_CONFIG.apiBase}/get_slots.php?date=${getDate()}`)
            .then(data => {
                const info = document.getElementById('shift-info');
                const dayNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
                const d = new Date(getDate() + 'T00:00:00');
                const dayName = dayNames[d.getDay()];
                info.textContent = `${data.shift.start.substring(0,5)} - ${data.shift.end.substring(0,5)} (${dayName})${data.shift.is_override ? ' [Override]' : ''}`;
            });
    }

    function loadAllGroups() {
        document.querySelectorAll('.tab-content[data-group-id]').forEach(panel => {
            const groupId = panel.dataset.groupId;
            loadGroupData(groupId);
        });
    }

    function loadGroupData(groupId) {
        const panel = document.querySelector(`[data-group-id="${groupId}"]`);
        const defaultCells = parseInt(panel.dataset.defaultCells) || 1;
        const rate = parseFloat(panel.dataset.rate) || 0;

        // Initialize group data
        groupData[groupId] = {
            defaultCells,
            rate,
            slots: [],
            entries: {},
            selectedSlot: null
        };

        // Fetch slots and existing entries
        Promise.all([
            App.get(`${PE_CONFIG.apiBase}/get_slots.php?date=${getDate()}&group_id=${groupId}`),
            App.post(`${PE_CONFIG.apiBase}/save_entry.php`, {
                action: 'get',
                date: getDate(),
                group_id: parseInt(groupId)
            })
        ]).then(([slotData, entryData]) => {
            groupData[groupId].slots = slotData.slots;

            // Map entries by slot number
            if (entryData.entries) {
                entryData.entries.forEach(e => {
                    groupData[groupId].entries[e.slot_number] = e;
                });
            }

            renderSlotSelector(groupId);
            renderHistoryTable(groupId);
            updateDaySummary(groupId);

            // Select first slot without data, or first slot
            const firstEmptySlot = groupData[groupId].slots.find(s => !groupData[groupId].entries[s.slot_number]);
            selectSlot(groupId, firstEmptySlot ? firstEmptySlot.slot_number : groupData[groupId].slots[0]?.slot_number);
        });
    }

    function renderSlotSelector(groupId) {
        const dropdown = document.getElementById(`slot-selector-${groupId}`);
        const data = groupData[groupId];
        dropdown.innerHTML = '';

        data.slots.forEach(slot => {
            const hasData = !!data.entries[slot.slot_number];
            const option = document.createElement('option');
            option.value = slot.slot_number;
            const status = hasData ? ' [Entered]' : ' [Pending]';
            option.textContent = `${slot.label || 'Slot ' + slot.slot_number} (${slot.start_time}-${slot.end_time})${status}`;
            dropdown.appendChild(option);
        });

        // Bind change event
        dropdown.onchange = () => {
            const slotNum = parseInt(dropdown.value);
            if (slotNum) selectSlot(groupId, slotNum);
        };
    }

    function selectSlot(groupId, slotNumber) {
        if (!slotNumber) return;

        const data = groupData[groupId];
        data.selectedSlot = slotNumber;

        // Update dropdown value
        const dropdown = document.getElementById(`slot-selector-${groupId}`);
        if (dropdown) dropdown.value = slotNumber;

        // Find slot info
        const slot = data.slots.find(s => s.slot_number === slotNumber);
        if (!slot) return;

        // Update slot info display
        const infoEl = document.getElementById(`slot-info-${groupId}`);
        infoEl.querySelector('.slot-name').textContent = slot.label || `Slot ${slotNumber}`;
        infoEl.querySelector('.slot-time').textContent = `${slot.start_time} - ${slot.end_time}`;

        // Get existing entry or defaults
        const entry = data.entries[slotNumber] || {};
        const cells = entry.cells_operative ?? data.defaultCells;
        const manpower = entry.manpower_headcount ?? 0;
        const actual = entry.actual_output ?? 0;
        const reasonId = entry.deficit_reason_id || '';
        const reasonNotes = entry.deficit_reason_other || '';
        const minutesLost = entry.downtime_minutes ?? 0;

        // Populate form
        document.getElementById(`inp-cells-${groupId}`).value = cells;
        document.getElementById(`inp-manpower-${groupId}`).value = manpower;
        document.getElementById(`inp-actual-${groupId}`).value = actual;
        document.getElementById(`inp-reason-${groupId}`).value = reasonId;
        document.getElementById(`inp-reason-notes-${groupId}`).value = reasonNotes;
        document.getElementById(`inp-minutes-lost-${groupId}`).value = minutesLost;

        // Store effective minutes for calculations
        data.currentEffMin = slot.effective_minutes;

        // Update calculations
        updateCalculations(groupId);

        // Clear status
        document.getElementById(`status-${groupId}`).textContent = '';
    }

    function updateCalculations(groupId) {
        const data = groupData[groupId];
        const rate = data.rate;
        const effMin = data.currentEffMin || 0;
        const cells = parseInt(document.getElementById(`inp-cells-${groupId}`).value) || 0;
        const actual = parseInt(document.getElementById(`inp-actual-${groupId}`).value) || 0;

        const target = rate * (effMin / 60) * cells;
        const variance = actual - target;

        document.getElementById(`calc-effmin-${groupId}`).textContent = formatNum(effMin);
        document.getElementById(`calc-target-${groupId}`).textContent = formatNum(target);

        const varEl = document.getElementById(`calc-variance-${groupId}`);
        varEl.textContent = (variance >= 0 ? '+' : '') + formatNum(variance);
        varEl.className = `calc-value ${variance >= 0 ? 'positive' : 'negative'}`;
    }

    function updateDaySummary(groupId) {
        const data = groupData[groupId];
        let totalTarget = 0, totalActual = 0, totalDowntime = 0;

        // Calculate from entries
        Object.values(data.entries).forEach(e => {
            totalTarget += parseFloat(e.target_output || 0);
            totalActual += parseInt(e.actual_output || 0);
            totalDowntime += parseFloat(e.downtime_minutes || 0);
        });

        const variance = totalActual - totalTarget;

        document.getElementById(`ds-target-${groupId}`).textContent = formatNum(totalTarget);
        document.getElementById(`ds-actual-${groupId}`).textContent = formatNum(totalActual, 0);

        const varEl = document.getElementById(`ds-variance-${groupId}`);
        varEl.textContent = (variance >= 0 ? '+' : '') + formatNum(variance);
        varEl.style.color = variance >= 0 ? 'var(--success)' : 'var(--error)';

        document.getElementById(`ds-downtime-${groupId}`).textContent = formatNum(totalDowntime, 0) + 'm';
    }

    function renderHistoryTable(groupId) {
        const data = groupData[groupId];
        const tbody = document.querySelector(`#history-${groupId} tbody`);
        tbody.innerHTML = '';

        if (data.slots.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--neutral-500);">No slots configured</td></tr>';
            return;
        }

        let hasSomeData = false;

        data.slots.forEach(slot => {
            const entry = data.entries[slot.slot_number];
            const tr = document.createElement('tr');

            if (entry) {
                hasSomeData = true;
                const variance = (entry.actual_output || 0) - (entry.target_output || 0);
                const varClass = variance >= 0 ? 'variance-positive' : 'variance-negative';

                tr.innerHTML = `
                    <td>${escHtml(slot.label || 'Slot ' + slot.slot_number)}</td>
                    <td class="num">${entry.cells_operative || 0}</td>
                    <td class="num">${entry.manpower_headcount || 0}</td>
                    <td class="num">${formatNum(entry.target_output || 0)}</td>
                    <td class="num">${entry.actual_output || 0}</td>
                    <td class="num ${varClass}">${formatNum(variance)}</td>
                    <td class="num">${entry.downtime_minutes || 0}</td>
                    <td><button class="btn btn-sm btn-outline edit-btn" data-slot="${slot.slot_number}">Edit</button></td>
                `;
            } else {
                tr.innerHTML = `
                    <td>${escHtml(slot.label || 'Slot ' + slot.slot_number)}</td>
                    <td colspan="6" style="color:var(--neutral-400);font-style:italic;">No data</td>
                    <td><button class="btn btn-sm btn-primary edit-btn" data-slot="${slot.slot_number}">Enter</button></td>
                `;
            }

            tbody.appendChild(tr);
        });

        // Bind edit buttons
        tbody.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const slotNum = parseInt(btn.dataset.slot);
                selectSlot(groupId, slotNum);
                // Scroll to form on mobile
                document.getElementById(`entry-form-${groupId}`).scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    }

    // Bind input changes to update calculations
    document.querySelectorAll('.tab-content[data-group-id]').forEach(panel => {
        const groupId = panel.dataset.groupId;

        ['cells', 'actual'].forEach(field => {
            const input = document.getElementById(`inp-${field}-${groupId}`);
            if (input) {
                input.addEventListener('input', () => updateCalculations(groupId));
            }
        });
    });

    // Save slot entry
    document.querySelectorAll('.save-slot-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const groupId = btn.dataset.groupId;
            saveSlotEntry(groupId, btn);
        });
    });

    function saveSlotEntry(groupId, btn) {
        const data = groupData[groupId];
        const slotNumber = data.selectedSlot;

        if (!slotNumber) {
            App.toast('Please select a time slot first.', 'error');
            return;
        }

        const entry = {
            slot_number: slotNumber,
            cells_operative: parseInt(document.getElementById(`inp-cells-${groupId}`).value) || 0,
            manpower_headcount: parseInt(document.getElementById(`inp-manpower-${groupId}`).value) || 0,
            actual_output: parseInt(document.getElementById(`inp-actual-${groupId}`).value) || 0,
            deficit_reason_id: document.getElementById(`inp-reason-${groupId}`).value || null,
            deficit_reason_other: document.getElementById(`inp-reason-notes-${groupId}`).value || null,
            downtime_minutes: parseFloat(document.getElementById(`inp-minutes-lost-${groupId}`).value) || 0,
        };

        if (entry.deficit_reason_id) {
            entry.deficit_reason_id = parseInt(entry.deficit_reason_id);
        }

        btn.disabled = true;
        const status = document.getElementById(`status-${groupId}`);
        status.textContent = 'Saving...';
        status.style.color = 'var(--neutral-600)';

        App.post(`${PE_CONFIG.apiBase}/save_entry.php`, {
            date: getDate(),
            group_id: parseInt(groupId),
            entries: [entry],
        }).then(result => {
            btn.disabled = false;
            if (result.success) {
                status.textContent = 'Saved!';
                status.style.color = 'var(--success)';
                App.toast('Entry saved successfully.');

                // Update local data
                if (result.saved && result.saved.length > 0) {
                    const saved = result.saved[0];
                    data.entries[slotNumber] = {
                        ...entry,
                        target_output: saved.target_output,
                        effective_minutes: saved.effective_minutes
                    };
                }

                // Refresh UI
                renderSlotSelector(groupId);
                renderHistoryTable(groupId);
                updateDaySummary(groupId);
                updateCalculations(groupId);

                // Move to next slot without data
                const nextEmpty = data.slots.find(s =>
                    s.slot_number > slotNumber && !data.entries[s.slot_number]
                );
                if (nextEmpty) {
                    setTimeout(() => selectSlot(groupId, nextEmpty.slot_number), 500);
                }

            } else {
                status.textContent = result.error || 'Save failed.';
                status.style.color = 'var(--error)';
                App.toast(result.error || 'Save failed.', 'error');
            }
        }).catch(err => {
            btn.disabled = false;
            status.textContent = 'Error saving.';
            status.style.color = 'var(--error)';
            App.toast('Network error.', 'error');
        });
    }

    // Utility functions
    function formatNum(n, decimals = 1) {
        if (n === null || n === undefined) return '0';
        return Number(n).toLocaleString(undefined, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }

    function escHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
});
