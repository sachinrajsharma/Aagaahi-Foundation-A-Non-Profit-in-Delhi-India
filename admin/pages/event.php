<?php
/** One gallery event: details, photos (upload, describe, reorder, delete). */
$pdo = db();
$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
$st = $pdo->prepare('SELECT * FROM events WHERE id = ?'); $st->execute([$id]); $ev = $st->fetch();
if (!$ev) { flash('err', 'That event does not exist.'); redirect(admin_url('gallery')); }

if (is_post()) {
    csrf_require();
    $act = (string)($_POST['action'] ?? '');
    $back = admin_url('event', ['id' => $id]);
    if ($act === 'details') {
        $title = post_str('title', 160) ?: $ev['title']; $area = post_str('area', 160) ?: $ev['area'];
        $pdo->prepare('UPDATE events SET title=?, area=?, area_intro=?, description=?, sort_order=?, is_published=? WHERE id=?')
            ->execute([$title, $area, post_str('area_intro', 400) ?: null, post_str('description', 1000) ?: null, (int)($_POST['sort_order'] ?? 0), !empty($_POST['is_published']) ? 1 : 0, $id]);
        if ($area !== $ev['area']) {
            $o = $pdo->prepare('SELECT MIN(area_order) FROM events WHERE area = ? AND id <> ?'); $o->execute([$area, $id]); $ord = $o->fetchColumn();
            if ($ord !== null && $ord !== false) $pdo->prepare('UPDATE events SET area_order = ? WHERE id = ?')->execute([(int)$ord, $id]);
        }
        log_action('event.update', 'event', $id, $title);
        flash('ok', 'Saved.');
    } elseif ($act === 'photos') {
        $files = $_FILES['photos'] ?? null; $added = 0; $failed = [];
        if ($files && is_array($files['name'])) {
            $max = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM event_photos WHERE event_id = ' . $id)->fetchColumn();
            $ins = $pdo->prepare('INSERT INTO event_photos (event_id, path, thumb_path, width, height, alt, sort_order) VALUES (?,?,?,?,?,?,?)');
            foreach (array_slice(array_keys($files['name']), 0, 30) as $i) {
                if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
                try {
                    $img = process_upload(['name' => $files['name'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]]);
                    $ins->execute([$id, $img['path'], $img['thumb_path'], $img['width'], $img['height'], post_str('alt', 300) ?: $ev['title'], ++$max]);
                    $added++;
                } catch (RuntimeException $e) { $failed[] = $files['name'][$i] . ': ' . $e->getMessage(); }
            }
        }
        log_action('event.photos_add', 'event', $id, $added . ' photos');
        flash($failed ? 'err' : 'ok', $added . ' photo' . ($added === 1 ? '' : 's') . ' added.' . ($failed ? ' Problems: ' . implode(' ', $failed) : ''));
    } elseif ($act === 'photo') {
        $pdo->prepare('UPDATE event_photos SET alt = ?, sort_order = ? WHERE id = ? AND event_id = ?')->execute([post_str('alt', 300) ?: null, (int)($_POST['sort_order'] ?? 0), (int)($_POST['photo_id'] ?? 0), $id]);
        flash('ok', 'Photo saved.');
    } elseif ($act === 'photo_delete') {
        $s = $pdo->prepare('SELECT path, thumb_path FROM event_photos WHERE id = ? AND event_id = ?'); $s->execute([(int)($_POST['photo_id'] ?? 0), $id]); $ph = $s->fetch();
        if ($ph) {
            $pdo->prepare('DELETE FROM event_photos WHERE id = ?')->execute([(int)$_POST['photo_id']]);
            delete_upload_files($ph['path'], $ph['thumb_path']);        // only files in /uploads/ are removed, never the original site photos
            log_action('event.photo_delete', 'event', $id);
            flash('ok', 'Photo deleted.');
        }
    } elseif ($act === 'delete') {
        $s = $pdo->prepare('SELECT path, thumb_path FROM event_photos WHERE event_id = ?'); $s->execute([$id]);
        $files = $s->fetchAll();
        $pdo->prepare('DELETE FROM events WHERE id = ?')->execute([$id]);     // photos cascade
        foreach ($files as $f) delete_upload_files($f['path'], $f['thumb_path']);
        log_action('event.delete', 'event', $id, $ev['title']);
        flash('ok', 'Event deleted.');
        redirect(admin_url('gallery'));
    }
    redirect($back);
}
$photos = $pdo->prepare('SELECT * FROM event_photos WHERE event_id = ? ORDER BY sort_order, id'); $photos->execute([$id]); $photos = $photos->fetchAll();
$areas = $pdo->query('SELECT DISTINCT area FROM events ORDER BY area')->fetchAll(PDO::FETCH_COLUMN);
?>
<p><a href="<?= h(admin_url('gallery')) ?>">← Gallery</a></p>
<h1><?= h($ev['title']) ?></h1>
<form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="details"><h2>Details</h2>
  <div class="grid3"><div><label for="t">Name</label><input id="t" name="title" value="<?= h($ev['title']) ?>" required maxlength="160"></div>
  <div><label for="a">Focus area</label><input id="a" name="area" value="<?= h($ev['area']) ?>" list="areas" maxlength="160"><datalist id="areas"><?php foreach ($areas as $a): ?><option value="<?= h($a) ?>"><?php endforeach; ?></datalist></div>
  <div><label for="o">Order</label><input id="o" name="sort_order" type="number" value="<?= (int)$ev['sort_order'] ?>"></div></div>
  <label for="d">Short description</label><textarea id="d" name="description" rows="2" maxlength="1000"><?= h($ev['description'] ?? '') ?></textarea>
  <label for="i">Introduction for the whole focus area <span class="muted">(optional)</span></label><input id="i" name="area_intro" value="<?= h($ev['area_intro'] ?? '') ?>" maxlength="400">
  <label class="check"><input type="checkbox" name="is_published" value="1" <?= $ev['is_published'] ? 'checked' : '' ?>> Published (visible on the website)</label>
  <button class="btn sm">Save details</button>
