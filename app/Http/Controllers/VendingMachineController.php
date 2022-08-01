<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class VendingMachineController extends Controller
{
    public function index()
    {
        return Inertia::render('VendingMachine/Index');
    }
}
