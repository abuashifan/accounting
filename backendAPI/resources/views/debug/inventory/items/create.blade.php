@extends('debug.layout')

@section('title', 'Create Item')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 m-0">Create Item</h1>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-primary" href="{{ route('debug.inventory.items') }}">Items</a>
        </div>
    </div>

    @include('debug.inventory.items.form')
@endsection

@push('scripts')
    <script>
        (() => {
            const createResult = document.getElementById('createResult');
            const createForm = document.getElementById('createItemForm');

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
                const res = await window.DebugApi.apiJson('/api/accounts');
                const accounts = res.data || [];

                fillAccountSelect(document.getElementById('inventory_account_id'), accounts);
                fillAccountSelect(document.getElementById('cogs_account_id'), accounts);
                fillAccountSelect(document.getElementById('revenue_account_id'), accounts);
                fillAccountSelect(document.getElementById('inventory_adjustment_account_id'), accounts);
                fillAccountSelect(document.getElementById('goods_in_transit_account_id'), accounts);
            }

            function toIntOrNull(value) {
                const trimmed = String(value ?? '').trim();
                if (!trimmed) return null;
                const parsed = parseInt(trimmed, 10);
                return Number.isFinite(parsed) ? parsed : null;
            }

            function toNumber(value) {
                const trimmed = String(value ?? '').trim();
                if (!trimmed) return 0;
                const parsed = Number(trimmed);
                return Number.isFinite(parsed) ? parsed : 0;
            }

            async function createItem() {
                const codeEl = document.getElementById('code');
                const nameEl = document.getElementById('name');
                const typeEl = document.getElementById('type');
                const unitEl = document.getElementById('unit');

                console.log('[createItem] elements', { createForm, codeEl, nameEl, typeEl, unitEl });

                const fd = createForm ? new FormData(createForm) : null;
                const codeValue = String(fd?.get('code') ?? codeEl?.value ?? '').trim();
                const nameValue = String(fd?.get('name') ?? nameEl?.value ?? '').trim();
                const typeValue = String(fd?.get('type') ?? typeEl?.value ?? '').trim();
                const unitValue = String(fd?.get('unit') ?? unitEl?.value ?? '').trim();

                const captured = {
                    codeValue,
                    nameValue,
                    typeValue,
                    unitValue,
                    selling_price: String(fd?.get('selling_price') ?? ''),
                    cost_method: String(fd?.get('cost_method') ?? ''),
                    inventory_account_id: String(fd?.get('inventory_account_id') ?? ''),
                    cogs_account_id: String(fd?.get('cogs_account_id') ?? ''),
                    revenue_account_id: String(fd?.get('revenue_account_id') ?? ''),
                    inventory_adjustment_account_id: String(fd?.get('inventory_adjustment_account_id') ?? ''),
                    goods_in_transit_account_id: String(fd?.get('goods_in_transit_account_id') ?? ''),
                    is_active: String(fd?.get('is_active') ?? ''),
                };

                console.log('[createItem] captured', captured);
                createResult.textContent = JSON.stringify({ step: 'captured', ...captured }, null, 2);

                if (!codeValue) {
                    window.DebugApi.showAlert('warning', 'Code field is required');
                    codeEl?.focus();
                    return;
                }
                if (!nameValue) {
                    window.DebugApi.showAlert('warning', 'Name field is required');
                    nameEl?.focus();
                    return;
                }
                if (!typeValue) {
                    window.DebugApi.showAlert('warning', 'Type field is required');
                    typeEl?.focus();
                    return;
                }
                if (!unitValue) {
                    window.DebugApi.showAlert('warning', 'Unit field is required');
                    unitEl?.focus();
                    return;
                }

                const payload = {
                    code: codeValue,
                    name: nameValue,
                    type: typeValue,
                    unit: unitValue,
                    selling_price: toNumber(fd?.get('selling_price')),
                    cost_method: String(fd?.get('cost_method') ?? '').trim(),
                    inventory_account_id: toIntOrNull(fd?.get('inventory_account_id')),
                    cogs_account_id: toIntOrNull(fd?.get('cogs_account_id')),
                    revenue_account_id: toIntOrNull(fd?.get('revenue_account_id')),
                    inventory_adjustment_account_id: toIntOrNull(fd?.get('inventory_adjustment_account_id')),
                    goods_in_transit_account_id: toIntOrNull(fd?.get('goods_in_transit_account_id')),
                    is_active: String(fd?.get('is_active') ?? '') === '1',
                };

                console.log('[createItem] sending payload', payload, 'json=', JSON.stringify(payload));
                createResult.textContent = JSON.stringify({ step: 'payload', payload }, null, 2);

                const response = await window.DebugApi.apiFetch('/api/items', {
                    method: 'POST',
                    body: JSON.stringify(payload),
                });

                const body = await response.json().catch(() => null);
                createResult.textContent = JSON.stringify(body, null, 2);

                console.log('[createItem] response', { status: response.status, body });

                if (!response.ok) {
                    const message = body?.message || `Request failed (${response.status})`;
                    const errors = body?.errors ? JSON.stringify(body.errors, null, 2) : null;
                    window.DebugApi.showAlert('danger', message, errors);
                    return;
                }

                window.DebugApi.showAlert('success', 'Item created');
                window.location.href = '{{ route('debug.inventory.items') }}';
            }

            createForm?.addEventListener('submit', (e) => {
                e.preventDefault();
                createItem().catch(() => {});
            });

            document.getElementById('code')?.focus();
            loadAccounts().catch(() => {});
        })();
    </script>
@endpush

