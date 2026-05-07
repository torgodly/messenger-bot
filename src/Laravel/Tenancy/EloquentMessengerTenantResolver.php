<?php

namespace MessengerBot\Laravel\Tenancy;

use Illuminate\Database\Eloquent\Model;
use MessengerBot\Contracts\MessengerConnectable;
use MessengerBot\Kernel\Contracts\TenantResolver;
use MessengerBot\Kernel\Tenancy\TenantResolution;
use MessengerBot\Support\MessengerConnection;

/**
 * Resolve webhook Page ID to a tenant context using an Eloquent model that implements {@see MessengerConnectable}.
 */
abstract class EloquentMessengerTenantResolver implements TenantResolver
{
    /**
     * @return class-string<Model&MessengerConnectable>
     */
    abstract protected function modelClass(): string;

    public function resolveFromPageId(string $pageId): ?TenantResolution
    {
        if ($pageId === '') {
            return null;
        }

        $class = trim($this->modelClass());
        if ($class === '' || ! class_exists($class)) {
            return null;
        }

        if (! is_a($class, Model::class, true) || ! is_a($class, MessengerConnectable::class, true)) {
            return null;
        }

        $column = trim($this->facebookPageIdColumn());
        if ($column === '') {
            $column = 'facebook_page_id';
        }

        /** @var Model|null $model */
        $model = $class::query()->where($column, $pageId)->first();
        if ($model === null || ! $model instanceof MessengerConnectable) {
            return null;
        }

        return MessengerConnection::toResolution($model);
    }

    protected function facebookPageIdColumn(): string
    {
        return 'facebook_page_id';
    }
}
