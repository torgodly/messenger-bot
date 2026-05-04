<?php

namespace MessengerBot\OAuth;

use Illuminate\Support\Facades\Http;
use MessengerBot\Http\GraphException;

class FacebookOAuthClient
{
    public function __construct(
        protected string $graphVersion,
        protected string $appId,
        protected string $appSecret,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function exchangeCodeForUserAccessToken(string $code, string $redirectUri): array
    {
        $url = "https://graph.facebook.com/{$this->graphVersion}/oauth/access_token";

        $response = Http::acceptJson()->get($url, [
            'client_id' => $this->appId,
            'redirect_uri' => $redirectUri,
            'client_secret' => $this->appSecret,
            'code' => $code,
        ]);

        return $this->decodeOrThrow($response->json(), $response->status());
    }

    /**
     * @return array<string, mixed>
     */
    public function exchangeLongLivedUserToken(string $shortLivedUserToken): array
    {
        $url = "https://graph.facebook.com/{$this->graphVersion}/oauth/access_token";

        $response = Http::acceptJson()->get($url, [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'fb_exchange_token' => $shortLivedUserToken,
        ]);

        return $this->decodeOrThrow($response->json(), $response->status());
    }

    /**
     * @return list<array{id: string, name: string, access_token: string}>
     */
    public function fetchManagedPages(string $userAccessToken): array
    {
        $url = "https://graph.facebook.com/{$this->graphVersion}/me/accounts";
        $response = Http::acceptJson()->get($url, [
            'fields' => 'id,name,access_token',
            'access_token' => $userAccessToken,
        ]);

        $data = $this->decodeOrThrow($response->json(), $response->status());
        $list = $data['data'] ?? [];
        if (! is_array($list)) {
            return [];
        }

        $out = [];
        foreach ($list as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = (string) ($row['id'] ?? '');
            $name = (string) ($row['name'] ?? '');
            $tok = (string) ($row['access_token'] ?? '');
            if ($id !== '' && $tok !== '') {
                $out[] = ['id' => $id, 'name' => $name, 'access_token' => $tok];
            }
        }

        return $out;
    }

    /**
     * @return array{expires_at: ?int, is_valid: bool}
     */
    public function debugInputToken(string $inputToken): array
    {
        $appAccessToken = $this->appId.'|'.$this->appSecret;
        $url = "https://graph.facebook.com/{$this->graphVersion}/debug_token";

        $response = Http::acceptJson()->get($url, [
            'input_token' => $inputToken,
            'access_token' => $appAccessToken,
        ]);

        $data = $this->decodeOrThrow($response->json(), $response->status());
        $inner = $data['data'] ?? [];
        if (! is_array($inner)) {
            return ['expires_at' => null, 'is_valid' => false];
        }

        $expires = $inner['expires_at'] ?? null;
        $expiresAt = null;
        if ($expires !== null && (int) $expires !== 0) {
            $expiresAt = (int) $expires;
        }

        return [
            'expires_at' => $expiresAt,
            'is_valid' => (bool) ($inner['is_valid'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>
     */
    protected function decodeOrThrow(?array $data, int $status): array
    {
        if ($data === null) {
            throw new GraphException('Invalid JSON from Facebook OAuth', $status, []);
        }
        if (isset($data['error'])) {
            $msg = is_array($data['error'])
                ? (string) ($data['error']['message'] ?? 'OAuth error')
                : 'OAuth error';

            throw new GraphException($msg, $status, $data);
        }

        return $data;
    }
}
