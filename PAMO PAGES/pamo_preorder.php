<?php
session_start();
$basePath = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PAMO - Pre-Order</title>
    <link rel="stylesheet" href="../PAMO CSS/styles.css">
    <link rel="stylesheet" href="../PAMO CSS/preorder.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-content">
            <div class="page-header">
                <h2>Pre-Order Items</h2>
                <button class="action-btn" id="addPreItemBtn"><i class="material-icons">add_circle</i> New Pre-Order</button>
            </div>

            <div id="preorderList" class="card" style="padding:16px;">
                <table class="datatable">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Base Code</th>
                            <th>Price</th>
                            <th>Sizes</th>
                            <th>Requests</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="preorderRows"></tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Add Modal -->
    <div id="addPreModal" class="modal" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Pre-Order Item</h3>
                <span class="close" onclick="$('#addPreModal').hide()">&times;</span>
            </div>
            <form id="addPreForm" enctype="multipart/form-data">
                <div class="grid-2">
                    <div class="input-group">
                        <label>Item Code</label>
                        <input type="text" name="base_item_code" required>
                    </div>
                    <div class="input-group">
                        <label>Item Name</label>
                        <input type="text" name="item_name" required>
                    </div>
                    <div class="input-group">
                        <label>Category</label>
                        <select name="category_id" id="preCategory"></select>
                    </div>
                    <div class="input-group">
                        <label>Price</label>
                        <input type="number" name="price" step="0.01" min="0" required>
                    </div>
                    <div class="input-group">
                        <label>Sizes</label>
                        <input type="text" name="sizes" placeholder="e.g. S,M,L or One Size" required>
                    </div>
                    <div class="input-group" id="preSubcatGroup" style="display:none;">
                        <label>Subcategories</label>
                        <select name="subcategory_ids[]" id="preSubcategories" multiple style="height:120px;"></select>
                    </div>
                    <div class="input-group" style="grid-column: 1 / span 2;">
                        <label>Image</label>
                        <input type="file" name="image" accept="image/*">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="save-btn">Save</button>
                    <button type="button" class="cancel-btn" onclick="$('#addPreModal').hide()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Deliver Modal -->
    <div id="deliverModal" class="modal" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Mark Delivered</h3>
                <span class="close" onclick="$('#deliverModal').hide()">&times;</span>
            </div>
            <div id="deliverBody"></div>
            <div class="modal-footer">
                <button type="button" class="save-btn" id="deliverSubmit">Save</button>
                <button type="button" class="cancel-btn" onclick="$('#deliverModal').hide()">Cancel</button>
            </div>
        </div>
    </div>

    <script>
    let CATEGORIES = [];
    function loadCategories() {
        return $.getJSON('../PAMO%20Inventory%20backend/api_categories_list.php')
            .then(rows => {
                CATEGORIES = rows || [];
                const $cat = $('#preCategory').empty();
                $cat.append('<option value="">-- none --</option>');
                $cat.append('<option value="__add__">+ Add new category…</option>');
                CATEGORIES.forEach(r => $cat.append(`<option value="${r.id}" data-has="${r.has_subcategories ? 1 : 0}">${r.name}</option>`));
            });
    }
    function loadSubcategories(categoryId, selectIds = []) {
        if (!categoryId) { $('#preSubcatGroup').hide(); $('#preSubcategories').empty(); return; }
        return $.getJSON('../PAMO%20Inventory%20backend/api_subcategories_list.php', { category_id: categoryId })
            .then(rows => {
                const $s = $('#preSubcategories');
                if ($s.data('select2')) { $s.select2('destroy'); }
                $s.empty();
                $s.append('<option value="__add__">+ Add new subcategory…</option>');
                (rows||[]).forEach(r => {
                    const opt = $('<option>').val(r.id).text(r.name);
                    if (selectIds.includes(String(r.id))) opt.attr('selected', true);
                    $s.append(opt[0]);
                });
                $('#preSubcatGroup').show();
                $s.select2({ placeholder: 'Select subcategories…', width: '100%' });
            });
    }
    function loadPreorders() {
        return $.getJSON('../PAMO_PREORDER_BACKEND/api_preorder_list.php')
            .then(resp => {
                const $tbody = $('#preorderRows').empty();
                (resp.items||[]).forEach(it => {
                    const img = it.image_path ? `../${it.image_path}` : '../uploads/itemlist/default.png';
                    const isPending = (String(it.status).toLowerCase() === 'pending');
                    const actionCell = isPending
                      ? `<button class="table-btn" onclick="openDeliver(this)">Mark Delivered</button>`
                      : `<button class="table-btn" disabled style="opacity:.6; cursor:not-allowed;">Delivered</button>`;
                    const row = `
                        <tr data-id="${it.id}" data-sizes="${it.sizes}">
                            <td><img src="${img}" alt="" style="width:58px;height:58px;object-fit:cover;border-radius:6px;"></td>
                            <td>${it.item_name}</td>
                            <td>${it.base_item_code}</td>
                            <td>₱${parseFloat(it.price).toFixed(2)}</td>
                            <td>${it.sizes}</td>
                            <td><strong>${it.total_requests}</strong></td>
                            <td>${it.status}</td>
                            <td>
                                ${actionCell}
                            </td>
                        </tr>`;
                    $tbody.append(row);
                });
            });
    }

    function openDeliver(btn) {
        const $tr = $(btn).closest('tr');
        const id = $tr.data('id');
        const sizes = String($tr.data('sizes')||'').split(',').map(s=>s.trim()).filter(Boolean);
        let html = `<input type="hidden" id="deliverPreId" value="${id}">`;
        html += '<div class="grid-2">';
        sizes.forEach(s => {
            html += `<div class="input-group"><label>Size ${s}</label><input type="number" min="0" step="1" data-size="${s}" class="deliverQty" value="0"></div>`;
        });
        html += '</div>';
        $('#deliverBody').html(html);
        $('#deliverModal').show();
    }

    $('#deliverSubmit').on('click', function(){
        const id = parseInt($('#deliverPreId').val(), 10);
        const delivered = {};
        $('.deliverQty').each(function(){
            const s = $(this).data('size');
            const v = parseInt($(this).val(), 10) || 0;
            if (v>0) delivered[s] = v;
        });
        if (Object.keys(delivered).length === 0) { alert('Enter at least one quantity'); return; }
        $.ajax({
            url: '../PAMO_PREORDER_BACKEND/api_preorder_mark_delivered.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ preorder_item_id: id, delivered }),
        }).done(()=>{ $('#deliverModal').hide(); loadPreorders(); })
        .fail(xhr=> alert(xhr.responseJSON?.message || 'Failed'));
    });

    $('#addPreItemBtn').on('click', function(){
        $('#addPreForm')[0].reset();
        $('#preSubcatGroup').hide();
        $('#addPreModal').show();
    });

    $('#addPreForm').on('submit', function(e){
        e.preventDefault();
        const form = this;
        const fd = new FormData(form);
        $.ajax({
            url: '../PAMO_PREORDER_BACKEND/api_preorder_create.php',
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false
        }).done(()=>{ $('#addPreModal').hide(); loadPreorders(); })
        .fail(xhr=> alert(xhr.responseJSON?.message || 'Failed'));
    });

    // Category dynamic add and subcategory prompt behavior
    $('#preCategory').on('change', async function(){
        const val = $(this).val();
        if (val === '__add__') {
            const name = prompt('Enter new category name:');
            if (!name) { await loadCategories(); return; }
            const has = confirm('Does this category have subcategories? Click OK for Yes, Cancel for No.') ? 1 : 0;
            try {
                const resp = await fetch('../PAMO%20Inventory%20backend/api_categories_create.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ name, has_subcategories: has }) });
                const data = await resp.json();
                if (!data.success) throw new Error(data.message||'Failed');
                await loadCategories();
                $('#preCategory').val(String(data.id)).trigger('change');
            } catch(err) {
                alert(err.message);
                await loadCategories();
            }
            return;
        }
        const has = Number($('#preCategory option:selected').data('has')||0);
        if (has) {
            await loadSubcategories(val);
        } else {
            $('#preSubcatGroup').hide();
            $('#preSubcategories').empty();
        }
    });

    // Subcategory dynamic add
    $('#preSubcategories').on('change', async function(){
        const vals = ($(this).val()||[]).map(String);
        if (vals.includes('__add__')) {
            const name = prompt('Enter new subcategory name:');
            const categoryId = $('#preCategory').val();
            if (!name || !categoryId) { $(this).val(vals.filter(v=>v!=='__add__')); return; }
            try {
                const resp = await fetch('../PAMO%20Inventory%20backend/api_subcategories_create.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ category_id: Number(categoryId), name }) });
                const data = await resp.json();
                if (!data.success) throw new Error(data.message||'Failed');
                await loadSubcategories(categoryId, [String(data.id)]);
            } catch(err) { alert(err.message); }
        }
    });

    $(async function(){
        await loadCategories();
        // Enhance category dropdown
        if (!$('#preCategory').data('select2')) {
            $('#preCategory').select2({ placeholder: 'Select category…', width: '100%' });
        }
        await loadPreorders();
    });
    </script>

    <style>
        .page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; }
        .grid-2 { display:grid; grid-template-columns: 1fr 1fr; gap:16px; }
        .datatable { width:100%; border-collapse: collapse; }
        .datatable th, .datatable td { padding:10px; border-bottom:1px solid #eee; text-align:left; }
        .table-btn { padding:6px 10px; border:none; background:#007bff; color:#fff; border-radius:4px; cursor:pointer; }
        .modal { position:fixed; inset:0; background:rgba(0,0,0,.4); display:flex; align-items:center; justify-content:center; }
        .modal-content { background:#fff; padding:16px; border-radius:8px; width: min(720px, 96vw); }
        .modal-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }
        .input-group { display:flex; flex-direction:column; }
        .input-group input, .input-group select, .input-group textarea { padding:8px 10px; border:1px solid #ddd; border-radius:6px; }
        .save-btn { background:#007bff; color:#fff; border:none; padding:10px 16px; border-radius:6px; cursor:pointer; }
        .cancel-btn { background:#dc3545; color:#fff; border:none; padding:10px 16px; border-radius:6px; cursor:pointer; }
        .modal-footer { display:flex; justify-content:flex-end; gap:10px; margin-top:12px; }
    </style>
</body>
</html>