</form>
<form method="post" enctype="multipart/form-data" class="card"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="photos"><h2>Add photos</h2>
  <div class="grid3"><div><label for="ph">Choose up to 30 photos</label><input id="ph" type="file" name="photos[]" multiple accept="image/*" required></div>
  <div><label for="al">Description <span class="muted">(optional)</span></label><input id="al" name="alt" maxlength="300"></div><div class="end"><button class="btn">Upload</button></div></div>
  <p class="muted">Photos are shrunk and location details are removed automatically.</p>
</form>
<div class="media-grid">
  <?php if (!$photos): ?><p class="muted">No photos yet.</p><?php endif; ?>
  <?php foreach ($photos as $ph): ?>
    <div class="media-item"><img src="<?= h($ph['thumb_path']) ?>" alt="<?= h($ph['alt'] ?? '') ?>" loading="lazy">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="photo"><input type="hidden" name="photo_id" value="<?= (int)$ph['id'] ?>">
        <input name="alt" value="<?= h($ph['alt'] ?? '') ?>" placeholder="Description" maxlength="300"><div class="inline-form"><input name="sort_order" type="number" value="<?= (int)$ph['sort_order'] ?>" class="narrow" title="Order"><button class="btn sm ghost">Save</button></div></form>
      <form method="post" data-confirm="Delete this photo?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="photo_delete"><input type="hidden" name="photo_id" value="<?= (int)$ph['id'] ?>"><button class="btn sm red">Delete</button></form></div>
  <?php endforeach; ?>
</div>
<form method="post" class="card danger-zone" data-confirm="Delete this whole event and all its photos? This cannot be undone."><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="delete"><button class="btn red">Delete this event</button></form>
