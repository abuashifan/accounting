@extends('debug.layout')

@section('title', 'Edit Invoice')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 m-0">Edit Invoice #{{ $id }}</h1>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('debug.invoices.index') }}">Back</a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="alert alert-warning small">
                Recommended for posted transactions: <span class="fw-semibold">VOID/RETURN</span>.
                Editing/deleting posted invoice is <span class="fw-semibold">dangerous</span> and requires admin setting + "I understand the risk".
                Also: posted invoice cannot be edited/deleted if it already has payments or returns.
            </div>

            <form id="form">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="invoice_no">Invoice No</label>
                        <input class="form-control form-control-sm" id="invoice_no" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="invoice_date">Invoice Date</label>
                        <input class="form-control form-control-sm" type="date" id="invoice_date" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Total</label>
                        <input class="form-control form-control-sm" id="total_preview" readonly>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="description">Description (optional)</label>
                        <input class="form-control form-control-sm" id="description">
                    </div>
                </div>

                <hr class="my-3">

                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-5">
                        <label class="form-label" for="item_id">Item</label>
                        <select class="form-select form-select-sm" id="item_id" required></select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="warehouse_id">Warehouse</label>
                        <select class="form-select form-select-sm" id="warehouse_id" required></select>
                    </div>
                    <div class="col-6 col-md-1">
                        <label class="form-label" for="quantity">Qty</label>
                        <input class="form-control form-control-sm" type="number" step="0.0001" min="0" id="quantity" required>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="unit_price">Unit Price</label>
                        <input class="form-control form-control-sm" type="number" step="0.01" min="0" id="unit_price" required>
                    </div>
                </div>

                <div class="mt-3 d-flex gap-2">
                    <button class="btn btn-primary btn-sm" type="submit">Save</button>
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('debug.invoices.index') }}">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const invoiceId = {{ (int) $id }};

            const itemSelect = document.getElementById('item_id');
            const whSelect = document.getElementById('warehouse_id');
            const qtyEl = document.getElementById('quantity');
            const priceEl = document.getElementById('unit_price');
            const totalEl = document.getElementById('total_preview');

            function escapeHtml(text) {
                return String(text ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }

            function recalcTotal() {
                const qty = Number(qtyEl.value || 0);
                const price = Number(priceEl.value || 0);
                totalEl.value = (Math.round(qty * price * 100) / 100).toFixed(2);
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

            async function loadInvoice() {
                const res = await window.DebugApi.apiFetch(`/api/invoices/${invoiceId}`);
                const payload = await res.json().catch(() => null);
                const invoice = payload?.data;

                if (!res.ok || !invoice) {
                    const msg = payload?.message || `Request failed (${res.status})`;
                    const errors = payload?.errors ? JSON.stringify(payload.errors, null, 2) : null;
                    window.DebugApi.showAlert('danger', msg, errors);
                    return null;
                }

                document.getElementById('invoice_no').value = invoice.invoice_no || '';
                document.getElementById('invoice_date').value = (invoice.invoice_date || '').slice(0, 10);
                document.getElementById('description').value = invoice.description || '';

                const line = (invoice.invoice_lines || [])[0] || null;
                if (line) {
                    itemSelect.value = String(line.item_id || '');
                    whSelect.value = String(line.warehouse_id || '');
                    qtyEl.value = String(line.quantity ?? '');
                    priceEl.value = String(line.unit_price ?? '');
                }

                recalcTotal();
                return invoice;
            }

            document.getElementById('form').addEventListener('submit', async (e) => {
                e.preventDefault();
                const payload = {
                    invoice_no: document.getElementById('invoice_no').value,
                    invoice_date: document.getElementById('invoice_date').value,
                    description: document.getElementById('description').value || null,
                    lines: [{
                        item_id: Number(itemSelect.value || 0),
                        warehouse_id: Number(whSelect.value || 0),
                        quantity: Number(qtyEl.value || 0),
                        unit_price: Number(priceEl.value || 0),
                    }]
                };

                const res = await window.DebugApi.apiFetch(`/api/invoices/${invoiceId}`, {
                    method: 'PUT',
                    body: JSON.stringify(payload),
                });
                const body = await res.json().catch(() => null);

                if (!res.ok || !body?.data) {
                    const msg = body?.message || `Request failed (${res.status})`;
                    const errors = body?.errors ? JSON.stringify(body.errors, null, 2) : null;
                    window.DebugApi.showAlert('danger', msg, errors);
                    return;
                }

                window.DebugApi.showAlert('success', 'Invoice updated');
                window.location.href = '{{ route('debug.invoices.index') }}';
            });

            qtyEl.addEventListener('input', recalcTotal);
            priceEl.addEventListener('input', recalcTotal);

            Promise.all([loadItems(), loadWarehouses()])
                .then(() => loadInvoice())
                .catch(() => {});
        })();
    </script>
@endpush

