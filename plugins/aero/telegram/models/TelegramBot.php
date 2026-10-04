<?php namespace Aero\Telegram\Models;

use Crypt;
use Model;
use Aero\Hello\Models\Account;

class TelegramBot extends Model
{
    public $table = 'aero_telegram_bots';

    public $fillable = ['account_id', 'bot_id', 'username', 'token', 'webhook_secret'];

    protected $hidden = ['token', 'webhook_secret'];

    public $belongsTo = [
        'account' => [Account::class, 'key' => 'account_id'],
    ];

    public function setTokenAttribute($value): void
    {
        $this->attributes['token'] = $value === null || $value === '' ? null : Crypt::encrypt($value);
    }

    public function getTokenAttribute($value): ?string
    {
        return $value ? Crypt::decrypt($value) : null;
    }
}
