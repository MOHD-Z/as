<?php
require_once __DIR__ . '/includes/auth.php';

if (admin_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE email = ?");
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login</title>
<link rel="stylesheet" href="admin.css">
</head>
<body style="display:flex;align-items:center;justify-content:center;min-height:100vh;">
  <div class="panel" style="width:360px;">
    <div class="panel-head"><h2>StreamCMS Admin Login</h2></div>
    <div class="panel-body">
      <?php if ($error): ?><div class="notice" style="border-color:#713038;color:#ff9ca4;"><?= h($error) ?></div><?php endif; ?>
      <form method="post">
        <div class="field" style="margin-bottom:12px;">
          <label>Email</label>
          <input type="email" name="email" required value="<?= h($_POST['email'] ?? '') ?>">
        </div>
        <div class="field" style="margin-bottom:16px;">
          <label>Password</label>
          <input type="password" name="password" required>
        </div>
        <button type="submit" class="btn primary" style="width:100%;">Login</button>
      </form>
      <p class="muted" style="margin-top:16px;font-size:11px;">
        First time? Default seed login: admin@anistream.test / admin123
      </p>
    </div>
  </div>
</body>
</html>
