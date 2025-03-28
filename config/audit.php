<?php

return [

    'code_description_mismatches' => [
        'title' => 'Code Description Mismatches',
        'description' => 'Flags HCPCS codes used with multiple descriptions.',
        'why' => 'This can indicate inconsistent billing or attempts to disguise duplicate or inflated charges. Descriptions should generally match the HCPCS code exactly.',
    ],

    'price_inconsistencies' => [
        'title' => 'Price Inconsistencies',
        'description' => 'Detects HCPCS codes billed at multiple unit prices.',
        'why' => 'May suggest unauthorized price changes, inconsistent billing practices, or intentional upcharging for the same service or item.',
    ],

    'line_total_mismatches' => [
        'title' => 'Line Item Total Mismatches',
        'description' => 'Checks if unit price × quantity matches the total billed.',
        'why' => 'Helps identify miscalculations, rounding errors, or intentional inflation of charges.',
    ],

    'duplicate_charges' => [
        'title' => 'Duplicate Charges',
        'description' => 'Detects multiple identical charges for the same patient, item, and date.',
        'why' => 'Can reveal billing system errors or fraudulent attempts to double-bill patients or insurers.',
    ],

    'high_unit_prices' => [
        'title' => 'High Unit Prices',
        'description' => 'Finds unusually high prices per unit (e.g. over $500).',
        'why' => 'May point to pricing errors, price gouging, or billing for more expensive versions of standard items.',
    ],

    'suspiciously_high_quantities' => [
        'title' => 'Suspiciously High Quantities',
        'description' => 'Flags charges with very high quantities billed per line.',
        'why' => 'Can indicate stockpiling, overprescription, or billing for items not actually delivered.',
    ],

    'multiple_same_day_charges' => [
        'title' => 'Multiple Same-Day Charges',
        'description' => 'Detects same patient being billed multiple times for the same code on the same day.',
        'why' => 'May expose padded billing or duplicated entries disguised as separate transactions.',
    ],

    'same_code_multiple_patients' => [
        'title' => 'Code Used Across Many Patients',
        'description' => 'Shows HCPCS codes billed across many unique patients.',
        'why' => 'May suggest a code is being overused or inappropriately applied to boost billing.',
    ],

    'missing_payment_info' => [
        'title' => 'Missing Payment Information',
        'description' => 'Identifies claims with missing payment date, number, or type.',
        'why' => 'Could indicate ghost claims, incomplete submissions, or errors that prevent proper processing or auditing.',
    ],

    'code_usage_frequency' => [
        'title' => 'Most Frequently Billed Codes',
        'description' => 'Lists top 10 most-used HCPCS codes.',
        'why' => 'Useful for quickly identifying high-volume billing items that warrant further review for overuse or abuse.',
    ],

];
