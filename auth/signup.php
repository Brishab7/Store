<?php
session_start();
include '../includes/db_connect.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (empty($username) || empty($email) || empty($password) || empty($confirm)) {
        $error = "All fields are required!";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match!";
    } else {
        // Check if user already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username=? OR email=?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $error = "Username or email already exists!";
        } else {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'admin')");
            $stmt->bind_param("sss", $username, $email, $hashed);
            if ($stmt->execute()) {
                header("Location: ../index.php?registered=1");
                exit;
            } else {
                $error = "Registration failed: " . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign Up | Store Management</title>
<link rel="shortcut icon" href="images/store.png" type="image/x-icon">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');

body {
    font-family: 'Inter', sans-serif;
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    background: #f0f2f5;
}

.login-container {
    display: flex;
    width: 900px;
    max-width: 95%;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    background: #fff;
}

.login-left {
    flex: 1;
    background: url('../images/brishab.jpeg') no-repeat center center;
    background-size: cover;
    position: relative;
}

.login-left::before {
    content:'';
    position:absolute;
    top:0; left:0; right:0; bottom:0;
    
}

.login-left-text {
    position: absolute;
    bottom: 30px;
    left: 30px;
    color: #fff;
    z-index: 1;
}

.login-left-text h2 { font-weight: 700; font-size: 28px; }
.login-left-text p { font-weight: 500; font-size: 16px; max-width: 200px; }

.login-right {
    flex: 1;
    padding: 50px 40px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.login-right h3 {
    font-weight: 700;
    margin-bottom: 1.5rem;
     color: #1e3c72;
    text-align: center;
}

.input-wrapper {
    position: relative;
    margin-bottom: 20px;
}

.input-wrapper i {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #999;
}

.form-control {
    border-radius: 50px;
    padding-left: 45px;
    height: 45px;
}

.btn-signup {
    border-radius: 50px;
    background: #1e3c72;
    color: #fff;
    font-weight: 600;
    transition: all 0.3s;
    height: 45px;
}

.btn-signup:hover {
    background: #21ed06ff;
}

.signup-link {
    text-align: center;
    margin-top: 20px;
}

.signup-link a {
    color: #1e3c72;
    text-decoration: none;
    font-weight: 600;
}

.signup-link a:hover {
    text-decoration: underline;
}

.alert {
    border-radius: 12px;
    font-size: 14px;
    padding: 0.5rem 1rem;
}

@media(max-width:768px){
    .login-container { flex-direction: column; }
    .login-left { height: 200px; }
}
</style>
</head>
<body>

<div class="login-container">
    <!-- Left image panel -->
    <div class="login-left">
        <div class="login-left-text">
           
        </div>
    </div>

    <!-- Right signup form -->
    <div class="login-right">
        <h3>Sign Up</h3>

        <?php if($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" class="needs-validation" novalidate>
            <div class="input-wrapper">
                <i class="fas fa-user"></i>
                <input type="text" name="username" class="form-control" placeholder="Username" required>
            </div>
            <div class="input-wrapper">
                <i class="fas fa-envelope"></i>
                <input type="email" name="email" class="form-control" placeholder="Email" required>
            </div>
            <div class="input-wrapper">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" class="form-control" placeholder="Password" required>
            </div>
            <div class="input-wrapper">
                <i class="fas fa-lock"></i>
                <input type="password" name="confirm" class="form-control" placeholder="Confirm Password" required>
            </div>
            <button type="submit" class="btn btn-signup w-100"><i class="fas fa-user-plus me-1"></i> Sign Up</button>
        </form>

        <div class="signup-link">
            <p>Already have an account? <a href="../index.php">Login</a></p>
        </div>
    </div>
</div>

<script>
(() => {
  'use strict';
  const forms = document.querySelectorAll('.needs-validation');
  Array.from(forms).forEach(form => {
    form.addEventListener('submit', event => {
      if (!form.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
      }
      form.classList.add('was-validated');
    }, false);
  });
})();
</script>

</body>
</html>
