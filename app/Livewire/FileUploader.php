<?php

namespace App\Livewire;

use App\Jobs\ProcessCsvUpload;
use App\Models\File;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithFileUploads;

class FileUploader extends Component
{
    use WithFileUploads;

    public ?\Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file = null;

    public function getRules(): array
    {
        return [
            'file' => 'required|file|mimes:csv,txt|max:102400', // 100MB
        ];
    }

    public function updatedFile()
    {
        $this->validate();

        try {
            $extension = $this->file->getClientOriginalExtension();
            $uniqueFilename = uniqid() . '.' . $extension;

            // Store the file
            $path = $this->file->storeAs('uploads', $uniqueFilename, 'public');

            // Create DB record with 'extracting' status
            $fileModel = File::create([
                'filename' => $uniqueFilename,
                'original_filename' => $this->file->getClientOriginalName(),
                'file_dir' => 'uploads',
                'status' => 'extracting',
            ]);

            // Reset FilePond
            $this->reset('file');
            $this->dispatch('pondReset');

            // Start processing job
            ProcessCsvUpload::dispatch($fileModel);

            // Redirect to file page
            return redirect()->route('files.show', $fileModel->id);
        } catch (\Exception $e) {
            Log::error('File upload error: ' . $e->getMessage());
            $this->addError('file', 'Something went wrong during the upload.');
        }
    }

    public function render()
    {
        return view('livewire.file-uploader');
    }
}
