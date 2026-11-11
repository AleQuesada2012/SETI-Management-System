<?php

use App\Models\User;

// SECURITY.md · Sessions: 120 minutes idle, 9 hours in all, and nothing confirmed or connected outlives them.

test('a session ends nine hours after sign-in, even while it is in use', function () {
    $this->actingAs(User::factory()->create());
    $this->get(route('profile.edit'))->assertOk();

    // Never idle for 120 minutes: only the absolute limit can end it.
    foreach (range(1, 8) as $hour) {
        $this->travel(1)->hours();
        $this->get(route('profile.edit'))->assertOk();
    }

    $this->travel(1)->hours();
    $this->get(route('profile.edit'))->assertRedirect(route('login', ['reason' => 'expired']));
    $this->assertGuest();
});

test('the sign-in page says why the session ended, however many times it loads', function () {
    // A Livewire request follows the redirect itself and the browser then loads the page again: a flashed message
    // would be gone by the second load, the reason in the URL is not.
    foreach (range(1, 2) as $load) {
        $this->get(route('login', ['reason' => 'expired']))->assertOk()->assertSee(__('auth.session_expired'));
    }

    $this->get(route('login', ['reason' => 'anything-else']))->assertOk()->assertDontSee('data-test="signed-out-reason"', false);
});

test('signing in again starts a new nine hours', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('profile.edit'))->assertOk();
    $this->travel(9)->hours();
    $this->get(route('profile.edit'))->assertRedirect(route('login', ['reason' => 'expired']));

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $this->get(route('profile.edit'))->assertOk();
});

test('a confirmed password stays confirmed no longer than an idle session lasts', function () {
    expect(config('auth.password_timeout'))->toBe(60 * config('session.lifetime'));
});

test('the WebSocket server accepts browsers only from this site', function () {
    expect(config('reverb.apps.apps.0.allowed_origins'))->toBe([parse_url(config('app.url'), PHP_URL_HOST)]);
});
