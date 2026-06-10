<?php namespace Aero\Sites\Jobs;

use Aero\Sites\Classes\Notifications\NotificationDispatcher;
use Aero\Sites\Models\ContactSubmission;
use Queue;

class DispatchContactNotification
{
    public ContactSubmission $submission;

    public function __construct(ContactSubmission $submission)
    {
        $this->submission = $submission;
    }

    public function fire($job, $data): void
    {
        $submission = ContactSubmission::find($data['submission_id']);

        if (!$submission) {
            $job->delete();
            return;
        }

        app(NotificationDispatcher::class)->dispatch($submission);
        $job->delete();
    }

    public static function dispatch(ContactSubmission $submission): void
    {
        Queue::push(static::class, [
            'submission_id' => $submission->id,
        ]);
    }
}
