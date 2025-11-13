<?php
require_once __DIR__ . '/auth/session_check.php';
include 'includes/db_connect.php';

if (!isset($_GET['id'])) {
    die('Sale ID not provided');
}

$sale_id = intval($_GET['id']);

// Fetch sale info
$sale_res = $conn->query("SELECT s.id, s.customer_id, s.total_amount, s.status, s.created_at
                          FROM sales s WHERE s.id=$sale_id");
$sale = $sale_res->fetch_assoc();
if (!$sale) die('Sale not found');

// Fetch sale items
$items_res = $conn->query("
    SELECT p.name, si.quantity, si.price
    FROM sale_items si
    JOIN products p ON si.product_id = p.id
    WHERE si.sale_id=$sale_id
");

// Generate HTML
?>
<div>
    <h4 class="text-center">Invoice #<?= str_pad($sale['id'],4,'0',STR_PAD_LEFT) ?></h4>
    <p>
        <strong>Customer ID:</strong> <?= $sale['customer_id'] ?><br>
        <strong>Date:</strong> <?= $sale['created_at'] ?><br>
        <strong>Status:</strong> <?= ucfirst($sale['status']) ?>
    </p>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Product</th><th>Qty</th><th>Unit Price</th><th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            while ($item = $items_res->fetch_assoc()):
                $line_total = $item['quantity'] * $item['price'];
            ?>
            <tr>
                <td><?= htmlspecialchars($item['name']) ?></td>
                <td><?= $item['quantity'] ?></td>
                <td>Rs <?= number_format($item['price'],2) ?></td>
                <td>Rs <?= number_format($line_total,2) ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="3" class="text-end">Grand Total</th>
                <th>Rs <?= number_format($sale['total_amount'],2) ?></th>
            </tr>
        </tfoot>
    </table>
</div>
