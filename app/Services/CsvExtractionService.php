<?php

namespace App\Services;

use App\Models\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;
use Carbon\Carbon;

class CsvExtractionService
{
    public function handle(File $file): void
    {
        $path = Storage::disk('public')->path($file->file_dir . '/' . $file->filename);

        $csv = Reader::createFromPath($path, 'r');
        $csv->setHeaderOffset(0); // assumes the first row is headers

        $records = $csv->getRecords();

        $batch = [];
        $batchSize = 500;

        foreach ($records as $record) {
            $batch[] = [
                'file_id' => $file->id,
                'PatientID' => $record['PatientID'],
                'InsuredInsId' => $record['InsuredInsId'],
                'OrderNumber' => $record['OrderNumber'],
                'Payee' => $record['Payee'] ?? null,
                'DealerInvoiceNo' => $record['DealerInvoiceNo'] ?? null,
                'DOS' => $this->parseDate($record['DOS'] ?? null),
                'HCPCsCode' => $record['HCPCsCode'] ?? null,
                'Modifier' => $record['Modifier'] ?? null,
                'Description' => $record['Description'] ?? null,
                'BilledQuantity' => (int) ($record['BilledQuantity'] ?? 0),
                'ProviderAmountEach' => (float) ($record['ProviderAmountEach'] ?? 0),
                'ProviderAmountTotal' => (float) ($record['ProviderAmountTotal'] ?? 0),
                'LineItemAPBalance' => (float) ($record['LineItemAPBalance'] ?? 0),
                'AppliedAPAmount' => (float) ($record['AppliedAPAmount'] ?? 0),
                'PaymentNumber' => $record['PaymentNumber'] ?? null,
                'PaymentType' => $record['PaymentType'] ?? null,
                'PaymentDate' => $this->parseDate($record['PaymentDate'] ?? null),
                'DuplicateOrderStop' => $record['DuplicateOrderStop'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($batch) >= $batchSize) {
                DB::table('invoices')->insert($batch);
                $batch = [];
            }
        }

        // Insert any remaining records
        if (!empty($batch)) {
            DB::table('invoices')->insert($batch);
        }
    }

    protected function parseDate(?string $value): ?string
    {
        try {
            return $value ? Carbon::createFromFormat('n/j/y', $value)?->toDateString() : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
