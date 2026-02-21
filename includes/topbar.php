<?php
// includes/topbar.php
// Universal search/filter/sort toolbar — include AFTER header.php
// Accepts: $topbarConfig = ['groups'=>[], 'filters'=>[], 'sorts'=>[], 'tableId'=>'mainTable']
// Defaults applied if $topbarConfig not set.

$tbCfg  = $topbarConfig ?? [];
$groups  = $tbCfg['groups']  ?? [];
$filters = $tbCfg['filters'] ?? [];
$sorts   = $tbCfg['sorts']   ?? [];
$tableId = $tbCfg['tableId'] ?? 'mainTable';
?>
<div class="universal-topbar" id="universalTopbar">

    <!-- Search -->
    <div class="utb-search-wrap">
        <svg class="utb-search-icon" xmlns="http://www.w3.org/2000/svg" width="15" height="15"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input type="text" class="utb-search"
               id="utbSearch"
               placeholder="Search <?= e($pageTitle ?? 'records') ?>…"
               autocomplete="off"
               data-table="<?= e($tableId) ?>">
        <button class="utb-clear-btn" id="utbClearBtn" title="Clear search">&times;</button>
    </div>

    <!-- Group By -->
    <?php if (!empty($groups)): ?>
    <div class="utb-ctrl-wrap">
        <label class="utb-ctrl-label">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
            </svg>
            Group by
        </label>
        <select class="utb-select" id="utbGroup" onchange="utbGroupBy(this.value,'<?= e($tableId) ?>')">
            <option value="">None</option>
            <?php foreach ($groups as $gVal => $gLabel): ?>
            <option value="<?= e($gVal) ?>"><?= e($gLabel) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>

    <!-- Filter -->
    <?php if (!empty($filters)): ?>
    <div class="utb-ctrl-wrap">
        <label class="utb-ctrl-label">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
            </svg>
            Filter
        </label>
        <select class="utb-select" id="utbFilter" onchange="utbFilter(this.value,'<?= e($tableId) ?>',<?= e($tbCfg['filterCol'] ?? 'null') ?>)">
            <option value="">All</option>
            <?php foreach ($filters as $fVal => $fLabel): ?>
            <option value="<?= e($fVal) ?>"><?= e($fLabel) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>

    <!-- Sort By -->
    <?php if (!empty($sorts)): ?>
    <div class="utb-ctrl-wrap">
        <label class="utb-ctrl-label">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="15" y2="12"/>
                <line x1="3" y1="18" x2="9" y2="18"/>
            </svg>
            Sort by...
        </label>
        <select class="utb-select" id="utbSort" onchange="utbSort(this.value,'<?= e($tableId) ?>')">
            <option value="">Default</option>
            <?php foreach ($sorts as $sVal => $sLabel): ?>
            <option value="<?= e($sVal) ?>"><?= e($sLabel) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>

    <div class="utb-spacer"></div>

    <!-- Result count badge -->
    <span class="utb-count" id="utbCount"></span>

</div>

<script>
// ── Live search across a table ────────────────────────────────────────────
(function(){
    const inp  = document.getElementById('utbSearch');
    const clrBtn = document.getElementById('utbClearBtn');

    inp?.addEventListener('input', function(){
        utbSearch(this.value, this.dataset.table);
        clrBtn.style.opacity = this.value ? '1' : '0';
    });
    clrBtn?.addEventListener('click', function(){
        inp.value = '';
        utbSearch('', inp.dataset.table);
        this.style.opacity = '0';
        inp.focus();
    });
})();

function utbSearch(query, tableId){
    const q = query.trim().toLowerCase();
    const tbody = document.querySelector('#' + tableId + ' tbody');
    if(!tbody) return;
    const rows = tbody.querySelectorAll('tr[data-searchable]');
    let vis = 0;
    rows.forEach(r => {
        const text = r.dataset.searchable.toLowerCase();
        const show = !q || text.includes(q);
        r.style.display = show ? '' : 'none';
        if(show) vis++;
    });
    updateCount(vis, rows.length);
}

function utbFilter(val, tableId, colIdx){
    const tbody = document.querySelector('#' + tableId + ' tbody');
    if(!tbody) return;
    const rows = tbody.querySelectorAll('tr[data-searchable]');
    let vis = 0;
    rows.forEach(r => {
        let show = true;
        if(val){
            if(colIdx !== null && colIdx !== undefined){
                const cell = r.cells[colIdx];
                show = cell && cell.textContent.toLowerCase().includes(val.toLowerCase());
            } else {
                show = r.dataset.searchable.toLowerCase().includes(val.toLowerCase());
            }
        }
        r.style.display = show ? '' : 'none';
        if(show) vis++;
    });
    updateCount(vis, rows.length);
}

function utbSort(val, tableId){
    if(!val) return;
    const [colStr, dir] = val.split(':');
    const col = parseInt(colStr, 10);
    const asc = dir !== 'desc';
    const tbody = document.querySelector('#' + tableId + ' tbody');
    if(!tbody) return;
    const rows = Array.from(tbody.querySelectorAll('tr[data-searchable]'));
    rows.sort((a, b) => {
        const ta = (a.cells[col]?.textContent || '').trim();
        const tb = (b.cells[col]?.textContent || '').trim();
        const na = parseFloat(ta.replace(/[^0-9.\-]/g,''));
        const nb = parseFloat(tb.replace(/[^0-9.\-]/g,''));
        if(!isNaN(na) && !isNaN(nb)) return asc ? na-nb : nb-na;
        return asc ? ta.localeCompare(tb) : tb.localeCompare(ta);
    });
    rows.forEach(r => tbody.appendChild(r));
}

function utbGroupBy(val, tableId){
    // Reset if no grouping
    if(!val){ utbSearch('', tableId); return; }
    const tbody = document.querySelector('#' + tableId + ' tbody');
    if(!tbody) return;
    const rows = Array.from(tbody.querySelectorAll('tr[data-searchable]'));
    // Group by data-group-{val} attribute
    const groups = {};
    rows.forEach(r => {
        const key = r.dataset['group_' + val] || r.dataset['group' + val.charAt(0).toUpperCase() + val.slice(1)] || '—';
        (groups[key] = groups[key] || []).push(r);
    });
    tbody.innerHTML = '';
    Object.entries(groups).sort(([a],[b]) => a.localeCompare(b)).forEach(([key, rs]) => {
        const sep = document.createElement('tr');
        sep.style.cssText = 'background:#f0edf4;';
        const td = document.createElement('td');
        td.colSpan = 99;
        td.style.cssText = 'font-weight:700;font-size:11px;letter-spacing:.8px;color:#714B67;padding:6px 14px;text-transform:uppercase;';
        td.textContent = key;
        sep.appendChild(td);
        tbody.appendChild(sep);
        rs.forEach(r => tbody.appendChild(r));
    });
    updateCount(rows.length, rows.length);
}

function updateCount(vis, total){
    const el = document.getElementById('utbCount');
    if(!el) return;
    el.textContent = vis < total ? `${vis} of ${total}` : `${total} record${total !== 1 ? 's' : ''}`;
    el.style.display = total > 0 ? '' : 'none';
}
</script>
