<?php namespace Aero\School\Models;

use Aero\School\Classes\TenantOwned;
use Model;

class Announcement extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use TenantOwned;

    public $table = 'aero_school_announcements';

    public $fillable = ['tenant_id', 'title', 'body', 'audience', 'level', 'course_id', 'attachment_url', 'status', 'sent_at', 'total', 'delivered', 'failed', 'created_by'];

    public $rules = ['title' => 'required|string|max:255', 'body' => 'required|string', 'audience' => 'in:all,level,course'];

    public $dates = ['sent_at'];

    public $belongsTo = ['course' => [Course::class, 'key' => 'course_id']];

    public $hasMany = ['deliveries' => [Delivery::class, 'key' => 'announcement_id']];

    public function beforeValidate(): void
    {
        foreach (['course_id', 'level', 'attachment_url'] as $f) {
            if ($this->{$f} === '') {
                $this->{$f} = null;
            }
        }
    }

    public function getAudienceOptions(): array
    {
        return ['all' => 'Todo el colegio', 'level' => 'Un nivel', 'course' => 'Un curso'];
    }

    public function getLevelOptions(): array
    {
        return Course::LEVELS;
    }

    public function getCourseIdOptions(): array
    {
        return Course::visible()->active()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getStatusOptions(): array
    {
        return ['draft' => 'Borrador', 'sending' => 'Enviando', 'sent' => 'Enviado'];
    }
}
