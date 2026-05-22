<?php

use MessengerBot\Http\Middleware\EnsureJsonMessengerWebhook;
use MessengerBot\Http\Middleware\VerifyMessengerSignature;

return [

    'app_id' => env('MESSENGER_BOT_APP_ID'),

    'app_secret' => env('MESSENGER_BOT_APP_SECRET'),

    'verify_token' => env('MESSENGER_BOT_VERIFY_TOKEN', ''),

    /*
    | Optional fallback Page token (e.g. tests). Prefer OAuth: token is stored in cache with expiry.
    */
    'page_access_token' => env('MESSENGER_BOT_PAGE_ACCESS_TOKEN', ''),

    /*
    | Long-lived Page token from OAuth (see README). Use a persistent cache store (redis, database) in production.
    */
    'page_token' => [
        'cache_key' => env('MESSENGER_BOT_PAGE_TOKEN_CACHE_KEY', 'messenger_bot:page_token'),
        'cache_store' => env('MESSENGER_BOT_PAGE_TOKEN_CACHE_STORE'),
    ],

    /*
    | Facebook Login OAuth to obtain a long-lived Page access token without putting it in .env.
    | Register "Valid OAuth Redirect URIs" in the Meta app to match redirect_uri (or the derived callback URL).
    */
    'oauth' => [
        'auto_register' => env('MESSENGER_BOT_OAUTH_AUTO_REGISTER_ROUTES', true),
        'path_prefix' => env('MESSENGER_BOT_OAUTH_PATH_PREFIX', 'messenger-bot/oauth'),
        'redirect_uri' => env('MESSENGER_BOT_OAUTH_REDIRECT_URI'),
        'preferred_page_id' => env('MESSENGER_BOT_OAUTH_PREFERRED_PAGE_ID'),
        'success_redirect_path' => env('MESSENGER_BOT_OAUTH_SUCCESS_PATH', '/'),
        'refresh_warning_seconds' => (int) env('MESSENGER_BOT_OAUTH_REFRESH_WARNING_SECONDS', 604800),
        'scopes' => array_values(array_filter(array_map('trim', explode(',', env('MESSENGER_BOT_OAUTH_SCOPES') ?: 'pages_messaging,pages_manage_metadata,pages_read_engagement,pages_manage_engagement,pages_show_list')))),
        'throttle_redirect' => env('MESSENGER_BOT_OAUTH_THROTTLE_REDIRECT', '20,1'),
        'throttle_callback' => env('MESSENGER_BOT_OAUTH_THROTTLE_CALLBACK', '30,1'),
        /*
        | Multi-tenant OAuth: redirect may include tenant_id, connection_id, mt_sig (HMAC of tenant|connection).
        | When require_mt_signature is true, mt_sig must match hash_hmac('sha256', tenant_id."\n".connection_id, app_secret).
        */
        'require_mt_signature' => (bool) env('MESSENGER_BOT_OAUTH_REQUIRE_MT_SIGNATURE', true),
        'dual_write_legacy_token' => (bool) env('MESSENGER_BOT_OAUTH_DUAL_WRITE_LEGACY', true),
    ],

    /*
    | Multi-tenant: resolve webhook entry Page ID to tenant + connection; contextual Page token for Graph.
    */
    'tenancy' => [
        'enabled' => (bool) env('MESSENGER_BOT_TENANCY_ENABLED', false),
        /*
        | Optional custom TenantResolver FQCN. If empty, a default resolver uses connection_model below.
        */
        'resolver' => env('MESSENGER_BOT_TENANCY_RESOLVER'),
        /*
        | Eloquent model class (must implement MessengerConnectable, e.g. use InteractsWithMessengerConnection).
        | Used automatically when tenancy.enabled and tenancy.resolver are not set. php artisan messenger-bot:install --tenant --model=...
        | Page ID column: config key connection_page_id_column (env MESSENGER_BOT_TENANCY_PAGE_ID_COLUMN). Default facebook_page_id;
        | override on the model via messengerFacebookPageIdColumn() (e.g. page_id in Matager).
        */
        'connection_model' => env('MESSENGER_BOT_TENANCY_CONNECTION_MODEL'),
        'connection_page_id_column' => env('MESSENGER_BOT_TENANCY_PAGE_ID_COLUMN', 'facebook_page_id'),
        'fallback_to_legacy_when_unresolved' => (bool) env('MESSENGER_BOT_TENANCY_FALLBACK_LEGACY', true),
        'skip_entry_when_unresolved' => (bool) env('MESSENGER_BOT_TENANCY_SKIP_UNRESOLVED', false),
    ],

    /*
    | After multi-tenant OAuth stores a connection token, optionally subscribe webhook fields and sync persistent menu.
    */
    'after_connection_token_stored' => [
        'subscribe_webhooks' => (bool) env('MESSENGER_BOT_AUTO_SUBSCRIBE_AFTER_OAUTH', false),
        'sync_persistent_menu' => (bool) env('MESSENGER_BOT_AUTO_SYNC_MENU_AFTER_OAUTH', false),
        'skip_token_check' => (bool) env('MESSENGER_BOT_LINK_SKIP_TOKEN_CHECK', false),
        'queue' => (bool) env('MESSENGER_BOT_AFTER_OAUTH_QUEUE', false),
        'queue_name' => env('MESSENGER_BOT_AFTER_OAUTH_QUEUE_NAME'),
        'queue_connection' => env('MESSENGER_BOT_AFTER_OAUTH_QUEUE_CONNECTION'),
        'queue_retry_on_failure' => (bool) env('MESSENGER_BOT_LINK_QUEUE_RETRY', true),
    ],

    /*
    | Optional infrastructure for host apps that queue comment handling (package does not ship DB rules).
    */
    'comment_handlers' => [
        'queue' => (bool) env('MESSENGER_BOT_COMMENT_HANDLERS_QUEUE', false),
        'queue_name' => env('MESSENGER_BOT_COMMENT_HANDLERS_QUEUE_NAME', 'webhooks'),
    ],

    'connection_tokens' => [
        'cache_store' => env('MESSENGER_BOT_CONNECTION_TOKEN_CACHE_STORE'),
        'token_key_prefix' => env('MESSENGER_BOT_CONNECTION_TOKEN_PREFIX', 'messenger_bot:mt:conn:'),
        'page_index_prefix' => env('MESSENGER_BOT_CONNECTION_PAGE_INDEX_PREFIX', 'messenger_bot:mt:page:'),
        'version_prefix' => env('MESSENGER_BOT_POSTS_CACHE_VER_PREFIX', 'messenger_bot:mt:posts_ver:'),
    ],

    'posts' => [
        'cache_store' => env('MESSENGER_BOT_POSTS_CACHE_STORE'),
        'default_max_posts' => (int) env('MESSENGER_BOT_POSTS_DEFAULT_MAX_POSTS', 500),
        'default_cache_ttl_seconds' => (int) env('MESSENGER_BOT_POSTS_CACHE_TTL', 300),
        'default_limit_per_request' => (int) env('MESSENGER_BOT_POSTS_LIMIT_PER_REQUEST', 25),
        'default_max_api_calls' => (int) env('MESSENGER_BOT_POSTS_MAX_API_CALLS', 50),
    ],

    'graph_version' => env('MESSENGER_BOT_GRAPH_VERSION', 'v24.0'),

    'webhook' => [
        /*
        | When true, the webhook route is registered from MessengerBotServiceProvider::boot()
        | outside Laravel's routes/web.php "web" middleware group (which forces CSRF and breaks Meta POSTs with HTTP 419).
        | Set to false only if you register MessengerBot::routes() yourself from a non-web route file or bootstrap "then" callback.
        */
        'auto_register' => env('MESSENGER_BOT_AUTO_REGISTER_ROUTES', true),
        'path' => env('MESSENGER_BOT_WEBHOOK_PATH', '/webhook/messenger'),
        'max_body_bytes' => (int) env('MESSENGER_BOT_MAX_BODY_BYTES', 262144),
        'signature_enabled' => env('MESSENGER_BOT_SIGNATURE_ENABLED', true),
        /*
        | Do not use the "web" middleware group here: it enables CSRF verification and Meta's
        | POST webhooks will fail with HTTP 419 (Page Expired). Use only the middleware below,
        | or add non-session middleware (e.g. "api") if you need it — then exclude this path
        | from CSRF in your App\Http\Middleware\VerifyCsrfToken $except if you must use "web".
        */
        'middleware' => [
            EnsureJsonMessengerWebhook::class,
            VerifyMessengerSignature::class,
        ],
    ],

    'conversation' => [
        'driver' => env('MESSENGER_BOT_CONVERSATION_DRIVER', 'cache'),
        'cache_store' => env('MESSENGER_BOT_CACHE_STORE'),
        'cache_prefix' => env('MESSENGER_BOT_CACHE_PREFIX', 'messenger_bot:conv:'),
        'ttl_minutes' => (int) env('MESSENGER_BOT_CACHE_TTL', 120),
    ],

    'logging' => [
        'channel' => env('MESSENGER_BOT_LOG_CHANNEL'),
    ],

    /*
    | Informational: Page webhook fields commonly subscribed in Meta App Dashboard.
    */
    'webhook_fields' => [
        'messages',
        'messaging_postbacks',
        'messaging_optins',
        'message_deliveries',
        'message_reads',
        'message_echoes',
        'feed',
    ],

    /*
    | Required by Meta when you set persistent_menu: welcome "Get Started" postback payload.
    | Register MessengerBot::payload() for this string (e.g. a short welcome message).
    */
    'get_started' => [
        'payload' => env('MESSENGER_BOT_GET_STARTED_PAYLOAD', 'GET_STARTED'),
        /*
        | Auto-reply when the user taps Get Started and you have not registered MessengerBot::payload().
        | Set MESSENGER_BOT_GET_STARTED_REPLY to an empty string in .env to disable.
        */
        'default_reply' => env('MESSENGER_BOT_GET_STARTED_REPLY', 'Welcome! Use the menu below.'),
    ],

    /*
    | Persistent menu (Messenger Profile API). Set to null or [] to skip menu sync on install/sync.
    | Postback payloads must match your MessengerBot::payload() registrations.
    */
    'persistent_menu' => [
        [
            'locale' => 'default',
            'composer_input_disabled' => false,
            'call_to_actions' => [
                [
                    'type' => 'postback',
                    'title' => 'Products',
                    'payload' => 'SHOW_PRODUCTS',
                ],
                [
                    'type' => 'postback',
                    'title' => 'Receipt',
                    'payload' => 'DEMO_RECEIPT',
                ],
                [
                    'type' => 'postback',
                    'title' => 'Support',
                    'payload' => 'HUMAN',
                ],
            ],
        ],
    ],

];
