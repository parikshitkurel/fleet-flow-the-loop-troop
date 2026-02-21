// FleetFlow — app.js

// ─── MODAL ───────────────────────────────
function openModal(id) {
    document.getElementById(id)?.classList.add('open');
}
function closeModal(id) {
    document.getElementById(id)?.classList.remove('open');
}
document.addEventListener('click', e => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
    }
    if (e.target.classList.contains('modal-close')) {
        e.target.closest('.modal-overlay')?.classList.remove('open');
    }
});

// ─── CONFIRM DELETE ───────────────────────
function confirmAction(msg, url) {
    if (confirm(msg)) window.location.href = url;
}

// ─── STATUS TOGGLE CONFIRM ────────────────
function toggleStatus(id, type, current) {
    const next = current === 'Available' ? 'Out of Service' : 'Available';
    if (confirm(`Change status to "${next}"?`)) {
        fetch(`/fleetflownew/api/${type}.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=toggle_status&id=${id}`
        }).then(r => r.json()).then(d => {
            if (d.success) location.reload();
            else alert(d.error || 'Failed');
        });
    }
}

// ─── TRIP FORM VALIDATION (CLIENT SIDE) ───
function setupTripForm() {
    const form = document.getElementById('tripForm');
    if (!form) return;

    const vehicleSelect = form.querySelector('[name="vehicle_id"]');
    const cargoInput = form.querySelector('[name="cargo_weight"]');
    const capacityHint = document.getElementById('capacityHint');

    const vehicleCapacities = window.vehicleCapacities || {};

    function checkCapacity() {
        const vid = vehicleSelect?.value;
        const cap = vehicleCapacities[vid];
        const wt = parseFloat(cargoInput?.value) || 0;
        if (!cap || !capacityHint) return;
        capacityHint.textContent = `Max capacity: ${cap.toLocaleString()} kg`;
        if (wt > cap) {
            capacityHint.style.color = 'var(--danger)';
            capacityHint.textContent += ` — ⚠ Cargo exceeds capacity!`;
        } else {
            capacityHint.style.color = 'var(--text-sm)';
        }
    }

    vehicleSelect?.addEventListener('change', checkCapacity);
    cargoInput?.addEventListener('input', checkCapacity);
    checkCapacity();
}

// ─── PHONE VALIDATION ─────────────────────
function setupPhoneValidation() {
    const phoneInputs = document.querySelectorAll('input[type="tel"][name="phone"]');
    phoneInputs.forEach(input => {
        input.addEventListener('keypress', function (e) {
            if (e.which < 48 || e.which > 57) e.preventDefault();
        });
        input.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').substring(0, 10);
            const pattern = /^[6-9]\d{9}$/;
            if (this.value && !pattern.test(this.value)) {
                this.classList.add('is-invalid');
                this.setCustomValidity('Enter valid 10-digit Indian mobile number (starts with 6-9)');
            } else {
                this.classList.remove('is-invalid');
                this.setCustomValidity('');
            }
        });
        input.addEventListener('blur', function () { this.reportValidity(); });
    });
}

// ─── PLATE VALIDATION ──────────────────────
function setupPlateValidation() {
    const plateInputs = document.querySelectorAll('input[name="license_plate"]');
    plateInputs.forEach(input => {
        input.addEventListener('input', function () {
            this.value = this.value.toUpperCase();
            const pattern = /^[A-Z]{2}[0-9]{2}\s[A-Z]{1,2}\s[0-9]{4}$/;
            if (this.value && !pattern.test(this.value)) {
                this.classList.add('is-invalid');
                this.setCustomValidity('Enter valid Indian vehicle number (e.g., MP09 AB 1234)');
            } else {
                this.classList.remove('is-invalid');
                this.setCustomValidity('');
            }
        });
        input.addEventListener('blur', function () { this.reportValidity(); });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    setupTripForm();
    setupPhoneValidation();
    setupPlateValidation();

    // Auto-dismiss alerts
    document.querySelectorAll('.alert').forEach(el => {
        setTimeout(() => el.style.opacity = '0', 4000);
        setTimeout(() => el.remove(), 4500);
    });

    // ─── SIDEBAR TOGGLE (DESKTOP) ─────────────────────────
    const mobileToggle = document.getElementById('sidebarToggle');

    function setSidebarState(collapsed) {
        document.body.classList.toggle('sidebar-collapsed', collapsed);
        localStorage.setItem('sidebarCollapsed', collapsed);
    }

    mobileToggle?.addEventListener('click', () => {
        const isCurrentlyCollapsed = document.body.classList.contains('sidebar-collapsed');
        setSidebarState(!isCurrentlyCollapsed);
    });

    // Restore state on load
    if (localStorage.getItem('sidebarCollapsed') === 'true') {
        document.body.classList.add('sidebar-collapsed');
    }
});

