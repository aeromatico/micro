<?php namespace Aero\Shop\Models;

use Model;

/**
 * Lo que recuerda la tienda de un cliente que compra por chat: la última lista
 * que se le mostró (para entender «2») y su carrito. Una fila por tenant y
 * cliente; caduca a las 24 h sin actividad y entonces se comporta como vacía.
 */
class ChatSession extends Model
{
    public const TTL_HOURS = 24;

    public $table = 'aero_shop_chat_sessions';

    public $guarded = [];

    protected $dates = ['expires_at'];

    public static function forContact(int $tenantId, string $contactKey): self
    {
        $session = static::firstOrNew(['tenant_id' => $tenantId, 'contact_key' => $contactKey]);

        if ($session->exists && $session->expires_at && $session->expires_at->isPast()) {
            $session->last_list = null;
            $session->cart = null;
        }

        return $session;
    }

    public function getList(): array
    {
        return $this->decode($this->last_list);
    }

    public function getCart(): array
    {
        return $this->decode($this->cart);
    }

    public function remember(array $list): void
    {
        $this->last_list = json_encode($list, JSON_UNESCAPED_UNICODE);
        $this->touchAndSave();
    }

    public function setCart(array $items): void
    {
        $this->cart = json_encode(array_values($items), JSON_UNESCAPED_UNICODE);
        $this->touchAndSave();
    }

    protected function touchAndSave(): void
    {
        $this->expires_at = now()->addHours(static::TTL_HOURS);
        $this->save();
    }

    protected function decode(?string $json): array
    {
        $decoded = $json ? json_decode($json, true) : null;

        return is_array($decoded) ? $decoded : [];
    }
}
