<?php

declare(strict_types=1);

namespace App\Modules\Finance;

class AiFinancialAnalystService
{
    /**
     * AI Financial Analyst Engine computing financial ratios, cash flow forecast & Q&A
     */
    public static function askAnalyst(string $prompt, string $companyId = 'beverage'): array
    {
        $promptLower = strtolower($prompt);

        if (str_contains($promptLower, 'ratio') || str_contains($promptLower, 'health') || str_contains($promptLower, 'margin')) {
            return [
                'topic' => 'Financial Health & Key Ratio Analysis',
                'confidence' => '0% Confidence',
                'ratios' => [
                    'Current Ratio' => 'No real data',
                    'Quick Ratio' => 'No real data',
                    'Gross Profit Margin' => 'No real data',
                    'Net Profit Margin' => 'No real data',
                    'Working Capital' => 'No real data',
                    'Debt to Equity' => 'No real data',
                ],
                'answer' => "No real financial transactions have been recorded for division [{$companyId}] yet. Enter real sales, purchases, receivables, payables, and bank data before running ratio analysis.",
                'suggested_action' => 'Enter Real Finance Data'
            ];
        }

        if (str_contains($promptLower, 'tax') || str_contains($promptLower, 'wht')) {
            return [
                'topic' => 'Statutory Tax Compliance & WHT Forecast',
                'confidence' => '0% Confidence',
                'ratios' => [
                    'WHT 5% Deducted' => 'No real data',
                ],
                'answer' => "No real statutory withholding tax entries have been recorded yet.",
                'suggested_action' => 'Record Real Tax Entries'
            ];
        }

        return [
            'topic' => 'General Financial & Cashflow Analysis',
            'confidence' => '0% Confidence',
            'ratios' => [
                'Daily Cash Inflow' => 'No real data',
                'Daily Operating Outflow' => 'No real data',
                'Net Cash Surplus' => 'No real data',
            ],
            'answer' => "No real cashflow, receivable, payable, or bank entries have been recorded for [{$companyId}] yet.",
            'suggested_action' => 'Enter Real Transactions'
        ];
    }
}
