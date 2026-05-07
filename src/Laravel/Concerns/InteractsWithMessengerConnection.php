<?php

namespace MessengerBot\Laravel\Concerns;

use Illuminate\Database\Eloquent\Model;
use MessengerBot\Contracts\MessengerConnectable;
use MessengerBot\Kernel\Tenancy\TenantResolution;
use MessengerBot\Support\MessengerConnection;

/**
 * Opinionated defaults for Eloquent models. Override {@see messengerTenantIdColumn()},
 * {@see messengerConnectionKeyColumn()}, or {@see messengerFacebookPageIdColumn()} if your schema differs.
 *
 * @mixin Model
 */
trait InteractsWithMessengerConnection
{
    public function toMessengerTenantResolution(): TenantResolution
    {
        /** @var Model&MessengerConnectable $this */
        return MessengerConnection::toResolution($this);
    }

    public function messengerTenantKey(): string
    {
        $v = $this->getAttribute($this->messengerTenantIdColumn());
        if ($v === null || $v === '') {
            throw new \RuntimeException('Messenger tenant key is empty; set '.$this->messengerTenantIdColumn().' or override messengerTenantKey().');
        }

        return (string) $v;
    }

    public function messengerConnectionKey(): string
    {
        $v = $this->getAttribute($this->messengerConnectionKeyColumn());
        if ($v === null || $v === '') {
            return (string) $this->getKey();
        }

        return (string) $v;
    }

    public function facebookPageId(): string
    {
        $v = $this->getAttribute($this->messengerFacebookPageIdColumn());
        if ($v === null || $v === '') {
            throw new \RuntimeException('Facebook Page ID is empty; set '.$this->messengerFacebookPageIdColumn().' or override facebookPageId().');
        }

        return (string) $v;
    }

    public function messengerDisplayName(): ?string
    {
        foreach (['name', 'title', 'label'] as $col) {
            if ($this->offsetExists($col)) {
                $v = $this->getAttribute($col);
                if (is_string($v) && $v !== '') {
                    return $v;
                }
            }
        }

        return null;
    }

    /**
     * Attribute storing the tenant / org scope (string).
     */
    protected function messengerTenantIdColumn(): string
    {
        return 'tenant_id';
    }

    /**
     * When null/empty, {@see messengerConnectionKey()} falls back to the model primary key.
     */
    protected function messengerConnectionKeyColumn(): string
    {
        return 'id';
    }

    protected function messengerFacebookPageIdColumn(): string
    {
        return 'facebook_page_id';
    }
}
