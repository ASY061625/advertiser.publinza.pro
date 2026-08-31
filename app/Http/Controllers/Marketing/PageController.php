<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Inertia\Response;

class PageController extends Controller
{
    public function howItWorks(): Response
    {
        return inertia('HowItWorks');
    }

    public function pricing(): Response
    {
        return inertia('Pricing');
    }

    public function publishers(): Response
    {
        return inertia('Publishers');
    }

    public function terms(): Response
    {
        return inertia('Terms');
    }

    public function privacy(): Response
    {
        return inertia('Privacy');
    }
}
