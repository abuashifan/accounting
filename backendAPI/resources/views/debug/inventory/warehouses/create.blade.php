@extends('debug.layout')

@section('title', 'Create Warehouse')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 m-0">Create Warehouse</h1>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-primary" href="{{ route('debug.inventory.warehouses') }}">Warehouses</a>
        </div>
    </div>

    @include('debug.inventory.warehouses.form')
@endsection

@push('scripts')
    <script>
        (() => {
            const createResult = document.getElementById('createResult');
            const createForm = document.getElementById('createWarehouseForm');

            async function createWarehouse() {
                const codeEl = document.getElementById('code');
                const nameEl = document.getElementById('name');

                console.log('[createWarehouse] elements', { createForm, codeEl, nameEl });

                const fd = createForm ? new FormData(createForm) : null;
                const codeValue = String(fd?.get('code') ?? codeEl?.value ?? '').trim();
                const nameValue = String(fd?.get('name') ?? nameEl?.value ?? '').trim();

                console.log('[createWarehouse] captured', { codeValue, nameValue });
                createResult.textContent = JSON.stringify({ step: 'captured', codeValue, nameValue }, null, 2);

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

                const payload = { code: codeValue, name: nameValue };
                console.log('[createWarehouse] sending payload', payload, 'json=', JSON.stringify(payload));
                createResult.textContent = JSON.stringify({ step: 'payload', payload }, null, 2);

                const response = await window.DebugApi.apiFetch('/api/warehouses', {
                    method: 'POST',
                    body: JSON.stringify(payload),
                });

                const body = await response.json().catch(() => null);
                createResult.textContent = JSON.stringify(body, null, 2);

                console.log('[createWarehouse] response', { status: response.status, body });

                if (!response.ok) {
                    const message = body?.message || `Request failed (${response.status})`;
                    const errors = body?.errors ? JSON.stringify(body.errors, null, 2) : null;
                    window.DebugApi.showAlert('danger', message, errors);
                    return;
                }

                window.DebugApi.showAlert('success', 'Warehouse created');
                window.location.href = '{{ route('debug.inventory.warehouses') }}';
            }

            createForm?.addEventListener('submit', (e) => {
                e.preventDefault();
                createWarehouse().catch(() => {});
            });

            document.getElementById('code')?.focus();
        })();
    </script>
@endpush

