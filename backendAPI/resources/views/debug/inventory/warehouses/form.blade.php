<div class="card mb-3">
    <div class="card-body">
        <div class="fw-semibold mb-2">Warehouse Details</div>
        <form id="createWarehouseForm" class="row g-3 align-items-end" autocomplete="off">
            <div class="col-12 col-md-3">
                <label class="form-label" for="code">Code</label>
                <input class="form-control form-control-sm" id="code" name="code" type="text" placeholder="WH-01" required>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="name">Name</label>
                <input class="form-control form-control-sm" id="name" name="name" type="text" placeholder="Warehouse name" required>
            </div>
            <div class="col-12 col-md-3 text-muted small">
                Endpoint: <code>POST /api/warehouses</code>
            </div>
            <div class="col-12 d-flex gap-2">
                <button id="createBtn" type="submit" class="btn btn-sm btn-primary">Create Warehouse</button>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('debug.inventory.warehouses') }}">Back</a>
            </div>
        </form>
        <pre class="small mb-0 mt-3" id="createResult"></pre>
    </div>
</div>

