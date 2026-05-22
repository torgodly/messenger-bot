<?php

namespace MessengerBot\OAuth;

use MessengerBot\Contracts\PageAccessTokenRepository;
use MessengerBot\Contracts\ValidatesMessengerPageLink;
use MessengerBot\Exceptions\PageLinkRejectedException;
use MessengerBot\Kernel\Contracts\ConnectionTokenRepository;
use MessengerBot\Support\GraphContainerReset;

/**
 * Validates (when multi-tenant), stores Page token, and resets Graph container bindings.
 */
final class CompleteOAuthPageLink
{
    public function __construct(
        protected FacebookOAuthClient $oauth,
        protected ConnectionTokenRepository $connectionTokens,
        protected PageAccessTokenRepository $pageTokens,
    ) {}

    /**
     * @param  array{id: string, name: string, access_token: string}  $page
     * @param  array{tenant_id: string, connection_id: string}|null  $mt
     */
    public function complete(array $page, ?array $mt = null): void
    {
        $pageId = trim((string) ($page['id'] ?? ''));
        $pageToken = trim((string) ($page['access_token'] ?? ''));
        if ($pageId === '' || $pageToken === '') {
            throw new \InvalidArgumentException('Page id and access_token are required.');
        }

        $mt = $this->normalizeMt($mt);
        if ($mt !== null && (bool) config('messenger-bot.tenancy.enabled', false)) {
            $this->assertMayLink($page, $mt);
        }

        $debug = $this->oauth->debugInputToken($pageToken);
        $expiresAt = $debug['expires_at'];

        $dualWriteLegacy = (bool) config('messenger-bot.oauth.dual_write_legacy_token', true);
        $wroteConnection = false;

        if ($mt !== null) {
            $this->connectionTokens->put([
                'access_token' => $pageToken,
                'expires_at' => $expiresAt,
                'page_id' => $pageId,
                'tenant_id' => $mt['tenant_id'],
                'connection_id' => $mt['connection_id'],
            ]);
            $wroteConnection = true;
        }

        if (! $wroteConnection || $dualWriteLegacy) {
            $this->pageTokens->put([
                'access_token' => $pageToken,
                'expires_at' => $expiresAt,
                'page_id' => $pageId,
            ]);
        }

        GraphContainerReset::forget(app());
    }

    /**
     * @param  array{id: string, name: string, access_token: string}  $page
     * @param  array{tenant_id: string, connection_id: string}  $mt
     */
    protected function assertMayLink(array $page, array $mt): void
    {
        $class = trim((string) config('messenger-bot.oauth.validates_page_link', ''));
        if ($class === '' || ! class_exists($class)) {
            throw new \RuntimeException('MESSENGER_BOT_VALIDATES_PAGE_LINK is not configured.');
        }

        $validator = app($class);
        if (! $validator instanceof ValidatesMessengerPageLink) {
            throw new \RuntimeException('MESSENGER_BOT_VALIDATES_PAGE_LINK must implement '.ValidatesMessengerPageLink::class.'.');
        }

        try {
            $validator->assertMayLinkPage($page, $mt);
        } catch (PageLinkRejectedException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new PageLinkRejectedException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @param  array<string, mixed>|null  $mt
     * @return array{tenant_id: string, connection_id: string}|null
     */
    protected function normalizeMt(?array $mt): ?array
    {
        if ($mt === null) {
            return null;
        }

        $tenantId = trim((string) ($mt['tenant_id'] ?? ''));
        $connectionId = trim((string) ($mt['connection_id'] ?? ''));
        if ($tenantId === '' || $connectionId === '') {
            return null;
        }

        return [
            'tenant_id' => $tenantId,
            'connection_id' => $connectionId,
        ];
    }
}
