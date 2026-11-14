<?php
/** Blog posts: list, search, delete. */
$pdo = db();
if (is_post()) {
    csrf_require();
    if (($_POST['action'] ?? '') === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $t = $pdo->prepare('SELECT title FROM posts WHERE id = ?'); $t->execute([$id]); $title = (string)$t->fetchColumn();
        $pdo->prepare('DELETE FROM posts WHERE id = ?')->execute([$id]);
        log_action('post.delete', 'post', $id, $title);
        flash('ok', 'Post deleted.');
    }
    redirect(admin_url('posts'));
}
$status = in_array($_GET['status'] ?? '', ['draft', 'scheduled', 'published'], true) ? $_GET['status'] : '';
$q = trim((string)($_GET['q'] ?? ''));
$where = []; $args = [];
if ($status !== '') { $where[] = 'p.status = ?'; $args[] = $status; }
if ($q !== '') { $where[] = '(p.title LIKE ? OR p.slug LIKE ?)'; $like = '%' . addcslashes($q, '%_\\') . '%'; array_push($args, $like, $like); }
$st = $pdo->prepare('SELECT p.id, p.title, p.slug, p.status, p.published_at, p.updated_at, c.name AS category FROM posts p LEFT JOIN categories c ON c.id = p.category_id'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY p.updated_at DESC LIMIT 200');
$st->execute($args);
$rows = $st->fetchAll();
?>
<div class="head"><h1>Blog posts</h1><a class="btn" href="<?= h(admin_url('post')) ?>">Write a new post</a></div>
<form method="get" class="filters"><input type="hidden" name="p" value="posts">
  <select name="status"><option value="">All</option><?php foreach (['published', 'scheduled', 'draft'] as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select>
  <input type="search" name="q" value="<?= h($q) ?>" placeholder="Search title or address"><button class="btn sm">Filter</button>
</form>
<div class="card"><table class="tbl"><thead><tr><th>Title</th><th>Category</th><th>Status</th><th>Published</th><th>Updated</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $p): ?>
  <tr>
    <td><a href="<?= h(admin_url('post', ['id' => $p['id']])) ?>"><b><?= h($p['title']) ?></b></a><br><small class="muted">/blog/<?= h($p['slug']) ?>/</small></td>
    <td><?= h($p['category'] ?? '') ?></td><td><?= status_badge($p['status']) ?></td><td><?= h(fmt_dt($p['published_at'])) ?></td><td><?= h(fmt_dt($p['updated_at'])) ?></td>
    <td class="actions"><a class="btn sm ghost" href="<?= h(admin_url('post', ['id' => $p['id']])) ?>">Edit</a>
      <a class="btn sm ghost" href="/blog/<?= h($p['slug']) ?>/<?= $p['status'] === 'published' ? '' : '?preview=1' ?>" target="_blank" rel="noopener">View</a>
      <form method="post" data-confirm="Delete this post for ever?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn sm red">Delete</button></form></td>
  </tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="6" class="muted">No posts found.</td></tr><?php endif; ?>
</tbody></table></div>
