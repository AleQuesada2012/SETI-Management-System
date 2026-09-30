<?php

// Every string the user reads is Spanish (.ai/rules/frontend.md), including the ones Flux's own components print:
// the pagination summary, the sidebar toggle, the close button of modals…

use Illuminate\Support\Facades\File;

test('every interface string of the Flux components has a Spanish translation', function () {
    $translations = json_decode(File::get(lang_path('es.json')), true, flags: JSON_THROW_ON_ERROR);

    $keys = collect(File::allFiles(base_path('vendor/livewire/flux/stubs/resources/views/flux')))
        ->flatMap(function (SplFileInfo $file): array {
            preg_match_all("/__\\('((?:[^'\\\\]|\\\\.)+)'\\)/", File::get($file->getPathname()), $matches);

            return $matches[1];
        })
        ->reject(fn (string $key): bool => preg_match('/^[a-z_]+\.[a-z_.]+$/', $key) === 1) // group keys: lang/es/*.php
        ->unique()
        ->sort()
        ->values();

    expect($keys)->not->toBeEmpty()
        ->and($keys->reject(fn (string $key): bool => isset($translations[$key]))->all())->toBe([]);
});

test('the pagination labels are plain text, so screen readers do not read HTML entities', function () {
    expect(__('pagination.previous'))->toBe('« Anterior')
        ->and(__('pagination.next'))->toBe('Siguiente »');
});
