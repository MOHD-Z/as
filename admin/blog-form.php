<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();
require_once __DIR__ . '/../includes/upload.php';

function slugify_post($text) {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

$id = $_GET['id'] ?? null;
$post = null;
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ?");
    $stmt->execute([$id]);
    $post = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $excerpt = trim($_POST['excerpt'] ?? '');
    $body = $_POST['body'] ?? ''; // HTML from the rich text editor — not trimmed/escaped, stored as-is
    $image = handle_poster_upload('image_file', trim($_POST['image'] ?? ''));
    $slug = slugify_post($title);

    if ($id) {
        $stmt = $pdo->prepare("UPDATE blog_posts SET title=?, slug=?, excerpt=?, body=?, image=? WHERE id=?");
        $stmt->execute([$title, $slug, $excerpt, $body, $image, $id]);
        admin_flash('Post updated.');
    } else {
        $stmt = $pdo->prepare("INSERT INTO blog_posts (title, slug, excerpt, body, image) VALUES (?,?,?,?,?)");
        $stmt->execute([$title, $slug, $excerpt, $body, $image]);
        admin_flash('Post created.');
    }
    header('Location: blog.php');
    exit;
}

$admin_page_title = $post ? 'Edit Post' : 'Add Post';
$admin_active = 'blog';
include __DIR__ . '/includes/layout_top.php';
?>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css" rel="stylesheet">
  <style>
    /* Quill needs a light editing surface to be usable; keep it boxed off from the dark admin theme */
    #editor { background:#fff; color:#111; min-height:280px; border-radius:0 0 8px 8px; }
    .ql-toolbar.ql-snow { border-radius:8px 8px 0 0; background:#e9ebf4; }
  </style>

  <div class="panel">
    <div class="panel-body">
      <form method="post" enctype="multipart/form-data" id="blog-form">
        <div class="form-grid">
          <div class="field full"><label>Title</label><input type="text" name="title" required value="<?= h($post['title'] ?? '') ?>"></div>

          <div class="field">
            <label>Poster URL</label>
            <input type="text" name="image" placeholder="img/blog/blog-1.jpg or https://..." value="<?= h($post['image'] ?? '') ?>">
          </div>
          <div class="field">
            <label>...or upload a poster</label>
            <input type="file" name="image_file" accept="image/*">
          </div>
          <?php if (!empty($post['image'])): ?>
            <div class="field full">
              <label>Current poster</label>
              <img src="<?= (strpos($post['image'], 'http') === 0) ? h($post['image']) : '../' . h($post['image']) ?>" alt="" style="max-height:120px;border-radius:6px;">
            </div>
          <?php endif; ?>

          <div class="field full"><label>Excerpt</label><input type="text" name="excerpt" value="<?= h($post['excerpt'] ?? '') ?>"></div>

          <div class="field full">
            <label>Body</label>
            <div id="editor"><?= $post['body'] ?? '' ?></div>
            <input type="hidden" name="body" id="body-input">
          </div>
        </div>
        <div style="margin-top:16px;">
          <button type="submit" class="btn primary"><?= $post ? 'Save Changes' : 'Create Post' ?></button>
          <a href="blog.php" class="btn">Cancel</a>
        </div>
      </form>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js"></script>
  <script>
    const quill = new Quill('#editor', {
      theme: 'snow',
      modules: {
        toolbar: [
          [{ header: [1, 2, 3, false] }],
          [{ size: ['small', false, 'large', 'huge'] }],
          ['bold', 'italic', 'underline', 'strike'],
          [{ color: [] }, { background: [] }],
          [{ align: [] }],
          [{ list: 'ordered' }, { list: 'bullet' }],
          ['link', 'image'],
          ['clean']
        ]
      }
    });

    // Custom image handler — upload the picked file, then insert it at the cursor
    quill.getModule('toolbar').addHandler('image', () => {
      const input = document.createElement('input');
      input.setAttribute('type', 'file');
      input.setAttribute('accept', 'image/*');
      input.click();
      input.onchange = async () => {
        const file = input.files[0];
        if (!file) return;
        const formData = new FormData();
        formData.append('image', file);
        try {
          const res = await fetch('upload-image.php', { method: 'POST', body: formData });
          const data = await res.json();
          if (data.url) {
            const range = quill.getSelection(true);
            quill.insertEmbed(range.index, 'image', data.url);
          } else {
            alert(data.error || 'Upload failed.');
          }
        } catch (e) {
          alert('Upload failed — check your connection and try again.');
        }
      };
    });

    document.getElementById('blog-form').addEventListener('submit', () => {
      document.getElementById('body-input').value = quill.root.innerHTML;
    });
  </script>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
