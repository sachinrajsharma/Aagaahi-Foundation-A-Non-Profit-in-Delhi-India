<?php
/** Blog post editor (new + edit) with the SEO assistant. */
$pdo = db();
$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
$post = null;
if ($id) {
    $st = $pdo->prepare('SELECT * FROM posts WHERE id = ?'); $st->execute([$id]); $post = $st->fetch() ?: null;
    if (!$post) { flash('err', 'That post does not exist.'); redirect(admin_url('posts')); }
}
$errors = [];

if (is_post()) {
    csrf_require();
    $title = post_str('title', 220);
    $slug = slugify_title(post_str('slug', 190) ?: $title);
    $content = sanitize_html((string)($_POST['content'] ?? ''));
    $status = in_array($_POST['status'] ?? '', ['draft', 'scheduled', 'published'], true) ? $_POST['status'] : 'draft';
    $excerpt = post_str('excerpt', 400);
    if ($excerpt === '') $excerpt = make_excerpt($content, 180);
    $metaTitle = post_str('meta_title', 120); $metaDesc = post_str('meta_desc', 220); $kw = post_str('focus_keyword', 160);
    $cover = clean_image_ref((string)($_POST['cover_image'] ?? '')); $coverAlt = post_str('cover_alt', 300);
    $author = post_str('author_name', 160) ?: 'Aagahi Foundation';
    $noindex = !empty($_POST['noindex']) ? 1 : 0;

    // category: an existing one, or a new name typed in
    $catId = null; $newCat = post_str('new_category', 140);
    if ($newCat !== '') {
        $cs = slugify_title($newCat);
        $f = $pdo->prepare('SELECT id FROM categories WHERE slug = ?'); $f->execute([$cs]); $catId = $f->fetchColumn() ?: null;
        if (!$catId) { $pdo->prepare('INSERT INTO categories (name, slug) VALUES (?,?)')->execute([$newCat, $cs]); $catId = (int)$pdo->lastInsertId(); }
    } elseif ((int)($_POST['category_id'] ?? 0) > 0) { $catId = (int)$_POST['category_id']; }

    // FAQs: matching lists of questions and answers
    $faqs = [];
    foreach ((array)($_POST['faq_q'] ?? []) as $i => $q) {
        $q = trim((string)$q); $a = trim((string)(($_POST['faq_a'] ?? [])[$i] ?? ''));
        if ($q !== '' && $a !== '' && count($faqs) < 12) $faqs[] = ['q' => mb_substr($q, 0, 300), 'a' => mb_substr($a, 0, 1500)];
    }

    // publish date (entered in Indian time)
    $when = trim((string)($_POST['published_at'] ?? ''));
    $publishedAt = null;
    if ($when !== '') { $ts = strtotime(str_replace('T', ' ', $when)); if ($ts) $publishedAt = date('Y-m-d H:i:s', $ts); else $errors[] = 'The publish date is not valid.'; }
    if ($status === 'scheduled' && !$publishedAt) $errors[] = 'Choose the date and time when the post should go live.';
    if ($status === 'published' && !$publishedAt) $publishedAt = ($post && $post['published_at']) ? $post['published_at'] : date('Y-m-d H:i:s');

    if ($title === '') $errors[] = 'Please enter a title.';
    if (html_text($content) === '') $errors[] = 'The post has no text yet.';
    $dup = $pdo->prepare('SELECT id FROM posts WHERE slug = ? AND id <> ?'); $dup->execute([$slug, $id]);
    if ($dup->fetchColumn()) $errors[] = 'Another post already uses the address "' . $slug . '". Please change the address.';

    if (!$errors) {
        $params = [$slug, $title, $excerpt, $content, $cover ?: null, $coverAlt ?: null, $catId, $author, $metaTitle ?: null, $metaDesc ?: null, $kw ?: null,
                   $faqs ? json_encode($faqs, JSON_UNESCAPED_UNICODE) : null, $status, $publishedAt, $noindex];
        if ($post) {
            $pdo->prepare('UPDATE posts SET slug=?, title=?, excerpt=?, content=?, cover_image=?, cover_alt=?, category_id=?, author_name=?, meta_title=?, meta_desc=?, focus_keyword=?, faqs=?, status=?, published_at=?, noindex=? WHERE id=?')
                ->execute(array_merge($params, [$id]));
            if ($slug !== $post['slug']) {      // the old address keeps working
                $pdo->prepare('INSERT INTO redirects (from_slug, to_slug) VALUES (?,?) ON DUPLICATE KEY UPDATE to_slug = VALUES(to_slug)')->execute([$post['slug'], $slug]);
                $pdo->prepare('DELETE FROM redirects WHERE from_slug = ?')->execute([$slug]);
            }
            log_action('post.update', 'post', $id, $title);
        } else {
            $pdo->prepare('INSERT INTO posts (slug, title, excerpt, content, cover_image, cover_alt, category_id, author_name, meta_title, meta_desc, focus_keyword, faqs, status, published_at, noindex, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute(array_merge($params, [$me['id']]));
            $id = (int)$pdo->lastInsertId();
            $pdo->prepare('DELETE FROM redirects WHERE from_slug = ?')->execute([$slug]);
            log_action('post.create', 'post', $id, $title);
        }
        flash('ok', $status === 'published' ? 'Saved and published.' : ($status === 'scheduled' ? 'Saved. It will go live at the chosen time.' : 'Draft saved.'));
        redirect(admin_url('post', ['id' => $id]));
    }
    // validation failed: show the form again with what was typed
    $post = array_merge($post ?? [], ['title' => $title, 'slug' => $slug, 'content' => $content, 'status' => $status, 'excerpt' => $excerpt, 'meta_title' => $metaTitle,
        'meta_desc' => $metaDesc, 'focus_keyword' => $kw, 'cover_image' => $cover, 'cover_alt' => $coverAlt, 'author_name' => $author, 'noindex' => $noindex,
        'category_id' => $catId, 'faqs' => json_encode($faqs), 'published_at' => $publishedAt]);
}

$p = $post ?? ['title' => '', 'slug' => '', 'content' => '', 'status' => 'draft', 'excerpt' => '', 'meta_title' => '', 'meta_desc' => '', 'focus_keyword' => '', 'cover_image' => '',
               'cover_alt' => '', 'author_name' => 'Aagahi Foundation', 'noindex' => 0, 'category_id' => null, 'faqs' => null, 'published_at' => null];
$faqList = json_decode((string)($p['faqs'] ?? ''), true) ?: [];
if (!$faqList) $faqList = [['q' => '', 'a' => '']];
$cats = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$whenVal = !empty($p['published_at']) ? date('Y-m-d\TH:i', strtotime((string)$p['published_at'])) : '';
?>
<div class="head"><h1><?= $id ? 'Edit post' : 'New post' ?></h1>
  <?php if ($id && !empty($post['slug'])): ?><a class="btn sm ghost" href="/blog/<?= h($post['slug']) ?>/<?= ($post['status'] ?? '') === 'published' ? '' : '?preview=1' ?>" target="_blank" rel="noopener">View on website ↗</a><?php endif; ?></div>
<?php foreach ($errors as $e): ?><div class="flash err"><?= h($e) ?></div><?php endforeach; ?>

<form method="post" id="post-form" class="post-layout"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="post-main">
    <div class="card">
      <label for="title">Title</label><input id="title" name="title" value="<?= h($p['title']) ?>" maxlength="220" required class="big">
      <label for="slug">Web address <span class="muted">/blog/<b id="slug-preview"><?= h($p['slug']) ?></b>/</span></label>
      <input id="slug" name="slug" value="<?= h($p['slug']) ?>" maxlength="190" placeholder="made from the title">
      <label>Post</label>
      <div class="toolbar" id="toolbar">
        <button type="button" data-cmd="formatBlock" data-val="p" title="Paragraph">¶</button>
        <button type="button" data-cmd="formatBlock" data-val="h2" title="Heading">H2</button>
        <button type="button" data-cmd="formatBlock" data-val="h3" title="Sub-heading">H3</button>
        <button type="button" data-cmd="bold" title="Bold"><b>B</b></button>
        <button type="button" data-cmd="italic" title="Italic"><i>I</i></button>
        <button type="button" data-cmd="insertUnorderedList" title="Bullet list">• List</button>
        <button type="button" data-cmd="insertOrderedList" title="Numbered list">1. List</button>
        <button type="button" data-cmd="formatBlock" data-val="blockquote" title="Quote">“ ”</button>
        <button type="button" data-act="link" title="Link">Link</button>
        <button type="button" data-act="image" title="Insert an image">Image</button>
        <button type="button" data-act="table" title="Insert a table">Table</button>
        <button type="button" data-cmd="removeFormat" title="Clear formatting">Clear</button>
        <button type="button" data-act="source" title="Edit the HTML">&lt;/&gt;</button>
      </div>
      <div id="editor" class="editor" contenteditable="true" spellcheck="true"><?= $p['content'] /* already sanitised */ ?></div>
      <textarea id="source" class="source" hidden rows="18"></textarea>
      <textarea id="content" name="content" hidden></textarea>
    </div>

    <div class="card"><h2>Frequently asked questions <small class="muted">(shown under the post and used by Google)</small></h2>
      <div id="faqs"><?php foreach ($faqList as $f): ?>
        <div class="faq"><input name="faq_q[]" value="<?= h($f['q'] ?? '') ?>" placeholder="Question" maxlength="300"><textarea name="faq_a[]" rows="2" placeholder="Answer: 2 to 4 short sentences" maxlength="1500"><?= h($f['a'] ?? '') ?></textarea><button type="button" class="btn sm ghost" data-faq-remove>Remove</button></div>
      <?php endforeach; ?></div>
      <button type="button" class="btn sm ghost" id="faq-add">Add a question</button>
    </div>

    <div class="card"><h2>Search engine settings</h2>
      <label for="focus_keyword">Focus keyphrase <span class="muted">(what people would search for)</span></label><input id="focus_keyword" name="focus_keyword" value="<?= h($p['focus_keyword']) ?>" maxlength="160">
      <label for="meta_title">SEO title <span class="muted" id="mt-count"></span></label><input id="meta_title" name="meta_title" value="<?= h($p['meta_title']) ?>" maxlength="120">
      <label for="meta_desc">Meta description <span class="muted" id="md-count"></span></label><textarea id="meta_desc" name="meta_desc" rows="2" maxlength="220"><?= h($p['meta_desc']) ?></textarea>
      <label for="excerpt">Short summary (shown on the blog list)</label><textarea id="excerpt" name="excerpt" rows="2" maxlength="400"><?= h($p['excerpt']) ?></textarea>
      <label class="check"><input type="checkbox" name="noindex" value="1" <?= !empty($p['noindex']) ? 'checked' : '' ?>> Ask search engines not to list this post</label>
    </div>
  </div>

  <aside class="post-side">
    <div class="card"><h2>Publish</h2>
      <label for="status">Status</label>
      <select id="status" name="status"><?php foreach (['draft' => 'Draft (not public)', 'published' => 'Published', 'scheduled' => 'Scheduled'] as $k => $l): ?><option value="<?= $k ?>" <?= $p['status'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
      <label for="published_at">Publish date and time <span class="muted">(India time)</span></label><input id="published_at" name="published_at" type="datetime-local" value="<?= h($whenVal) ?>">
      <button class="btn block" type="submit">Save</button>
    </div>
    <div class="card"><h2>SEO check <span id="seo-score" class="score"></span></h2><div id="seo-list" class="seo-list muted">Type a focus keyphrase to see tips.</div><div id="read-list" class="seo-list"></div></div>
    <div class="card"><h2>Cover image</h2>
      <div id="cover-preview"><?php if ($p['cover_image']): ?><img src="<?= h($p['cover_image']) ?>" alt=""><?php endif; ?></div>
      <input type="hidden" id="cover_image" name="cover_image" value="<?= h($p['cover_image']) ?>">
      <button type="button" class="btn sm ghost" data-act="cover">Choose image</button> <button type="button" class="btn sm ghost" data-act="cover-clear">Remove</button>
      <label for="cover_alt">Describe the image <span class="muted">(for screen readers and Google)</span></label><input id="cover_alt" name="cover_alt" value="<?= h($p['cover_alt']) ?>" maxlength="300">
    </div>
    <div class="card"><h2>Details</h2>
      <label for="category_id">Category</label>
      <select id="category_id" name="category_id"><option value="">None</option><?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$p['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select>
      <label for="new_category">…or a new category</label><input id="new_category" name="new_category" maxlength="140">
      <label for="author_name">Author name</label><input id="author_name" name="author_name" value="<?= h($p['author_name']) ?>" maxlength="160">
    </div>
  </aside>
</form>

<div id="picker" class="modal" hidden><div class="modal-box">
  <div class="head"><h2>Choose an image</h2><button type="button" class="btn sm ghost" data-picker-close>Close</button></div>
  <form id="picker-upload" enctype="multipart/form-data"><input type="file" name="file" accept="image/*" required> <input name="alt" placeholder="Describe it (optional)"> <button class="btn sm">Upload new</button></form>
  <div id="picker-grid" class="media-grid"></div>
</div></div>
<script type="application/json" id="seo-config"><?= json_encode(['host' => parse_url((string)($GLOBALS['ngo']['site_url'] ?? ''), PHP_URL_HOST) ?: ($_SERVER['HTTP_HOST'] ?? ''), 'brand' => $GLOBALS['ngo']['name'] ?? '', 'csrf' => csrf_token(), 'mediaUrl' => admin_url('media')], JSON_UNESCAPED_SLASHES) ?></script>
