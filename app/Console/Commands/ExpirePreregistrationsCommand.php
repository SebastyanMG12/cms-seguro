<?php

namespace App\Console\Commands;

use App\Services\PreregistrationService;
use Illuminate\Console\Command;

class ExpirePreregistrationsCommand extends Command
{
    protected $signature = 'hospital:expire-preregistrations';

    protected $description = 'Expira pre-registros de urgencias cuyo tiempo de validez ha vencido sin haber ingresado';

    public function handle(PreregistrationService $service): int
    {
        $this->info('Iniciando expiración de pre-registros vencidos...');
        $count = $service->expireOldPreregistrations();
        $this->info("Se han expirado {$count} pre-registros vencidos.");

        return self::SUCCESS;
    }
}
