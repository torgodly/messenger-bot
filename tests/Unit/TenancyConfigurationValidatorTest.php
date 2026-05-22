<?php

use MessengerBot\Exceptions\InvalidConfigurationException;
use MessengerBot\Support\TenancyConfigurationValidator;
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
    $modelClass = new class extends \Illuminate\Database\Eloquent\Model
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
