@extends('debug.layout')

@section('title', 'Edit Payment')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 m-0">Edit Payment #{{ $id }}</h1>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('debug.payments.index') }}">Back</a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="alert alert-warning small">
                Recommended for posted transactions: <span class="fw-semibold">VOID/REVERSAL</span>.
                Editing/deleting posted payment is <span class="fw-semibold">dangerous</span> and requires admin setting + "I understand the risk".
            </div>

            <form id="form">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="payment_no">Payment No</label>
                        <input class="form-control form-control-sm" id="payment_no" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="payment_date">Payment Date</label>
                        <input class="form-control form-control-sm" type="date" id="payment_date" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="amount">Amount</label>
                        <input class="form-control form-control-sm" type="number" step="0.01" min="0" id="amount" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="invoice_label">Invoice</label>
                        <input class="form-control form-control-sm" id="invoice_label" readonly>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="description">Description (optional)</label>
                        <input class="form-control form-control-sm" id="description">
                    </div>
                </div>

                <div class="mt-3 d-flex gap-2">
                    <button class="btn btn-primary btn-sm" type="submit">Save</button>
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('debug.payments.index') }}">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const paymentId = {{ (int) $id }};

            async function loadPayment() {
                const res = await window.DebugApi.apiFetch(`/api/payments/${paymentId}`);
                const payload = await res.json().catch(() => null);
                const payment = payload?.data;

                if (!res.ok || !payment) {
                    const msg = payload?.message || `Request failed (${res.status})`;
                    const errors = payload?.errors ? JSON.stringify(payload.errors, null, 2) : null;
                    window.DebugApi.showAlert('danger', msg, errors);
                    return null;
                }

                document.getElementById('payment_no').value = payment.payment_no || '';
                document.getElementById('payment_date').value = (payment.payment_date || '').slice(0, 10);
                document.getElementById('amount').value = payment.amount ?? '';
                document.getElementById('description').value = payment.description || '';

                const invNo = payment.invoice?.invoice_no || ('#' + (payment.invoice_id || '-'));
                document.getElementById('invoice_label').value = invNo;

                return payment;
            }

            document.getElementById('form').addEventListener('submit', async (e) => {
                e.preventDefault();
                const payload = {
                    payment_no: document.getElementById('payment_no').value,
                    payment_date: document.getElementById('payment_date').value,
                    amount: Number(document.getElementById('amount').value || 0),
                    description: document.getElementById('description').value || null,
                };

                const res = await window.DebugApi.apiFetch(`/api/payments/${paymentId}`, {
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

                window.DebugApi.showAlert('success', 'Payment updated');
                window.location.href = '{{ route('debug.payments.index') }}';
            });

            loadPayment().catch(() => {});
        })();
    </script>
@endpush

