<?php

use App\Console\Commands\CreateClientPermission;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Register the client permission commands
app(\Illuminate\Contracts\Console\Kernel::class)->registerCommand(new CreateClientPermission());
app(\Illuminate\Contracts\Console\Kernel::class)->registerCommand(new \App\Console\Commands\ListClientPermissions());
