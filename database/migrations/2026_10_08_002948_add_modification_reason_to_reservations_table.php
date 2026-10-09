<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * RF_49 · Why the reservation was last modified (optional). Each modification overwrites it, so the audit log keeps
 * every reason next to the change it explains. reporting.reservations lists its columns, so it needs no change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->text('modification_reason')->nullable()->comment('Why the last modification was made (RF_49); history in audit_logs');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('modification_reason');
        });
    }
};
