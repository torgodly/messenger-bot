<?php

namespace MessengerBot\Http;

use Illuminate\Support\Facades\Http;

class GraphClient
{
    public function __construct(
        protected string $graphVersion,
        protected PageAccessTokenProvider $pageAccessToken,
        protected string $appSecret,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->request('get', $path, $query, null);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    public function post(string $path, array $query = [], array $json = []): array
    {
        return $this->request('post', $path, $query, $json);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function delete(string $path, array $query = []): array
    {
        return $this->request('delete', $path, $query, null);
    }

    /**
     * @return array<string, mixed>
     */
    public function replyToPublicComment(string $commentId, string $message): array
    {
        return $this->post("{$commentId}/comments", [], ['message' => $message]);
    }

    /**
     * @return array<string, mixed>
     */
    public function privateReplyToCommentEdge(string $commentId, string $message): array
    {
        return $this->post("{$commentId}/private_replies", [], ['message' => $message]);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $json
     * @return array<string, mixed>
     */
    protected function request(string $method, string $path, array $query, ?array $json): array
    {
        $path = ltrim($path, '/');
        $base = "https://graph.facebook.com/{$this->graphVersion}/{$path}";

        $token = $this->pageAccessToken->token();
        $query['access_token'] = $token;
        if ($this->appSecret !== '') {
            $query['appsecret_proof'] = hash_hmac('sha256', $token, $this->appSecret);
        }

        $url = $base.'?'.http_build_query($query);

        $pending = Http::acceptJson();
        if ($json !== null) {
            $pending = $pending->asJson();
        }

        $response = match ($method) {
            'get' => $pending->get($url),
            'post' => $json === null || $json === []
                ? $pending->post($url)
                : $pending->post($url, $json),
            'delete' => $pending->delete($url),
            default => throw new \InvalidArgumentException("Unsupported method {$method}"),
        };

        $data = $response->json() ?? [];
        if (! $response->successful() || isset($data['error'])) {
            $msg = is_array($data['error'] ?? null)
                ? (string) ($data['error']['message'] ?? 'Graph error')
                : 'Graph error';

            throw new GraphException($msg, $response->status(), is_array($data) ? $data : []);
        }

        return is_array($data) ? $data : [];
    }
}
