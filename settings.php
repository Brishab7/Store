<?php
session_start();
include 'includes/db_connect.php';
include 'includes/header.php';

$admin_id = 1; // demo admin ID

// Fetch admin info from session or database
$admin = $_SESSION['admin'] ?? null;
if(!$admin){
    $result = $conn->query("SELECT * FROM admin_settings WHERE id=$admin_id");
    $admin = $result ? $result->fetch_assoc() : ['username'=>'Admin','full_name'=>'Admin','theme'=>'light'];
    $_SESSION['admin'] = $admin;
}

// Handle AJAX save
if(isset($_POST['action']) && $_POST['action']=='save'){
    $username = $conn->real_escape_string($_POST['username']);
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $theme = ($_POST['theme']=='dark') ? 'dark' : 'light';

    if(!empty($_POST['password'])){
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $sql = "UPDATE admin_settings SET username='$username', full_name='$full_name', theme='$theme', password='$password' WHERE id=$admin_id";
    } else {
        $sql = "UPDATE admin_settings SET username='$username', full_name='$full_name', theme='$theme' WHERE id=$admin_id";
    }

    if($conn->query($sql)){
        // Update session immediately
        $_SESSION['admin'] = [
            'username'=>$username,
            'full_name'=>$full_name,
            'theme'=>$theme
        ];
        echo json_encode(['success'=>true,'message'=>'Settings updated!','username'=>$username,'full_name'=>$full_name,'theme'=>$theme]);
    } else {
        echo json_encode(['success'=>false,'message'=>'Error updating settings']);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Settings - Smart POS</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { font-family:'Segoe UI',sans-serif; transition:0.3s; background:#f4f5f7;}
.dark-mode { background:#343a40; color:#f8f9fa; }
.settings-container { max-width: 950px; margin:50px auto; }
.card { border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,0.1); transition:0.2s; }
.card:hover { transform:translateY(-3px); }
.card-header { background: linear-gradient(135deg,#4e73df,#1cc88a); color:#fff; font-size:1.3rem; font-weight:600; border-radius:15px 15px 0 0; }
.form-floating>.form-control:focus { box-shadow:0 0 0 0.25rem rgba(28,200,138,0.25); border-color:#1cc88a; }
.btn-primary { background: linear-gradient(135deg,#4e73df,#1cc88a); border:none; transition:0.3s; }
.btn-primary:hover { background: linear-gradient(135deg,#1cc88a,#4e73df); }
.theme-preview { width:45px;height:45px;border-radius:50%;display:inline-block;margin-right:10px;border:2px solid #ddd;cursor:pointer;transition:0.2s; }
.theme-preview:hover { transform: scale(1.1); }
.theme-light { background:#f8f9fa; border:2px solid #4e73df; }
.theme-dark { background:#343a40; border:2px solid #1cc88a; }
.alert { display:none; }
</style>
</head>
<body class="<?= $admin['theme']=='dark'?'dark-mode':'' ?>">

<div class="settings-container">
<div class="card">
    <div class="card-header d-flex align-items-center">
        <i class="bi bi-gear-fill me-2"></i> Settings
    </div>
    <div class="card-body">

        <div class="alert alert-success" id="successMessage"></div>

        <form id="settingsForm" class="row g-4">
            <h5 class="mb-3">Profile Info</h5>
            <div class="col-md-6 form-floating">
                <input type="text" class="form-control" name="username" id="username" placeholder="Username" value="<?= htmlspecialchars($admin['username']); ?>" required>
                <label for="username">Username</label>
            </div>
            <div class="col-md-6 form-floating">
                <input type="text" class="form-control" name="full_name" id="full_name" placeholder="Full Name" value="<?= htmlspecialchars($admin['full_name']); ?>" required>
                <label for="full_name">Full Name</label>
            </div>

            <h5 class="mt-4 mb-3">Security</h5>
            <div class="col-md-6 form-floating">
                <input type="password" class="form-control" name="password" id="password" placeholder="New Password">
                <label for="password">Password <small>(leave blank to keep current)</small></label>
            </div>

            <h5 class="mt-4 mb-3">Appearance</h5>
            <div class="col-12">
                <label class="form-label">Theme</label>
                <div>
                    <label class="me-3">
                        <input type="radio" name="theme" value="light" <?= $admin['theme']=='light'?'checked':''; ?>>
                        <span class="theme-preview theme-light" data-theme="light"></span> Light
                    </label>
                    <label>
                        <input type="radio" name="theme" value="dark" <?= $admin['theme']=='dark'?'checked':''; ?>>
                        <span class="theme-preview theme-dark" data-theme="dark"></span> Dark
                    </label>
                </div>
            </div>

            <div class="col-12 text-end mt-3">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Live theme preview
document.querySelectorAll('.theme-preview').forEach(el=>{
    el.addEventListener('click', function(){
        const theme = this.getAttribute('data-theme');
        if(theme=='dark'){ document.body.classList.add('dark-mode'); }
        else{ document.body.classList.remove('dark-mode'); }
        document.querySelector(`input[name="theme"][value="${theme}"]`).checked = true;
    });
});

// AJAX form submit
document.getElementById('settingsForm').addEventListener('submit', function(e){
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action','save');

    fetch('', {method:'POST', body: formData})
    .then(res=>res.json())
    .then(data=>{
        const msg = document.getElementById('successMessage');
        msg.textContent = data.message;
        msg.style.display='block';
        setTimeout(()=> { msg.style.display='none'; }, 3000);

        if(data.success){
            // Update header immediately
            const userFullName = document.getElementById('userFullName');
            const dashboardTitle = document.getElementById('dashboardTitle');
            if(userFullName) userFullName.textContent = data.full_name;
            if(dashboardTitle) dashboardTitle.textContent = data.username+"'s Dashboard Overview";
            this.password.value = '';
        }
    }).catch(err=>console.error(err));
});
</script>
</body>
</html>
