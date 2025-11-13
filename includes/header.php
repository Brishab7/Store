<?php
include __DIR__.'/db_connect.php';

// For demo, using admin ID = 1 (replace with $_SESSION['admin_id'] if login implemented)
$admin_id = 1;

// Fetch latest admin info
$admin = null;
$result = $conn->query("SELECT * FROM admin_settings WHERE id=$admin_id");
if($result){
    $admin = $result->fetch_assoc();
}

// Apply theme dynamically
$theme_class = (isset($admin['theme']) && $admin['theme']=='dark') ? 'bg-dark text-white' : '';

// Detect current page for active link highlight
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Smart POS</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="shortcut icon" href="images/store.png" type="image/x-icon">

<!-- Bootstrap CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<!-- Custom CSS -->
<link rel="stylesheet" href="assets/css/style.css">

<style>
/* Sidebar */
.sidebar {
    width: 250px;
    height: 100vh;
    position: fixed;
    transition: all 0.3s;
    background: linear-gradient(to bottom, #466087ff, #6610f2);
    color: #fff;
}
.sidebar .sidebar-heading { font-size: 1.5rem; }
.sidebar .list-group-item {
    background: transparent;
    color: #fff;
    border: none;
    transition: 0.2s;
}
.sidebar .list-group-item:hover,
.sidebar .list-group-item.active {
    background: rgba(255,255,255,0.2);
    color: #fff;
    border-radius: 8px;
}
#page-content-wrapper { transition: all 0.3s; margin-left: 250px; }
#wrapper.toggled #page-content-wrapper { margin-left: 0; }

/* Optional: smooth resizing for cards */
.dashboard-card { transition: transform 0.2s, width 0.3s; }

/* Navbar */
.navbar { border-bottom: 1px solid #ddd; }
.navbar .btn-outline-dark { border: none; }

/* Sidebar toggle icon */
.icon-circle { width: 50px; height: 50px; border-radius: 50%; display: flex; justify-content: center; align-items: center; }
</style>
</head>
<body class="<?= $theme_class ?>">
<div class="d-flex" id="wrapper">

  <!-- Sidebar -->
  <nav class="sidebar">
    <div class="sidebar-heading text-center py-4 fw-bold">
        <i class="bi bi-shop fs-4"></i> <?= htmlspecialchars($admin['full_name'] ?? 'Brishab'); ?>
    </div>
    <div class="list-group list-group-flush mt-3">
        <a href="dashboard.php" class="list-group-item list-group-item-action <?= $currentPage=='dashboard.php'?'active':'' ?>">
            <i class="bi bi-speedometer2 me-2"></i> Dashboard
        </a>
        <a href="products.php" class="list-group-item list-group-item-action <?= $currentPage=='products.php'?'active':'' ?>">
            <i class="bi bi-box-seam me-2"></i> Products
        </a>
        <a href="sales.php" class="list-group-item list-group-item-action <?= $currentPage=='sales.php'?'active':'' ?>">
            <i class="bi bi-cash-stack me-2"></i> Sales
        </a>
        <a href="customers.php" class="list-group-item list-group-item-action <?= $currentPage=='customers.php'?'active':'' ?>">
            <i class="bi bi-people me-2"></i> Customers
        </a>
        <a href="reports.php" class="list-group-item list-group-item-action <?= $currentPage=='reports.php'?'active':'' ?>">
            <i class="bi bi-bar-chart-line me-2"></i> Reports
        </a>
        <a href="settings.php" class="list-group-item list-group-item-action <?= $currentPage=='settings.php'?'active':'' ?>">
            <i class="bi bi-gear me-2"></i> Settings
        </a>
    </div>
  </nav>

  <!-- Page Content -->
  <div id="page-content-wrapper" class="w-100">

    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm px-4">
        <button class="btn btn-outline-dark me-2" id="menu-toggle"><i class="bi bi-list"></i></button>
        <span class="navbar-brand fw-semibold" id="dashboardTitle"><?= htmlspecialchars($admin['username'] ?? 'Store') ?> </span>
        <div class="ms-auto dropdown">
            <button class="btn btn-outline-primary dropdown-toggle" type="button" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle me-1"></i> <span id="userFullName"><?= htmlspecialchars($admin['username'] ?? 'Brishab') ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
                <li><a class="dropdown-item" href="settings.php">Profile & Settings</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="auth/logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

<script>
    // Toggle sidebar
    document.getElementById('menu-toggle').addEventListener('click', function() {
        document.getElementById('wrapper').classList.toggle('toggled');
    });
</script>
