<?php

namespace App\Http\Controllers;

use App\Enums\MarketingFeature;
use Illuminate\Contracts\View\View;

class FeatureLandingController extends Controller
{
    /**
     * Overview of every feature page, at /functies.
     */
    public function index(): View
    {
        return view('features.index');
    }

    /**
     * The landing page for one feature, such as /functies/automatische-incasso.
     */
    public function show(MarketingFeature $feature): View
    {
        return view('features.show', ['feature' => $feature]);
    }
}
