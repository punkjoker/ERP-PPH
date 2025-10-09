<?php 
include 'db_con.php';

// Always define $id early (default 0)
$id = intval($_POST['id'] ?? ($_GET['id'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = intval($_POST['supplier_id'] ?? 0);
    $po_no = trim($_POST['po_no'] ?? '');
    $notes = $_POST['notes'] ?? '';
    $status = intval($_POST['status'] ?? 0);
    $discount_percentage = floatval($_POST['discount_percentage'] ?? 0);
    $discount_amount = floatval($_POST['discount_amount'] ?? 0);
    $tax_percentage = floatval($_POST['tax_percentage'] ?? 0);
    $tax_amount = floatval($_POST['tax_amount'] ?? 0);

    // Auto-generate PO number if blank
    if (empty($po_no)) {
        $res = $conn->query("SELECT MAX(po_no) as last_po FROM po_list");
        $row = $res->fetch_assoc();
        $last_po = $row['last_po'] ?? 40000;
        $po_no = $last_po < 40000 ? 40001 : $last_po + 1;
    }

    if ($id > 0) {
        // Update existing PO
        $stmt = $conn->prepare("UPDATE po_list 
            SET supplier_id=?, po_no=?, notes=?, status=?, discount_percentage=?, discount_amount=?, tax_percentage=?, tax_amount=? 
            WHERE id=?");
        $stmt->bind_param("issiddddi", 
            $supplier_id, $po_no, $notes, $status, 
            $discount_percentage, $discount_amount, 
            $tax_percentage, $tax_amount, $id
        );
        $stmt->execute();
        $stmt->close();

        // Delete old items
        $conn->query("DELETE FROM order_items WHERE po_id = $id");

    } else {
        // Insert new PO
        $stmt = $conn->prepare("INSERT INTO po_list 
            (supplier_id, po_no, notes, status, discount_percentage, discount_amount, tax_percentage, tax_amount) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issidddd", 
            $supplier_id, $po_no, $notes, $status, 
            $discount_percentage, $discount_amount, 
            $tax_percentage, $tax_amount
        );
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();
    }

    // Insert items into order_items (manual_name replaces description)
    if (!empty($_POST['qty'])) {
        foreach ($_POST['qty'] as $i => $qty) {
            $qty = floatval($qty);
            $unit = $_POST['unit'][$i] ?? '';
            $unit_price = floatval($_POST['unit_price'][$i]);

            $product_id = !empty($_POST['product_id'][$i]) ? intval($_POST['product_id'][$i]) : null;
            $manual_name = $_POST['manual_name'][$i] ?? null; // store manual item name

            $stmt = $conn->prepare("INSERT INTO order_items 
                (po_id, product_id, manual_name, quantity, unit, unit_price) 
                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisdss", $id, $product_id, $manual_name, $qty, $unit, $unit_price);
            $stmt->execute();
            $stmt->close();
        }
    }

    header("Location: approved_purchases.php?success=1");
    exit;
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= isset($id) ? "Update Purchase Order" : "New Purchase Order" ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 pt-20">
<div class="p-6 sm:ml-64">
    <?php include 'navbar.php'; ?>

    <div class="max-w-7xl mx-auto bg-white shadow-lg rounded-lg p-6 mt-6">
        <h2 class="text-2xl font-bold text-blue-700 mb-6 border-b pb-2">
            <?= isset($id) ? "Update Purchase Order Details" : "New Purchase Order" ?>
        </h2>

        <form method="POST" id="po-form" class="space-y-6">
            <input type="hidden" name="id" value="<?= $id ?? '' ?>">

            <!-- Supplier & PO Number -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Supplier</label>
                    <select name="supplier_id" class="w-full border rounded p-2 focus:ring-2 focus:ring-blue-400">
                        <option value="" disabled <?= !isset($supplier_id) ? "selected" : '' ?>>Select supplier</option>
                        <?php
                        $supplier_qry = $conn->query("SELECT * FROM suppliers ORDER BY supplier_name ASC");
                        while ($row = $supplier_qry->fetch_assoc()):
                        ?>
                            <option value="<?= $row['id'] ?>" 
                                <?= isset($supplier_id) && $supplier_id == $row['id'] ? 'selected' : '' ?> 
                                <?= $row['status'] == 'unavailable' ? 'disabled' : '' ?>>
                                <?= htmlspecialchars($row['supplier_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">PO #</label>
                    <input type="text" name="po_no" value="<?= $po_no ?? '' ?>" 
                           class="w-full border rounded p-2 focus:ring-2 focus:ring-blue-400">
                    <p class="text-xs text-gray-500 mt-1">Leave blank to auto-generate upon saving.</p>
                </div>
            </div>

            <!-- Items Table -->
            <div class="overflow-x-auto mt-4">
                <table class="w-full border text-sm rounded-lg" id="item-list">
                    <thead class="bg-blue-100 text-blue-800 font-semibold">
                        <tr>
                            <th class="p-2 text-center">Action</th>
                            <th class="p-2 text-left">Item</th>
                            <th class="p-2 text-center">Qty</th>
                            <th class="p-2 text-center">Unit</th>
                            <th class="p-2 text-right">Unit Price</th>
                            <th class="p-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($id > 0): 
                            $items_qry = $conn->query("SELECT * FROM order_items WHERE po_id = '$id'");
                            while($row = $items_qry->fetch_assoc()): ?>
                        <tr class="po-item border-b">
                            <td class="p-2 text-center">
                                <button type="button" onclick="rem_item(this)" class="bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600">X</button>
                            </td>
                            <td class="p-1">
                                <input type="hidden" name="product_id[]" value="<?= $row['product_id'] ?>">
                                <input type="text" name="manual_name[]" value="<?= htmlspecialchars($row['manual_name']) ?>" class="w-full border rounded p-1">
                            </td>
                            <td class="p-1"><input type="number" name="qty[]" value="<?= $row['quantity'] ?>" class="w-full border rounded p-1 text-center"></td>
                            <td class="p-1"><input type="text" name="unit[]" value="<?= $row['unit'] ?>" class="w-full border rounded p-1 text-center"></td>
                            <td class="p-1"><input type="number" step="0.01" name="unit_price[]" value="<?= $row['unit_price'] ?>" class="w-full border rounded p-1 text-right"></td>
                            <td class="p-1 text-right total-price"><?= number_format($row['quantity'] * $row['unit_price'],2) ?></td>
                        </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
                <button type="button" id="add_row" class="mt-2 bg-blue-600 text-white px-4 py-1 rounded hover:bg-blue-700">+ Add Row</button>
            </div>

            <!-- Subtotal, Discount, Tax, Total -->
            <div class="mt-4 max-w-md ml-auto bg-gray-50 p-4 rounded-lg shadow-inner">
                <div class="flex justify-between mb-2"><span class="font-semibold">Subtotal:</span> <span id="sub_total">0</span></div>
                <div class="flex justify-between mb-2">
                    <span>Discount %:</span>
                    <input type="number" step="0.01" name="discount_percentage" value="<?= $discount_percentage ?? 0 ?>" class="w-20 border rounded p-1 text-right">
                </div>
                <div class="flex justify-between mb-2"><span>Discount Amount:</span> <input type="text" name="discount_amount" readonly class="w-24 border rounded p-1 text-right"></div>
                <div class="flex justify-between mb-2">
                    <span>Tax %:</span>
                    <input type="number" step="0.01" name="tax_percentage" value="<?= $tax_percentage ?? 0 ?>" class="w-20 border rounded p-1 text-right">
                </div>
                <div class="flex justify-between mb-2"><span>Tax Amount:</span> <input type="text" name="tax_amount" readonly class="w-24 border rounded p-1 text-right"></div>
                <div class="flex justify-between font-bold text-lg border-t pt-2"><span>Total:</span> <span id="total">0</span></div>
            </div>

            <!-- Submit Button -->
            <div class="flex gap-4 mt-6">
                <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700 font-semibold">Save Purchase Order</button>
                <a href="approved_purchases.php" class="bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600 font-semibold">Cancel</a>
            </div>

        </form>
    </div>
</div>

<!-- Hidden template row -->
<table class="hidden" id="item-clone">
    <tr class="po-item border-b">
        <td class="p-2 text-center">
            <button type="button" onclick="rem_item(this)" class="bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600">X</button>
        </td>
        <td class="p-1"><input type="text" name="manual_name[]" class="w-full border rounded p-1"></td>
        <td class="p-1"><input type="number" name="qty[]" class="w-full border rounded p-1 text-center"></td>
        <td class="p-1"><input type="text" name="unit[]" class="w-full border rounded p-1 text-center"></td>
        <td class="p-1"><input type="number" step="0.01" name="unit_price[]" class="w-full border rounded p-1 text-right"></td>
        <td class="p-1 text-right total-price">0</td>
    </tr>
</table>

<script>
function rem_item(btn) { btn.closest('tr').remove(); calculate(); }

function calculate() {
    let subtotal = 0;
    document.querySelectorAll('#item-list tbody tr').forEach(tr => {
        let qty = parseFloat(tr.querySelector("[name='qty[]']").value) || 0;
        let price = parseFloat(tr.querySelector("[name='unit_price[]']").value) || 0;
        let rowTotal = qty * price;
        tr.querySelector('.total-price').innerText = rowTotal.toFixed(2);
        subtotal += rowTotal;
    });

    let discountPerc = parseFloat(document.querySelector("[name='discount_percentage']").value) || 0;
    let discountAmount = subtotal * (discountPerc / 100);
    document.querySelector("[name='discount_amount']").value = discountAmount.toFixed(2);

    let taxPerc = parseFloat(document.querySelector("[name='tax_percentage']").value) || 0;
    let taxAmount = (subtotal - discountAmount) * (taxPerc / 100);
    document.querySelector("[name='tax_amount']").value = taxAmount.toFixed(2);

    document.getElementById('sub_total').innerText = subtotal.toFixed(2);
    document.getElementById('total').innerText = (subtotal - discountAmount + taxAmount).toFixed(2);
}

// Add new row
document.getElementById('add_row').addEventListener('click', () => {
    let clone = document.querySelector('#item-clone tr').cloneNode(true);
    document.querySelector('#item-list tbody').appendChild(clone);
    clone.querySelectorAll("input").forEach(inp => inp.addEventListener("input", calculate));
    calculate();
});

// Bind inputs to recalc
document.querySelectorAll("[name='qty[]'], [name='unit_price[]'], [name='discount_percentage'], [name='tax_percentage']")
.forEach(inp => inp.addEventListener("input", calculate));

calculate();
</script>
</body>
</html>
