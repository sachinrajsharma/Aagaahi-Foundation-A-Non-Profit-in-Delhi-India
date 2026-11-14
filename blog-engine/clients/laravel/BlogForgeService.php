<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * BlogForgeService — Laravel wrapper for the BlogForge Engine.
 *
 * config/services.php:
 *   'blogforge' => [
 *       'base_url' => env('BLOGFORGE_URL', 'http://localhost:4200'),
 *       'api_key'  => env('BLOGFORGE_KEY'),
 *       'secret'   => env('BLOGFORGE_SECRET'),
 *   ],
 */
class BlogForgeService
{
    protected string $baseUrl, $apiKey, $secret;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.blogforge.base_url'), '/');
        $this->apiKey  = (string) config('services.blogforge.api_key');
        $this->secret  = (string) config('services.blogforge.secret');
    }

    protected function client()
    {
        return Http::withHeaders(['X-API-Key' => $this->apiKey])->acceptJson()->timeout(20);
    }

    public function listPosts(array $opts = []): array
    {
        return $this->client()->get($this->baseUrl . '/api/posts', $opts)->throw()->json();
    }

    public function getPost(string $slug): array
    {
        return $this->client()->get($this->baseUrl . '/api/posts/' . urlencode($slug))->throw()->json();
    }

    public function analytics(int $days = 30): array
    {
        return $this->client()->get($this->baseUrl . '/api/analytics', ['days' => $days])->throw()->json();
    }

    public function verifyWebhook(string $rawBody, ?string $signature): array
    {
        $expected = hash_hmac('sha256', $rawBody, $this->secret);
        if (! hash_equals($expected, (string) $signature)) abort(400, 'Invalid webhook signature.');
        return json_decode($rawBody, true) ?? [];
    }
}
