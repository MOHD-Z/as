<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || $email === '' || strlen($password) < 6) {
        $error = 'Name, email, and a password of at least 6 characters are required.';
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'A user with that email already exists.';
        } else {
            $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?,?,?)")
                ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            admin_flash('User created.');
            header('Location: users.php');
            exit;
        }
    }
}

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([(int)$_GET['delete']]);
    admin_flash('User removed.');
    header('Location: users.php');
    exit;
}

$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();

$admin_page_title = 'Users';
$admin_active = 'users';
include __DIR__ . '/includes/layout_top.php';
?>
  <p class="muted" style="margin-bottom:16px;">Visitor accounts — created via Sign Up on the public site, or manually below.</p>

  <div class="panel" style="margin-bottom:20px;">
    <div class="panel-head"><h2>Create User</h2></div>
    <div class="panel-body">
      <?php if ($error): ?><div class="notice" style="border-color:#713038;color:#ff9ca4;"><?= h($error) ?></div><?php endif; ?>
      <form method="post" class="form-grid">
        <input type="hidden" name="action" value="create">
        <div class="field"><label>Name</label><input type="text" name="name" required value="<?= h($_POST['name'] ?? '') ?>"></div>
        <div class="field"><label>Email</label><input type="email" name="email" required value="<?= h($_POST['email'] ?? '') ?>"></div>
        <div class="field"><label>Password (min 6 chars)</label><input type="password" name="password" required></div>
        <div class="field full"><button type="submit" class="btn primary">Create User</button></div>
      </form>
    </div>
  </div>

  <div class="panel">
    <div class="panel-body table-wrap">
      <table class="table">
        <thead><tr><th>Name</th><th>Email</th><th>Joined</th><th></th></tr></thead>
        <tbody>
          <?php if (!$users): ?><tr><td colspan="4" class="empty">No registered users yet.</td></tr><?php endif; ?>
          <?php foreach ($users as $u): ?>
            <tr>
              <td><?= h($u['name']) ?></td>
              <td class="muted"><?= h($u['email']) ?></td>
              <td class="muted"><?= h($u['created_at']) ?></td>
              <td><a href="users.php?delete=<?= (int)$u['id'] ?>" class="btn danger" onclick="return confirm('Remove this user?')">Remove</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
