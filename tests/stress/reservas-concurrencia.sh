#!/usr/bin/env bash
#
# Concurrency test of RF_46 (test plan, "Pruebas de estrés", Reservas): N processes book the same space and the same
# hour at the same instant. Exactly one may succeed.
#
#   1. Through CreateReservation, the Action every booking page uses: it checks for overlaps, then inserts.
#   2. With bare inserts that skip that check, so only the database's EXCLUDE constraint (reservations_no_overlap)
#      stands between them.
#
# Local environment only: it books as the demo account reservante@seti.test. Run from the repository root with the
# stack up (docker compose up -d):
#
#   tests/stress/reservas-concurrencia.sh [requests=10] [space=LAB-110]
#
# Each process boots Laravel first and then waits for the same second (time_sleep_until), so the bookings really
# arrive together. Exit status 0 when both phases end with exactly one reservation.
set -euo pipefail

REQUESTS="${1:-10}"
SPACE="${2:-LAB-110}"
OUTPUT="$(mktemp -d)"
trap 'rm -rf "$OUTPUT"' EXIT

# A block nobody has booked: a random weekday 150 to 400 days ahead (not a Sunday), 10:00 to 11:00, and 12:00 to
# 13:00 for the second phase.
while :; do
    DATE="$(date -d "+$((150 + RANDOM % 250)) days" +%Y-%m-%d)"
    [[ "$(date -d "$DATE" +%u)" != 7 ]] && break
done

artisan() {
    docker compose exec -T app php artisan "$@"
}

# Runs REQUESTS copies of a PHP snippet at the same second and prints one line per process.
race() {
    local phase="$1" code="$2" go
    go=$(($(date +%s) + 8))

    for i in $(seq 1 "$REQUESTS"); do
        artisan tinker --execute "\$i = $i; \$go = $go; $code" >"$OUTPUT/$phase-$i.out" 2>&1 &
    done
    wait

    for i in $(seq 1 "$REQUESTS"); do
        printf '  proceso %2d: %s\n' "$i" "$(grep -E '^(CREADA|RECHAZADA)' "$OUTPUT/$phase-$i.out" || tail -n 1 "$OUTPUT/$phase-$i.out")"
    done
}

count() {
    artisan tinker --execute "
        \$space = App\Models\Space::query()->where('code', '$SPACE')->firstOrFail();
        echo App\Models\Reservation::query()->where('space_id', \$space->id)
            ->where('starts_at', '$DATE $1')->where('status', 'confirmed')->count();
    " | tail -n 1
}

SETUP="
    \$actor = App\Models\User::query()->where('email', 'reservante@seti.test')->firstOrFail();
    \$space = App\Models\Space::query()->where('code', '$SPACE')->firstOrFail();
    time_sleep_until(\$go);
"

echo "Espacio $SPACE, $DATE, $REQUESTS solicitudes simultáneas."
echo
echo "Fase 1. Reservar 10:00-11:00 con la acción CreateReservation (validación de la aplicación y de la base de datos):"
race action "$SETUP
    try {
        \$r = app(App\Actions\Reservations\CreateReservation::class)->handle(\$actor, [
            'space_id' => \$space->id, 'date' => '$DATE', 'start_time' => '10:00', 'end_time' => '11:00',
            'title' => \"Prueba de concurrencia \$i\", 'attendees' => 5, 'responsible_id' => \$actor->id,
            'notes' => 'Dato de prueba: se elimina antes de entregar el sistema.',
        ]);
        echo \"CREADA reserva #{\$r->id}\", PHP_EOL;
    } catch (Illuminate\Validation\ValidationException \$e) {
        echo 'RECHAZADA: ', collect(\$e->errors())->flatten()->first(), PHP_EOL;
    }
"
ACTION_CREATED="$(count '10:00:00')"
echo "  Reservas creadas: $ACTION_CREATED"

echo
echo "Fase 2. Insertar 12:00-13:00 directamente, sin la validación de la aplicación (solo la restricción EXCLUDE):"
race insert "$SETUP
    try {
        \$r = App\Models\Reservation::query()->create([
            'space_id' => \$space->id, 'starts_at' => '$DATE 12:00', 'ends_at' => '$DATE 13:00',
            'title' => \"Prueba de concurrencia \$i\", 'attendees' => 5,
            'responsible_id' => \$actor->id, 'created_by' => \$actor->id,
        ]);
        echo \"CREADA reserva #{\$r->id}\", PHP_EOL;
    } catch (Illuminate\Database\QueryException \$e) {
        echo 'RECHAZADA por la base de datos: ', str_contains(\$e->getMessage(), 'reservations_no_overlap') ? 'reservations_no_overlap' : \$e->getMessage(), PHP_EOL;
    }
"
INSERT_CREATED="$(count '12:00:00')"
echo "  Reservas creadas: $INSERT_CREATED"

echo
if [[ "$ACTION_CREATED" == 1 && "$INSERT_CREATED" == 1 ]]; then
    echo "Resultado: EXITOSO. En cada fase se creó exactamente una reserva y las demás se rechazaron."
else
    echo "Resultado: FALLIDO. Se esperaba exactamente una reserva por fase."
    exit 1
fi
