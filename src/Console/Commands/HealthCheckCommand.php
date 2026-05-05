<?php

namespace Nawasara\Zoom\Console\Commands;

use Illuminate\Console\Command;
use Nawasara\Zoom\Services\ZoomClient;

class HealthCheckCommand extends Command
{
    protected $signature = 'zoom:health-check';

    protected $description = 'Check Zoom API connection and token validity';

    public function handle(ZoomClient $client)
    {
        $this->info('Checking Zoom API connection...');

        if (! $client->isConfigured()) {
            $this->error('Zoom credentials not configured in Vault');
            return 1;
        }

        $result = $client->testConnection();

        if ($result['success']) {
            $this->info('✓ '.$result['message']);
            return 0;
        }

        $this->error('✗ '.$result['message']);
        return 1;
    }
}
