@extends('layouts.app')

@section('content')
    @php
        $config = config("audit." . $report->key);
        $description = $config['description'] ?? null;
        $whyItMatters = $config['why'] ?? null;
    @endphp

    <div class="max-w-6xl mx-auto mt-12 space-y-6 pb-12">
        <div class="bg-white p-6 rounded-xl shadow">

            <a href="{{ route('files.show', $report->file_id) }}" class="text-xl text-primary-800 font-semibold mb-4 flex items-center space-x-2 py-2 px-4 bg-primary-100 rounded hover:bg-primary-200 transition">
                <x-svg.file class="w-5 h-5"/>
                <span>{{ $report->file->original_filename }}</span>
            </a>

            <div class="mt-6 border rounded p-4">

                <div class="rounded bg-primary-100 p-2 mb-4">
                    <h2 class="text-xl font-semibold mb-4 flex items-center space-x-2">
                        <span>{{ $report->title }}</span>
                    </h2>

                    @if ($description)
                        <div class="flex items-start space-x-2">
                            <x-svg.info-circle class="w-5 h-5 text-primary-500"/>
                            <div>
                                <p class="text-sm text-gray-600 mb-2">
                                    {{ $description }}
                                </p>

                                @if ($whyItMatters)
                                    <p class="text-xs text-gray-500 italic mb-4">
                                        <span class="text-gray-600">Why it matters:</span> {{ $whyItMatters }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

                <div class="text-sm text-gray-700">
                    <div class="flex justify-between items-start gap-4">
                        <div class="w-full grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm text-gray-700">
                            @foreach ($item->data as $key => $val)
                                <div>
                                <span
                                    class="font-medium text-gray-800">{{ ucwords(str_replace('_', ' ', $key)) }}:</span>
                                    <span class="ml-1">
                                    @if (is_array($val))
                                            {{ collect($val)->implode(', ') }}
                                        @else
                                            {{ $val }}
                                        @endif
                                </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            @if ($item->invoices->isNotEmpty())

                <div class="mt-6 border rounded p-4">
                    <h5 class="font-medium text-lg mb-3 ">Associated {{ Str::plural('Invoice', $item->invoices->count()) }}</h5>

                    <div class="space-y-4">
                        @foreach($item->invoices as $invoice)
                            <div
                                class="w-full rounded bg-gray-50 border border-primary-200 p-4 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm text-gray-700">
                                <div><span class="text-gray-800 font-medium">PatientID:</span><span
                                        class="ml-1">{{ $invoice->PatientID }}</span></div>
                                <div><span class="text-gray-800 font-medium">InsuredInsId:</span><span
                                        class="ml-1">{{ $invoice->InsuredInsId }}</span></div>
                                <div><span class="text-gray-800 font-medium">OrderNumber:</span><span
                                        class="ml-1">{{ $invoice->OrderNumber }}</span></div>
                                <div><span class="text-gray-800 font-medium">Payee:</span><span
                                        class="ml-1">{{ $invoice->Payee }}</span></div>
                                <div><span class="text-gray-800 font-medium">DealerInvoiceNo:</span><span
                                        class="ml-1">{{ $invoice->DealerInvoiceNo }}</span></div>
                                <div><span class="text-gray-800 font-medium">DOS:</span><span
                                        class="ml-1">{{ $invoice->DOS }}</span></div>
                                <div><span class="text-gray-800 font-medium">HCPCsCode:</span><span
                                        class="ml-1">{{ $invoice->HCPCsCode }}</span></div>
                                <div><span class="text-gray-800 font-medium">Modifier:</span><span
                                        class="ml-1">{{ $invoice->Modifier }}</span></div>
                                <div><span class="text-gray-800 font-medium">Description:</span><span
                                        class="ml-1">{{ $invoice->Description }}</span></div>
                                <div><span class="text-gray-800 font-medium">BilledQuantity:</span><span
                                        class="ml-1">{{ $invoice->BilledQuantity }}</span></div>
                                <div><span class="text-gray-800 font-medium">ProviderAmountEach:</span><span
                                        class="ml-1">{{ $invoice->ProviderAmountEach }}</span></div>
                                <div><span class="text-gray-800 font-medium">ProviderAmountTotal:</span><span
                                        class="ml-1">{{ $invoice->ProviderAmountTotal }}</span></div>
                                <div><span class="text-gray-800 font-medium">LineItemAPBalance:</span><span
                                        class="ml-1">{{ $invoice->LineItemAPBalance }}</span></div>
                                <div><span class="text-gray-800 font-medium">AppliedAPAmount:</span><span
                                        class="ml-1">{{ $invoice->AppliedAPAmount }}</span></div>
                                <div><span class="text-gray-800 font-medium">PaymentNumber:</span><span
                                        class="ml-1">{{ $invoice->PaymentNumber }}</span></div>
                                <div><span class="text-gray-800 font-medium">PaymentType:</span><span
                                        class="ml-1">{{ $invoice->PaymentType }}</span></div>
                                <div><span class="text-gray-800 font-medium">PaymentDate:</span><span
                                        class="ml-1">{{ $invoice->PaymentDate }}</span></div>
                                <div><span class="text-gray-800 font-medium">DuplicateOrderStop:</span><span
                                        class="ml-1">{{ $invoice->DuplicateOrderStop }}</span></div>
                            </div>
                        @endforeach
                    </div>
                </div>

            @endif

        </div>
        <a href="{{ route('audit.report.show', $report->id) }}"
            class="mt-6 inline-block text-primary-600 hover:underline">
            ← Back to Report
        </a>
    </div>
@endsection
