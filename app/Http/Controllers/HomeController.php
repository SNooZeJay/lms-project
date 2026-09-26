<?php

namespace App\Http\Controllers;

use App\Support\PublishedCourses;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        // The public catalog is what proves this is a learning platform, so the
        // home page reads the same published courses the catalog serves. There is
        // no separate list of featured courses to keep in step.
        ['free' => $free, 'paid' => $paid] = PublishedCourses::forHomePage();

        return view('public.home', [
            'freeCourses' => $free,
            'paidCourses' => $paid,
        ]);
    }
}
