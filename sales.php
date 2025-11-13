<?php
ob_start();
require_once __DIR__ . '/auth/session_check.php';
include 'includes/db_connect.php';
include 'includes/header.php';

// Get logged-in user ID
$user_id = $_SESSION['user_id'] ?? 0;

// ------------------- Sale token to prevent duplicate posts -------------------
if (empty($_SESSION['sale_token'])) {
    $_SESSION['sale_token'] = bin2hex(random_bytes(16));
}
$token = $_SESSION['sale_token'];

// ------------------- ALERT SYSTEM (SweetAlert2) - same as customers page -------------------
if(isset($_SESSION['alert'])){
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
    echo "<script>
        document.addEventListener('DOMContentLoaded', function(){
            Swal.fire({
                icon: '{$_SESSION['alert']['type']}',
                title: '{$_SESSION['alert']['title']}',
                text: '{$_SESSION['alert']['message']}',
                timer: 2000,
                showConfirmButton: false
            });
        });
    </script>";
    unset($_SESSION['alert']);
}

// ------------------- Fetch Customers -------------------
$customers_result = $conn->query("SELECT id, name FROM customers WHERE user_id = $user_id ORDER BY name ASC");

// ------------------- Fetch Products -------------------
$products_result = $conn->query("SELECT * FROM products WHERE user_id = $user_id AND stock>0 ORDER BY name ASC");

// ------------------- Handle Sale Submission -------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_sale') {
    // Token validation (prevents duplicate manual resubmission / spam)
    if (!isset($_POST['token']) || $_POST['token'] !== $_SESSION['sale_token']) {
        $_SESSION['alert'] = [
            'type'=>'error',
            'title'=>'⚠️ Duplicate Submission',
            'message'=>'This sale was already submitted or the form token is invalid.'
        ];
        header("Location: sales.php");
        exit;
    }
    // regenerate token for next form
    $_SESSION['sale_token'] = bin2hex(random_bytes(16));
    $customer_id = intval($_POST['customer_id'] ?? 0);
    $customer_name = trim($_POST['customer_name'] ?? '');
    $cart = json_decode($_POST['cart'], true);

    if (!$cart || count($cart) === 0) {
        $_SESSION['alert'] = [
            'type'=>'error',
            'title'=>'Cart Empty',
            'message'=>'Cart is empty. Add items before saving.'
        ];
        header("Location: sales.php");
        exit;
    }

    // If new customer name provided, insert user-specific customer
    if ($customer_name && !$customer_id) {
        $stmt = $conn->prepare("INSERT INTO customers (name, user_id) VALUES (?, ?)");
        $stmt->bind_param("si", $customer_name, $user_id);
        $stmt->execute();
        $customer_id = $stmt->insert_id;
        $stmt->close();
    }

    $total_amount = 0;
    foreach ($cart as $item) {
        $total_amount += $item['price'] * $item['quantity'];
    }

    $status = 'paid';

    $conn->begin_transaction();
    try {
        // Insert sale with user_id
        $stmt = $conn->prepare("INSERT INTO sales (customer_id, total_amount, status, created_at, user_id) VALUES (?, ?, ?, NOW(), ?)");
        $stmt->bind_param("idsi", $customer_id, $total_amount, $status, $user_id);
        $stmt->execute();
        $sale_id = $stmt->insert_id;
        $stmt->close();

        // Insert sale items and update stock safely (user-specific)
        $stmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        $update_stock = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id=? AND user_id=?");

        foreach ($cart as $item) {
            $stmt->bind_param("iiid", $sale_id, $item['id'], $item['quantity'], $item['price']);
            $stmt->execute();

            $update_stock->bind_param("iii", $item['quantity'], $item['id'], $user_id);
            $update_stock->execute();
        }

        $stmt->close();
        $update_stock->close();

        $conn->commit();

        // Use the same SweetAlert session-based popup style as customers page
        $_SESSION['alert'] = [
            'type'=>'success',
            'title'=>'✅ Sale Recorded',
            'message'=>'Sale saved successfully!'
        ];

        // Redirect to avoid double-submit on refresh
        header("Location: sales.php");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['alert'] = [
            'type'=>'error',
            'title'=>'❌ Error',
            'message'=>'Error saving sale: '.$e->getMessage()
        ];
        header("Location: sales.php");
        exit;
    }
}

