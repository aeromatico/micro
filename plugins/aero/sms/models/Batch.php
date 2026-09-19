<?php namespace Aero\Sms\Models;

use Model;

class Batch extends Model
{
    public $table = 'aero_sms_batches';

    public $fillable = ['uuid', 'name', 'tenant_id', 'api_key_id', 'consumer', 'status', 'total', 'scheduled_at'];

    protected $dates = ['scheduled_at', 'completed_at'];

    public $hasMany = [
        'messages' => [Message::class],
    ];

    /** Conteo por estado, en una sola consulta. */
    public function stats(): array
    {
        $rows = Message::where('batch_id', $this->id)
            ->selectRaw('status, COUNT(*) as n, SUM(segments) as seg')
            ->groupBy('status')
            ->get();

        $by = $rows->pluck('n', 'status')->map(fn ($n) => (int) $n)->all();

        return [
            'total'     => (int) $this->total,
            'queued'    => ($by['queued'] ?? 0) + ($by['sending'] ?? 0),
            'sent'      => $by['sent'] ?? 0,
            'delivered' => $by['delivered'] ?? 0,
            'failed'    => ($by['failed'] ?? 0) + ($by['undelivered'] ?? 0),
            'blocked'   => $by['blocked'] ?? 0,
            'cancelled' => $by['cancelled'] ?? 0,
            'segments'  => (int) $rows->sum('seg'),
        ];
    }

    /** Marca el lote como completado cuando ya no queda nada por enviar. */
    public function refreshStatus(): void
    {
        if (in_array($this->status, ['completed', 'cancelled'], true)) {
            return;
        }

        $pending = Message::where('batch_id', $this->id)->whereIn('status', ['queued', 'sending'])->exists();

        if (!$pending) {
            $this->status = 'completed';
            $this->completed_at = now();
            $this->saveQuietly();
        }
    }

    public function getProgressAttribute(): string
    {
        $s = $this->stats();

        return ($s['total'] - $s['queued']) . ' / ' . $s['total'];
    }
}
