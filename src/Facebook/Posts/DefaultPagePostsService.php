<?php

namespace MessengerBot\Facebook\Posts;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Support\Facades\Event;
use MessengerBot\Contracts\PageAccessTokenRepository;
use MessengerBot\Events\PostsCacheHit;
use MessengerBot\Events\PostsCacheMiss;
use MessengerBot\Events\PostsSynced;
use MessengerBot\Http\GraphClient;
use MessengerBot\Kernel\Contracts\Clock;
use MessengerBot\Kernel\Contracts\ConnectionTokenRepository;
use MessengerBot\Kernel\Contracts\PostsCache;
use MessengerBot\Kernel\Contracts\SyncsFacebookPagePosts;
use MessengerBot\Kernel\Posts\Post;
use MessengerBot\Kernel\Posts\PostsCacheKey;
use MessengerBot\Kernel\Posts\PostsSyncOptions;
use MessengerBot\Kernel\Posts\PostsSyncRequest;
use MessengerBot\Kernel\Posts\PostsSyncResult;
use MessengerBot\Kernel\Tenancy\ConnectionId;
use MessengerBot\Kernel\Tenancy\TenantContextHolder;
use MessengerBot\Kernel\Tenancy\TenantResolution;

final class DefaultPagePostsService implements SyncsFacebookPagePosts
{
    public function __construct(
        protected GraphClient $graph,
        protected PostsCache $postsCache,
        protected ConnectionTokenRepository $connectionTokens,
        protected TenantContextHolder $tenantContext,
        protected Clock $clock,
        protected CacheFactory $cacheFactory,
        protected PageAccessTokenRepository $legacyTokens,
    ) {}

    public function sync(PostsSyncRequest $request, ?PostsSyncOptions $options = null): PostsSyncResult
    {
        if ($options === null) {
            $options = new PostsSyncOptions(
                limitPerRequest: (int) config('messenger-bot.posts.default_limit_per_request', 25),
                maxApiCalls: (int) config('messenger-bot.posts.default_max_api_calls', 50),
                cacheTtlSeconds: (int) config('messenger-bot.posts.default_cache_ttl_seconds', 300),
            );
        }

        $record = $this->connectionTokens->getByConnectionId($request->connectionId);
        if ($record !== null) {
            if ($record->pageId !== $request->pageId) {
                throw new \InvalidArgumentException('PostsSyncRequest pageId does not match connection token pageId.');
            }
            $resolution = new TenantResolution($record->tenantId, $record->connectionId, $record->pageId);

            return $this->tenantContext->run($resolution, fn (): PostsSyncResult => $this->syncWithContext($request, $options));
        }

        if (! (bool) config('messenger-bot.tenancy.enabled', false)) {
            $token = $this->legacyTokens->getToken();
            if ($token === null || $token === '') {
                $token = trim((string) config('messenger-bot.page_access_token', ''));
            }
            if ($token === '') {
                throw new \RuntimeException('No Page access token available. Connect OAuth, set MESSENGER_BOT_PAGE_ACCESS_TOKEN, or store a connection token.');
            }

            $legacyPageId = $this->legacyTokens->getPageId();
            if ($legacyPageId !== null && $legacyPageId !== '' && $legacyPageId !== $request->pageId) {
                throw new \InvalidArgumentException('PostsSyncRequest pageId does not match the cached legacy Page ID.');
            }

            return $this->tenantContext->run(null, fn (): PostsSyncResult => $this->syncWithContext($request, $options));
        }

        throw new \RuntimeException('No Page access token stored for this connection.');
    }

