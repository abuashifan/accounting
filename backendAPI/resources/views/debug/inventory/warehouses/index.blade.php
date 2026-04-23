@extends('debug.layout')

@section('title', 'Debug Warehouses')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 m-0">Warehouses</h1>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-primary" href="{{ route('debug.inventory.warehouses.create') }}">Create Warehouse</a>
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
                        <th></th>
                    </tr>
                </thead>
                <tbody id="tbody">
                    <tr><td colspan="4" class="text-muted">Loading...</td></tr>
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
                const url = new URL('/api/warehouses', window.location.origin);
                const response = await window.DebugApi.apiFetch(url.toString());
                const payload = await response.json().catch(() => null);

                if (!response.ok || !payload?.data) {
                    const message = payload?.message || `Request failed (${response.status})`;
                    const errors = payload?.errors ? JSON.stringify(payload.errors, null, 2) : null;
                    window.DebugApi.showAlert('danger', message, errors);
                    return;
                }

                const warehouses = payload.data || [];
                tbody.innerHTML = '';
                if (!warehouses.length) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-muted">No warehouses</td></tr>';
                    return;
                }

                for (const wh of warehouses) {
                    tbody.insertAdjacentHTML('beforeend', `
                        <tr>
                            <td>${escapeHtml(wh.id)}</td>
                            <td>${escapeHtml(wh.code)}</td>
                            <td>${escapeHtml(wh.name)}</td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-secondary" href="{{ url('/debug/inventory/warehouses') }}/${wh.id}/edit">Edit</a>
                                <button class="btn btn-sm btn-outline-danger" type="button" data-delete="${wh.id}">Delete</button>
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
                if (!confirm(`Delete warehouse #${id}?`)) return;

                const r = await window.DebugApi.apiFetch(`/api/warehouses/${id}`, { method: 'DELETE' });
                const b = await r.json().catch(() => null);
                if (!r.ok) {
                    const msg = b?.message || `Request failed (${r.status})`;
                    const errors = b?.errors ? JSON.stringify(b.errors, null, 2) : null;
                    window.DebugApi.showAlert('danger', msg, errors);
                    return;
                }

                window.DebugApi.showAlert('success', 'Warehouse deleted');
                load().catch(() => {});
            });
            load().catch(() => {});
        })();
    </script>
@endpush
