/**
 * Common JS utilities for Assembly Line Production Tracker
 */

const App = {
    baseUrl: (function() {
        const scripts = document.querySelectorAll('script[src*="app.js"]');
        if (scripts.length) {
            const src = scripts[scripts.length - 1].src;
            return src.replace('/assets/js/app.js', '');
        }
        // Fallback: derive from current page
        const path = window.location.pathname;
        if (path.includes('/pages/reports/')) return path.substring(0, path.indexOf('/pages/reports/'));
        if (path.includes('/pages/')) return path.substring(0, path.indexOf('/pages/'));
        if (path.includes('/api/')) return path.substring(0, path.indexOf('/api/'));
        return path.substring(0, path.lastIndexOf('/'));
    })(),

    /**
     * AJAX POST request
     */
    post(url, data) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        }).then(r => r.json());
    },

    /**
     * AJAX GET request
     */
    get(url) {
        return fetch(url).then(r => r.json());
    },

    /**
     * Show toast notification
     */
    toast(message, type = 'success', duration = 3000) {
        const existing = document.querySelectorAll('.toast');
        existing.forEach(t => t.remove());

        const el = document.createElement('div');
        el.className = `toast ${type}`;
        el.textContent = message;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), duration);
    },

    /**
     * Format number with commas
     */
    formatNumber(n) {
        if (n === null || n === undefined) return '0';
        return Number(n).toLocaleString('en-US');
    },

    /**
     * Format decimal to fixed places
     */
    formatDecimal(n, places = 2) {
        if (n === null || n === undefined) return '0.00';
        return Number(n).toFixed(places);
    },

    /**
     * Format date as DD-MMM-YYYY
     */
    formatDate(dateStr) {
        if (!dateStr) return '';
        const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        const d = new Date(dateStr + 'T00:00:00');
        const day = String(d.getDate()).padStart(2, '0');
        const mon = months[d.getMonth()];
        const year = d.getFullYear();
        return `${day}-${mon}-${year}`;
    },

    /**
     * Initialize date inputs to show DD-MMM-YYYY format in the field
     */
    initDateInputs() {
        document.querySelectorAll('input[type="date"]').forEach(input => {
            if (input.dataset.formatted) return; // Already processed
            input.dataset.formatted = 'true';

            const originalValue = input.value; // YYYY-MM-DD format
            const inputId = input.id;
            const inputName = input.name || inputId;

            // Create a text input to display DD-MMM-YYYY
            const textInput = document.createElement('input');
            textInput.type = 'text';
            textInput.className = input.className + ' date-text-input';
            textInput.value = App.formatDate(originalValue);
            textInput.readOnly = true;
            textInput.style.cssText = 'cursor:pointer;' + (input.style.cssText || '');
            if (inputId) textInput.id = inputId + '_display';

            // Hide the original date input but keep it functional
            input.style.cssText = 'position:absolute;opacity:0;width:1px;height:1px;pointer-events:none;';
            input.tabIndex = -1;

            // Insert text input before the hidden date input
            input.parentNode.insertBefore(textInput, input);

            // Click on text input opens the date picker
            textInput.addEventListener('click', () => {
                input.showPicker ? input.showPicker() : input.focus();
            });

            // When date changes, update the text display
            input.addEventListener('change', () => {
                textInput.value = App.formatDate(input.value);
            });
        });
    },

    /**
     * Get variance class
     */
    varianceClass(value) {
        const n = parseFloat(value);
        if (n >= 0) return 'variance-positive';
        return 'variance-negative';
    },

    /**
     * Get variance cell class
     */
    varianceCellClass(value) {
        const n = parseFloat(value);
        if (n >= 0) return 'variance-cell-positive';
        return 'variance-cell-negative';
    },

    /**
     * Tab switching
     */
    initTabs(container) {
        const tabBtns = container.querySelectorAll('.tab-btn');
        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const target = btn.dataset.tab;
                tabBtns.forEach(b => b.classList.remove('active'));
                container.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                btn.classList.add('active');
                const tabEl = container.querySelector(`#${target}`);
                if (tabEl) tabEl.classList.add('active');
            });
        });
    },

    /**
     * Expandable row toggle
     */
    initExpandable() {
        document.querySelectorAll('.expandable-toggle').forEach(toggle => {
            toggle.addEventListener('click', () => {
                toggle.classList.toggle('open');
                const targetId = toggle.dataset.target;
                const detail = document.getElementById(targetId);
                if (detail) detail.classList.toggle('open');
            });
        });
    },

    /**
     * Responsive nav dropdown toggle for mobile
     */
    initNav() {
        document.querySelectorAll('.nav-dropdown > a').forEach(link => {
            link.addEventListener('click', (e) => {
                if (window.innerWidth <= 768) {
                    e.preventDefault();
                    link.parentElement.classList.toggle('open');
                }
            });
        });
    }
};

document.addEventListener('DOMContentLoaded', () => {
    App.initNav();
    App.initDateInputs();
});
