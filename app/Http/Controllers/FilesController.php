<?php

namespace App\Http\Controllers;

use App\Models\File;
use Illuminate\Http\Request;

class FilesController extends Controller
{
    public function show($id)
    {
        try {
            $file = File::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return redirect()->route('dashboard')->with('error', 'File not found.');
        }

        return view('file-show', compact('file'));
    }
}
