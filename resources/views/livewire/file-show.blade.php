<div
    @if($file->status !== 'done')
        wire:poll.4s
    @endif
    class="max-w-6xl mx-auto mt-12 space-y-6"
>
    <div class="bg-white p-6 rounded-xl shadow">
        <h2 class="text-xl font-semibold mb-4">
            File: {{ $file->original_filename }}
        </h2>

        @if ($file->status === 'extracting')
            <div class="flex items-center space-x-4">
                <div class="flex items-center space-x-2 text-primary-500">
                    <x-svg.spinner class="w-5 h-5 text-primary-500"/>
                    <span>Extracting file...</span>
                </div>
            </div>
        @elseif ($file->status === 'auditing')
            <div class="flex items-center space-x-4">
                <div class="flex items-center space-x-2 text-green-500">
                    <x-svg.check-circle class="w-5 h-5 text-green-500"/>
                    <span>File Extracted</span>
                </div>
                <div class="flex items-center space-x-2 text-primary-500">
                    <x-svg.spinner class="w-5 h-5 text-primary-500"/>
                    <span>Auditing data...</span>
                </div>
            </div>
        @elseif ($file->status === 'done')
            <div class="space-y-4">
                <h3 class="text-lg font-semibold mb-2">Audit Results</h3>

                <div class="space-y-4">
                    @foreach ($audits as $key => $audit)
                        <x-audit.section
                            :label="$key"
                            :title="$audit['title']"
                            :count="$audit['count']"
                            :items="$audit['items']"
                            :expanded="$expandedSections[$key] ?? false"
                            :toggle="'toggleSection(\'' . $key . '\')'"
                        />
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
