<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class StorefrontController extends Controller
{
    public function home(): Response
    {
        return Inertia::render('storefront/Home');
    }

    public function checkout(): Response
    {
        return Inertia::render('storefront/Checkout');
    }

    public function myOrders(): Response
    {
        return Inertia::render('storefront/MyOrders');
    }

    public function orderSuccess(): Response
    {
        return Inertia::render('storefront/OrderSuccess');
    }

    public function support(): Response
    {
        return Inertia::render('storefront/Support');
    }

    public function termsOfUse(): Response
    {
        return Inertia::render('storefront/TermsOfUse');
    }

    public function privacyStatement(): Response
    {
        return Inertia::render('storefront/PrivacyStatement');
    }
}
