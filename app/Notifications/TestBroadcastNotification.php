<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class TestBroadcastNotification extends Notification implements ShouldBroadcastNow
{
    use Queueable;

    public ?string $customTitle;
    public ?string $customMessage;
    public ?string $actionUrl;

    /**
     * Create a new notification instance.
     */
    public function __construct(?string $title = null, ?string $message = null, ?string $actionUrl = null)
    {
        $this->customTitle = $title;
        $this->customMessage = $message;
        $this->actionUrl = $actionUrl ?? '/portal';
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $title = $this->customTitle ?: __('messages.test_notification_title');
        $message = $this->customMessage ?: __('messages.test_notification_message');

        return [
            'type' => 'system_test',
            'title' => $title,
            'message' => $message,
            'title_key' => 'test_notification_title',
            'message_key' => 'test_notification_message',
            'title_params' => [],
            'message_params' => [],
            'action_url' => $this->actionUrl,
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $title = $this->customTitle ?: __('messages.test_notification_title');
        $message = $this->customMessage ?: __('messages.test_notification_message');

        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'system_test',
            'title' => $title,
            'message' => $message,
            'action_url' => $this->actionUrl,
            'is_read' => false,
            'read_at' => null,
            'created_at' => now()->toISOString(),
            'created_at_human' => __('messages.just_now') ?: 'الآن',
            'data' => $this->toArray($notifiable),
        ]);
    }
}
