<?php

use App\Actions\Assets\NotifyDueMaintenance;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Retention: read notifications older than 180 days (App\Models\Notification, ADR-0006).
Schedule::command('model:prune')->daily();

// RF_27: reminders of scheduled maintenance due soon or overdue (maintenance.due, Agent-DB.md §10).
Artisan::command('maintenance:notify-due', function (NotifyDueMaintenance $notifyDueMaintenance) {
    $this->info("Recordatorios enviados: {$notifyDueMaintenance->handle()}");
})->purpose('Notify the managers of scheduled maintenance due soon or overdue');

Schedule::command('maintenance:notify-due')->dailyAt('07:00');
