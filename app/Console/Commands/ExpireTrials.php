<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Flips trials whose window has ended to past_due (which makes the workspace
 * read-only via EnsureTenantActive). Run daily by the scheduler.
 */
class ExpireTrials extends Command
{
    protected $signature = 'tenants:expire-trials';

    protected $description = 'Flip expired trials to past_due (read-only).';

    public function handle(): int
    {
        $count = Tenant::where('status', 'trial')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', now())
            ->update(['status' => 'past_due']);

        $this->info("Expired {$count} trial(s).");

        return self::SUCCESS;
    }
}
