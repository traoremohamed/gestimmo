<?php

namespace App\Http\Controllers;

use App\Services\Alertes;
use Illuminate\Http\Request;

class AlerteController extends Controller
{
    public function index(Request $request, Alertes $alertes)
    {
        if ($request->boolean('actualiser')) {
            $alertes->oublier($request->user());
        }

        return view('alertes', ['alertes' => $alertes->pour($request->user())]);
    }
}
