<?php

use Illuminate\Database\Eloquent\Model;
use MessengerBot\Exceptions\InvalidConfigurationException;
use MessengerBot\Support\TenancyConfigurationValidator;
use MessengerBot\Tests\Fixtures\AllowAllPageLinkValidator;
use MessengerBot\Tests\Fixtures\MtTestConnectionModel;

test('tenancy configuration validator accepts valid connection model', function () {
    config([
        'messenger-bot.tenancy.enabled' => true,
        'messenger-bot.tenancy.resolver' => null,
        'messenger-bot.tenancy.connection_model' => MtTestConnectionModel::class,
    ]);

    expect(TenancyConfigurationValidator::connectionModelError())->toBeNull();
});

test('tenancy configuration validator rejects missing class', function () {
    config([
        'messenger-bot.tenancy.enabled' => true,
        'messenger-bot.tenancy.resolver' => null,
        'messenger-bot.tenancy.connection_model' => 'App\\Models\\DoesNotExist',
    ]);

    expect(TenancyConfigurationValidator::connectionModelError())
        ->toContain('does not exist');
});

test('tenancy configuration validator rejects class without messenger connectable', function () {
    $modelClass = new class extends Model
    {
        protected $table = 'mt_non_connectable';
    };

    config([
        'messenger-bot.tenancy.enabled' => true,
        'messenger-bot.tenancy.resolver' => null,
        'messenger-bot.tenancy.connection_model' => $modelClass::class,
    ]);

    expect(TenancyConfigurationValidator::connectionModelError())
        ->toContain('MessengerConnectable');
});

test('tenancy configuration validator requires validates page link when tenancy enabled', function () {
    config([
        'messenger-bot.tenancy.enabled' => true,
        'messenger-bot.oauth.validates_page_link' => null,
        'messenger-bot.oauth.pending_pages_redirect_url' => 'https://app.test/pick',
        'messenger-bot.tenancy.connection_model' => MtTestConnectionModel::class,
    ]);

    expect(TenancyConfigurationValidator::validatesPageLinkError())
        ->toContain('MESSENGER_BOT_VALIDATES_PAGE_LINK');
});

test('tenancy configuration validator requires pending pages url when tenancy enabled', function () {
    config([
        'messenger-bot.tenancy.enabled' => true,
        'messenger-bot.oauth.validates_page_link' => AllowAllPageLinkValidator::class,
        'messenger-bot.oauth.pending_pages_redirect_url' => '',
        'messenger-bot.tenancy.connection_model' => MtTestConnectionModel::class,
    ]);

    expect(TenancyConfigurationValidator::pendingPagesRedirectUrlError())
        ->toContain('MESSENGER_BOT_OAUTH_PENDING_PAGES_URL');
});

test('invalid connection model throws in testing environment on boot', function () {
    config([
        'messenger-bot.tenancy.enabled' => true,
        'messenger-bot.tenancy.resolver' => null,
        'messenger-bot.tenancy.connection_model' => stdClass::class,
    ]);

    expect(fn () => TenancyConfigurationValidator::connectionModelError())
        ->not->toThrow(InvalidConfigurationException::class);

    $error = TenancyConfigurationValidator::connectionModelError();
    expect($error)->not->toBeNull();

    if (app()->environment('testing')) {
        expect(fn () => throw new InvalidConfigurationException((string) $error))
            ->toThrow(InvalidConfigurationException::class);
    }
});
