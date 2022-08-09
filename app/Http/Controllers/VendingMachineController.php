<?php

namespace App\Http\Controllers;

use App\Http\Resources\VendingMachineResource;
use App\Models\VendingMachine;
use Illuminate\Http\Request;
use Inertia\Inertia;


class VendingMachineController extends Controller
{
    public function index()
    {
        return Inertia::render('VendingMachine/Index', [
            'vendingMachines' => VendingMachineResource::collection(VendingMachine::all())
        ]);
    }
}
