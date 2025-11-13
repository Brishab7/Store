<?php
require_once __DIR__ . '/auth/session_check.php';
include __DIR__ . '/includes/db_connect.php';

$user_id = $_SESSION['user_id']; // logged-in user

// ----------------- Dashboard Data -----------------
$data = [];

// Total sales today
$res = $conn->query("SELECT SUM(total_amount) as total FROM sales WHERE user_id=$user_id AND DATE(created_at)=CURDATE()");
$data['sales'] = $res->fetch_assoc()['total'] ?? 0;

// Total expenses today
$res = $conn->query("SELECT SUM(amount) as total FROM expenses WHERE user_id=$user_id AND DATE(created_at)=CURDATE()");
$data['expenses'] = $res->fetch_assoc()['total'] ?? 0;

// Profit = sales - expenses
$data['profit'] = $data['sales'] - $data['expenses'];

// Low stock items
$res = $conn->query("SELECT COUNT(*) as low_stock FROM products WHERE user_id=$user_id AND stock<5");
$data['low_stock'] = $res->fetch_assoc()['low_stock'] ?? 0;

// Pending payments
$res = $conn->query("SELECT COUNT(*) as pending FROM sales WHERE user_id=$user_id AND status='pending'");
$data['pending'] = $res->fetch_assoc()['pending'] ?? 0;

// Last 7 days chart data
$chart = ['labels'=>[], 'sales'=>[], 'expenses'=>[], 'profit'=>[]];
for($i=6;$i>=0;$i--){
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart['labels'][] = date('d M', strtotime($date));

    $sale = $conn->query("SELECT SUM(total_amount) as total FROM sales WHERE user_id=$user_id AND DATE(created_at)='$date'")->fetch_assoc()['total'] ?? 0;
    $exp = $conn->query("SELECT SUM(amount) as total FROM expenses WHERE user_id=$user_id AND DATE(created_at)='$date'")->fetch_assoc()['total'] ?? 0;

    $chart['sales'][] = floatval($sale);
    $chart['expenses'][] = floatval($exp);
    $chart['profit'][] = floatval($sale-$exp);
}
$data['chart'] = $chart;
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Store/Brishab</title>
<link rel="shortcut icon" href="images/store.png" type="image/x-icon">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body { background: #f5f7fa; font-family: "Inter", sans-serif; }
.dashboard-card { border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.1); }
.icon-circle { width:50px; height:50px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.5rem; }
</style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="container-fluid py-4">

<!-- Dashboard Metrics -->
<div class="row g-4">
  <div class="col-md-3 col-sm-6">
    <div class="card dashboard-card p-3 text-white" style="background: linear-gradient(135deg,#4e73df,#1cc88a);">
      <div class="d-flex align-items-center">
        <div class="icon-circle bg-white text-dark"><i class="bi bi-currency-rupee"></i></div>
        <div class="ms-3">
          <h6>Total Sales (Today)</h6>
          <h4 class="fw-bold" id="sales">Rs. <?= number_format($data['sales'],2) ?></h4>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="card dashboard-card p-3 text-white" style="background: linear-gradient(135deg,#f6c23e,#f8b16c);">
      <div class="d-flex align-items-center">
        <div class="icon-circle bg-white text-dark"><i class="bi bi-cash-coin"></i></div>
        <div class="ms-3">
          <h6>Expenses</h6>
          <h4 class="fw-bold" id="expenses">Rs. <?= number_format($data['expenses'],2) ?></h4>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="card dashboard-card p-3 text-white" style="background: linear-gradient(135deg,#1cc88a,#36b9cc);">
      <div class="d-flex align-items-center">
        <div class="icon-circle bg-white text-dark"><i class="bi bi-graph-up-arrow"></i></div>
        <div class="ms-3">
          <h6>Profit</h6>
          <h4 class="fw-bold" id="profit">Rs. <?= number_format($data['profit'],2) ?></h4>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="card dashboard-card p-3 text-white" style="background: linear-gradient(135deg,#e74a3b,#fd5e53);">
      <div class="d-flex align-items-center">
        <div class="icon-circle bg-white text-dark"><i class="bi bi-exclamation-circle"></i></div>
        <div class="ms-3">
          <h6>Low Stock</h6>
          <h4 class="fw-bold" id="low_stock"><?= $data['low_stock'] ?> Items</h4>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Charts & Pending -->
<div class="row mt-4 g-4">
  <div class="col-lg-8 col-md-12">
    <div class="card shadow-sm border-0 p-3">
      <h6 class="fw-semibold mb-3">📊 Sales vs Expenses vs Profit (Last 7 days)</h6>
      <canvas id="salesChart" height="120"></canvas>
    </div>
  </div>
  <div class="col-lg-4 col-md-12">
    <div class="card shadow-sm border-0 p-3 text-center" style="background: linear-gradient(135deg,#36b9cc,#4e73df); color:#fff;">
      <div class="icon-circle bg-white text-dark mx-auto mb-3"><i class="bi bi-clock-history fs-3"></i></div>
      <h6>Pending Payments</h6>
      <h4 class="fw-bold" id="pending"><?= $data['pending'] ?></h4>
    </div>
  </div>
</div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>


<script>
// Render chart
const ctx = document.getElementById('salesChart').getContext('2d');
const salesChart = new Chart(ctx, {
    type:'line',
    data:{
        labels: <?= json_encode($data['chart']['labels']) ?>,
        datasets:[
            {label:'Sales', data: <?= json_encode($data['chart']['sales']) ?>, borderColor:'#4e73df', backgroundColor:'rgba(78,115,223,0.1)', tension:0.4},
            {label:'Expenses', data: <?= json_encode($data['chart']['expenses']) ?>, borderColor:'#f6c23e', backgroundColor:'rgba(246,194,58,0.1)', tension:0.4},
            {label:'Profit', data: <?= json_encode($data['chart']['profit']) ?>, borderColor:'#1cc88a', backgroundColor:'rgba(28,200,138,0.1)', tension:0.4},
        ]
    },
    options:{responsive:true, plugins:{legend:{position:'top'}}, scales:{y:{beginAtZero:true}}}
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


</body>
</html>
