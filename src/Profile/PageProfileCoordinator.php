<?php

namespace MessengerBot\Profile;

use Illuminate\Console\Command;
use MessengerBot\Console\InstallMessengerBotCommand;
use MessengerBot\Console\SyncMessengerPageCommand;
use MessengerBot\Http\GraphException;

/**
 * Runs Graph-side Page setup used by {@see InstallMessengerBotCommand}
 * and {@see SyncMessengerPageCommand}.
 */
class PageProfileCoordinator
{
    public function __construct(
        protected PageWebhookSubscriber $webhookSubscriber,
        protected PersistentMenuConfigurator $persistentMenu,
        protected PageAccessTokenHealthCheck $tokenHealth,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function subscribeWebhooks(): array
    {
        return $this->webhookSubscriber->subscribe();
    }

    /**
     * @return array<string, mixed>|null Null when {@code persistent_menu} is empty / disabled.
     */
    public function syncPersistentMenuFromConfig(): ?array
    {
        $menu = config('messenger-bot.persistent_menu');
        if (! is_array($menu) || $menu === []) {
            return null;
        }

        return $this->persistentMenu->sync($menu);
    }

    public function runForConsole(Command $command, bool $subscribe, bool $menu, bool $skipTokenCheck = false): int
    {
        if (($subscribe || $menu) && ! $skipTokenCheck) {
            try {
                $this->tokenHealth->assertValid();
                $command->info('Page access token validated.');
            } catch (GraphException $e) {
                $command->error('Page access token check failed: '.$e->getMessage());

                return Command::FAILURE;
            } catch (\Throwable $e) {
                $command->error('Page access token check failed: '.$e->getMessage());

                return Command::FAILURE;
            }
        } elseif (($subscribe || $menu) && $skipTokenCheck) {
            $command->comment('Skipped Page token validation (--skip-token-check).');
        }

        if ($subscribe) {
            try {
                $this->subscribeWebhooks();
                $command->info('Page webhook fields subscribed: '.implode(', ', (array) config('messenger-bot.webhook_fields', [])));
            } catch (GraphException $e) {
                $command->error('Webhook field subscription failed: '.$e->getMessage());

                return Command::FAILURE;
            } catch (\Throwable $e) {
                $command->error($e->getMessage());

                return Command::FAILURE;
            }
        } else {
            $command->comment('Skipped webhook field subscription (--skip-subscribe).');
        }

        if ($menu) {
            try {
                $result = $this->syncPersistentMenuFromConfig();
                if ($result === null) {
                    $command->comment('Skipped persistent menu (messenger-bot.persistent_menu is empty).');
                } else {
                    $command->info('Persistent menu synced via messenger_profile.');
                }
            } catch (GraphException $e) {
                $command->error('Persistent menu sync failed: '.$e->getMessage());

                return Command::FAILURE;
            } catch (\Throwable $e) {
                $command->error($e->getMessage());

                return Command::FAILURE;
            }
        } else {
            $command->comment('Skipped persistent menu (--skip-menu).');
        }

        return Command::SUCCESS;
    }
}
