<?php
include 'includes/db_connect.php';

$data = [];

// Total sales today
$res = $conn->query("SELECT SUM(total_amount) as total FROM sales WHERE DATE(created_at)=CURDATE()");
$data['sales'] = $res->fetch_assoc()['total'] ?? 0;

// Total expenses today (you need an expenses table)
$res = $conn->query("SELECT SUM(amount) as total FROM expenses WHERE DATE(created_at)=CURDATE()");
$data['expenses'] = $res->fetch_assoc()['total'] ?? 0;

// Profit = sales - expenses
$data['profit'] = $data['sales'] - $data['expenses'];

// Low stock items
$res = $conn->query("SELECT COUNT(*) as low_stock FROM products WHERE stock<5");
$data['low_stock'] = $res->fetch_assoc()['low_stock'] ?? 0;

// Pending payments
$res = $conn->query("SELECT COUNT(*) as pending FROM sales WHERE status='pending'");
$data['pending'] = $res->fetch_assoc()['pending'] ?? 0;

// Last 7 days chart data
$chart = [
    'labels' => [],
    'sales' => [],
    'expenses' => [],
    'profit' => []
];

for($i=6;$i>=0;$i--){
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart['labels'][] = date('d M', strtotime($date));

    $sale = $conn->query("SELECT SUM(total_amount) as total FROM sales WHERE DATE(created_at)='$date'")->fetch_assoc()['total'] ?? 0;
    $exp = $conn->query("SELECT SUM(amount) as total FROM expenses WHERE DATE(created_at)='$date'")->fetch_assoc()['total'] ?? 0;

    $chart['sales'][] = floatval($sale);
    $chart['expenses'][] = floatval($exp);
    $chart['profit'][] = floatval($sale - $exp);
}

$data['chart'] = $chart;

header('Content-Type: application/json');
echo json_encode($data);
