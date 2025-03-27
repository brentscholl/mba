<?php

namespace App\Livewire;

use App\Models\File;
use Livewire\Component;

class FilesIndex extends Component
{
    public $files;

    public function render()
    {
        $this->files = File::all();

        return view('livewire.files-index')
            ->layout('layouts.app');
    }
}
