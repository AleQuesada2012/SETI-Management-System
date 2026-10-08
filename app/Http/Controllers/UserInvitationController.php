<?php

namespace App\Http\Controllers;

use App\Actions\Users\AcceptUserInvitation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserInvitationController extends Controller
{
    public function create(): View
    {
        return view('pages::auth.accept-invitation');
    }

    public function store(Request $request, AcceptUserInvitation $acceptInvitation): RedirectResponse
    {
        $acceptInvitation->handle($request->only('token', 'email', 'password', 'password_confirmation'));

        return to_route('login')->with('status', __('users.invitation.accepted'));
    }
}
