<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $location = $_POST['location'] === 'footer' ? 'footer' : 'header';
    $maxOrder = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM nav_links WHERE location = " . $pdo->quote($location))->fetchColumn();
    $stmt = $pdo->prepare("INSERT INTO nav_links (label, url, location, visibility, sort_order, visible) VALUES (?,?,?,?,?,1)");
    $stmt->execute([trim($_POST['label']), trim($_POST['url']), $location, $_POST['visibility'], $maxOrder + 1]);
    admin_flash('Link added.');
    header('Location: menus.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $stmt = $pdo->prepare("UPDATE nav_links SET label=?, url=?, visibility=?, sort_order=? WHERE id=?");
    $stmt->execute([trim($_POST['label']), trim($_POST['url']), $_POST['visibility'], (int)$_POST['sort_order'], (int)$_POST['id']]);
    admin_flash('Link updated.');
    header('Location: menus.php');
    exit;
}

if (isset($_GET['toggle'])) {
    $pdo->prepare("UPDATE nav_links SET visible = NOT visible WHERE id = ?")->execute([(int)$_GET['toggle']]);
    header('Location: menus.php');
    exit;
}

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM nav_links WHERE id = ?")->execute([(int)$_GET['delete']]);
    admin_flash('Link deleted.');
    header('Location: menus.php');
    exit;
}

$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM nav_links WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editing = $stmt->fetch();
}

$headerLinks = $pdo->query("SELECT * FROM nav_links WHERE location = 'header' ORDER BY sort_order")->fetchAll();
$footerLinks = $pdo->query("SELECT * FROM nav_links WHERE location = 'footer' ORDER BY sort_order")->fetchAll();
$visLabels = ['always' => 'Always', 'guest_only' => 'Logged-out only', 'auth_only' => 'Logged-in only'];

$admin_page_title = 'Header & Footer Menus';
$admin_active = 'menus';
include __DIR__ . '/includes/layout_top.php';

function render_nav_table($links, $visLabels) { ?>
  <table class="table">
    <thead><tr><th>Order</th><th>Label</th><th>URL</th><th>Shown</th><th>Visible</th><th></th></tr></thead>
    <tbody>
      <?php if (!$links): ?><tr><td colspan="6" class="empty">No links yet.</td></tr><?php endif; ?>
      <?php foreach ($links as $l): ?>
        <tr>
          <td><?= (int)$l['sort_order'] ?></td>
          <td><?= h($l['label']) ?></td>
          <td class="muted"><?= h($l['url']) ?></td>
          <td class="muted"><?= h($visLabels[$l['visibility']] ?? $l['visibility']) ?></td>
          <td><span class="status <?= $l['visible'] ? 'published' : 'archived' ?>"><?= $l['visible'] ? 'Shown' : 'Hidden' ?></span></td>
          <td>
            <a href="menus.php?edit=<?= (int)$l['id'] ?>" class="btn">Edit</a>
            <a href="menus.php?toggle=<?= (int)$l['id'] ?>" class="btn"><?= $l['visible'] ? 'Hide' : 'Show' ?></a>
            <a href="menus.php?delete=<?= (int)$l['id'] ?>" class="btn danger" onclick="return confirm('Delete this link?')">Delete</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php }
?>
  <p class="muted" style="margin-bottom:16px;">
    Control what shows in your header and footer navigation — reorder, rename, hide, or
    remove any link. To hide Login/Sign Up entirely (e.g. if you don't want visitors
    signing up), just click "Hide" next to them below.
  </p>

  <div class="panel" style="margin-bottom:20px;">
    <div class="panel-head"><h2><?= $editing ? 'Edit Link' : 'Add Link' ?></h2></div>
    <div class="panel-body">
      <form method="post" class="form-grid">
        <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>
        <div class="field"><label>Label</label><input type="text" name="label" required value="<?= h($editing['label'] ?? '') ?>"></div>
        <div class="field"><label>URL</label><input type="text" name="url" placeholder="e.g. blog.php or https://..." required value="<?= h($editing['url'] ?? '') ?>"></div>
        <?php if (!$editing): ?>
        <div class="field">
          <label>Location</label>
          <select name="location">
            <option value="header">Header</option>
            <option value="footer">Footer</option>
          </select>
        </div>
        <?php endif; ?>
        <div class="field">
          <label>Show to</label>
          <select name="visibility">
            <?php foreach ($visLabels as $val => $lbl): ?>
              <option value="<?= $val ?>" <?= ($editing['visibility'] ?? 'always') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php if ($editing): ?>
        <div class="field"><label>Order</label><input type="number" name="sort_order" value="<?= (int)$editing['sort_order'] ?>"></div>
        <?php endif; ?>
        <div class="field full">
          <button type="submit" class="btn primary"><?= $editing ? 'Save Changes' : '+ Add Link' ?></button>
          <?php if ($editing): ?><a href="menus.php" class="btn">Cancel</a><?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <div class="panel" style="margin-bottom:20px;">
    <div class="panel-head"><h2>Header Menu</h2></div>
    <div class="panel-body table-wrap"><?php render_nav_table($headerLinks, $visLabels); ?></div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Footer Menu</h2></div>
    <div class="panel-body table-wrap"><?php render_nav_table($footerLinks, $visLabels); ?></div>
  </div>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
