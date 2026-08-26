<?php
require __DIR__ . '/includes/bootstrap.php';
if (user()) redirect(user()['role'] === 'admin' ? 'admin/dashboard.php' : 'cashier/pos.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stmt = db()->prepare("SELECT id,name,username,password,role FROM users WHERE username=? AND status='active' LIMIT 1");
    $stmt->execute([trim($_POST['username'] ?? '')]); $account = $stmt->fetch();
    if ($account && password_verify($_POST['password'] ?? '', $account['password'])) {
        unset($account['password']); session_regenerate_id(true); $_SESSION['user']=$account; audit('login'); redirect($account['role']==='admin'?'admin/dashboard.php':'cashier/pos.php');
    }
    $error='Invalid username or password.';
}
$pageTitle='Login'; require __DIR__.'/includes/header.php'; ?>
<div class="row justify-content-center mt-5"><div class="col-12 col-sm-8 col-md-5 col-lg-4"><div class="card p-4"><h2 class="text-center mb-1">AI Camera POS</h2><p class="text-muted text-center mb-4">Sign in to continue</p><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
<form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><label class="form-label">Username</label><input class="form-control form-control-lg mb-3" name="username" required autofocus><label class="form-label">Password</label><input class="form-control form-control-lg mb-4" type="password" name="password" required><button class="btn btn-primary btn-lg w-100">Login</button></form>
<div class="border rounded-3 p-3 mt-4 bg-light"><div class="small fw-bold text-uppercase text-muted mb-2">Login Accounts</div><div class="d-flex justify-content-between gap-3 mb-2"><span><strong>Admin</strong><br><small class="text-muted">Full system access</small></span><span class="text-end"><code>admin</code><br><code>Admin@123</code></span></div><hr class="my-2"><div class="d-flex justify-content-between gap-3"><span><strong>Cashier</strong><br><small class="text-muted">POS access</small></span><span class="text-end"><code>cashier</code><br><code>Cashier@123</code></span></div></div>
</div></div></div>
<?php require __DIR__.'/includes/footer.php';
