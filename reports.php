<?php
require_once __DIR__ . '/auth/session_check.php';
include 'includes/db_connect.php';
include 'includes/header.php';

$user_id = $_SESSION['user_id'] ?? 0;

// ------------------- Filters -------------------
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date   = $_GET['end_date'] ?? date('Y-m-d');

// ------------------- Fetch KPIs -------------------
$kpiQuery = "SELECT 
    SUM(s.total_amount) AS total_sales,
    SUM(CASE WHEN s.status='pending' THEN 1 ELSE 0 END) AS pending_sales,
    SUM(CASE WHEN s.status='paid' THEN 1 ELSE 0 END) AS paid_sales,
    COUNT(DISTINCT s.id) AS total_sales_count,
    (SELECT COUNT(*) FROM customers WHERE user_id=$user_id) AS total_customers
FROM sales s
WHERE s.user_id = $user_id AND DATE(s.created_at) BETWEEN '$start_date' AND '$end_date'";

$kpi = $conn->query($kpiQuery)->fetch_assoc();

// ------------------- Fetch Sales Table -------------------
$salesQuery = "SELECT s.id, s.total_amount, s.status, s.created_at,
    GROUP_CONCAT(CONCAT(p.name,' x',si.quantity) SEPARATOR ', ') AS products
    FROM sales s
    JOIN sale_items si ON s.id=si.sale_id
    JOIN products p ON si.product_id=p.id
    WHERE s.user_id=$user_id AND DATE(s.created_at) BETWEEN '$start_date' AND '$end_date'
    GROUP BY s.id
    ORDER BY s.created_at DESC";
$sales = $conn->query($salesQuery);

// ------------------- Chart Data -------------------
$chartData = $conn->query("
    SELECT DATE(created_at) as date, SUM(total_amount) as total
    FROM sales
    WHERE user_id=$user_id AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'
    GROUP BY DATE(created_at)
    ORDER BY DATE(created_at)
");

$chartDates = $chartAmounts = [];
while($row = $chartData->fetch_assoc()){
    $chartDates[] = $row['date'];
    $chartAmounts[] = $row['total'];
}

if(empty($chartDates)){
    $chartDates[] = date('Y-m-d');
    $chartAmounts[] = 0;
}

// ------------------- Top Products Data -------------------
$topProductsData = $conn->query("
    SELECT p.name, SUM(si.quantity) as qty
    FROM sale_items si
    JOIN products p ON si.product_id=p.id
    JOIN sales s ON s.id=si.sale_id
    WHERE s.user_id=$user_id AND DATE(s.created_at) BETWEEN '$start_date' AND '$end_date'
    GROUP BY p.id
    ORDER BY qty DESC
    LIMIT 5
");
$topProducts = $topQty = [];
while($row = $topProductsData->fetch_assoc()){
    $topProducts[] = $row['name'];
    $topQty[] = $row['qty'];
}
?>
<div class="container-fluid py-4">

  <!-- Page Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <form class="d-flex gap-2" method="GET">
      <input type="date" name="start_date" value="<?= $start_date ?>" class="form-control">
      <input type="date" name="end_date" value="<?= $end_date ?>" class="form-control">
      <button class="btn btn-primary">Filter</button>
    </form>
  </div>

  <!-- KPI Cards -->
  <div class="row g-4 mb-4">
    <!-- Total Sales -->
    <div class="col-md-3 col-sm-6">
      <div class="card shadow-sm text-white" style="background: linear-gradient(135deg,#4e73df,#1cc88a);">
        <div class="card-body d-flex align-items-center">
          <i class="bi bi-currency-rupee fs-2 me-3"></i>
          <div>
            <small>Total Sales</small>
            <h5 class="fw-bold">Rs. <?= number_format($kpi['total_sales'] ?? 0,2) ?></h5>
          </div>
        </div>
      </div>
    </div>
    <!-- Pending Sales -->
    <div class="col-md-3 col-sm-6">
      <div class="card shadow-sm text-white" style="background: linear-gradient(135deg,#f6c23e,#f8b16c);">
        <div class="card-body d-flex align-items-center">
          <i class="bi bi-hourglass-split fs-2 me-3"></i>
          <div>
            <small>Pending Sales</small>
            <h5 class="fw-bold"><?= $kpi['pending_sales'] ?? 0 ?></h5>
          </div>
        </div>
      </div>
    </div>
    <!-- Paid Sales -->
    <div class="col-md-3 col-sm-6">
      <div class="card shadow-sm text-white" style="background: linear-gradient(135deg,#1cc88a,#36b9cc);">
        <div class="card-body d-flex align-items-center">
          <i class="bi bi-check2-circle fs-2 me-3"></i>
          <div>
            <small>Paid Sales</small>
            <h5 class="fw-bold"><?= $kpi['paid_sales'] ?? 0 ?></h5>
          </div>
        </div>
      </div>
    </div>
    <!-- Total Customers -->
    <div class="col-md-3 col-sm-6">
      <div class="card shadow-sm text-white" style="background: linear-gradient(135deg,#e74a3b,#fd5e53);">
        <div class="card-body d-flex align-items-center">
          <i class="bi bi-people fs-2 me-3"></i>
          <div>
            <small>Total Customers</small>
            <h5 class="fw-bold"><?= $kpi['total_customers'] ?? 0 ?></h5>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Charts -->
  <div class="row g-4 mb-4">
    <div class="col-md-8">
      <div class="card shadow-sm">
        <div class="card-header bg-primary text-white fw-bold">Sales Over Time</div>
        <div class="card-body">
          <canvas id="salesChart"></canvas>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm">
        <div class="card-header bg-warning text-dark fw-bold">Top Products</div>
        <div class="card-body">
          <canvas id="topProductsChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Sales Table -->
  <div class="card shadow-sm mb-4">
    <div class="card-header bg-dark text-white fw-bold">Sales Details</div>
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Products</th>
            <th>Total Amount</th>
            <th>Status</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php $i=1; while($s=$sales->fetch_assoc()): ?>
          <tr>
            <td><?= $i++ ?></td>
            <td><?= $s['products'] ?></td>
            <td>Rs. <?= number_format($s['total_amount'],2) ?></td>
            <td>
              <span class="badge <?= $s['status']=='paid'?'bg-success':'bg-warning text-dark' ?>"><?= $s['status'] ?></span>
            </td>
            <td><?= $s['created_at'] ?></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const salesCtx = document.getElementById('salesChart').getContext('2d');
new Chart(salesCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode($chartDates) ?>,
        datasets: [{
            label: 'Sales Over Time',
            data: <?= json_encode($chartAmounts) ?>,
            backgroundColor: 'rgba(28,200,138,0.2)',
            borderColor: 'rgba(28,200,138,1)',
            borderWidth: 2,
            fill: true,
            tension: 0.3
        }]
    },
    options: { responsive:true, plugins:{ legend:{ display:true } }, scales:{ y:{ beginAtZero:true } } }
});

const topProductsCtx = document.getElementById('topProductsChart').getContext('2d');
new Chart(topProductsCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($topProducts) ?>,
        datasets: [{ label:'Qty Sold', data: <?= json_encode($topQty) ?>, backgroundColor:'rgba(78,115,223,0.7)', borderColor:'rgba(78,115,223,1)', borderWidth:1 }]
    },
    options: { responsive:true, plugins:{ legend:{ display:false } } }
});
</script>
