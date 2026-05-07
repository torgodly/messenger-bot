<?php

namespace MessengerBot\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use MessengerBot\Contracts\PageAccessTokenSource;
use MessengerBot\Profile\PageProfileCoordinator;
use MessengerBot\Support\GraphContainerReset;
use MessengerBot\Support\MessengerBotEnvWriter;

class InstallMessengerBotCommand extends Command
{
    protected $signature = 'messenger-bot:install
                            {--force : Overwrite published messenger-bot config if it already exists}
                            {--tenant : Write multi-tenant .env keys (uses connection_model resolver; no custom resolver class required)}
                            {--model= : Eloquent model FQCN implementing MessengerConnectable (sets MESSENGER_BOT_TENANCY_CONNECTION_MODEL)}
                            {--skip-subscribe : Do not POST /me/subscribed_apps}
                            {--skip-menu : Do not POST persistent_menu to messenger_profile}
                            {--skip-token-check : Skip Graph token validation (GET /me) before subscribe/menu}';

    protected $description = 'Publish config, ensure .env keys, subscribe Page webhook fields, sync persistent menu, and print Meta checklist';

    public function handle(): int
    {
        $this->call('vendor:publish', [
            '--tag' => 'messenger-bot-config',
            '--force' => (bool) $this->option('force'),
        ]);

        $this->newLine();

        $basePath = (string) $this->laravel->basePath();
        $envPath = $basePath.DIRECTORY_SEPARATOR.'.env';
        $examplePath = $basePath.DIRECTORY_SEPARATOR.'.env.example';

        if (! is_file($envPath)) {
            if (is_file($examplePath)) {
                if (! @copy($examplePath, $envPath)) {
                    $this->error('Could not create .env from .env.example. Copy it manually, then re-run this command.');

                    return self::FAILURE;
                }
                $this->info('Created .env from .env.example.');
            } else {
                if (file_put_contents($envPath, '') === false) {
                    $this->error('Could not create .env in the application root.');

                    return self::FAILURE;
                }
                $this->warn('Created an empty .env (no .env.example found).');
            }
        }

        $writer = MessengerBotEnvWriter::forApplicationBasePath($basePath);
        $writer->appendMessengerStarterIfMissing();

        $verify = $writer->get('MESSENGER_BOT_VERIFY_TOKEN');
        if ($verify === null || trim($verify) === '') {
            $defaultVerify = Str::random(32);
            if ($this->input->isInteractive()) {
                $entered = (string) $this->ask('Webhook verify token (Meta → your Page → Webhooks)', $defaultVerify);
                $verify = trim($entered) === '' ? $defaultVerify : trim($entered);
            } else {
                $verify = $defaultVerify;
                $this->line('Generated MESSENGER_BOT_VERIFY_TOKEN for non-interactive install.');
            }
            $writer->put('MESSENGER_BOT_VERIFY_TOKEN', $verify);
        }

        if ($this->ensureMetaAppCredentials($writer) !== self::SUCCESS) {
            return self::FAILURE;
        }

        if ((bool) $this->option('tenant')) {
            $this->applyTenantEnvDefaults($writer);
        }

        $this->applyMessengerConfigFromEnv($writer);

        $pageTokenProvider = $this->laravel->make(PageAccessTokenSource::class);
        if (trim($pageTokenProvider->token()) === '') {
            $connect = $this->oauthConnectUrl();
            if (! $this->input->isInteractive()) {
                $this->error('No Page access token yet. Open this URL in a browser to connect Facebook, then run install again:');
                $this->line($connect);
                $this->printChecklist();

                return self::FAILURE;
            }
            $this->warn('No Page access token in cache (and no optional MESSENGER_BOT_PAGE_ACCESS_TOKEN in .env).');
            $this->line('Open in a browser: '.$connect);
            if (! $this->confirm('Have you finished the Facebook login and returned to this app?', false)) {
                $this->printChecklist();

                return self::FAILURE;
            }
            GraphContainerReset::forget($this->laravel);
            $pageTokenProvider = $this->laravel->make(PageAccessTokenSource::class);
            if (trim($pageTokenProvider->token()) === '') {
                $this->error('Token still missing. Complete OAuth using the URL above, then run: php artisan messenger-bot:install');
                $this->printChecklist();

                return self::FAILURE;
            }
        }

        GraphContainerReset::forget($this->laravel);

        if (
            (bool) config('messenger-bot.webhook.signature_enabled', true)
            && trim((string) config('messenger-bot.app_secret', '')) === ''
        ) {
            $this->warn('MESSENGER_BOT_APP_SECRET is empty while signature verification is enabled — webhooks skip signature check until you set the App Secret.');
        }

        $coordinator = $this->laravel->make(PageProfileCoordinator::class);

        $exit = $coordinator->runForConsole(
            $this,
            ! $this->option('skip-subscribe'),
            ! $this->option('skip-menu'),
            (bool) $this->option('skip-token-check'),
        );

        if ($exit !== self::SUCCESS) {
            return $exit;
        }

        $this->newLine();
        $this->printChecklist();

        return self::SUCCESS;
    }

