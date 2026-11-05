<?php namespace Aero\Tickets\Models;

use Aero\Tickets\Classes\Audit as AuditLog;
use Model;

/** Comprador sin cuenta (identidad = correo). Puede bloquearse para impedirle nuevas compras. */
class Buyer extends Model
{
    public $table = 'aero_tickets_buyers';
    public $guarded = [];
    public $dates = ['first_order_at', 'last_order_at', 'blocked_at'];

    public static array $statuses = ['active' => 'Activo', 'blocked' => 'Bloqueado'];
    public function getStatusOptions(): array { return self::$statuses; }

    /** Registra/actualiza al comprador al hacer una orden. */
    public static function noteOrder(string $email, ?string $name, ?string $phone): self
    {
        $email = mb_strtolower(trim($email));
        $b = static::where('email', $email)->first() ?: new static(['email' => $email, 'first_order_at' => now()]);
        $b->name = $name ?: $b->name;
        $b->phone = $phone ?: $b->phone;
        $b->orders_count = (int) $b->orders_count + 1;
        $b->last_order_at = now();
        $b->save();

        return $b;
    }

    public static function isBlocked(?string $email): bool
    {
        return $email && static::where('email', mb_strtolower(trim($email)))->where('status', 'blocked')->exists();
    }

    public function block(string $reason): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new \ApplicationException('Indica el motivo del bloqueo.');
        }
        $this->forceFill(['status' => 'blocked', 'blocked_reason' => mb_substr($reason, 0, 500), 'blocked_at' => now(), 'blocked_by' => \Aero\Tickets\Classes\Audit::actor()['name']])->save();
        AuditLog::record('buyer.blocked', 'buyer', $this->id, ['note' => $this->email . ': ' . $reason]);
    }

    public function unblock(?string $note = null): void
    {
        $this->forceFill(['status' => 'active', 'blocked_reason' => null, 'blocked_at' => null, 'blocked_by' => null])->save();
        AuditLog::record('buyer.unblocked', 'buyer', $this->id, ['note' => trim($this->email . ' ' . $note)]);
    }

    public function beforeDelete(): void { throw new \ApplicationException('Los compradores no se eliminan.'); }
}
