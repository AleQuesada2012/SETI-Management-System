<?php

namespace App\Http\Controllers;

use App\Reports\PowerBiFeed;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One dataset of the Power BI feed as CSV (RF_61, RF_73). Authenticated by AuthenticatePowerBiFeed, not by a user
 * session: Power BI Service calls it on its refresh schedule.
 */
class PowerBiFeedController extends Controller
{
    public function __invoke(string $dataset, PowerBiFeed $feed): StreamedResponse
    {
        abort_unless(PowerBiFeed::exists($dataset), 404);

        return response()->stream(function () use ($dataset, $feed): void {
            $output = fopen('php://output', 'w');
            $feed->write($dataset, $output);
            fclose($output);
            $feed->recordPull();
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="'.$dataset.'.csv"',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
