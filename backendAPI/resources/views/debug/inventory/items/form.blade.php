<div class="card mb-3">
    <div class="card-body">
        <div class="fw-semibold mb-2">Item Details</div>
        <form id="createItemForm" class="row g-3 align-items-end" autocomplete="off">
            <div class="col-12 col-md-3">
                <label class="form-label" for="code">Code</label>
                <input class="form-control form-control-sm" id="code" name="code" type="text" placeholder="ITEM-001" required>
            </div>
            <div class="col-12 col-md-5">
                <label class="form-label" for="name">Name</label>
                <input class="form-control form-control-sm" id="name" name="name" type="text" placeholder="Item name" required>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="type">Type</label>
                <select class="form-select form-select-sm" id="type" name="type" required>
                    <option value="inventory">inventory</option>
                    <option value="service">service</option>
                    <option value="non-inventory">non-inventory</option>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="unit">Unit</label>
                <input class="form-control form-control-sm" id="unit" name="unit" type="text" value="pcs" required>
            </div>

            <div class="col-12 col-md-3">
                <label class="form-label" for="selling_price">Selling Price</label>
                <input class="form-control form-control-sm" type="number" step="0.01" id="selling_price" name="selling_price" value="0">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="cost_method">Cost Method</label>
                <select class="form-select form-select-sm" id="cost_method" name="cost_method">
                    <option value="average" selected>average</option>
                    <option value="fifo">fifo (not supported)</option>
                    <option value="lifo">lifo (not supported)</option>
                </select>
                <div class="text-muted small mt-1">Only <code>average</code> is supported by service.</div>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="is_active">Active</label>
                <select class="form-select form-select-sm" id="is_active" name="is_active">
                    <option value="1" selected>Yes</option>
                    <option value="0">No</option>
                </select>
            </div>

            <div class="col-12"><hr class="my-1"></div>

            <div class="col-12 col-md-4">
                <label class="form-label" for="inventory_account_id">Inventory Account</label>
                <select class="form-select form-select-sm" id="inventory_account_id" name="inventory_account_id"></select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="cogs_account_id">COGS Account</label>
                <select class="form-select form-select-sm" id="cogs_account_id" name="cogs_account_id"></select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="revenue_account_id">Revenue Account</label>
                <select class="form-select form-select-sm" id="revenue_account_id" name="revenue_account_id"></select>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="inventory_adjustment_account_id">Inventory Adjustment Account</label>
                <select class="form-select form-select-sm" id="inventory_adjustment_account_id" name="inventory_adjustment_account_id"></select>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="goods_in_transit_account_id">Goods In Transit Account</label>
                <select class="form-select form-select-sm" id="goods_in_transit_account_id" name="goods_in_transit_account_id"></select>
            </div>

            <div class="col-12 col-md-9 text-muted small">
                Endpoint: <code>POST /api/items</code>
            </div>
            <div class="col-12 d-flex gap-2">
                <button id="createBtn" type="submit" class="btn btn-sm btn-primary">Create Item</button>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('debug.inventory.items') }}">Back</a>
            </div>
        </form>
        <pre class="small mb-0 mt-3" id="createResult"></pre>
    </div>
</div>

