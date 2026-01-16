<?php

namespace App\Http\Controllers;

use App\Models\Devise;
use Illuminate\Http\Request;

class DeviseController extends Controller
{
    public function index(Request $request)
    {
        if ($request->company_db) {
            return Devise::on($request->company_db)->get();
        } else {
            return Devise::all();
        }
    }
}
