<?php

namespace App\Notifications;

use App\Models\SubscriptionUpgradeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class SubscriptionUpgradeRequestedNotification extends Notification implements ShouldBroadcastNow
{
    use Queueable;

    public $upgradeRequest;

    /**
     * Create a new notification instance.
     */
    public function __construct(SubscriptionUpgradeRequest $upgradeRequest)
    {
        $this->upgradeRequest = $upgradeRequest;
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
        $companyName = $this->upgradeRequest->company ? $this->upgradeRequest->company->company_name : 'شركة';
        $planName = $this->upgradeRequest->requestedPlan ? $this->upgradeRequest->requestedPlan->name : 'باقة';
        $isRenewal = $this->upgradeRequest->request_type === 'تجديد';

        return [
            'type' => 'subscription_upgrade_request',
            'request_id' => $this->upgradeRequest->id,
            'company_id' => $this->upgradeRequest->company_id,
            'company_name' => $companyName,
            'plan_name' => $planName,
            'request_type' => $this->upgradeRequest->request_type,
            'title_key' => $isRenewal ? 'notification_subscription_renewal_title' : 'notification_subscription_upgrade_title',
            'message_key' => $isRenewal ? 'notification_subscription_renewal_message' : 'notification_subscription_upgrade_message',
            'title_params' => [],
            'message_params' => [
                'company' => $companyName,
                'plan' => $planName,
            ],
            'action_url' => '/super-admin/subscriptions?tab=requests&status=pending',
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $companyName = $this->upgradeRequest->company ? $this->upgradeRequest->company->company_name : 'شركة';
        $planName = $this->upgradeRequest->requestedPlan ? $this->upgradeRequest->requestedPlan->name : 'باقة';
        $isRenewal = $this->upgradeRequest->request_type === 'تجديد';

        $title = $isRenewal
            ? __('messages.notification_subscription_renewal_title')
            : __('messages.notification_subscription_upgrade_title');

        $message = $isRenewal
            ? __('messages.notification_subscription_renewal_message', ['company' => $companyName, 'plan' => $planName])
            : __('messages.notification_subscription_upgrade_message', ['company' => $companyName, 'plan' => $planName]);

        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'subscription_upgrade_request',
            'title' => $title,
            'message' => $message,
            'action_url' => '/super-admin/subscriptions?tab=requests&status=pending',
            'is_read' => false,
            'read_at' => null,
            'created_at' => now()->toISOString(),
            'created_at_human' => __('messages.just_now') ?: 'الآن',
            'data' => $this->toArray($notifiable),
        ]);
    }
}
