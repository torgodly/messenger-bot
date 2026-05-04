<?php

namespace MessengerBot\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use MessengerBot\Contracts\PageAccessTokenRepository;
use MessengerBot\Support\GraphContainerReset;

class ClearMessengerPageTokenCommand extends Command
{
    protected $signature = 'messenger-bot:clear-page-token';

    protected $description = 'Remove the cached Page access token and reset Graph bindings (re-run OAuth after)';

    public function handle(PageAccessTokenRepository $repository): int
    {
        $repository->forget();
        GraphContainerReset::forget($this->laravel);

        $this->info('Cached Page token cleared.');
        if (Route::has('messenger-bot.oauth.redirect')) {
            $this->line('Reconnect: '.route('messenger-bot.oauth.redirect', [], true));
        }

        return self::SUCCESS;
    }
}
