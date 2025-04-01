<div
    @if($file->status !== 'done')
        wire:poll.4s
    @endif
    x-data="{
        tab: '',
        init() {
            const urlTab = (new URLSearchParams(window.location.search)).get('tab');
            this.setTab(urlTab || 'manual', false);
        },
        setTab(newTab, push = true) {
            this.tab = newTab;
            const url = new URL(window.location);
            url.searchParams.set('tab', newTab);
            if (push) {
                window.history.replaceState(null, '', url);
            }
        }
    }"
    x-init="init()"
    class="max-w-6xl mx-auto mt-12 space-y-6 pb-12"
>
    <div class="bg-white p-6 rounded-xl shadow">
        <h2 class="text-xl font-semibold mb-4 flex items-center space-x-2 py-2 px-4 bg-primary-100 rounded">
            <x-svg.file class="w-5 h-5"/>
            <span>{{ $file->original_filename }}</span>
        </h2>

        {{-- Progress Tracker --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:space-x-4 space-y-2 sm:space-y-0 mb-4">
            {{-- File Extraction --}}
            @if (in_array($file->status, ['auditing', 'auditing-ai', 'done']))
                <div class="flex items-center space-x-2 text-green-500">
                    <x-svg.check-circle class="w-5 h-5 text-green-500"/>
                    <span>File Extracted</span>
                </div>
            @elseif ($file->status === 'extracting')
                <div class="flex items-center space-x-2 text-primary-500">
                    <x-svg.spinner class="w-5 h-5 text-primary-500"/>
                    <span>Extracting file...</span>
                </div>
            @endif

            {{-- Manual Audit --}}
            @if (in_array($file->status, ['auditing-ai', 'done']))
                <div class="flex items-center space-x-2 text-green-500">
                    <x-svg.check-circle class="w-5 h-5 text-green-500"/>
                    <span>Manual Audit Complete</span>
                </div>
            @elseif ($file->status === 'auditing')
                <div class="flex items-center space-x-2 text-primary-500">
                    <x-svg.spinner class="w-5 h-5 text-primary-500"/>
                    <span>Running Manual Audit...</span>
                </div>
            @endif

            {{-- AI Audit --}}
            @if ($file->status === 'done')
                <div class="flex items-center space-x-2 text-green-500">
                    <x-svg.check-circle class="w-5 h-5 text-green-500"/>
                    <span>AI Audit Complete</span>
                </div>
            @elseif ($file->status === 'auditing-ai')
                <div class="flex items-center space-x-2 text-primary-500">
                    <x-svg.spinner class="w-5 h-5 text-primary-500"/>
                    <span>Running AI Audit...</span>
                </div>
            @endif
        </div>

        {{-- Final Results --}}
        @if (!empty($audits['manual']) || !empty($audits['ai']) || $file->status !== 'done')
            <div class="space-y-4">

                <div class="border border-primary-200 p-4 rounded-lg text-sm text-primary-800">
                    <div class="flex justify-between items-center">
                        <div class="font-semibold">Total Invoices in File</div>
                        <div class="font-bold space-x-2 flex items-center">
                            @if($file->status === 'extracting')
                                <x-svg.spinner class="w-4 h-4 text-primary-500 mr-2"/>
                            @endif
                            {{ number_format($totalInvoiceCount) }}
                        </div>
                    </div>
                </div>

                {{-- Tabs --}}
                @if($file->status !== 'extracting')
                <h3 class="text-lg font-semibold mb-2">Audit Results:</h3>
                <div class="mt-6 flex space-x-4 border-b border-gray-200">
                    <button
                        class="px-4 py-2 text-sm font-medium"
                        :class="tab === 'manual' ? 'border-b-2 border-primary-500 text-primary-600' : 'text-gray-500 hover:text-primary-500'"
                        @click="setTab('manual')"
                        x-show="{{ json_encode(!empty($audits['manual']) || $file->status !== 'done') }}"
                    >
                        Manual Audits
                    </button>
                    <button
                        class="px-4 py-2 text-sm font-medium"
                        :class="tab === 'ai' ? 'border-b-2 border-primary-500 text-primary-600' : 'text-gray-500 hover:text-primary-500'"
                        @click="setTab('ai')"
                        x-show="{{ json_encode(!empty($audits['ai']) || $file->status === 'auditing-ai') }}"
                    >
                        AI Audits
                    </button>
                </div>

                {{-- Manual Audits --}}
                <div x-show="tab === 'manual'" class="space-y-4 mt-4">
                    @if (!empty($audits['manual']))
                        @foreach ($audits['manual'] as $key => $audit)
                            <x-audit.section
                                :label="$key"
                                :title="$audit['title']"
                                :count="$audit['count']"
                                :items="$audit['items']"
                                :limit="$sectionLimits[$key] ?? 5"
                                :expanded="$expandedSections[$key] ?? false"
                                :toggle="$key"
                                :reportId="$audit['id']"
                            />

                        @endforeach
                    @elseif ($file->status === 'auditing')
                        <div class="text-primary-500 text-sm flex items-center space-x-2">
                            <x-svg.spinner class="w-4 h-4 text-primary-500"/>
                            <span>Audit is still processing...</span>
                        </div>
                    @else
                        <div class="text-gray-300 text-sm italic">Manual audit data not available yet.</div>
                    @endif
                </div>

                {{-- AI Audits --}}
                <div x-show="tab === 'ai'" class="space-y-4 mt-6">
                    @if (!empty($audits['ai']))
                        @foreach ($audits['ai'] as $key => $audit)
                            <x-audit.section
                                :label="$key"
                                :title="$audit['title']"
                                :count="$audit['count']"
                                :items="$audit['items']"
                                :limit="$sectionLimits[$key] ?? 5"
                                :expanded="$expandedSections[$key] ?? false"
                                :toggle="$key"
                                :reportId="$audit['id']"
                            />

                        @endforeach
                        @if ($file->status === 'auditing-ai')
                            <div class="text-primary-500 text-sm flex items-center space-x-2">
                                <x-svg.spinner class="w-4 h-4 text-primary-500"/>
                                <span>AI audit is still processing...</span>
                            </div>
                        @endif
                    @elseif ($file->status === 'auditing-ai')
                        <div class="text-primary-500 text-sm flex items-center space-x-2">
                            <x-svg.spinner class="w-4 h-4 text-primary-500"/>
                            <span>AI audit is still processing...</span>
                        </div>
                    @else
                        <div class="text-gray-300 text-sm italic">No AI audit results found yet.</div>
                    @endif
                </div>
                    @endif
            </div>
        @endif
    </div>
        <div class="mt-6 flex space-x-4 justify-end w-full">
            <button wire:click="rerunManualAudit" class="text-xs text-gray-300 hover:text-gray-400">Rerun Manual Audit</button>
            <button wire:click="rerunAIAudit" class="text-xs text-gray-300 hover:text-gray-400">Rerun AI Audit</button>
        </div>
</div>
