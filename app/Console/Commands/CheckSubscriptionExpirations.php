<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckSubscriptionExpirations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:check-expirations 
                            {--company= : Check a specific company UUID} 
                            {--force : Force send notifications regardless of recent dispatch history}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and sync subscription statuses, evaluate grace periods, and dispatch automated expiration notifications.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $companyId = $this->option('company');
        $force = (bool) $this->option('force');

        $this->info('Starting subscription expiration checks and notifications...');

        $query = Company::query();
        if ($companyId) {
            $query->where('id', $companyId);
        }

        $companies = $query->get();

        if ($companies->isEmpty()) {
            $this->warn('No companies found to process.');
            return Command::SUCCESS;
        }

        $tableRows = [];

        foreach ($companies as $company) {
            $tz = $company->timezone ?: config('app.timezone', 'Asia/Riyadh');
            $localNow = Carbon::now($tz);

            $result = SubscriptionService::checkAndNotifyCompany($company, $force);

            $statusText = 'Active';
            if ($result['is_expired'] ?? false) {
                $statusText = 'Expired (Suspended)';
            }

            $tableRows[] = [
                'Company' => $company->company_name,
                'Timezone' => $tz,
                'Local Time' => $localNow->format('Y-m-d H:i'),
                'Days Left' => $result['days_remaining'] ?? 'N/A',
                'Status' => $statusText,
                'Action Taken' => $result['action_taken'] ?? 'none',
            ];
        }

        $this->table(
            ['Company', 'Timezone', 'Local Time', 'Days Left', 'Status', 'Action Taken'],
            $tableRows
        );

        $this->info('Subscription expiration checks completed successfully.');
        return Command::SUCCESS;
    }
}
