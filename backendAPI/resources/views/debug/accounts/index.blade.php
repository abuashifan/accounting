@extends('debug.layout')

@section('title', 'Debug Accounts')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 m-0">Accounts</h1>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-primary" href="{{ route('debug.accounts.create') }}">Create Account</a>
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
                        <th>Active</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="tbody">
                    <tr><td colspan="6" class="text-muted">Loading...</td></tr>
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
                const url = new URL('/api/accounts', window.location.origin);
                url.searchParams.set('include_inactive', '1');
                const res = await window.DebugApi.apiJson(url.toString());
                const accounts = res.data || [];

                tbody.innerHTML = '';
                if (!accounts.length) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-muted">No accounts</td></tr>';
                    return;
                }

                for (const acc of accounts) {
                    tbody.insertAdjacentHTML('beforeend', `
                        <tr>
                            <td>${acc.id}</td>
                            <td>${escapeHtml(acc.code)}</td>
                            <td>${escapeHtml(acc.name)}</td>
                            <td>${escapeHtml(acc.type)}</td>
                            <td>${acc.is_active ? 'Yes' : 'No'}</td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-secondary" href="{{ url('/debug/accounts') }}/${acc.id}/edit">Edit</a>
                                <button class="btn btn-sm btn-outline-danger" type="button" data-delete="${acc.id}">Delete</button>
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
                if (!confirm(`Delete account #${id}?`)) return;

                const r = await window.DebugApi.apiFetch(`/api/accounts/${id}`, { method: 'DELETE' });
                const b = await r.json().catch(() => null);
                if (!r.ok || b?.success === false) {
                    const msg = b?.message || `Request failed (${r.status})`;
                    const errors = b?.errors ? JSON.stringify(b.errors, null, 2) : null;
                    window.DebugApi.showAlert('danger', msg, errors);
                    return;
                }

                window.DebugApi.showAlert('success', 'Account deleted');
                load().catch(() => {});
            });

            load().catch(() => {});
        })();
    </script>
@endpush
