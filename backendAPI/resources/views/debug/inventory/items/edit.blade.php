@extends('debug.layout')

@section('title', 'Edit Item')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 m-0">Edit Item</h1>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-primary" href="{{ route('debug.inventory.items') }}">Items</a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="fw-semibold mb-2">Item Details</div>
            <form id="editItemForm" class="row g-3 align-items-end" autocomplete="off">
                <div class="col-12 col-md-3">
                    <label class="form-label" for="code">Code</label>
                    <input class="form-control form-control-sm" id="code" name="code" type="text" required>
                </div>
                <div class="col-12 col-md-5">
                    <label class="form-label" for="name">Name</label>
                    <input class="form-control form-control-sm" id="name" name="name" type="text" required>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label" for="type">Type</label>
                    <select class="form-select form-select-sm" id="type" name="type" required>
                        <option value="inventory">inventory</option>
                        <option value="service">service</option>
                        <option value="non-inventory">non-inventory</option>
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label" for="unit">Unit</label>
                    <input class="form-control form-control-sm" id="unit" name="unit" type="text" required>
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label" for="selling_price">Selling Price</label>
                    <input class="form-control form-control-sm" type="number" step="0.01" id="selling_price" name="selling_price" value="0">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label" for="cost_method">Cost Method</label>
                    <select class="form-select form-select-sm" id="cost_method" name="cost_method" required>
                        <option value="average">average</option>
                        <option value="fifo">fifo (not supported)</option>
                        <option value="lifo">lifo (not supported)</option>
                    </select>
                    <div class="text-muted small mt-1">Only <code>average</code> is supported by service.</div>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label" for="is_active">Active</label>
                    <select class="form-select form-select-sm" id="is_active" name="is_active">
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                    </select>
                </div>

                <div class="col-12"><hr class="my-1"></div>

                <div class="col-12 col-md-4">
                    <label class="form-label" for="inventory_account_id">Inventory Account</label>
                    <select class="form-select form-select-sm" id="inventory_account_id" name="inventory_account_id" required></select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="cogs_account_id">COGS Account</label>
                    <select class="form-select form-select-sm" id="cogs_account_id" name="cogs_account_id" required></select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="revenue_account_id">Revenue Account</label>
                    <select class="form-select form-select-sm" id="revenue_account_id" name="revenue_account_id" required></select>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="inventory_adjustment_account_id">Inventory Adjustment Account</label>
                    <select class="form-select form-select-sm" id="inventory_adjustment_account_id" name="inventory_adjustment_account_id" required></select>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="goods_in_transit_account_id">Goods In Transit Account</label>
                    <select class="form-select form-select-sm" id="goods_in_transit_account_id" name="goods_in_transit_account_id" required></select>
                </div>

                <div class="col-12 col-md-9 text-muted small">
                    Endpoint: <code>PUT /api/items/{{ $id }}</code>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button id="saveBtn" type="submit" class="btn btn-sm btn-primary">Save</button>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('debug.inventory.items') }}">Back</a>
                </div>
            </form>
            <pre class="small mb-0 mt-3" id="result"></pre>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const itemId = {{ (int) $id }};
            const form = document.getElementById('editItemForm');
            const result = document.getElementById('result');

            function escapeHtml(text) {
                return String(text ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }

            function fillAccountSelect(selectEl, accounts) {
                selectEl.innerHTML = '<option value="">Select account</option>';
                for (const acc of accounts || []) {
                    selectEl.insertAdjacentHTML(
                        'beforeend',
                        `<option value="${acc.id}">${escapeHtml(acc.code)} - ${escapeHtml(acc.name)} (${escapeHtml(acc.type)})</option>`
                    );
                }
            }

            async function loadAccounts() {
                const res = await window.DebugApi.apiJson('/api/accounts?include_inactive=1');
                const accounts = res.data || [];

                fillAccountSelect(document.getElementById('inventory_account_id'), accounts);
                fillAccountSelect(document.getElementById('cogs_account_id'), accounts);
                fillAccountSelect(document.getElementById('revenue_account_id'), accounts);
                fillAccountSelect(document.getElementById('inventory_adjustment_account_id'), accounts);
                fillAccountSelect(document.getElementById('goods_in_transit_account_id'), accounts);
            }

            async function loadItem() {
                const r = await window.DebugApi.apiFetch(`/api/items/${itemId}`);
                const b = await r.json().catch(() => null);
                if (!r.ok || !b?.data) {
                    const msg = b?.message || `Request failed (${r.status})`;
                    const errors = b?.errors ? JSON.stringify(b.errors, null, 2) : null;
                    window.DebugApi.showAlert('danger', msg, errors);
                    throw new Error(msg);
                }

                const item = b.data;
                document.getElementById('code').value = item.code || '';
                document.getElementById('name').value = item.name || '';
                document.getElementById('type').value = item.type || 'inventory';
                document.getElementById('unit').value = item.unit || 'pcs';
                document.getElementById('selling_price').value = Number(item.selling_price || 0);
                document.getElementById('cost_method').value = item.cost_method || 'average';
                document.getElementById('is_active').value = item.is_active ? '1' : '0';

                document.getElementById('inventory_account_id').value = item.inventory_account_id ? String(item.inventory_account_id) : '';
                document.getElementById('cogs_account_id').value = item.cogs_account_id ? String(item.cogs_account_id) : '';
                document.getElementById('revenue_account_id').value = item.revenue_account_id ? String(item.revenue_account_id) : '';
                document.getElementById('inventory_adjustment_account_id').value = item.inventory_adjustment_account_id ? String(item.inventory_adjustment_account_id) : '';
                document.getElementById('goods_in_transit_account_id').value = item.goods_in_transit_account_id ? String(item.goods_in_transit_account_id) : '';
            }

            function toInt(value) {
                const trimmed = String(value ?? '').trim();
                const parsed = parseInt(trimmed, 10);
                return Number.isFinite(parsed) ? parsed : 0;
            }

            function toNumber(value) {
                const trimmed = String(value ?? '').trim();
                if (!trimmed) return 0;
                const parsed = Number(trimmed);
                return Number.isFinite(parsed) ? parsed : 0;
            }

            async function save() {
                const fd = new FormData(form);
                const payload = {
                    code: String(fd.get('code') ?? '').trim(),
                    name: String(fd.get('name') ?? '').trim(),
                    type: String(fd.get('type') ?? '').trim(),
                    unit: String(fd.get('unit') ?? '').trim(),
                    selling_price: toNumber(fd.get('selling_price')),
                    cost_method: String(fd.get('cost_method') ?? '').trim(),
                    inventory_account_id: toInt(fd.get('inventory_account_id')),
                    cogs_account_id: toInt(fd.get('cogs_account_id')),
                    revenue_account_id: toInt(fd.get('revenue_account_id')),
                    inventory_adjustment_account_id: toInt(fd.get('inventory_adjustment_account_id')),
                    goods_in_transit_account_id: toInt(fd.get('goods_in_transit_account_id')),
                    is_active: String(fd.get('is_active') ?? '') === '1',
                };

                result.textContent = JSON.stringify({ step: 'payload', payload }, null, 2);

                const r = await window.DebugApi.apiFetch(`/api/items/${itemId}`, {
                    method: 'PUT',
                    body: JSON.stringify(payload),
                });
                const b = await r.json().catch(() => null);
                result.textContent = JSON.stringify(b, null, 2);

                if (!r.ok || !b?.data) {
                    const msg = b?.message || `Request failed (${r.status})`;
                    const errors = b?.errors ? JSON.stringify(b.errors, null, 2) : null;
                    window.DebugApi.showAlert('danger', msg, errors);
                    return;
                }

                window.DebugApi.showAlert('success', 'Item updated');
                window.location.href = '{{ route('debug.inventory.items') }}';
            }

            form.addEventListener('submit', (e) => {
                e.preventDefault();
                save().catch(() => {});
            });

            Promise.all([loadAccounts(), loadItem()]).catch(() => {});
        })();
    </script>
@endpush