    protected function applyTenantEnvDefaults(MessengerBotEnvWriter $writer): void
    {
        $writer->put('MESSENGER_BOT_TENANCY_ENABLED', 'true');
        if (! $writer->hasLine('MESSENGER_BOT_TENANCY_FALLBACK_LEGACY')) {
            $writer->put('MESSENGER_BOT_TENANCY_FALLBACK_LEGACY', 'true');
        }

        $model = trim((string) $this->option('model'));
        if ($model === '' && $this->input->isInteractive()) {
            $model = trim((string) $this->ask(
                'Eloquent model class for Facebook Page rows (must implement MessengerConnectable; leave empty to set later in .env)',
                ''
            ));
        }

        if ($model !== '') {
            $writer->put('MESSENGER_BOT_TENANCY_CONNECTION_MODEL', $model);
            $this->info('Set MESSENGER_BOT_TENANCY_CONNECTION_MODEL='.$model);
        } else {
            $this->warn('Tenancy enabled. Add MESSENGER_BOT_TENANCY_CONNECTION_MODEL=Your\\Model to .env (or pass --model= on install). Custom MESSENGER_BOT_TENANCY_RESOLVER still overrides the default lookup.');
        }
    }

    /**
     * Prompt for Meta App ID and App Secret when .env lines are empty (before OAuth URL / Graph steps).
     */
    protected function ensureMetaAppCredentials(MessengerBotEnvWriter $writer): int
    {
        $trimmed = fn (string $key): string => trim((string) ($writer->get($key) ?? ''));

        if ($trimmed('MESSENGER_BOT_APP_ID') === '') {
            if (! $this->input->isInteractive()) {
                $this->error('MESSENGER_BOT_APP_ID is empty. Set it in .env or run this command interactively.');

                return self::FAILURE;
            }
            $id = trim((string) $this->ask('Facebook App ID (Meta → App settings → Basic)'));
            if ($id === '') {
                $this->error('App ID is required for OAuth and Graph.');

                return self::FAILURE;
            }
            $writer->put('MESSENGER_BOT_APP_ID', $id);
        }

        if ($trimmed('MESSENGER_BOT_APP_SECRET') === '') {
            if (! $this->input->isInteractive()) {
                $this->error('MESSENGER_BOT_APP_SECRET is empty. Set it in .env or run this command interactively.');

                return self::FAILURE;
            }
            $secret = (string) $this->secret('Facebook App Secret (Meta → App settings → Basic)');
            if (trim($secret) === '') {
                $this->error('App Secret is required for OAuth and webhook signature verification.');

                return self::FAILURE;
            }
            $writer->put('MESSENGER_BOT_APP_SECRET', $secret);
        }

        return self::SUCCESS;
    }

    protected function oauthConnectUrl(): string
    {
        if (! Route::has('messenger-bot.oauth.redirect')) {
            return '(OAuth routes disabled — set MESSENGER_BOT_OAUTH_AUTO_REGISTER_ROUTES=true or MESSENGER_BOT_PAGE_ACCESS_TOKEN)';
        }

        return route('messenger-bot.oauth.redirect', [], true);
    }

