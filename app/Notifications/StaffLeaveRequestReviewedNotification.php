<?php

namespace App\Notifications;

use App\Models\StaffLeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffLeaveRequestReviewedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly StaffLeaveRequest $leaveRequest) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $status = strtoupper((string) $this->leaveRequest->status);

        return (new MailMessage)
            ->subject('Leave request update')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Your leave request has been reviewed.')
            ->line('Status: '.$status)
            ->line('Leave type: '.ucfirst((string) $this->leaveRequest->leave_type))
            ->line('Date range: '.$this->leaveRequest->start_date?->toDateString().' to '.$this->leaveRequest->end_date?->toDateString())
            ->line('Review note: '.($this->leaveRequest->review_note ?: 'No note provided.'));
    }
}
