<?php
require_once __DIR__ . '/auth/session_check.php';
ob_start();

include 'includes/db_connect.php';
include 'includes/header.php';

// ==== ALERTS ====
if (isset($_SESSION['alert'])) {
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
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

// Get logged-in user ID
$user_id = $_SESSION['user_id'];

// ==== ADD PRODUCT ====
if (isset($_POST['add_product'])) {
    $name = $_POST['name'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];

    $stmt = $conn->prepare("INSERT INTO products (name, price, stock, active, user_id) VALUES (?, ?, ?, 1, ?)");
    $stmt->bind_param("sdii", $name, $price, $stock, $user_id);
    $stmt->execute();
    $stmt->close();

    $_SESSION['alert'] = [
        'type' => 'success',
        'title' => '✅ Product Added',
        'message' => "$name added successfully!"
    ];
    header("Location: products.php");
    exit;
}

// ==== UPDATE PRODUCT ====
if (isset($_POST['update_product'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];

    $stmt = $conn->prepare("UPDATE products SET name=?, price=?, stock=? WHERE id=? AND user_id=?");
    $stmt->bind_param("sdiii", $name, $price, $stock, $id, $user_id);
    $stmt->execute();
    $stmt->close();

    $_SESSION['alert'] = [
        'type' => 'success',
        'title' => '✏️ Product Updated',
        'message' => "$name updated successfully!"
    ];
    header("Location: products.php");
    exit;
}

// ==== DELETE PRODUCT ====
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    // Check foreign key (sale_items)
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM sale_items WHERE product_id=?");
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkStmt->bind_result($count);
    $checkStmt->fetch();
    $checkStmt->close();

    if ($count > 0) {
        $_SESSION['alert'] = [
            'type' => 'error',
            'title' => '❌ Cannot Delete',
            'message' => 'Product linked to sales!'
        ];
    } else {
        $stmt = $conn->prepare("DELETE FROM products WHERE id=? AND user_id=?");
        $stmt->bind_param("ii", $id, $user_id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['alert'] = [
            'type' => 'success',
            'title' => '🗑️ Deleted',
            'message' => 'Product deleted successfully!'
        ];
    }
    header("Location: products.php");
    exit;
}

// ==== TOGGLE ACTIVE/INACTIVE ====
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    $stmt = $conn->prepare("UPDATE products SET active = NOT active WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $stmt->close();

    $_SESSION['alert'] = [
        'type' => 'info',
        'title' => '🔄 Status Changed',
        'message' => 'Product status updated.'
    ];
    header("Location: products.php");
    exit;
}

// ==== FETCH PRODUCTS ONLY FOR LOGGED-IN USER ====
$result = $conn->query("SELECT * FROM products WHERE user_id=$user_id ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Professional Product Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
<style>
body { font-family:'Poppins',sans-serif; background:#f0f2f5; }
.card { border-radius:15px; box-shadow:0 5px 25px rgba(0,0,0,0.1);}
.table thead th { background:linear-gradient(45deg,#4e73df,#1cc88a); color:white; }
.table tbody tr:hover { background: rgba(0,0,0,0.03); transition:0.3s; }
.btn { border-radius:50px; transition:0.3s;}
.btn:hover { transform: scale(1.05); }
.modal-content { border-radius:15px;}
.badge-active { background:linear-gradient(90deg,#1cc88a,#17a673); color:white;}
.badge-inactive { background:linear-gradient(90deg,#6c757d,#495057); color:white;}
</style>
</head>
<body>

<div class="container my-5">
<div class="card p-4">

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="fw-bold">🛒 Product Management</h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-circle me-1"></i> Add Product</button>
</div>

<!-- Filter & Search -->
<div class="row mb-3 g-3">
    <div class="col-md-6">
        <input type="text" id="searchInput" class="form-control" placeholder="Search product...">
    </div>
    <div class="col-md-3">
        <select id="statusFilter" class="form-select">
            <option value="all">All Status</option>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
        </select>
    </div>
</div>

<div class="table-responsive">
<table class="table align-middle" id="productTable">
    <thead>
        <tr>
            <th onclick="sortTable(0)">ID <i class="bi bi-arrow-down-up"></i></th>
            <th onclick="sortTable(1)">Product <i class="bi bi-arrow-down-up"></i></th>
            <th onclick="sortTable(2)">Price <i class="bi bi-arrow-down-up"></i></th>
            <th onclick="sortTable(3)">Stock <i class="bi bi-arrow-down-up"></i></th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php while($row=$result->fetch_assoc()): ?>
    <tr data-status="<?= $row['active'] ?>">
        <td><?= $row['id'] ?></td>
        <td><?= htmlspecialchars($row['name']) ?></td>
        <td><?= number_format($row['price'],2) ?></td>
        <td><?= $row['stock'] ?></td>
        <td>
            <a href="?toggle=<?= $row['id'] ?>" class="badge <?= $row['active'] ? 'badge-active' : 'badge-inactive' ?>">
                <?= $row['active'] ? 'Active' : 'Inactive' ?>
            </a>
        </td>
        <td>
            <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#edit<?= $row['id'] ?>"><i class="bi bi-pencil-square"></i></button>
            <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')"><i class="bi bi-trash"></i></a>
        </td>
    </tr>

    <!-- Edit Modal -->
    <div class="modal fade" id="edit<?= $row['id'] ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content p-3">
                <form method="POST">
                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title">Edit Product</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3"><label>Name</label><input type="text" name="name" class="form-control" value="<?= htmlspecialchars($row['name']) ?>" required></div>
                        <div class="mb-3"><label>Price</label><input type="number" step="0.01" name="price" class="form-control" value="<?= $row['price'] ?>" required></div>
                        <div class="mb-3"><label>Stock</label><input type="number" name="stock" class="form-control" value="<?= $row['stock'] ?>" required></div>
                    </div>
                    <div class="modal-footer"><button type="submit" name="update_product" class="btn btn-success">Update</button></div>
                </form>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
    </tbody>
</table>
</div>
</div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
<div class="modal-dialog">
<div class="modal-content p-3">
    <form method="POST">
        <div class="modal-header bg-primary text-white">
            <h5 class="modal-title">Add Product</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3"><label>Name</label><input type="text" name="name" class="form-control" required></div>
            <div class="mb-3"><label>Price</label><input type="number" step="0.01" name="price" class="form-control" required></div>
            <div class="mb-3"><label>Stock</label><input type="number" name="stock" class="form-control" required></div>
        </div>
        <div class="modal-footer"><button type="submit" name="add_product" class="btn btn-success">Add</button></div>
    </form>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Live Search
document.getElementById('searchInput').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    document.querySelectorAll('#productTable tbody tr').forEach(row => {
        row.style.display = row.cells[1].innerText.toLowerCase().includes(filter) ? '' : 'none';
    });
});

// Status Filter
document.getElementById('statusFilter').addEventListener('change', function() {
    let val = this.value;
    document.querySelectorAll('#productTable tbody tr').forEach(row => {
        row.style.display = (val === 'all' || row.dataset.status === val) ? '' : 'none';
    });
});

// Sort Table
function sortTable(n) {
    let table = document.getElementById("productTable");
    let switching = true, dir = "asc", switchcount = 0;
    while(switching) {
        switching = false;
        let rows = table.rows;
        for (let i=1; i<rows.length-1; i++) {
            let shouldSwitch = false;
            let x = rows[i].cells[n].innerText.toLowerCase();
            let y = rows[i+1].cells[n].innerText.toLowerCase();
            if ((dir==="asc" && x>y) || (dir==="desc" && x<y)) { shouldSwitch = true; break; }
            if(shouldSwitch) { rows[i].parentNode.insertBefore(rows[i+1], rows[i]); switching=true; switchcount++; }
        }
        if(switchcount===0 && dir==="asc") { dir="desc"; switching=true; }
    }
}
</script>
</body>
</html>
