<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use MessengerBot\Events\ConnectablePageIdSynced;
use MessengerBot\Kernel\Contracts\ConnectionTokenRepository;
use MessengerBot\Laravel\Tenancy\ConfigurableMessengerTenantResolver;
use MessengerBot\Tests\Fixtures\MtTestConnectionModel;

test('configurable resolver returns null when page id is empty', function () {
    config([
        'messenger-bot.tenancy.connection_model' => MtTestConnectionModel::class,
    ]);

    $r = (new ConfigurableMessengerTenantResolver)->resolveFromPageId('');

    expect($r)->toBeNull();
});

test('configurable resolver returns null when connection model is not configured', function () {
    config(['messenger-bot.tenancy.connection_model' => null]);

    $r = (new ConfigurableMessengerTenantResolver)->resolveFromPageId('123');

    expect($r)->toBeNull();
});

test('configurable resolver returns null when connection model class does not exist', function () {
    config(['messenger-bot.tenancy.connection_model' => 'App\\Models\\DoesNotExist']);

    $r = (new ConfigurableMessengerTenantResolver)->resolveFromPageId('123');

    expect($r)->toBeNull();
});

test('configurable resolver finds Eloquent row by facebook_page_id', function () {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ],
        'messenger-bot.tenancy.connection_model' => MtTestConnectionModel::class,
        'messenger-bot.tenancy.connection_page_id_column' => 'facebook_page_id',
    ]);

    DB::purge('sqlite');
    $this->app['db']->reconnect();

    Schema::create('mt_test_connections', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->string('facebook_page_id');
    });

    MtTestConnectionModel::query()->create([
        'tenant_id' => 'org-1',
        'facebook_page_id' => 'page-xyz',
    ]);

    $r = (new ConfigurableMessengerTenantResolver)->resolveFromPageId('page-xyz');

    expect($r)->not->toBeNull()
        ->and($r->tenantId->value)->toBe('org-1')
        ->and($r->connectionId->value)->toBe('1')
        ->and($r->pageId)->toBe('page-xyz');
});

test('configurable resolver falls back to connection token page index when db row missing', function () {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ],
        'messenger-bot.tenancy.connection_model' => MtTestConnectionModel::class,
        'messenger-bot.tenancy.connection_page_id_column' => 'facebook_page_id',
    ]);

    DB::purge('sqlite');
    $this->app['db']->reconnect();

    Schema::create('mt_test_connections', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->string('facebook_page_id')->nullable();
    });

    MtTestConnectionModel::query()->create([
        'tenant_id' => 'org-1',
        'facebook_page_id' => null,
    ]);

    app(ConnectionTokenRepository::class)->put([
        'access_token' => 'cached-token',
        'expires_at' => null,
        'page_id' => 'page-from-cache',
        'tenant_id' => 'org-1',
        'connection_id' => '1',
    ]);

    Event::fake([ConnectablePageIdSynced::class]);

    $r = (new ConfigurableMessengerTenantResolver)->resolveFromPageId('page-from-cache');

    expect($r)->not->toBeNull()
        ->and($r->tenantId->value)->toBe('org-1')
        ->and($r->connectionId->value)->toBe('1')
        ->and($r->pageId)->toBe('page-from-cache');

    Event::assertDispatched(ConnectablePageIdSynced::class, fn (ConnectablePageIdSynced $e) => $e->connectionId === '1' && $e->pageId === 'page-from-cache');
});
