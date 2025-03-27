@extends('layouts.app')

@section('content')
    <div class="h-screen w-full flex items-center justify-center p-4">
        <div class="max-w-3xl w-full mx-auto">
            @livewire('file-uploader')
        </div>
    </div>
@endsection
