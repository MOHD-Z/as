<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || $email === '' || strlen($password) < 6) {
        $error = 'Please fill all fields — password must be at least 6 characters.';
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'An account with that email already exists.';
        } else {
            $ins = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
            $ins->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);

            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            redirect('index.php');
        }
    }
}

$page_title = get_setting('site_name', 'AniStream') . ' | ' . __('signup', 'Sign Up');
include __DIR__ . '/includes/header.php';
?>
    <section class="login spad">
      <div class="container">
        <div class="row">
          <div class="col-lg-6 offset-lg-3">
            <div class="login__form">
              <h3><?= __('signup', 'Sign Up') ?></h3>
              <?php if ($error): ?><p style="color:#ff4747;"><?= h($error) ?></p><?php endif; ?>
              <form method="post">
                <input type="text" name="name" placeholder="Full Name" required class="form-control mb-3" value="<?= h($_POST['name'] ?? '') ?>">
                <input type="email" name="email" placeholder="Email" required class="form-control mb-3" value="<?= h($_POST['email'] ?? '') ?>">
                <input type="password" name="password" placeholder="Password (min 6 chars)" required class="form-control mb-3">
                <button type="submit" class="site-btn"><?= __('signup_btn', 'Sign Up') ?></button>
              </form>
              <p class="mt-3"><a href="login.php"><?= __('have_account', 'Already have an account? Login here') ?></a></p>
            </div>
          </div>
        </div>
      </div>
    </section>
<?php include __DIR__ . '/includes/footer.php'; ?>
