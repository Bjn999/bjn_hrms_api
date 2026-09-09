<?php

namespace App\Notifications;

use App\Models\SubscriptionUpgradeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class SubscriptionUpgradeStatusNotification extends Notification implements ShouldBroadcastNow
{
    use Queueable;

    public SubscriptionUpgradeRequest $upgradeRequest;
    public string $status; // 'approved' or 'rejected'

    /**
     * Create a new notification instance.
     */
    public function __construct(SubscriptionUpgradeRequest $upgradeRequest, string $status)
    {
        $this->upgradeRequest = $upgradeRequest;
        $this->status = $status;
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
        $planName = $this->upgradeRequest->requestedPlan ? $this->upgradeRequest->requestedPlan->name : 'الباقة';
        $isRenewal = $this->upgradeRequest->request_type === 'renewal' || $this->upgradeRequest->request_type === 'تجديد';
        $isApproved = $this->status === 'approved';

        if ($isApproved) {
            $titleKey = $isRenewal ? 'notification_sub_renewal_approved_title' : 'notification_sub_upgrade_approved_title';
            $messageKey = $isRenewal ? 'notification_sub_renewal_approved_message' : 'notification_sub_upgrade_approved_message';
        } else {
            $titleKey = $isRenewal ? 'notification_sub_renewal_rejected_title' : 'notification_sub_upgrade_rejected_title';
            $messageKey = $isRenewal ? 'notification_sub_renewal_rejected_message' : 'notification_sub_upgrade_rejected_message';
        }

        return [
            'type' => 'subscription_upgrade_status',
            'status' => $this->status,
            'request_id' => $this->upgradeRequest->id,
            'company_id' => $this->upgradeRequest->company_id,
            'plan_name' => $planName,
            'request_type' => $this->upgradeRequest->request_type,
            'admin_notes' => $this->upgradeRequest->admin_notes,
            'title_key' => $titleKey,
            'message_key' => $messageKey,
            'title_params' => [],
            'message_params' => [
                'plan' => $planName,
                'notes' => $this->upgradeRequest->admin_notes ?: '',
            ],
            'action_url' => '/portal/settings?tab=subscription_data',
        ];
    }

    /**
     * Get the broadcastable representation of the notification (Sent via WebSocket).
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $planName = $this->upgradeRequest->requestedPlan ? $this->upgradeRequest->requestedPlan->name : 'الباقة';
        $isRenewal = $this->upgradeRequest->request_type === 'renewal' || $this->upgradeRequest->request_type === 'تجديد';
        $isApproved = $this->status === 'approved';

        if ($isApproved) {
            $title = $isRenewal
                ? __('messages.notification_sub_renewal_approved_title')
                : __('messages.notification_sub_upgrade_approved_title');

            $message = $isRenewal
                ? __('messages.notification_sub_renewal_approved_message', ['plan' => $planName])
                : __('messages.notification_sub_upgrade_approved_message', ['plan' => $planName]);
        } else {
            $title = $isRenewal
                ? __('messages.notification_sub_renewal_rejected_title')
                : __('messages.notification_sub_upgrade_rejected_title');

            $message = $isRenewal
                ? __('messages.notification_sub_renewal_rejected_message', ['plan' => $planName, 'notes' => $this->upgradeRequest->admin_notes ?: ''])
                : __('messages.notification_sub_upgrade_rejected_message', ['plan' => $planName, 'notes' => $this->upgradeRequest->admin_notes ?: '']);
        }

        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'subscription_upgrade_status',
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