// ------------------- Fetch Sales for Logged-in User (for records table) -------------------
$sales_result = $conn->query("
    SELECT s.id, s.customer_id, s.total_amount, s.status, s.created_at,
           c.name AS customer_name,
           GROUP_CONCAT(CONCAT(p.name,' x', si.quantity) SEPARATOR ', ') AS items_list
    FROM sales s
    LEFT JOIN sale_items si ON s.id = si.sale_id
    LEFT JOIN products p ON si.product_id = p.id
    LEFT JOIN customers c ON s.customer_id = c.id
    WHERE s.user_id = $user_id
    GROUP BY s.id
    ORDER BY s.id DESC
");

$sales = [];
while ($row = $sales_result->fetch_assoc()) {
    $sales[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Sales | StoreMS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
/* --- YOUR ORIGINAL STYLES KEPT AS IS --- */
body { background: #f5f7fa; font-family: "Inter", sans-serif; }
.card { border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.1); margin-top:20px; }
.table th, .table td { vertical-align: middle; }
#billModal .modal-content { border-radius:12px; }
@media print {
  body * { visibility:hidden; }
  #printableBill, #printableBill * { visibility:visible; }
  #printableBill { position:absolute; top:0; left:0; width:100%; }
}
/* small sales-record mini-table styling */
.small-records { max-height: 220px; overflow:auto; border-radius:8px; }
.small-records table { margin-bottom:0; }
.small-records thead th { background: linear-gradient(45deg,#4e73df,#1cc88a); color:#fff; position:sticky; top:0; z-index:1; }
</style>
</head>
<body>

<div class="container">

<!-- Sale Form -->
<div class="card p-4">
<h5><i class="fas fa-cart-plus me-2 text-primary"></i>Record a Sale</h5>
<form method="POST" id="saleForm">
<input type="hidden" name="action" value="add_sale">
<input type="hidden" name="cart" id="cartInput">
<input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

<div class="row g-3 mb-3">
  <div class="col-md-4">
    <label>Select Customer</label>
    <select name="customer_id" id="customerSelect" class="form-select">
      <option value="">-- Select Existing Customer --</option>
      <?php while($c = $customers_result->fetch_assoc()): ?>
        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
      <?php endwhile; ?>
    </select>
    <small class="text-muted">Or type new customer name below</small>
    <input type="text" name="customer_name" class="form-control mt-1" placeholder="New Customer Name">
  </div>

  <div class="col-md-4">
    <label>Product</label>
    <select id="productSelect" class="form-select">
      <option value="">Select Product</option>
      <?php 
      $products_result->data_seek(0);
      while($p = $products_result->fetch_assoc()): ?>
        <option value="<?= $p['id'] ?>" data-price="<?= $p['price'] ?>" data-stock="<?= $p['stock'] ?>">
          <?= htmlspecialchars($p['name']) ?> (Rs <?= number_format($p['price'],2) ?>, Stock: <?= $p['stock'] ?>)
        </option>
      <?php endwhile; ?>
    </select>
  </div>

  <div class="col-md-2">
    <label>Quantity</label>
    <input type="number" id="quantityInput" class="form-control" min="1" value="1">
  </div>

  <div class="col-md-2 d-flex align-items-end">
    <button type="button" class="btn btn-success w-100" id="addToCartBtn"><i class="fas fa-plus me-1"></i>Add to Cart</button>
  </div>
</div>

<!-- Cart Table -->
<table class="table table-bordered" id="cartTable">
<thead class="table-light">
<tr>
<th>Product</th><th>Qty</th><th>Price</th><th>Total</th><th>Action</th>
</tr>
</thead>
<tbody></tbody>
<tfoot>
<tr>
<th colspan="3" class="text-end">Grand Total</th>
<th id="grandTotal">Rs 0</th><th></th>
</tr>
</tfoot>
</table>

<div class="text-end">
<button type="submit" class="btn btn-primary" id="saveBtn"><i class="fas fa-save me-1"></i>Save Sale</button>
</div>
</form>
</div>

<!-- Sales Table (Main) -->
<div class="card p-4">
<h5><i class="fas fa-table me-2 text-primary"></i>Sales Records</h5>
<div class="table-responsive">
<table class="table table-bordered">
<thead class="table-light">
<tr>
<th>#</th><th>Invoice</th><th>Customer</th><th>Products</th><th>Total</th><th>Status</th><th>Date</th><th>Bill</th>
</tr>
</thead>
<tbody>
<?php $i=1; foreach($sales as $s): ?>
<tr>
<td><?= $i++ ?></td>
<td>#<?= str_pad($s['id'],4,'0',STR_PAD_LEFT) ?></td>
<td><?= htmlspecialchars($s['customer_name'] ?? '-') ?></td>
<td><?= htmlspecialchars($s['items_list']) ?></td>
<td>Rs <?= number_format($s['total_amount'],2) ?></td>
<td><?= ucfirst($s['status']) ?></td>
<td><?= $s['created_at'] ?></td>
<td>
<button class="btn btn-sm btn-success" onclick="showBill(<?= $s['id'] ?>)">
<i class="fas fa-file-invoice me-1"></i>Bill</button>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>


<!-- Bill Modal (kept original) -->
<div class="modal fade" id="billModal" tabindex="-1">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content" id="printableBill">
<div class="modal-header border-0">
<h5 class="modal-title fw-bold text-primary"><i class="fas fa-file-invoice me-2"></i>Invoice</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body" id="billContent"></div>
<div class="modal-footer border-0">
<button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
<button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
</div>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let cart = [];

// Add product to cart
document.getElementById('addToCartBtn').addEventListener('click', ()=>{
    const sel = document.getElementById('productSelect');
    const pid = sel.value;
    if(!pid) return alert('Select a product');

    const name = sel.selectedOptions[0].text.split(' (')[0];
    const stock = parseInt(sel.selectedOptions[0].dataset.stock);
    const price = parseFloat(sel.selectedOptions[0].dataset.price);
    let qty = parseInt(document.getElementById('quantityInput').value);

    if(qty<1 || qty>stock) return alert('Invalid quantity');

    const exist = cart.find(p=>p.id==pid);
    if(exist){
        if(exist.quantity+qty>stock) return alert('Exceeds stock');
        exist.quantity += qty;
    }else{
        cart.push({id:pid,name,price,quantity:qty});
    }
    renderCart();
});

// Render cart
function renderCart(){
    const tbody = document.querySelector('#cartTable tbody');
    tbody.innerHTML = '';
    let total=0;
    cart.forEach((item,i)=>{
        const rowTotal = item.price*item.quantity;
        total+=rowTotal;
        tbody.innerHTML += `
        <tr>
            <td>${item.name}</td>
            <td>${item.quantity}</td>
            <td>Rs ${item.price.toFixed(2)}</td>
            <td>Rs ${rowTotal.toFixed(2)}</td>
            <td><button type="button" class="btn btn-sm btn-danger" onclick="removeItem(${i})"><i class="fas fa-times"></i></button></td>
        </tr>`;
    });
    document.getElementById('grandTotal').textContent = 'Rs '+total.toFixed(2);
}

// Remove item
function removeItem(i){ cart.splice(i,1); renderCart(); }

// Submit cart
document.getElementById('saleForm').addEventListener('submit', e=>{
    if(cart.length==0){ e.preventDefault(); alert('Cart empty'); return; }
    document.getElementById('cartInput').value = JSON.stringify(cart);

    // disable save button immediately to prevent double clicks
    const btn = document.getElementById('saveBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';
});

// Show bill modal
function showBill(id){
    fetch('sales_invoice.php?id='+id)
    .then(res=>res.text())
    .then(html=>{
        document.getElementById('billContent').innerHTML = html;
        new bootstrap.Modal(document.getElementById('billModal')).show();
    });
}
</script>
</body>
</html>
