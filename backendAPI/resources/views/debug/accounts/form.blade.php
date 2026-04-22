<div class="card mb-3">
    <div class="card-body">
        <div class="fw-semibold mb-2">Account Details</div>
        <form id="createAccountForm" class="row g-3 align-items-end" autocomplete="off">
            <div class="col-12 col-md-2">
                <label class="form-label" for="code">Code</label>
                <input class="form-control form-control-sm" id="code" name="code" type="text" placeholder="1000" required>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="name">Name</label>
                <input class="form-control form-control-sm" id="name" name="name" type="text" placeholder="Cash" required>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="type">Type</label>
                <select class="form-select form-select-sm" id="type" name="type" required>
                    <option value="asset">asset</option>
                    <option value="liability">liability</option>
                    <option value="equity">equity</option>
                    <option value="revenue">revenue</option>
                    <option value="expense">expense</option>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="parent_id">Parent (optional)</label>
                <select class="form-select form-select-sm" id="parent_id" name="parent_id"></select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="is_active">Active</label>
                <select class="form-select form-select-sm" id="is_active" name="is_active">
                    <option value="1" selected>Yes</option>
                    <option value="0">No</option>
                </select>
            </div>
            <div class="col-12 col-md-9 text-muted small">
                Endpoint: <code>POST /api/accounts</code>
            </div>
            <div class="col-12 d-flex gap-2">
                <button id="createBtn" type="submit" class="btn btn-sm btn-primary">Create Account</button>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('debug.accounts') }}">Back</a>
            </div>
        </form>
        <pre class="small mb-0 mt-3" id="createResult"></pre>
    </div>
</div>

