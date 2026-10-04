<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SecurityConfiguration;
use App\Services\SecurityEvents;
use Illuminate\Console\Command;

class SecurityCheckConfig extends Command
{
    protected $signature = 'security:check-config {--accounts : Check known synthetic accounts in the configured database}';
    protected $description = 'Validate security configuration offline without displaying secrets';

    public function handle(SecurityConfiguration $configuration): int
    {
        $this->line(app()->environment('production') ? 'PROFILE: production' : 'PROFILE: development/testing (HTTP allowed)');
        $issues = $configuration->critical();
        if (! config('app.key')) { $issues[] = 'APP_KEY_MISSING'; }
        if ($this->option('accounts') && app()->environment('production')) {
            if (User::whereIn('username', ['admin_test', 'secretario_test', 'e2e-admin', 'e2e-secretaria', 'e2e-secretaria-2', 'e2e-incomplete', '000000003'])->where('estado', true)->exists()) {
                $issues[] = 'SYNTHETIC_ACCOUNTS_PRESENT';
            }
        }
        foreach (array_unique($issues) as $issue) {
            $this->error('CRITICAL: '.$issue);
        }
        if ($issues) {
            SecurityEvents::record('security_configuration_failure', ['reason' => 'critical_config']);
            return self::FAILURE;
        }
        $this->info('Security configuration checks passed. Infrastructure and account review still required.');
        return self::SUCCESS;
    }
}