    protected function applyMessengerConfigFromEnv(MessengerBotEnvWriter $writer): void
    {
        $line = static function (string $key) use ($writer): ?string {
            if (! $writer->hasLine($key)) {
                return null;
            }

            return $writer->get($key);
        };

        $string = static function (string $key, string $default = '') use ($line): string {
            $v = $line($key);
            if ($v === null) {
                return $default;
            }

            return $v;
        };

        $nullableTrimmed = static function (string $key) use ($line): ?string {
            $v = $line($key);
            if ($v === null) {
                return null;
            }
            $t = trim($v);

            return $t === '' ? null : $t;
        };

        $bool = static function (string $key, bool $default) use ($line): bool {
            $v = $line($key);
            if ($v === null || trim($v) === '') {
                return $default;
            }

            return filter_var(trim($v), FILTER_VALIDATE_BOOLEAN);
        };

        $int = static function (string $key, int $default) use ($line): int {
            $v = $line($key);
            if ($v === null || trim($v) === '') {
                return $default;
            }

            return (int) trim($v);
        };

        $scopeLine = $line('MESSENGER_BOT_OAUTH_SCOPES');
        $scopes = ($scopeLine !== null && trim($scopeLine) !== '')
            ? array_values(array_filter(array_map('trim', explode(',', $scopeLine))))
            : [
                'pages_messaging',
                'pages_manage_metadata',
                'pages_read_engagement',
                'pages_manage_engagement',
                'pages_show_list',
            ];

        config([
            'messenger-bot.app_id' => $nullableTrimmed('MESSENGER_BOT_APP_ID'),
            'messenger-bot.app_secret' => $string('MESSENGER_BOT_APP_SECRET'),
            'messenger-bot.verify_token' => $string('MESSENGER_BOT_VERIFY_TOKEN'),
            'messenger-bot.page_access_token' => $string('MESSENGER_BOT_PAGE_ACCESS_TOKEN'),
            'messenger-bot.graph_version' => $string('MESSENGER_BOT_GRAPH_VERSION', 'v24.0'),
            'messenger-bot.webhook.auto_register' => $bool('MESSENGER_BOT_AUTO_REGISTER_ROUTES', true),
            'messenger-bot.webhook.path' => $string('MESSENGER_BOT_WEBHOOK_PATH', '/webhook/messenger'),
            'messenger-bot.webhook.max_body_bytes' => $int('MESSENGER_BOT_MAX_BODY_BYTES', 262144),
            'messenger-bot.webhook.signature_enabled' => $bool('MESSENGER_BOT_SIGNATURE_ENABLED', true),
            'messenger-bot.conversation.driver' => $string('MESSENGER_BOT_CONVERSATION_DRIVER', 'cache'),
            'messenger-bot.conversation.cache_store' => $nullableTrimmed('MESSENGER_BOT_CACHE_STORE'),
            'messenger-bot.conversation.cache_prefix' => $string('MESSENGER_BOT_CACHE_PREFIX', 'messenger_bot:conv:'),
            'messenger-bot.conversation.ttl_minutes' => $int('MESSENGER_BOT_CACHE_TTL', 120),
            'messenger-bot.logging.channel' => $nullableTrimmed('MESSENGER_BOT_LOG_CHANNEL'),
            'messenger-bot.page_token.cache_key' => $string('MESSENGER_BOT_PAGE_TOKEN_CACHE_KEY', 'messenger_bot:page_token'),
            'messenger-bot.page_token.cache_store' => $nullableTrimmed('MESSENGER_BOT_PAGE_TOKEN_CACHE_STORE'),
            'messenger-bot.oauth.auto_register' => $bool('MESSENGER_BOT_OAUTH_AUTO_REGISTER_ROUTES', true),
            'messenger-bot.oauth.path_prefix' => $string('MESSENGER_BOT_OAUTH_PATH_PREFIX', 'messenger-bot/oauth'),
            'messenger-bot.oauth.redirect_uri' => $nullableTrimmed('MESSENGER_BOT_OAUTH_REDIRECT_URI'),
            'messenger-bot.oauth.preferred_page_id' => $nullableTrimmed('MESSENGER_BOT_OAUTH_PREFERRED_PAGE_ID'),
            'messenger-bot.oauth.success_redirect_path' => $string('MESSENGER_BOT_OAUTH_SUCCESS_PATH', '/'),
            'messenger-bot.oauth.refresh_warning_seconds' => $int('MESSENGER_BOT_OAUTH_REFRESH_WARNING_SECONDS', 604800),
            'messenger-bot.oauth.scopes' => $scopes,
            'messenger-bot.get_started.payload' => $string('MESSENGER_BOT_GET_STARTED_PAYLOAD', 'GET_STARTED'),
            'messenger-bot.get_started.default_reply' => $string('MESSENGER_BOT_GET_STARTED_REPLY', 'Welcome! Use the menu below.'),
            'messenger-bot.oauth.throttle_redirect' => $string('MESSENGER_BOT_OAUTH_THROTTLE_REDIRECT', '20,1'),
            'messenger-bot.oauth.throttle_callback' => $string('MESSENGER_BOT_OAUTH_THROTTLE_CALLBACK', '30,1'),
            'messenger-bot.oauth.require_mt_signature' => $bool('MESSENGER_BOT_OAUTH_REQUIRE_MT_SIGNATURE', true),
            'messenger-bot.oauth.dual_write_legacy_token' => $bool('MESSENGER_BOT_OAUTH_DUAL_WRITE_LEGACY', true),
            'messenger-bot.tenancy.enabled' => $bool('MESSENGER_BOT_TENANCY_ENABLED', false),
            'messenger-bot.tenancy.resolver' => $nullableTrimmed('MESSENGER_BOT_TENANCY_RESOLVER'),
            'messenger-bot.tenancy.connection_model' => $nullableTrimmed('MESSENGER_BOT_TENANCY_CONNECTION_MODEL'),
            'messenger-bot.tenancy.connection_page_id_column' => $string('MESSENGER_BOT_TENANCY_PAGE_ID_COLUMN', 'facebook_page_id'),
            'messenger-bot.tenancy.fallback_to_legacy_when_unresolved' => $bool('MESSENGER_BOT_TENANCY_FALLBACK_LEGACY', true),
            'messenger-bot.tenancy.skip_entry_when_unresolved' => $bool('MESSENGER_BOT_TENANCY_SKIP_UNRESOLVED', false),
            'messenger-bot.connection_tokens.cache_store' => $nullableTrimmed('MESSENGER_BOT_CONNECTION_TOKEN_CACHE_STORE'),
            'messenger-bot.connection_tokens.token_key_prefix' => $string('MESSENGER_BOT_CONNECTION_TOKEN_PREFIX', 'messenger_bot:mt:conn:'),
            'messenger-bot.connection_tokens.page_index_prefix' => $string('MESSENGER_BOT_CONNECTION_PAGE_INDEX_PREFIX', 'messenger_bot:mt:page:'),
            'messenger-bot.connection_tokens.version_prefix' => $string('MESSENGER_BOT_POSTS_CACHE_VER_PREFIX', 'messenger_bot:mt:posts_ver:'),
            'messenger-bot.posts.cache_store' => $nullableTrimmed('MESSENGER_BOT_POSTS_CACHE_STORE'),
            'messenger-bot.posts.default_max_posts' => $int('MESSENGER_BOT_POSTS_DEFAULT_MAX_POSTS', 500),
            'messenger-bot.posts.default_cache_ttl_seconds' => $int('MESSENGER_BOT_POSTS_CACHE_TTL', 300),
            'messenger-bot.posts.default_limit_per_request' => $int('MESSENGER_BOT_POSTS_LIMIT_PER_REQUEST', 25),
            'messenger-bot.posts.default_max_api_calls' => $int('MESSENGER_BOT_POSTS_MAX_API_CALLS', 50),
        ]);
    }

