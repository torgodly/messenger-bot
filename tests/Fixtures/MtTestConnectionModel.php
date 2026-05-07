<?php

namespace MessengerBot\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use MessengerBot\Contracts\MessengerConnectable;
use MessengerBot\Laravel\Concerns\InteractsWithMessengerConnection;

/**
 * @internal
 */
final class MtTestConnectionModel extends Model implements MessengerConnectable
{
    use InteractsWithMessengerConnection;

    protected $table = 'mt_test_connections';

    public $timestamps = false;

    protected $guarded = [];
}
