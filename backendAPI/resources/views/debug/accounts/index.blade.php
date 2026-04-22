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
                        <th>Code</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Active</th>
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
                const url = new URL('/api/accounts', window.location.origin);
                url.searchParams.set('include_inactive', '1');
                const res = await window.DebugApi.apiJson(url.toString());
                const accounts = res.data || [];

                tbody.innerHTML = '';
                if (!accounts.length) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-muted">No accounts</td></tr>';
                    return;
                }

                for (const acc of accounts) {
                    tbody.insertAdjacentHTML('beforeend', `
                        <tr>
                            <td>${escapeHtml(acc.code)}</td>
                            <td>${escapeHtml(acc.name)}</td>
                            <td>${escapeHtml(acc.type)}</td>
                            <td>${acc.is_active ? 'Yes' : 'No'}</td>
                        </tr>
                    `);
                }
            }

            document.getElementById('reloadBtn').addEventListener('click', () => load().catch(() => {}));

            load().catch(() => {});
        })();
    </script>
@endpush
