<?php namespace Aero\Sites\Models;

use Model;

class ContactSubmission extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_sites_contact_submissions';

    public $fillable = [
        'tenant_id', 'name', 'email', 'phone', 'message',
        'metadata', 'status', 'dispatched_at',
    ];

    protected $dates = ['dispatched_at'];

    protected $casts = [
        'metadata' => 'array',
    ];

    public $rules = [
        'tenant_id' => 'required|exists:aero_sites_tenants,id',
        'name'      => 'required|min:2|max:100',
        'email'     => 'required|email',
        'phone'     => 'nullable|max:30',
        'message'   => 'required|min:5|max:2000',
    ];

    public $belongsTo = [
        'tenant' => [Tenant::class],
    ];

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function markAsSent(): void
    {
        $this->update([
            'status'        => 'sent',
            'dispatched_at' => now(),
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update(['status' => 'failed']);
    }

    public function markAsPartial(): void
    {
        $this->update([
            'status'        => 'partial',
            'dispatched_at' => now(),
        ]);
    }
}
