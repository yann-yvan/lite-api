<?php

namespace Nycorp\LiteApi\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Nycorp\LiteApi\Traits\LiteApiModel;

/**
 * Class Log
 *
 * @property int $id
 * @property string $service
 * @property string $level_name
 * @property string $message
 * @property string $channel
 * @property array $context
 * @property array $extra
 * @property Carbon $datetime
 * @property string|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models\Base
 */
class Log extends Model
{
    use SoftDeletes;

    const ID = 'id';
    const SERVICE = 'service';
    const LEVEL_NAME = 'level_name';
    const MESSAGE = 'message';
    const CHANNEL = 'channel';
    const CONTEXT = 'context';
    const EXTRA = 'extra';
    const DATETIME = 'datetime';
    const DELETED_AT = 'deleted_at';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    public $searchable = [];
    public $translatable = [];
    public $assets = [];

    use LiteApiModel;

    protected $table = 'logs';
    protected $casts = [
        self::ID => 'int',
       self::CONTEXT => 'json',
        self::EXTRA => 'json',
        self::DATETIME => 'datetime',
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime'
    ];

    protected $fillable = [
        Log::LEVEL_NAME,
        Log::MESSAGE,
        Log::SERVICE,
        Log::CONTEXT,
        Log::EXTRA,
        Log::DATETIME,
        Log::CHANNEL,
    ];
}
