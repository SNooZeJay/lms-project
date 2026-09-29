<?php

namespace App\Http\Controllers;

use App\Support\PublishedCourses;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    /**
     * The public landing page.
     *
     * Two things are read, and they are read from one place so they cannot
     * disagree.
     *
     * The cards are the published courses themselves, newest first, so there is no
     * separate list of featured courses to keep in step with the catalog. A landing
     * page that could show a course the catalog hides would advertise something a
     * student then cannot open.
     *
     * The figures in the panel are the totals of the whole published catalog, not
     * a sum of the cards drawn below them. The page draws three courses per group,
     * so summing the cards would describe six courses and label it the catalog.
     * With five courses in this repository the two agree by coincidence, and a
     * sixth course would turn the panel into a quiet lie. PublishedCourses::totals()
     * is what answers that, and the two are the same class for the same reason.
     */
    public function __invoke(): View
    {
        ['free' => $free, 'paid' => $paid] = PublishedCourses::forHomePage();

        return view('public.home', [
            'freeCourses' => $free,
            'paidCourses' => $paid,
            'totals' => PublishedCourses::totals(),
        ]);
    }
}
