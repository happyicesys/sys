<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class TutorialController extends Controller
{
    public function __construct()
    {
        // Allow access if user has one of the permissions, or just basic 'read tutorials'.
        // The detailed permission check can effectively be done in the View or here.
        // Given 'tutorials-operators' and 'tutorials-drivers' exist as permissions,
        // and 'read tutorials' is the base one in Authenticated.vue
        $this->middleware(['permission:read tutorials']);
    }

    public function index(Request $request)
    {
        return Inertia::render('Tutorial/Index');
    }
}
