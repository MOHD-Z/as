<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        redirect('index.php');
    } else {
        $error = 'Invalid email or password.';
    }
}

$page_title = get_setting('site_name', 'AniStream') . ' | ' . __('login', 'Login');
include __DIR__ . '/includes/header.php';
?>
    <section class="login spad">
      <div class="container">
        <div class="row">
          <div class="col-lg-6 offset-lg-3">
            <div class="login__form">
              <h3><?= __('login', 'Login') ?></h3>
              <?php if ($error): ?><p style="color:#ff4747;"><?= h($error) ?></p><?php endif; ?>
              <form method="post">
                <input type="email" name="email" placeholder="Email" required class="form-control mb-3" value="<?= h($_POST['email'] ?? '') ?>">
                <input type="password" name="password" placeholder="Password" required class="form-control mb-3">
                <button type="submit" class="site-btn"><?= __('login_btn', 'Login') ?></button>
              </form>
              <p class="mt-3"><a href="signup.php"><?= __('no_account', 'No account? Sign up here') ?></a></p>
            </div>
          </div>
        </div>
      </div>
    </section>
<?php include __DIR__ . '/includes/footer.php'; ?>
