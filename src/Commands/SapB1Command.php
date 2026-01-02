<?php

declare(strict_types=1);

namespace SapB1\Commands;

use Illuminate\Console\Command;

class SapB1Command extends Command
{
    public $signature = 'sap-b1';

    public $description = 'SAP B1 SDK Command';

    public function handle(): int
    {
        $this->comment('SAP B1 SDK - Commands will be implemented in Phase 8');

        return self::SUCCESS;
    }
}
