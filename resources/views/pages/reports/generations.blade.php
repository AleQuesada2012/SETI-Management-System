<?php

use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Reportes generados (RF_84): every export with its date, author, type and format, and its file for re-download
 * (route reports.generations.download). Owner: reports (@AleQuesada2012). Data: reporting.report_generations.
 */
new #[Title('reports.generations.title')] class extends Component {
    //
}; ?>

<div class="flex flex-col gap-6">
    <x-module-placeholder owner="Alejandro Quesada (@AleQuesada2012)" :requirements="['RF_84' => 'Consultar reportes previamente generados']" />
</div>
