<?php
/** Image library: upload, browse, delete. Also feeds the image picker in the post editor (JSON). */
$pdo = db();
$ajax = isset($_GET['json']) || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch');

if (is_post()) {
    csrf_require();
    $act = (string)($_POST['action'] ?? 'upload');
    if ($act === 'upload') {
        try {
            if (empty($_FILES['file'])) throw new RuntimeException('Please choose an image.');
            $img = process_upload($_FILES['file']);
            $alt = post_str('alt', 300);
            $pdo->prepare('INSERT INTO media (path, mime, size_bytes, width, height, alt, uploaded_by) VALUES (?,?,?,?,?,?,?)')
                ->execute([$img['path'], $img['mime'], $img['size'], $img['width'], $img['height'], $alt ?: null, $me['id']]);
            $mid = (int)$pdo->lastInsertId();
            log_action('media.upload', 'media', $mid);
            if ($ajax) json_out(['ok' => true, 'id' => $mid, 'path' => $img['path'], 'thumb' => $img['thumb_path'], 'alt' => $alt]);
            flash('ok', 'Image uploaded.');
        } catch (RuntimeException $e) {
            if ($ajax) json_out(['ok' => false, 'error' => $e->getMessage()], 400);
            flash('err', $e->getMessage());
        }
    } elseif ($act === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $s = $pdo->prepare('SELECT path FROM media WHERE id = ?'); $s->execute([$id]); $path = (string)$s->fetchColumn();
        if ($path !== '') {
            $pdo->prepare('DELETE FROM media WHERE id = ?')->execute([$id]);
            delete_upload_files($path, preg_replace('/\.(webp|jpg)$/', '-t.$1', $path) ?? '');
            log_action('media.delete', 'media', $id);
            flash('ok', 'Image deleted. Posts that used it will show a gap until you pick another image.');
        }
    }
    redirect(admin_url('media'));
}

$items = $pdo->query('SELECT id, path, alt, width, height, size_bytes, created_at FROM media ORDER BY created_at DESC LIMIT 300')->fetchAll();
if ($ajax && ($_GET['json'] ?? '') !== '') {
    json_out(['ok' => true, 'items' => array_map(fn($m) => ['id' => (int)$m['id'], 'path' => $m['path'], 'thumb' => preg_replace('/\.(webp|jpg)$/', '-t.$1', $m['path']), 'alt' => (string)$m['alt']], $items)]);
}
?>
<h1>Images</h1>
<p class="muted">Photos are shrunk automatically and location details are removed. Use them in blog posts and as cover images.</p>
<form method="post" enctype="multipart/form-data" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="upload">
  <div class="grid3"><div><label for="file">Photo</label><input id="file" type="file" name="file" accept="image/*" required></div>
  <div><label for="alt">Description <span class="muted">(optional)</span></label><input id="alt" name="alt" maxlength="300"></div><div class="end"><button class="btn">Upload</button></div></div>
</form>
<div class="media-grid">
  <?php if (!$items): ?><p class="muted">No images yet.</p><?php endif; ?>
  <?php foreach ($items as $m): $th = preg_replace('/\.(webp|jpg)$/', '-t.$1', $m['path']); ?>
    <div class="media-item"><img src="<?= h($th) ?>" alt="<?= h($m['alt'] ?? '') ?>" loading="lazy">
      <input readonly value="<?= h($m['path']) ?>" class="mono" data-select-all>
      <small class="muted"><?= (int)$m['width'] ?>×<?= (int)$m['height'] ?> · <?= round($m['size_bytes'] / 1024) ?> KB</small>
      <form method="post" data-confirm="Delete this image?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn sm red">Delete</button></form></div>
  <?php endforeach; ?>
</div>
