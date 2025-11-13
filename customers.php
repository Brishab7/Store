<?php
require_once __DIR__ . '/auth/session_check.php';
ob_start();
include 'includes/db_connect.php';
include 'includes/header.php';

$user_id = $_SESSION['user_id'] ?? 0;

// ------------------- ALERT SYSTEM -------------------
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

// ------------------- HANDLE FORM SUBMISSION -------------------
if(isset($_POST['save_customer'])){
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $status = $conn->real_escape_string($_POST['status']);

    if($id > 0){
        $conn->query("UPDATE customers SET name='$name', email='$email', phone='$phone', status='$status' WHERE id=$id AND user_id=$user_id");
        $_SESSION['alert'] = [
            'type'=>'success',
            'title'=>'✏️ Customer Updated',
            'message'=>"$name updated successfully!"
        ];
    } else {
        $conn->query("INSERT INTO customers (name,email,phone,status,user_id) VALUES ('$name','$email','$phone','$status',$user_id)");
        $_SESSION['alert'] = [
            'type'=>'success',
            'title'=>'✅ Customer Added',
            'message'=>"$name added successfully!"
        ];
    }
    header("Location: customers.php");
    exit;
}

// ------------------- HANDLE DELETE -------------------
if(isset($_GET['delete'])){
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM customers WHERE id=$id AND user_id=$user_id");
    $_SESSION['alert'] = [
        'type'=>'success',
        'title'=>'🗑️ Customer Deleted',
        'message'=>'Customer deleted successfully!'
    ];
    header("Location: customers.php");
    exit;
}

// ------------------- FETCH CUSTOMERS & KPIs -------------------
$res = $conn->query("SELECT * FROM customers WHERE user_id=$user_id ORDER BY id DESC");
$customers = [];
$total = $active = $inactive = 0;
while($row = $res->fetch_assoc()){
    $customers[] = $row;
    $total++;
    if($row['status']=='active') $active++; else $inactive++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Customers Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
<style>
body { font-family:'Poppins',sans-serif; background:#f4f6f9; }
.card { border-radius:15px; box-shadow:0 5px 25px rgba(0,0,0,0.1); }
.table thead th { background: linear-gradient(45deg,#4e73df,#1cc88a); color:white; }
.table tbody tr:hover { background: rgba(0,0,0,0.03); transition:0.3s; }
.btn { border-radius:50px; transition:0.3s; }
.btn:hover { transform: scale(1.05); }
.modal-content { border-radius:15px; }
.badge-active { background: linear-gradient(90deg,#1cc88a,#17a673); color:white; }
.badge-inactive { background: linear-gradient(90deg,#6c757d,#495057); color:white; }
</style>
</head>
<body>

<div class="container my-5">

<!-- KPIs -->
<div class="row g-4 mb-4">
  <div class="col-md-4 col-sm-6">
    <div class="card shadow-sm text-white" style="background: linear-gradient(135deg,#4e73df,#1cc88a);">
      <div class="card-body d-flex align-items-center">
        <i class="bi bi-people fs-2 me-3"></i>
        <div><small>Total Customers</small><h5 class="fw-bold"><?= $total; ?></h5></div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-sm-6">
    <div class="card shadow-sm text-white" style="background: linear-gradient(135deg,#1cc88a,#36b9cc);">
      <div class="card-body d-flex align-items-center">
        <i class="bi bi-person-check fs-2 me-3"></i>
        <div><small>Active Customers</small><h5 class="fw-bold"><?= $active; ?></h5></div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-sm-6">
    <div class="card shadow-sm text-white" style="background: linear-gradient(135deg,#e74a3b,#fd5e53);">
      <div class="card-body d-flex align-items-center">
        <i class="bi bi-person-x fs-2 me-3"></i>
        <div><small>Inactive Customers</small><h5 class="fw-bold"><?= $inactive; ?></h5></div>
      </div>
    </div>
  </div>
</div>

<!-- Search & Filter -->
<div class="row g-3 mb-3">
  <div class="col-md-6"><input type="text" id="searchInput" class="form-control" placeholder="Search customers..."></div>
  <div class="col-md-3">
    <select id="statusFilter" class="form-select">
      <option value="all">All Status</option>
      <option value="active">Active</option>
      <option value="inactive">Inactive</option>
    </select>
  </div>
</div>

<!-- Customers Table -->
<div class="card shadow-sm">
  <div class="card-header d-flex justify-content-between bg-primary text-white">
    <h5>Customers List</h5>
    <button class="btn btn-light btn-sm" onclick="openAddModal()"><i class="bi bi-plus-circle me-1"></i> Add Customer</button>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0" id="customerTable">
      <thead>
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php $i=1; foreach($customers as $c): ?>
        <tr data-status="<?= $c['status']; ?>">
          <td><?= $i++; ?></td>
          <td><?= htmlspecialchars($c['name']); ?></td>
          <td><?= htmlspecialchars($c['email']); ?></td>
          <td><?= htmlspecialchars($c['phone']); ?></td>
          <td><span class="badge <?= $c['status']=='active'?'badge-active':'badge-inactive'; ?>"><?= $c['status']; ?></span></td>
          <td>
            <button class="btn btn-sm btn-info me-1" onclick="openEditModal(<?= $c['id']; ?>,'<?= htmlspecialchars($c['name']); ?>','<?= htmlspecialchars($c['email']); ?>','<?= htmlspecialchars($c['phone']); ?>','<?= $c['status']; ?>')"><i class="bi bi-pencil"></i></button>
            <a href="customers.php?delete=<?= $c['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')"><i class="bi bi-trash"></i></a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="customerModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="modalTitle">Add Customer</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="customerId">
        <div class="mb-3"><label>Name</label><input type="text" class="form-control" name="name" id="customerName" required></div>
        <div class="mb-3"><label>Email</label><input type="email" class="form-control" name="email" id="customerEmail"></div>
        <div class="mb-3"><label>Phone</label><input type="text" class="form-control" name="phone" id="customerPhone"></div>
        <div class="mb-3"><label>Status</label>
          <select class="form-select" name="status" id="customerStatus">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="save_customer" class="btn btn-success">Save Customer</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function openAddModal(){
    document.getElementById('modalTitle').textContent='Add Customer';
    document.getElementById('customerId').value='';
    document.getElementById('customerName').value='';
    document.getElementById('customerEmail').value='';
    document.getElementById('customerPhone').value='';
    document.getElementById('customerStatus').value='active';
    new bootstrap.Modal(document.getElementById('customerModal')).show();
}

function openEditModal(id,name,email,phone,status){
    document.getElementById('modalTitle').textContent='Edit Customer';
    document.getElementById('customerId').value=id;
    document.getElementById('customerName').value=name;
    document.getElementById('customerEmail').value=email;
    document.getElementById('customerPhone').value=phone;
    document.getElementById('customerStatus').value=status;
    new bootstrap.Modal(document.getElementById('customerModal')).show();
}

// Live Search
document.getElementById('searchInput').addEventListener('keyup', function(){
    let filter = this.value.toLowerCase();
    document.querySelectorAll('#customerTable tbody tr').forEach(row=>{
        row.style.display = row.cells[1].innerText.toLowerCase().includes(filter) ? '' : 'none';
    });
});

// Status Filter
document.getElementById('statusFilter').addEventListener('change', function(){
    let val = this.value;
    document.querySelectorAll('#customerTable tbody tr').forEach(row=>{
        row.style.display = (val==='all' || row.dataset.status===val) ? '' : 'none';
    });
});
</script>
</body>
</html>
