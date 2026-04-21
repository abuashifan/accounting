@extends('debug.layout')

@section('title', 'Debug Dashboard')

@section('content')
    <h1 class="h4 mb-3">Dashboard</h1>

    <div class="row g-3">
        <div class="col-12 col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">Total Journal</div>
                    <div class="fs-4" id="totalJournal">-</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">Total Posted Journal</div>
                    <div class="fs-4" id="totalPostedJournal">-</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">Total Invoice</div>
                    <div class="fs-4" id="totalInvoice">-</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">Total Payment</div>
                    <div class="fs-4" id="totalPayment">-</div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (async () => {
            const res = await window.DebugApi.apiJson('{{ route('debug.api.dashboard-stats') }}');
            if (!res?.success) return;
            document.getElementById('totalJournal').textContent = res.data.total_journal;
            document.getElementById('totalPostedJournal').textContent = res.data.total_posted_journal;
            document.getElementById('totalInvoice').textContent = res.data.total_invoice;
            document.getElementById('totalPayment').textContent = res.data.total_payment;
        })().catch(() => {});
    </script>
@endpush
