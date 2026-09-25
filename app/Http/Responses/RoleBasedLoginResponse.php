<?php

namespace App\Http\Responses;

use App\Support\RoleBasedDestination;
use Laravel\Fortify\Contracts\LoginResponse;

class RoleBasedLoginResponse implements LoginResponse
{
    public function toResponse($request)
    {
        return redirect()->to(RoleBasedDestination::for($request->user()));
    }
}
