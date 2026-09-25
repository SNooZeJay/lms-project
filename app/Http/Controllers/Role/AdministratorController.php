<?php

namespace App\Http\Controllers\Role;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AdministratorController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('roles.administrator', [
            'user' => $request->user()->load('profile'),
        ]);
    }
}
