<?php namespace Aero\Sites\Classes\Notifications;

use Aero\Sites\Models\ContactSubmission;
use Aero\Sites\Models\NotificationChannel;

interface NotificationDriverInterface
{
    public function send(ContactSubmission $submission, NotificationChannel $channel): bool;
}
