<?php

use App\Actions\Assets\NotifyDueMaintenance;
use App\Actions\Users\CreateFirstSuperuser;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Validation\ValidationException;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Retention: read notifications older than 180 days (App\Models\Notification, ADR-0006).
Schedule::command('model:prune')->daily();

// The first Superusuario of a fresh database (compose.prod.yaml): refused once one exists (CreateFirstSuperuser).
Artisan::command('users:create-first-superuser {email} {name}', function (CreateFirstSuperuser $createFirstSuperuser) {
    try {
        $user = $createFirstSuperuser->handle((string) $this->argument('name'), (string) $this->argument('email'));
    } catch (ValidationException $exception) {
        collect($exception->errors())->flatten()->each(fn (string $message) => $this->error($message));

        return 1;
    }

    $this->info(__('users.first_superuser.invited', [
        'user' => $user->name,
        'email' => $user->email,
        'hours' => config('auth.passwords.invitations.expire') / 60,
    ]));

    return 0;
})->purpose('Create the first Superusuario of a fresh database and email them an invitation');

// RF_27: reminders of scheduled maintenance due soon or overdue (maintenance.due, Agent-DB.md §10).
Artisan::command('maintenance:notify-due', function (NotifyDueMaintenance $notifyDueMaintenance) {
    $this->info("Recordatorios enviados: {$notifyDueMaintenance->handle()}");
})->purpose('Notify the managers of scheduled maintenance due soon or overdue');

Schedule::command('maintenance:notify-due')->dailyAt('07:00');
