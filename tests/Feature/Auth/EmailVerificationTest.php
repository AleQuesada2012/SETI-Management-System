<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

function verificationUrl(User $user, ?string $hash = null): string
{
    return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => $hash ?? sha1($user->email),
    ]);
}

test('the verification notice renders', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('verification.notice'))
        ->assertOk();
});

test('unverified users are sent to the verification notice', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('start'))
        ->assertRedirect(route('verification.notice'));
});

test('the email can be verified', function () {
    $user = User::factory()->unverified()->create();
    Event::fake();

    $this->actingAs($user)->get(verificationUrl($user))
        ->assertRedirect(route('start', absolute: false).'?verified=1');

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('the email is not verified with an invalid hash', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(verificationUrl($user, sha1('wrong-email')));

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});
