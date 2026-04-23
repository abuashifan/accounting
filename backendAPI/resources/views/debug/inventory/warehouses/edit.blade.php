@extends('debug.layout')

@section('title', 'Edit Warehouse')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 m-0">Edit Warehouse</h1>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-primary" href="{{ route('debug.inventory.warehouses') }}">Warehouses</a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="fw-semibold mb-2">Warehouse Details</div>
            <form id="editWarehouseForm" class="row g-3 align-items-end" autocomplete="off">
                <div class="col-12 col-md-3">
                    <label class="form-label" for="code">Code</label>
                    <input class="form-control form-control-sm" id="code" name="code" type="text" required>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="name">Name</label>
                    <input class="form-control form-control-sm" id="name" name="name" type="text" required>
                </div>
                <div class="col-12 col-md-3 text-muted small">
                    Endpoint: <code>PUT /api/warehouses/{{ $id }}</code>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button id="saveBtn" type="submit" class="btn btn-sm btn-primary">Save</button>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('debug.inventory.warehouses') }}">Back</a>
                </div>
            </form>
            <pre class="small mb-0 mt-3" id="result"></pre>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const warehouseId = {{ (int) $id }};
            const form = document.getElementById('editWarehouseForm');
            const result = document.getElementById('result');

            async function loadWarehouse() {
                const r = await window.DebugApi.apiFetch(`/api/warehouses/${warehouseId}`);
                const b = await r.json().catch(() => null);
                if (!r.ok || !b?.data) {
                    const msg = b?.message || `Request failed (${r.status})`;
                    const errors = b?.errors ? JSON.stringify(b.errors, null, 2) : null;
                    window.DebugApi.showAlert('danger', msg, errors);
                    throw new Error(msg);
                }

                document.getElementById('code').value = b.data.code || '';
                document.getElementById('name').value = b.data.name || '';
            }

            async function save() {
                const fd = new FormData(form);
                const payload = {
                    code: String(fd.get('code') ?? '').trim(),
                    name: String(fd.get('name') ?? '').trim(),
                };

                result.textContent = JSON.stringify({ step: 'payload', payload }, null, 2);

                const r = await window.DebugApi.apiFetch(`/api/warehouses/${warehouseId}`, {
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

                window.DebugApi.showAlert('success', 'Warehouse updated');
                window.location.href = '{{ route('debug.inventory.warehouses') }}';
            }

            form.addEventListener('submit', (e) => {
                e.preventDefault();
                save().catch(() => {});
            });

            loadWarehouse().catch(() => {});
        })();
    </script>
@endpush

