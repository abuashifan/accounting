@extends('debug.layout')

@section('title', 'Create Account')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 m-0">Create Account</h1>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-primary" href="{{ route('debug.accounts') }}">Accounts</a>
        </div>
    </div>

    @include('debug.accounts.form')
@endsection

@push('scripts')
    <script>
        (() => {
            const parentSelect = document.getElementById('parent_id');
            const createResult = document.getElementById('createResult');
            const createForm = document.getElementById('createAccountForm');

            function escapeHtml(text) {
                return String(text ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }

            async function loadParents() {
                const url = new URL('/api/accounts', window.location.origin);
                url.searchParams.set('include_inactive', '1');
                const res = await window.DebugApi.apiJson(url.toString());
                const accounts = res.data || [];

                parentSelect.innerHTML = '<option value="">No parent</option>';
                for (const acc of accounts) {
                    parentSelect.insertAdjacentHTML('beforeend', `<option value="${acc.id}">${escapeHtml(acc.code)} - ${escapeHtml(acc.name)}</option>`);
                }
            }

            async function createAccount() {
                const codeEl = document.getElementById('code');
                const nameEl = document.getElementById('name');
                const typeEl = document.getElementById('type');
                const isActiveEl = document.getElementById('is_active');

                console.log('[createAccount] elements', { createForm, codeEl, nameEl, typeEl, parentSelect, isActiveEl });

                const fd = createForm ? new FormData(createForm) : null;
                const codeValue = String(fd?.get('code') ?? codeEl?.value ?? '').trim();
                const nameValue = String(fd?.get('name') ?? nameEl?.value ?? '').trim();
                const typeValue = String(fd?.get('type') ?? typeEl?.value ?? '').trim();
                const parentRaw = String(fd?.get('parent_id') ?? parentSelect?.value ?? '').trim();
                const isActiveRaw = String(fd?.get('is_active') ?? isActiveEl?.value ?? '').trim();

                const captured = { codeValue, nameValue, typeValue, parentRaw, isActiveRaw };
                console.log('[createAccount] captured', captured);
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

                const parentIdValue = parentRaw === '' ? null : parseInt(parentRaw, 10);
                const isActiveValue = isActiveRaw === '1';

                const payload = {
                    code: codeValue,
                    name: nameValue,
                    type: typeValue,
                    parent_id: parentIdValue,
                    is_active: isActiveValue,
                };

                console.log('[createAccount] sending payload', payload, 'json=', JSON.stringify(payload));
                createResult.textContent = JSON.stringify({ step: 'payload', payload }, null, 2);

                const response = await window.DebugApi.apiFetch('/api/accounts', {
                    method: 'POST',
                    body: JSON.stringify(payload),
                });

                const body = await response.json().catch(() => null);
                createResult.textContent = JSON.stringify(body, null, 2);

                console.log('[createAccount] response', { status: response.status, body });

                if (!response.ok || !body?.success) {
                    const message = body?.message || `Request failed (${response.status})`;
                    const errors = body?.errors ? JSON.stringify(body.errors, null, 2) : null;
                    window.DebugApi.showAlert('danger', message, errors);
                    return;
                }

                window.DebugApi.showAlert('success', 'Account created');
                window.location.href = '{{ route('debug.accounts') }}';
            }

            createForm?.addEventListener('submit', (e) => {
                e.preventDefault();
                createAccount().catch(() => {});
            });

            // Focus first field to avoid "clicked but nothing typed" confusion
            document.getElementById('code')?.focus();

            loadParents().catch(() => {});
        })();
    </script>
@endpush

