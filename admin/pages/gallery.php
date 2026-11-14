<?php
/** Gallery: events grouped by focus area. */
$pdo = db();
if (is_post()) {
    csrf_require();
    $title = post_str('title', 160); $area = post_str('area', 160) ?: 'General';
    if ($title === '') { flash('err', 'Please enter a name for the event.'); redirect(admin_url('gallery')); }
    $base = slugify_title($title); $slug = $base; $n = 2;
    $chk = $pdo->prepare('SELECT 1 FROM events WHERE slug = ?');
    for (;;) { $chk->execute([$slug]); if (!$chk->fetchColumn()) break; $slug = $base . '-' . $n++; }
    $ao = $pdo->prepare('SELECT MIN(area_order) FROM events WHERE area = ?'); $ao->execute([$area]); $order = $ao->fetchColumn();
    if ($order === null || $order === false) $order = 1 + (int)$pdo->query('SELECT COALESCE(MAX(area_order), 0) FROM events')->fetchColumn();
    $pdo->prepare('INSERT INTO events (area, area_order, title, slug, description, is_published) VALUES (?,?,?,?,?,1)')->execute([$area, (int)$order, $title, $slug, post_str('description', 1000) ?: null]);
    $eid = (int)$pdo->lastInsertId();
    log_action('event.create', 'event', $eid, $title);
    redirect(admin_url('event', ['id' => $eid]));
}
$events = $pdo->query('SELECT e.id, e.area, e.title, e.is_published, (SELECT COUNT(*) FROM event_photos p WHERE p.event_id = e.id) AS photos,
    (SELECT COALESCE(thumb_path, path) FROM event_photos p WHERE p.event_id = e.id ORDER BY p.sort_order, p.id LIMIT 1) AS cover
    FROM events e ORDER BY e.area_order, e.area, e.sort_order, e.id')->fetchAll();
$areas = [];
foreach ($events as $e) $areas[$e['area']][] = $e;
?>
<h1>Gallery</h1>
<p class="muted">Past events and programmes, grouped by focus area. What is published here appears in "Our work, in pictures" on the home page.</p>
<form method="post" class="card"><?= csrf_field() ?><h2>Add an event</h2>
  <div class="grid3"><div><label for="t">Event or programme name</label><input id="t" name="title" required maxlength="160"></div>
  <div><label for="a">Focus area</label><input id="a" name="area" list="areas" maxlength="160" placeholder="e.g. Healthy Hygiene &amp; Wellbeing"><datalist id="areas"><?php foreach (array_keys($areas) as $a): ?><option value="<?= h($a) ?>"><?php endforeach; ?></datalist></div>
  <div class="end"><button class="btn">Create, then add photos</button></div></div>
  <label for="d">Short description</label><textarea id="d" name="description" rows="2" maxlength="1000"></textarea>
</form>
<?php foreach ($areas as $name => $list): ?>
  <h2 class="area-h"><?= h($name) ?></h2>
  <div class="media-grid">
    <?php foreach ($list as $e): ?>
      <a class="media-item" href="<?= h(admin_url('event', ['id' => $e['id']])) ?>"><?php if ($e['cover']): ?><img src="<?= h($e['cover']) ?>" alt="" loading="lazy"><?php else: ?><div class="noimg">No photos yet</div><?php endif; ?>
        <b><?= h($e['title']) ?></b><small class="muted"><?= (int)$e['photos'] ?> photos · <?= $e['is_published'] ? 'published' : 'hidden' ?></small></a>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>
<?php if (!$events): ?><p class="muted">No events yet. Add the first one above.</p><?php endif; ?>
