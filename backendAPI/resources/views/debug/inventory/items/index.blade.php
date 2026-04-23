@extends('debug.layout')

@section('title', 'Debug Items')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 m-0">Items</h1>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-primary" href="{{ route('debug.inventory.items.create') }}">Create Item</a>
            <button id="reloadBtn" type="button" class="btn btn-sm btn-outline-primary">Reload</button>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Unit</th>
                        <th class="text-end">Qty</th>
                        <th>Cost Method</th>
                        <th class="text-end">Selling Price</th>
                        <th>Active</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="tbody">
                    <tr><td colspan="10" class="text-muted">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const tbody = document.getElementById('tbody');

            function escapeHtml(text) {
                return String(text ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }

            async function load() {
                const url = new URL('/api/items', window.location.origin);
                url.searchParams.set('include_stock', '1');
                url.searchParams.set('per_page', '200');
                const response = await window.DebugApi.apiFetch(url.toString());
                const payload = await response.json().catch(() => null);

                if (!response.ok || !payload?.data) {
                    const message = payload?.message || `Request failed (${response.status})`;
                    const errors = payload?.errors ? JSON.stringify(payload.errors, null, 2) : null;
                    window.DebugApi.showAlert('danger', message, errors);
                    return;
                }

                const items = payload.data.data || [];
                tbody.innerHTML = '';
                if (!items.length) {
                    tbody.innerHTML = '<tr><td colspan="10" class="text-muted">No items</td></tr>';
                    return;
                }

                for (const item of items) {
                    tbody.insertAdjacentHTML('beforeend', `
                        <tr>
                            <td>${escapeHtml(item.id)}</td>
                            <td>${escapeHtml(item.code)}</td>
                            <td>${escapeHtml(item.name)}</td>
                            <td>${escapeHtml(item.type)}</td>
                            <td>${escapeHtml(item.unit)}</td>
                            <td class="text-end">${escapeHtml(item.current_qty ?? 0)}</td>
                            <td>${escapeHtml(item.cost_method)}</td>
                            <td class="text-end">${escapeHtml(item.selling_price)}</td>
                            <td>${item.is_active ? 'Yes' : 'No'}</td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-secondary" href="{{ url('/debug/inventory/items') }}/${item.id}/edit">Edit</a>
                                <button class="btn btn-sm btn-outline-danger" type="button" data-delete="${item.id}">Delete</button>
                            </td>
                        </tr>
                    `);
                }
            }

            document.getElementById('reloadBtn').addEventListener('click', () => load().catch(() => {}));

            tbody.addEventListener('click', async (e) => {
                const btn = e.target.closest('[data-delete]');
                if (!btn) return;
                const id = btn.getAttribute('data-delete');
                if (!id) return;
                if (!confirm(`Delete item #${id}?`)) return;

                const r = await window.DebugApi.apiFetch(`/api/items/${id}`, { method: 'DELETE' });
                const b = await r.json().catch(() => null);
                if (!r.ok) {
                    const msg = b?.message || `Request failed (${r.status})`;
                    const errors = b?.errors ? JSON.stringify(b.errors, null, 2) : null;
                    window.DebugApi.showAlert('danger', msg, errors);
                    return;
                }

                window.DebugApi.showAlert('success', 'Item deleted');
                load().catch(() => {});
            });
            load().catch(() => {});
        })();
    </script>
@endpush
