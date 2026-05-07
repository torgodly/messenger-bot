<?php

namespace MessengerBot\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use MessengerBot\Contracts\PageAccessTokenSource;
use MessengerBot\Profile\PageProfileCoordinator;

class SyncMessengerPageCommand extends Command
{
    protected $signature = 'messenger-bot:sync-page
                            {--skip-subscribe : Do not POST /me/subscribed_apps}
                            {--skip-menu : Do not POST persistent_menu to messenger_profile}
                            {--skip-token-check : Skip Graph token validation (GET /me) before subscribe/menu}';

    protected $description = 'Subscribe Page webhook fields and sync persistent menu (Graph only; no config publish)';

    public function handle(PageProfileCoordinator $coordinator): int
    {
        $token = app(PageAccessTokenSource::class)->token();
        if (trim($token) === '') {
            $hint = Route::has('messenger-bot.oauth.redirect')
                ? 'Complete OAuth: '.route('messenger-bot.oauth.redirect', [], true)
                : 'Set MESSENGER_BOT_PAGE_ACCESS_TOKEN or enable OAuth routes (MESSENGER_BOT_OAUTH_AUTO_REGISTER_ROUTES).';

            $this->error('No Page access token. '.$hint);

            return self::FAILURE;
        }

        return $coordinator->runForConsole(
            $this,
            ! $this->option('skip-subscribe'),
            ! $this->option('skip-menu'),
            (bool) $this->option('skip-token-check'),
        );
    }
}
