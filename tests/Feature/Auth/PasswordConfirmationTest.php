<?php

use App\Models\User;

test('the confirm password screen renders', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('password.confirm'))
        ->assertOk();
});
