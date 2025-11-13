<?php
// session_check.php
session_start(); // must be first line, no HTML before

// If user is not logged in, redirect to login page
if (empty($_SESSION['user_id'])) {
    header("Location: index.php"); // relative path, works even with spaces in folder name
    exit();
}
