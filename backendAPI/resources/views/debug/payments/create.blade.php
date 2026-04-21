@extends('debug.layout')

@section('title', 'Create Payment')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 m-0">Create Payment</h1>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('debug.payments.index') }}">Back</a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="alert alert-warning small">
                Payment posting uses configured accounts (see <code>config/accounting.php</code>).
            </div>

            <form id="paymentForm">
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
                        <label class="form-label" for="invoice_id">Invoice ID</label>
                        <input class="form-control form-control-sm" type="number" id="invoice_id" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="amount">Amount</label>
                        <input class="form-control form-control-sm" type="number" step="0.01" min="0" id="amount" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="description">Description (optional)</label>
                        <input class="form-control form-control-sm" id="description">
                    </div>
                </div>

                <div class="mt-3">
                    <button class="btn btn-primary btn-sm" type="submit">Submit → PaymentService</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            document.getElementById('paymentForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                const payload = {
                    payment_no: document.getElementById('payment_no').value,
                    payment_date: document.getElementById('payment_date').value,
                    invoice_id: Number(document.getElementById('invoice_id').value),
                    amount: Number(document.getElementById('amount').value),
                    description: document.getElementById('description').value || null,
                };

                const res = await window.DebugApi.apiJson('{{ route('debug.api.payments.store') }}', {
                    method: 'POST',
                    body: JSON.stringify(payload),
                });

                if (res?.success) {
                    window.DebugApi.showAlert('success', 'Payment recorded');
                    window.location.href = '{{ route('debug.payments.index') }}';
                }
            });
        })();
    </script>
@endpush
