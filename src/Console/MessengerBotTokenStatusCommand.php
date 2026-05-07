<?php

namespace MessengerBot\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use MessengerBot\Contracts\PageAccessTokenRepository;
use MessengerBot\Contracts\PageAccessTokenSource;

class MessengerBotTokenStatusCommand extends Command
{
    protected $signature = 'messenger-bot:token-status';

    protected $description = 'Show cached Page token expiry and OAuth reconnect URL (token value is never printed)';

    public function handle(PageAccessTokenRepository $repository, PageAccessTokenSource $provider): int
    {
        $buffer = (int) config('messenger-bot.oauth.refresh_warning_seconds', 604800);

        $cached = $repository->getToken();
        if ($cached === null || $cached === '') {
            $this->warn('No Page token in the configured cache key.');
            $fallback = trim((string) config('messenger-bot.page_access_token', ''));
            if ($fallback !== '') {
                $this->line('Fallback: `MESSENGER_BOT_PAGE_ACCESS_TOKEN` is set in config (.env).');
            } elseif (Route::has('messenger-bot.oauth.redirect')) {
                $this->line('Connect your Page (stores long-lived token in cache):');
                $this->line(route('messenger-bot.oauth.redirect', [], true));
            } else {
                $this->line('OAuth routes are disabled; set MESSENGER_BOT_PAGE_ACCESS_TOKEN or enable MESSENGER_BOT_OAUTH_AUTO_REGISTER_ROUTES.');
            }

            return self::SUCCESS;
        }

        $this->info('Cached Page token: present (value hidden).');
        $this->line('Page ID: '.($repository->getPageId() ?? '—'));
        $expiresAt = $repository->getExpiresAt();
        if ($expiresAt === null) {
            $this->line('Expiry: unknown or non-expiring (per Meta `debug_token`).');
        } else {
            $this->line('Expires at (UTC): '.gmdate('Y-m-d H:i:s', $expiresAt));
            $this->line('Time remaining: '.max(0, $expiresAt - time()).' seconds.');
        }

        if ($repository->shouldRefreshSoon($buffer) && Route::has('messenger-bot.oauth.redirect')) {
            $this->warn('Token is inside the refresh warning window ('.$buffer.' seconds). Re-authorize before it expires:');
            $this->line(route('messenger-bot.oauth.redirect', [], true));
        }

        if ($provider->source() === 'cache') {
            $this->line('Active source for Graph: cache.');
        }

        return self::SUCCESS;
    }
}
