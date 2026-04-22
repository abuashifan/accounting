@extends('debug.layout')

@section('title', 'Journal Settings')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 m-0">Journal Settings</h1>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('debug.dashboard') }}">Back</a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="autoPostToggle">
                <label class="form-check-label" for="autoPostToggle">
                    Auto post journals on create
                </label>
            </div>
            <div class="text-muted small mt-2">
                If enabled, newly created journals will immediately be <code>posted</code> (not <code>draft</code>) and will appear in General Ledger / Trial Balance.
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const toggle = document.getElementById('autoPostToggle');

            async function load() {
                const res = await window.DebugApi.apiJson('/api/settings/journals');
                toggle.checked = !!res?.data?.auto_post;
            }

            async function save(value) {
                const res = await window.DebugApi.apiJson('/api/settings/journals', {
                    method: 'PUT',
                    body: JSON.stringify({ auto_post: !!value }),
                });
                toggle.checked = !!res?.data?.auto_post;
                window.DebugApi.showAlert('success', 'Settings saved');
            }

            toggle.addEventListener('change', async () => {
                try {
                    toggle.disabled = true;
                    await save(toggle.checked);
                } catch (e) {
                    // Re-fetch server value on failure.
                    await load().catch(() => {});
                } finally {
                    toggle.disabled = false;
                }
            });

            load().catch(() => {});
        })();
    </script>
@endpush

