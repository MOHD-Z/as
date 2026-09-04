<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $stmt = $pdo->prepare("INSERT INTO languages (code, name, is_default, rtl, enabled) VALUES (?,?,0,?,1)");
    $stmt->execute([trim($_POST['code']), trim($_POST['name']), isset($_POST['rtl']) ? 1 : 0]);
    admin_flash('Language added.');
    header('Location: languages.php');
    exit;
}

if (isset($_GET['default'])) {
    $pdo->exec("UPDATE languages SET is_default = 0");
    $pdo->prepare("UPDATE languages SET is_default = 1 WHERE id = ?")->execute([(int)$_GET['default']]);
    admin_flash('Default language updated.');
    header('Location: languages.php');
    exit;
}

if (isset($_GET['toggle'])) {
    $pdo->prepare("UPDATE languages SET enabled = NOT enabled WHERE id = ?")->execute([(int)$_GET['toggle']]);
    header('Location: languages.php');
    exit;
}

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM languages WHERE id = ? AND is_default = 0")->execute([(int)$_GET['delete']]);
    admin_flash('Language deleted.');
    header('Location: languages.php');
    exit;
}

$languages = $pdo->query("SELECT * FROM languages ORDER BY is_default DESC, name")->fetchAll();

$admin_page_title = 'Languages';
$admin_active = 'languages';
include __DIR__ . '/includes/layout_top.php';
?>
  <p class="muted" style="margin-bottom:16px;">
    Manage which languages are available. Arabic (and any language marked RTL) automatically
    gets a right-to-left layout on the public site. Full per-field translation editing isn't
    built yet — this controls which languages are enabled and which is the default.
  </p>

  <div class="panel" style="margin-bottom:20px;">
    <div class="panel-head"><h2>Add Language</h2></div>
    <div class="panel-body">
      <form method="post" class="form-grid">
        <input type="hidden" name="action" value="create">
        <div class="field"><label>Code (e.g. fr, es, tr)</label><input type="text" name="code" maxlength="10" required></div>
        <div class="field"><label>Name</label><input type="text" name="name" required></div>
        <div class="field">
          <label style="display:flex;align-items:center;gap:8px;margin-top:22px;">
            <input type="checkbox" name="rtl" style="width:16px;height:16px;"> Right-to-left (RTL)
          </label>
        </div>
        <div class="field full"><button type="submit" class="btn primary">+ Add Language</button></div>
      </form>
    </div>
  </div>

  <div class="panel">
    <div class="panel-body table-wrap">
      <table class="table">
        <thead><tr><th>Code</th><th>Name</th><th>Direction</th><th>Default</th><th>Enabled</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($languages as $l): ?>
            <tr>
              <td><?= h($l['code']) ?></td>
              <td><?= h($l['name']) ?></td>
              <td class="muted"><?= $l['rtl'] ? 'RTL' : 'LTR' ?></td>
              <td><?= $l['is_default'] ? '<span class="status published">Default</span>' : '<a href="languages.php?default=' . (int)$l['id'] . '" class="btn">Make Default</a>' ?></td>
              <td><span class="status <?= $l['enabled'] ? 'published' : 'archived' ?>"><?= $l['enabled'] ? 'Enabled' : 'Disabled' ?></span></td>
              <td>
                <?php if (!$l['is_default']): ?>
                  <a href="languages.php?toggle=<?= (int)$l['id'] ?>" class="btn">Toggle</a>
                  <a href="languages.php?delete=<?= (int)$l['id'] ?>" class="btn danger" onclick="return confirm('Delete this language?')">Delete</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
