<?php
require_once __DIR__ . '/auth/session_check.php';
include 'includes/db_connect.php';
header('Content-Type: application/json');

try {
    if($_SERVER['REQUEST_METHOD'] !== 'POST'){
        throw new Exception("Invalid request");
    }

    $cart = isset($_POST['cart']) ? json_decode($_POST['cart'], true) : [];
    if(!$cart || !is_array($cart)){
        throw new Exception("Cart is empty");
    }

    $customer_id = !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
    $status = isset($_POST['status']) && in_array($_POST['status'], ['paid','pending']) ? $_POST['status'] : 'paid';
    $total_amount = 0;

    // Calculate total
    foreach($cart as $item){
        $total_amount += $item['price'] * $item['quantity'];
    }

    // Begin transaction
    $conn->begin_transaction();

    // Insert into sales
    $stmt = $conn->prepare("INSERT INTO sales (customer_id, total_amount, status, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("ids", $customer_id, $total_amount, $status);
    $stmt->execute();
    $sale_id = $stmt->insert_id;
    $stmt->close();

    // Insert sale items and reduce stock
    $stmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
    $update_stock = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id=?");

    foreach($cart as $item){
        $stmt->bind_param("iiid", $sale_id, $item['id'], $item['quantity'], $item['price']);
        $stmt->execute();

        // Reduce product stock
        $update_stock->bind_param("ii", $item['quantity'], $item['id']);
        $update_stock->execute();
    }

    $stmt->close();
    $update_stock->close();

    $conn->commit();

    echo json_encode(['success'=>true]);

} catch(Exception $e){
    if($conn->errno) $conn->rollback();
    echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
}
