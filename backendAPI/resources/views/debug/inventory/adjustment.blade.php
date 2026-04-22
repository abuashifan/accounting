@extends('debug.layout')

@section('title', 'Debug Stock Adjustment')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 m-0">Stock Adjustment</h1>
        <button id="submitBtn" class="btn btn-sm btn-outline-primary">Submit</button>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form class="row g-3">
                <div class="col-12 col-md-3">
                    <label class="form-label" for="date">Date</label>
                    <input class="form-control form-control-sm" type="date" id="date" value="{{ now()->format('Y-m-d') }}">
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label" for="period_id">Accounting Period</label>
                    <select class="form-select form-select-sm" id="period_id">
                        <option value="">Select period</option>
                        @foreach ($periods as $period)
                            <option value="{{ $period->id }}">
                                {{ $period->start_date?->format('Y-m-d') }} → {{ $period->end_date?->format('Y-m-d') }}
                                @if ($period->is_closed) (closed) @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label" for="item_id">Item</label>
                    <select class="form-select form-select-sm" id="item_id"></select>
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label" for="warehouse_id">Warehouse</label>
                    <select class="form-select form-select-sm" id="warehouse_id"></select>
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label" for="quantity_delta">Quantity Delta</label>
                    <input class="form-control form-control-sm" type="number" step="0.0001" id="quantity_delta" placeholder="+10 or -5">
                    <div class="text-muted small mt-1">Positive = increase, Negative = decrease</div>
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label" for="unit_cost">Unit Cost (required if increase)</label>
                    <input class="form-control form-control-sm" type="number" step="0.000001" id="unit_cost" placeholder="e.g. 125.5">
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label" for="description">Description</label>
                    <input class="form-control form-control-sm" type="text" id="description" placeholder="Reason...">
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="text-muted small">Endpoint: <code>POST /api/stocks/adjustment</code></div>
            <pre class="small mb-0 mt-2" id="result"></pre>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const itemSelect = document.getElementById('item_id');
            const whSelect = document.getElementById('warehouse_id');
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
                whSelect.innerHTML = '<option value="">Select warehouse</option>';
                for (const wh of warehouses) {
                    whSelect.insertAdjacentHTML('beforeend', `<option value="${wh.id}">${escapeHtml(wh.code)} - ${escapeHtml(wh.name)}</option>`);
                }
            }

            async function submit() {
                const payload = {
                    date: document.getElementById('date').value,
                    accounting_period_id: Number(document.getElementById('period_id').value || 0),
                    item_id: Number(itemSelect.value || 0),
                    warehouse_id: Number(whSelect.value || 0),
                    quantity_delta: Number(document.getElementById('quantity_delta').value || 0),
                    unit_cost: document.getElementById('unit_cost').value === '' ? null : Number(document.getElementById('unit_cost').value),
                    description: document.getElementById('description').value || null,
                };

                const response = await window.DebugApi.apiFetch('/api/stocks/adjustment', {
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

