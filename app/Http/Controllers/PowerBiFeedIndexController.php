<?php

namespace App\Http\Controllers;

use App\Reports\PowerBiFeed;
use Illuminate\Http\Response;

/**
 * The base address of the Power BI feed (RF_73): the list of its datasets, one file name per line. Power BI Service
 * tests a Web data source credential against this address, the one the credential is saved for, before it accepts it.
 * It reads no data, so it does not count as a refresh.
 */
class PowerBiFeedIndexController extends Controller
{
    public function __invoke(): Response
    {
        $files = array_map(fn (string $dataset) => $dataset.'.csv', array_keys(PowerBiFeed::DATASETS));

        return response(implode("\n", $files)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
