<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiringSoonNotification extends Notification implements ShouldBroadcastNow
{
    use Queueable;

    public Subscription $subscription;
    public int $daysRemaining;

    /**
     * Create a new notification instance.
     */
    public function __construct(Subscription $subscription, int $daysRemaining)
    {
        $this->subscription = $subscription;
        $this->daysRemaining = $daysRemaining;
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
            'type' => 'subscription_expiring_soon',
            'subscription_id' => $this->subscription->id,
            'company_id' => $this->subscription->company_id,
            'plan_name' => $planName,
            'days_remaining' => $this->daysRemaining,
            'end_date' => $endDateStr,
            'title_key' => 'notification_sub_expiring_soon_title',
            'message_key' => 'notification_sub_expiring_soon_message',
            'title_params' => [],
            'message_params' => [
                'plan' => $planName,
                'days' => $this->daysRemaining,
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

        $title = __('messages.notification_sub_expiring_soon_title');
        $message = __('messages.notification_sub_expiring_soon_message', [
            'plan' => $planName,
            'days' => $this->daysRemaining,
            'date' => $endDateStr,
        ]);

        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'subscription_expiring_soon',
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
