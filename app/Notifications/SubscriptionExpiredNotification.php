<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiredNotification extends Notification implements ShouldBroadcastNow
{
    use Queueable;

    public Subscription $subscription;

    /**
     * Create a new notification instance.
     */
    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
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
     * Get the array representation of the notification (Saved to Database).
     */
    public function toArray(object $notifiable): array
    {
        $planName = $this->subscription->plan ? $this->subscription->plan->name : 'الباقة';
        $endDateStr = $this->subscription->end_date ? $this->subscription->end_date->format('Y-m-d') : '';

        return [
            'type' => 'subscription_expired',
            'subscription_id' => $this->subscription->id,
            'company_id' => $this->subscription->company_id,
            'plan_name' => $planName,
            'end_date' => $endDateStr,
            'title_key' => 'notification_sub_expired_title',
            'message_key' => 'notification_sub_expired_message',
            'title_params' => [],
            'message_params' => [
                'plan' => $planName,
                'date' => $endDateStr,
            ],
            'action_url' => '/portal/settings?tab=subscription_data',
        ];
    }

    /**
     * Get the broadcastable representation of the notification (Sent via WebSocket).
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $planName = $this->subscription->plan ? $this->subscription->plan->name : 'الباقة';
        $endDateStr = $this->subscription->end_date ? $this->subscription->end_date->format('Y-m-d') : '';

        $title = __('messages.notification_sub_expired_title');
        $message = __('messages.notification_sub_expired_message', [
            'plan' => $planName,
            'date' => $endDateStr,
        ]);

        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'subscription_expired',
            'title' => $title,
            'message' => $message,
            'action_url' => '/portal/settings?tab=subscription_data',
            'is_read' => false,
            'read_at' => null,
            'created_at' => now()->toISOString(),
            'created_at_human' => __('messages.just_now') ?: 'الآن',
            'data' => $this->toArray($notifiable),
        ]);
    }
}