    protected function printChecklist(): void
    {
        $path = (string) config('messenger-bot.webhook.path', '/webhook/messenger');
        $this->info('Meta checklist (Graph '.config('messenger-bot.graph_version', 'v24.0').') — full details: package README');
        $this->line('1. App: Messenger + Webhooks (Page). Connect your Page.');
        $this->line('2. Page token: use OAuth ('.$this->oauthConnectUrl().') or optional MESSENGER_BOT_PAGE_ACCESS_TOKEN in .env for tests.');
        $this->line('3. Dashboard: Webhooks → Page → Callback URL = {APP_URL}'.$path.' — Verify token = MESSENGER_BOT_VERIFY_TOKEN — Verify and Save.');
        $this->line('4. Dashboard: enable the same webhook fields as `webhook_fields` in config (or rely on `messenger-bot:install` / `messenger-bot:sync-page` for subscribed_apps).');
        $this->line('5. Re-run `php artisan messenger-bot:sync-page` after token or field list changes.');
        if ((bool) config('messenger-bot.tenancy.enabled', false)) {
            $this->line('6. Multi-tenant: ensure MESSENGER_BOT_TENANCY_CONNECTION_MODEL points to your Page row model (or set MESSENGER_BOT_TENANCY_RESOLVER). OAuth: MessengerOAuth::redirectToFacebook($model).');
        }
        $this->newLine();
    }
}
