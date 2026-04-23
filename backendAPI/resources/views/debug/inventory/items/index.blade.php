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
                        <th>Code</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Unit</th>
                        <th class="text-end">Qty</th>
                        <th>Cost Method</th>
                        <th class="text-end">Selling Price</th>
                        <th>Active</th>
                    </tr>
                </thead>
                <tbody id="tbody">
                    <tr><td colspan="8" class="text-muted">Loading...</td></tr>
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
                    tbody.innerHTML = '<tr><td colspan="8" class="text-muted">No items</td></tr>';
                    return;
                }

                for (const item of items) {
                    tbody.insertAdjacentHTML('beforeend', `
                        <tr>
                            <td>${escapeHtml(item.code)}</td>
                            <td>${escapeHtml(item.name)}</td>
                            <td>${escapeHtml(item.type)}</td>
                            <td>${escapeHtml(item.unit)}</td>
                            <td class="text-end">${escapeHtml(item.current_qty ?? 0)}</td>
                            <td>${escapeHtml(item.cost_method)}</td>
                            <td class="text-end">${escapeHtml(item.selling_price)}</td>
                            <td>${item.is_active ? 'Yes' : 'No'}</td>
                        </tr>
                    `);
                }
            }

            document.getElementById('reloadBtn').addEventListener('click', () => load().catch(() => {}));
            load().catch(() => {});
        })();
    </script>
@endpush
