<?php

namespace App\Http\Controllers;

use App\Enums\ClubSegment;
use Illuminate\Contracts\View\View;

class SegmentLandingController extends Controller
{
    /**
     * The landing page for one type of business, such as /voor/dansschool.
     */
    public function __invoke(ClubSegment $segment): View
    {
        return view('segments.show', ['segment' => $segment]);
    }
}
