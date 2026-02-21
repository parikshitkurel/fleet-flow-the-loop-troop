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
        fetch(`/fleetflow/api/${type}.php`, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
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
    const cargoInput    = form.querySelector('[name="cargo_weight"]');
    const capacityHint  = document.getElementById('capacityHint');

    const vehicleCapacities = window.vehicleCapacities || {};

    function checkCapacity() {
        const vid = vehicleSelect?.value;
        const cap = vehicleCapacities[vid];
        const wt  = parseFloat(cargoInput?.value) || 0;
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

document.addEventListener('DOMContentLoaded', () => {
    setupTripForm();

    // Auto-dismiss alerts
    document.querySelectorAll('.alert').forEach(el => {
        setTimeout(() => el.style.opacity = '0', 4000);
        setTimeout(() => el.remove(), 4500);
    });
});
