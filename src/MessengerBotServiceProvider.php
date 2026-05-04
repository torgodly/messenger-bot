<?php

namespace MessengerBot;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use MessengerBot\Contracts\ConversationStore;
use MessengerBot\Contracts\PageAccessTokenRepository;
use MessengerBot\Conversation\ArrayConversationStore;
use MessengerBot\Conversation\CacheConversationStore;
use MessengerBot\Dispatching\HandlerDispatcher;
use MessengerBot\Http\Controllers\FacebookOAuthController;
use MessengerBot\Http\Controllers\WebhookController;
use MessengerBot\Http\GraphClient;
use MessengerBot\Http\MessengerClient;
use MessengerBot\Http\PageAccessTokenProvider;
use MessengerBot\OAuth\FacebookOAuthClient;
use MessengerBot\Profile\PageAccessTokenHealthCheck;
use MessengerBot\Profile\PageProfileCoordinator;
use MessengerBot\Profile\PageWebhookSubscriber;
use MessengerBot\Profile\PersistentMenuConfigurator;
use MessengerBot\Routing\MessageRouter;
use MessengerBot\Support\CachedPageAccessTokenRepository;
use MessengerBot\Webhook\EntryIterator;
use MessengerBot\Webhook\FeedChangeParser;
use MessengerBot\Webhook\MessagingParser;
use MessengerBot\Webhook\SignatureValidator;
use MessengerBot\Webhook\WebhookProcessor;
use MessengerBot\Webhook\WebhookVerifier;

class MessengerBotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/messenger-bot.php', 'messenger-bot');

        $this->app->singleton(WebhookVerifier::class);
        $this->app->singleton(SignatureValidator::class);

        $this->app->singleton(PageAccessTokenRepository::class, function () {
            $store = config('messenger-bot.page_token.cache_store');

            return new CachedPageAccessTokenRepository(
                (string) config('messenger-bot.page_token.cache_key'),
                is_string($store) && $store !== '' ? $store : null,
            );
        });

        $this->app->singleton(PageAccessTokenProvider::class, function ($app) {
            return new PageAccessTokenProvider($app->make(PageAccessTokenRepository::class));
        });

        $this->app->singleton(GraphClient::class, function ($app) {
            return new GraphClient(
                (string) config('messenger-bot.graph_version'),
                $app->make(PageAccessTokenProvider::class),
                (string) config('messenger-bot.app_secret'),
            );
        });

        $this->app->bind(FacebookOAuthClient::class, function () {
            return new FacebookOAuthClient(
                (string) config('messenger-bot.graph_version'),
                (string) config('messenger-bot.app_id', ''),
                (string) config('messenger-bot.app_secret', ''),
            );
        });

        $this->app->singleton(MessengerClient::class, function ($app) {
            return new MessengerClient($app->make(GraphClient::class));
        });

        $this->app->singleton(PageWebhookSubscriber::class);
        $this->app->singleton(PersistentMenuConfigurator::class);
        $this->app->singleton(PageAccessTokenHealthCheck::class);
        $this->app->singleton(PageProfileCoordinator::class);

        $this->app->singleton(MessengerBotManager::class, function ($app) {
            return new MessengerBotManager;
        });

        $this->app->singleton(MessageRouter::class, function ($app) {
            return new MessageRouter($app->make(MessengerBotManager::class));
        });

        $this->app->singleton(HandlerDispatcher::class);

        $this->app->singleton(MessagingParser::class);
        $this->app->singleton(FeedChangeParser::class);
        $this->app->singleton(EntryIterator::class);

        $this->app->singleton(WebhookProcessor::class, function ($app) {
            return new WebhookProcessor(
                $app->make(MessengerBotManager::class),
                $app->make(MessageRouter::class),
                $app->make(HandlerDispatcher::class),
                $app->make(MessagingParser::class),
                $app->make(FeedChangeParser::class),
                $app->make(EntryIterator::class),
                $app,
            );
        });

        $this->app->bind(ConversationStore::class, function ($app) {
            $driver = (string) config('messenger-bot.conversation.driver', 'cache');

            return match ($driver) {
                'array' => new ArrayConversationStore,
                'cache' => new CacheConversationStore(
                    Cache::store(config('messenger-bot.conversation.cache_store')),
                    (string) config('messenger-bot.conversation.cache_prefix', 'messenger_bot:conv:'),
                    (int) config('messenger-bot.conversation.ttl_minutes', 120),
                ),
                default => $app->make($driver),
            };
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/messenger-bot.php' => config_path('messenger-bot.php'),
        ], 'messenger-bot-config');

        if (config('messenger-bot.webhook.auto_register', true)) {
            $this->registerWebhookRouteOutsideWebGroup();
        }

        if (config('messenger-bot.oauth.auto_register', true)) {
            $this->registerOAuthRoutesOutsideWebGroup();
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\ClearMessengerPageTokenCommand::class,
                Console\InstallMessengerBotCommand::class,
                Console\SyncMessengerPageCommand::class,
                Console\MessengerBotTokenStatusCommand::class,
            ]);
        }
    }

    /**
     * Register GET|POST webhook without routes/web.php's "web" middleware (avoids CSRF / HTTP 419 on Meta POST).
     */
    protected function registerWebhookRouteOutsideWebGroup(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $path = (string) config('messenger-bot.webhook.path', '/webhook/messenger');
        $stack = (array) config('messenger-bot.webhook.middleware', []);

        Route::match(['get', 'post'], $path, [WebhookController::class, 'handle'])
            ->middleware($stack);
    }

    /**
     * Facebook OAuth (GET only) outside the web middleware group to avoid CSRF on callback.
     */
    protected function registerOAuthRoutesOutsideWebGroup(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $prefix = trim((string) config('messenger-bot.oauth.path_prefix', 'messenger-bot/oauth'), '/');
        $throttleRedirect = trim((string) config('messenger-bot.oauth.throttle_redirect', '20,1'));
        $throttleCallback = trim((string) config('messenger-bot.oauth.throttle_callback', '30,1'));

        Route::get($prefix.'/facebook', [FacebookOAuthController::class, 'redirectToFacebook'])
            ->middleware($throttleRedirect !== '' ? ['throttle:'.$throttleRedirect] : [])
            ->name('messenger-bot.oauth.redirect');
        Route::get($prefix.'/facebook/callback', [FacebookOAuthController::class, 'callback'])
            ->middleware($throttleCallback !== '' ? ['throttle:'.$throttleCallback] : [])
            ->name('messenger-bot.oauth.callback');
    }
}