    protected function syncWithContext(PostsSyncRequest $request, PostsSyncOptions $options): PostsSyncResult
    {
        $since = $request->since ?? $this->defaultSinceUtc();
        $until = $request->until ?? $this->clock->nowUtc();
        $sinceUnix = $since->getTimestamp();
        $untilUnix = $until->getTimestamp();

        $fields = $request->fields ?? ['id', 'message', 'created_time', 'permalink_url'];
        $fieldsString = implode(',', $fields);
        $version = $this->connectionTokens->getPostsCacheVersion($request->connectionId);
        $filterHash = PostsCacheKey::filterHash($sinceUnix, $untilUnix, $fields, $options->limitPerRequest);
        $cacheKey = PostsCacheKey::build(
            $request->tenantId,
            $request->connectionId,
            $request->pageId,
            $filterHash,
            $version,
        );

        if (! $options->bypassCache) {
            $cached = $this->postsCache->get($cacheKey);
            if ($cached !== null) {
                Event::dispatch(new PostsCacheHit(
                    $request->tenantId->value,
                    $request->connectionId->value,
                    $request->pageId,
                    $cacheKey,
                ));

                $items = $this->deserializePosts($cached);

                return new PostsSyncResult(
                    $items,
                    false,
                    0,
                    true,
                    $this->clock->nowUtc(),
                    null,
                );
            }

            Event::dispatch(new PostsCacheMiss(
                $request->tenantId->value,
                $request->connectionId->value,
                $request->pageId,
                $cacheKey,
            ));
        }

        $lock = null;
        $lockKey = $cacheKey.':lock';
        if ($options->useStampedeLock && ! $options->bypassCache) {
            $lock = $this->cacheFactory->lock($lockKey, 15);
            $lock->block(5);
        }

        try {
            if (! $options->bypassCache) {
                $cachedAfterLock = $this->postsCache->get($cacheKey);
                if ($cachedAfterLock !== null) {
                    Event::dispatch(new PostsCacheHit(
                        $request->tenantId->value,
                        $request->connectionId->value,
                        $request->pageId,
                        $cacheKey,
                    ));

                    $items = $this->deserializePosts($cachedAfterLock);

                    return new PostsSyncResult(
                        $items,
                        false,
                        0,
                        true,
                        $this->clock->nowUtc(),
                        null,
                    );
                }
            }

            [$items, $truncated, $apiCalls, $nextUrl] = $this->fetchFromGraph(
                $request->pageId,
                $sinceUnix,
                $untilUnix,
                $fieldsString,
                $options,
                $request->maxPosts,
            );

            $serialized = array_map(fn (Post $p): array => $this->serializePost($p), $items);

            if (! $options->bypassCache) {
                $this->postsCache->put($cacheKey, $serialized, $options->cacheTtlSeconds);
            }

            $result = new PostsSyncResult(
                $items,
                $truncated,
                $apiCalls,
                false,
                $this->clock->nowUtc(),
                $nextUrl,
            );

            Event::dispatch(new PostsSynced(
                $request->tenantId->value,
                $request->connectionId->value,
                $request->pageId,
                count($items),
                $truncated,
                $apiCalls,
                false,
            ));

            return $result;
        } finally {
            if ($lock !== null) {
                $lock->release();
            }
        }
    }

    public function bumpPostsCacheVersion(ConnectionId $connectionId): int
    {
        return $this->connectionTokens->bumpPostsCacheVersion($connectionId);
    }

    /**
     * @return array{0: list<Post>, 1: bool, 2: int, 3: ?string}
     */
    protected function fetchFromGraph(
        string $pageId,
        int $sinceUnix,
        int $untilUnix,
        string $fieldsString,
        PostsSyncOptions $options,
        int $maxPosts,
    ): array {
        $items = [];
        $apiCalls = 0;
        $startedNs = $options->maxDurationMs > 0 ? hrtime(true) : null;

        $query = [
            'since' => $sinceUnix,
            'until' => $untilUnix,
            'fields' => $fieldsString,
            'limit' => $options->limitPerRequest,
        ];

        $data = $this->graph->get($pageId.'/posts', $query);
        $apiCalls++;

        $this->appendData($data, $items, $maxPosts);

        while (count($items) < $maxPosts && $apiCalls < $options->maxApiCalls) {
            if ($startedNs !== null && (hrtime(true) - $startedNs) / 1e6 >= $options->maxDurationMs) {
                break;
            }

            $next = isset($data['paging']['next']) && is_string($data['paging']['next']) ? $data['paging']['next'] : null;
            if ($next === null || $next === '') {
                break;
            }

            $data = $this->graph->getFromFullUrl($next);
            $apiCalls++;
            $this->appendData($data, $items, $maxPosts);
        }

        $hasNext = isset($data['paging']['next']) && is_string($data['paging']['next']) && $data['paging']['next'] !== '';
        $truncated = $hasNext || count($items) >= $maxPosts;

        $nextUrl = $hasNext ? (string) $data['paging']['next'] : null;

        return [$items, $truncated, $apiCalls, $nextUrl];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<Post>  $items
     */
    protected function appendData(array $data, array &$items, int $maxPosts): void
    {
        $rows = $data['data'] ?? [];
        if (! is_array($rows)) {
            return;
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $post = $this->mapRow($row);
            if ($post !== null) {
                $items[] = $post;
            }
            if (count($items) >= $maxPosts) {
                break;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function mapRow(array $row): ?Post
    {
        $id = isset($row['id']) ? (string) $row['id'] : '';
        if ($id === '') {
            return null;
        }

        return new Post(
            $id,
            isset($row['message']) ? (string) $row['message'] : null,
            isset($row['created_time']) ? (string) $row['created_time'] : null,
            isset($row['permalink_url']) ? (string) $row['permalink_url'] : null,
            $row,
        );
    }

    protected function defaultSinceUtc(): \DateTimeImmutable
    {
        $now = $this->clock->nowUtc();

        return $now->modify('first day of this month')->setTime(0, 0, 0);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializePost(Post $post): array
    {
        return [
            'id' => $post->id,
            'message' => $post->message,
            'created_time' => $post->createdTime,
            'permalink_url' => $post->permalinkUrl,
            'raw' => $post->raw,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<Post>
     */
    protected function deserializePosts(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = (string) ($row['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $out[] = new Post(
                $id,
                isset($row['message']) ? (string) $row['message'] : null,
                isset($row['created_time']) ? (string) $row['created_time'] : null,
                isset($row['permalink_url']) ? (string) $row['permalink_url'] : null,
                isset($row['raw']) && is_array($row['raw']) ? $row['raw'] : [],
            );
        }

        return $out;
    }
}
