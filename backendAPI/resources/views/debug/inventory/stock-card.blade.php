@extends('debug.layout')

@section('title', 'Debug Stock Card')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 m-0">Stock Card</h1>
        <button id="loadBtn" class="btn btn-sm btn-outline-primary">Load</button>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form class="row g-3 align-items-end">
                <div class="col-12 col-md-5">
                    <label class="form-label" for="item_id">Item</label>
                    <select class="form-select form-select-sm" id="item_id"></select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="warehouse_id">Warehouse (optional)</label>
                    <select class="form-select form-select-sm" id="warehouse_id"></select>
                </div>
                <div class="col-12 col-md-3">
                    <div class="text-muted small">Data source: <code>GET /api/reports/stock-card</code></div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th class="text-end">Qty In</th>
                        <th class="text-end">Qty Out</th>
                        <th class="text-end">Balance</th>
                    </tr>
                </thead>
                <tbody id="tbody">
                    <tr><td colspan="5" class="text-muted">Select item then click Load</td></tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const itemSelect = document.getElementById('item_id');
            const whSelect = document.getElementById('warehouse_id');
            const tbody = document.getElementById('tbody');

            function escapeHtml(text) {
                return String(text ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }

            async function loadItems() {
                const res = await window.DebugApi.apiFetch('/api/items');
                const payload = await res.json().catch(() => null);
                if (!res.ok || !payload?.data) return;
                const items = payload.data.data || [];
                itemSelect.innerHTML = '<option value="">Select item</option>';
                for (const it of items) {
                    itemSelect.insertAdjacentHTML('beforeend', `<option value="${it.id}">${escapeHtml(it.code)} - ${escapeHtml(it.name)}</option>`);
                }
            }

            async function loadWarehouses() {
                const res = await window.DebugApi.apiFetch('/api/warehouses');
                const payload = await res.json().catch(() => null);
                if (!res.ok || !payload?.data) return;
                const warehouses = payload.data || [];
                whSelect.innerHTML = '<option value="">All warehouses</option>';
                for (const wh of warehouses) {
                    whSelect.insertAdjacentHTML('beforeend', `<option value="${wh.id}">${escapeHtml(wh.code)} - ${escapeHtml(wh.name)}</option>`);
                }
            }

            async function loadCard() {
                const itemId = itemSelect.value;
                if (!itemId) {
                    window.DebugApi.showAlert('warning', 'item_id is required');
                    return;
                }

                const url = new URL('/api/reports/stock-card', window.location.origin);
                url.searchParams.set('item_id', itemId);
                if (whSelect.value) url.searchParams.set('warehouse_id', whSelect.value);

                const response = await window.DebugApi.apiFetch(url.toString());
                const payload = await response.json().catch(() => null);
                if (!response.ok || !payload?.data) {
                    const message = payload?.message || `Request failed (${response.status})`;
                    const errors = payload?.errors ? JSON.stringify(payload.errors, null, 2) : null;
                    window.DebugApi.showAlert('danger', message, errors);
                    return;
                }

                const entries = payload.data.entries || [];
                tbody.innerHTML = '';
                if (!entries.length) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-muted">No movements</td></tr>';
                    return;
                }

                for (const e of entries) {
                    tbody.insertAdjacentHTML('beforeend', `
                        <tr>
                            <td>${escapeHtml(e.date)}</td>
                            <td>${escapeHtml(e.type)}</td>
                            <td class="text-end">${escapeHtml(e.qty_in)}</td>
                            <td class="text-end">${escapeHtml(e.qty_out)}</td>
                            <td class="text-end">${escapeHtml(e.balance)}</td>
                        </tr>
                    `);
                }
            }

            document.getElementById('loadBtn').addEventListener('click', () => loadCard().catch(() => {}));

            Promise.all([loadItems(), loadWarehouses()]).catch(() => {});
        })();
    </script>
@endpush

