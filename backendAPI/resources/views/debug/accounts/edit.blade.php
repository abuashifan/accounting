@extends('debug.layout')

@section('title', 'Edit Account')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 m-0">Edit Account</h1>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-primary" href="{{ route('debug.accounts') }}">Accounts</a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="fw-semibold mb-2">Account Details</div>
            <form id="editAccountForm" class="row g-3 align-items-end" autocomplete="off">
                <div class="col-12 col-md-2">
                    <label class="form-label" for="code">Code</label>
                    <input class="form-control form-control-sm" id="code" name="code" type="text" required>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="name">Name</label>
                    <input class="form-control form-control-sm" id="name" name="name" type="text" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label" for="type">Type</label>
                    <select class="form-select form-select-sm" id="type" name="type" required>
                        <option value="asset">asset</option>
                        <option value="liability">liability</option>
                        <option value="equity">equity</option>
                        <option value="revenue">revenue</option>
                        <option value="expense">expense</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label" for="parent_id">Parent (optional)</label>
                    <select class="form-select form-select-sm" id="parent_id" name="parent_id"></select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label" for="is_active">Active</label>
                    <select class="form-select form-select-sm" id="is_active" name="is_active">
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                    </select>
                </div>
                <div class="col-12 col-md-9 text-muted small">
                    Endpoint: <code>PUT /api/accounts/{{ $id }}</code>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button id="saveBtn" type="submit" class="btn btn-sm btn-primary">Save</button>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('debug.accounts') }}">Back</a>
                </div>
            </form>
            <pre class="small mb-0 mt-3" id="result"></pre>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const accountId = {{ (int) $id }};
            const form = document.getElementById('editAccountForm');
            const parentSelect = document.getElementById('parent_id');
            const result = document.getElementById('result');

            function escapeHtml(text) {
                return String(text ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }

            async function loadParents(currentId) {
                const url = new URL('/api/accounts', window.location.origin);
                url.searchParams.set('include_inactive', '1');
                const res = await window.DebugApi.apiJson(url.toString());
                const accounts = res.data || [];

                parentSelect.innerHTML = '<option value="">No parent</option>';
                for (const acc of accounts) {
                    if (Number(acc.id) === Number(currentId)) continue;
                    parentSelect.insertAdjacentHTML(
                        'beforeend',
                        `<option value="${acc.id}">${escapeHtml(acc.code)} - ${escapeHtml(acc.name)}</option>`
                    );
                }
            }

            async function loadAccount() {
                const res = await window.DebugApi.apiJson(`/api/accounts/${accountId}`);
                const acc = res.data;
                if (!acc) throw new Error('Account not found');

                await loadParents(acc.id);

                document.getElementById('code').value = acc.code || '';
                document.getElementById('name').value = acc.name || '';
                document.getElementById('type').value = acc.type || 'asset';
                document.getElementById('is_active').value = acc.is_active ? '1' : '0';
                parentSelect.value = acc.parent_id ? String(acc.parent_id) : '';
            }

            async function save() {
                const fd = new FormData(form);
                const parentRaw = String(fd.get('parent_id') ?? '').trim();

                const payload = {
                    code: String(fd.get('code') ?? '').trim(),
                    name: String(fd.get('name') ?? '').trim(),
                    type: String(fd.get('type') ?? '').trim(),
                    parent_id: parentRaw === '' ? null : Number(parentRaw),
                    is_active: String(fd.get('is_active') ?? '') === '1',
                };

                result.textContent = JSON.stringify({ step: 'payload', payload }, null, 2);

                const r = await window.DebugApi.apiFetch(`/api/accounts/${accountId}`, {
                    method: 'PUT',
                    body: JSON.stringify(payload),
                });
                const b = await r.json().catch(() => null);
                result.textContent = JSON.stringify(b, null, 2);

                if (!r.ok || b?.success === false) {
                    const msg = b?.message || `Request failed (${r.status})`;
                    const errors = b?.errors ? JSON.stringify(b.errors, null, 2) : null;
                    window.DebugApi.showAlert('danger', msg, errors);
                    return;
                }

                window.DebugApi.showAlert('success', 'Account updated');
                window.location.href = '{{ route('debug.accounts') }}';
            }

            form.addEventListener('submit', (e) => {
                e.preventDefault();
                save().catch(() => {});
            });

            loadAccount().catch(() => {});
        })();
    </script>
@endpush

