<?php

namespace App\Http\Controllers;
use App\Models\Surveillant;

use Illuminate\Http\Request;

class SurveillantController extends Controller
{
    public function index() {
        return Surveillant::all();
    }
}
