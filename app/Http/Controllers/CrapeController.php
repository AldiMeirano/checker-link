<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CrapeController extends Controller
{
    // Method untuk menyapa
    public function sayHai()
    {
        return response()->json([
            'message' => 'Hai, saya di controller!',
        ]);
    }
}
