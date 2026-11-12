<?php

namespace App\Notifications;

use App\Enums\RequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RequestStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ServiceRequest $serviceRequest,
        public readonly RequestStatus $fromStatus,
        public readonly RequestStatus $toStatus,
        public readonly User $actor,
        public readonly ?string $comment = null
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Service Request #{$this->serviceRequest->id} Updated: {$this->toStatus->value}")
            ->greeting("Hello {$notifiable->name},")
            ->line("The status of service request '{$this->serviceRequest->title}' has changed.")
            ->line("Previous status: {$this->fromStatus->value}")
            ->line("New status: {$this->toStatus->value}")
            ->line("Changed by: {$this->actor->name}");

        if ($this->comment) {
            $mail->line("Note/Comment: {$this->comment}");
        }

        return $mail;
    }

    /**
     * Get the array representation for database storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'service_request_id' => $this->serviceRequest->id,
            'title' => $this->serviceRequest->title,
            'from_status' => $this->fromStatus->value,
            'to_status' => $this->toStatus->value,
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'comment' => $this->comment,
        ];
    }
}

