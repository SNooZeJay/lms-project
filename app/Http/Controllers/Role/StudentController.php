<?php

namespace App\Http\Controllers\Role;

use App\Http\Controllers\Controller;
use App\Support\ContinueLearning;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user()->load('profile');

        $continueProgress = ContinueLearning::forStudent($user);

        return view('roles.student', [
            'user' => $user,
            'continueProgress' => $continueProgress,
            'continueLesson' => $continueProgress?->lesson,
        ]);
    }
}
