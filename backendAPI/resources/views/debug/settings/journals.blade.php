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

    <div class="card mt-3 border-danger">
        <div class="card-body">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="dangerousToggle">
                <label class="form-check-label" for="dangerousToggle">
                    Allow admin to edit/delete <span class="text-danger fw-semibold">posted</span> transactions (Dangerous)
                </label>
            </div>
            <div class="alert alert-warning small mt-3 mb-0">
                <div class="fw-semibold">WARNING</div>
                <div>
                    Editing or deleting posted transactions can break the audit trail, General Ledger, and inventory stock balances.
                    Recommended approach for posted transactions is <span class="fw-semibold">VOID/REVERSAL</span> and <span class="fw-semibold">RETURN</span>.
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const toggle = document.getElementById('autoPostToggle');
            const dangerousToggle = document.getElementById('dangerousToggle');

            async function load() {
                const res = await window.DebugApi.apiJson('/api/settings/journals');
                toggle.checked = !!res?.data?.auto_post;
                dangerousToggle.checked = !!res?.data?.allow_admin_edit_delete_posted;
            }

            async function save() {
                const res = await window.DebugApi.apiJson('/api/settings/journals', {
                    method: 'PUT',
                    body: JSON.stringify({
                        auto_post: !!toggle.checked,
                        allow_admin_edit_delete_posted: !!dangerousToggle.checked,
                    }),
                });
                toggle.checked = !!res?.data?.auto_post;
                dangerousToggle.checked = !!res?.data?.allow_admin_edit_delete_posted;
                window.DebugApi.showAlert('success', 'Settings saved');
            }

            toggle.addEventListener('change', async () => {
                try {
                    toggle.disabled = true;
                    dangerousToggle.disabled = true;
                    await save();
                } catch (e) {
                    // Re-fetch server value on failure.
                    await load().catch(() => {});
                } finally {
                    toggle.disabled = false;
                    dangerousToggle.disabled = false;
                }
            });

            dangerousToggle.addEventListener('change', async () => {
                try {
                    if (dangerousToggle.checked) {
                        const ok = confirm(
                            'WARNING: Editing/deleting posted transactions can break audit trail, GL, and stock. ' +
                            'Recommended approach is VOID/RETURN. Enable anyway?'
                        );
                        if (!ok) {
                            dangerousToggle.checked = false;
                            return;
                        }
                    }

                    toggle.disabled = true;
                    dangerousToggle.disabled = true;
                    await save();
                } catch (e) {
                    await load().catch(() => {});
                } finally {
                    toggle.disabled = false;
                    dangerousToggle.disabled = false;
                }
            });

            load().catch(() => {});
        })();
    </script>
@endpush
