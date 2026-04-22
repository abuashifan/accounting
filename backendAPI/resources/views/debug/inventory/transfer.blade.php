@extends('debug.layout')

@section('title', 'Debug Stock Transfer')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 m-0">Stock Transfer</h1>
        <button id="submitBtn" class="btn btn-sm btn-outline-primary">Submit</button>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form class="row g-3">
                <div class="col-12 col-md-3">
                    <label class="form-label" for="date">Date</label>
                    <input class="form-control form-control-sm" type="date" id="date" value="{{ now()->format('Y-m-d') }}">
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label" for="item_id">Item</label>
                    <select class="form-select form-select-sm" id="item_id"></select>
                </div>

                <div class="col-12 col-md-2">
                    <label class="form-label" for="quantity">Quantity</label>
                    <input class="form-control form-control-sm" type="number" step="0.0001" id="quantity" placeholder="e.g. 3">
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label" for="from_wh">From Warehouse</label>
                    <select class="form-select form-select-sm" id="from_wh"></select>
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label" for="to_wh">To Warehouse</label>
                    <select class="form-select form-select-sm" id="to_wh"></select>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="text-muted small">Endpoint: <code>POST /api/stocks/transfer</code></div>
            <pre class="small mb-0 mt-2" id="result"></pre>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const itemSelect = document.getElementById('item_id');
            const fromSelect = document.getElementById('from_wh');
            const toSelect = document.getElementById('to_wh');
            const resultEl = document.getElementById('result');

            function escapeHtml(text) {
                return String(text ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }

            async function loadItems() {
                const res = await window.DebugApi.apiFetch('/api/items');
                const payload = await res.json().catch(() => null);
                const items = payload?.data?.data || [];
                itemSelect.innerHTML = '<option value="">Select item</option>';
                for (const it of items) {
                    itemSelect.insertAdjacentHTML('beforeend', `<option value="${it.id}">${escapeHtml(it.code)} - ${escapeHtml(it.name)}</option>`);
                }
            }

            async function loadWarehouses() {
                const res = await window.DebugApi.apiFetch('/api/warehouses');
                const payload = await res.json().catch(() => null);
                const warehouses = payload?.data || [];

                const opts = ['<option value="">Select warehouse</option>'];
                for (const wh of warehouses) {
                    opts.push(`<option value="${wh.id}">${escapeHtml(wh.code)} - ${escapeHtml(wh.name)}</option>`);
                }

                fromSelect.innerHTML = opts.join('');
                toSelect.innerHTML = opts.join('');
            }

            async function submit() {
                const payload = {
                    date: document.getElementById('date').value,
                    item_id: Number(itemSelect.value || 0),
                    quantity: Number(document.getElementById('quantity').value || 0),
                    from_warehouse_id: Number(fromSelect.value || 0),
                    to_warehouse_id: Number(toSelect.value || 0),
                };

                const response = await window.DebugApi.apiFetch('/api/stocks/transfer', {
                    method: 'POST',
                    body: JSON.stringify(payload),
                });
                const resBody = await response.json().catch(() => null);
                resultEl.textContent = JSON.stringify(resBody, null, 2);

                if (!response.ok) {
                    const message = resBody?.message || `Request failed (${response.status})`;
                    const errors = resBody?.errors ? JSON.stringify(resBody.errors, null, 2) : null;
                    window.DebugApi.showAlert('danger', message, errors);
                    return;
                }

                window.DebugApi.showAlert('success', 'OK');
            }

            document.getElementById('submitBtn').addEventListener('click', () => submit().catch(() => {}));
            Promise.all([loadItems(), loadWarehouses()]).catch(() => {});
        })();
    </script>
@endpush

