<?php
/**
 * BlogForge.php — plain-PHP client for the BlogForge Engine (cURL, no Composer).
 *
 *   require 'BlogForge.php';
 *   $bf = new BlogForge('https://blog-engine.com', 'pk_...', 'sk_...');
 *   $list = $bf->listPosts();               // published posts (summaries)
 *   $post = $bf->getPost('my-slug');        // full HTML + JSON-LD
 *   echo $post['post']['content'];
 */
class BlogForge
{
    private $baseUrl, $apiKey, $apiSecret;

    public function __construct($baseUrl, $apiKey, $apiSecret)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey; $this->apiSecret = $apiSecret;
    }

    private function req($method, $path, $body = null)
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'X-API-Key: ' . $this->apiKey],
            CURLOPT_TIMEOUT        => 20,
        ]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) throw new Exception('BlogForge request failed: ' . $err);
        $data = json_decode($raw, true);
        if ($code < 200 || $code >= 300) throw new Exception($data['error'] ?? ('BlogForge HTTP ' . $code));
        return $data;
    }

    public function listPosts(array $opts = []) { return $this->req('GET', '/api/posts' . ($opts ? '?' . http_build_query($opts) : '')); }
    public function getPost($slug)              { return $this->req('GET', '/api/posts/' . rawurlencode($slug)); }
    public function upsertPost(array $post)     { return $this->req('POST', '/api/posts', $post); }
    public function categories()               { return $this->req('GET', '/api/categories'); }
    public function analytics($days = 30)       { return $this->req('GET', '/api/analytics?days=' . (int) $days); }

    public function verifyWebhook($rawBody, $signatureHeader)
    {
        $expected = hash_hmac('sha256', $rawBody, $this->apiSecret);
        if (!hash_equals($expected, (string) $signatureHeader)) throw new Exception('Invalid webhook signature.');
        return json_decode($rawBody, true);
    }
}
