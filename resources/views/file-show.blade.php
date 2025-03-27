@extends('layouts.app')
@section('content')
    @livewire('file-show', ['file' => $file])
@endsection
