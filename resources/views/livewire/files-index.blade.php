<div>
    @section('content')
        <ul class="space-y-4 mx-auto max-w-3xl mt-12">
            @forelse($files as $file)
                <li>
                    <a href="{{ route('files.show', $file) }}" class="bg-white border p-4 flex justify-between items-center rounded-lg shadow hover:shadow-lg hover:border-primary-500 transition">
                        <span>{{ $file->original_filename }}</span>
                    </a>
                </li>
            @empty
                <li>
                    <div class="bg-white border p-4 flex justify-between items-center rounded-lg shadow">
                        <span>No files uploaded yet.</span>
                        <a href="{{ route('dashboard') }}" class="text-primary-500 hover:text-primary-700 transition">
                            Upload a file
                        </a>
                    </div>
                </li>
            @endforelse
        </ul>
    @endsection
</div>
