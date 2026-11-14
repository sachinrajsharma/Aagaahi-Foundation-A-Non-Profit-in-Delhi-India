<?php
/**
 * example-routes.php — render the BlogForge blog on your Laravel site's own
 * domain (best for SEO), plus a webhook receiver. Adjust views/namespaces.
 */

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Services\BlogForgeService;

// Blog index
Route::get('/blog', function (BlogForgeService $bf) {
    $data = $bf->listPosts(['limit' => 30]);
    return view('blog.index', ['posts' => $data['posts']]);   // loop $posts in the view
})->name('blog.index');

// Single post — inject $post['meta'] + $post['json_ld'] into your <head>,
// and render {!! $post['content'] !!} (already sanitized by the engine).
Route::get('/blog/{slug}', function (string $slug, BlogForgeService $bf) {
    try { $data = $bf->getPost($slug); }
    catch (\Throwable $e) { abort(404); }
    return view('blog.post', ['post' => $data['post']]);
})->name('blog.post');

// Webhook: revalidate a cache, notify an editor, etc.
Route::post('/blogforge/webhook', function (Request $request, BlogForgeService $bf) {
    $payload = $bf->verifyWebhook($request->getContent(), $request->header('X-BlogForge-Signature'));
    if (in_array($payload['event'] ?? '', ['post.published', 'post.updated'])) {
        \Cache::forget('blog:index');   // example: bust your cached list
        \Log::info('BlogForge', ['event' => $payload['event'], 'slug' => $payload['data']['slug'] ?? null]);
    }
    return response()->json(['ok' => true]);
})->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
