<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    public function in(Request $request){

        return $request->all();
    }


    public function out(Request $request){
        return [];
    }
}
