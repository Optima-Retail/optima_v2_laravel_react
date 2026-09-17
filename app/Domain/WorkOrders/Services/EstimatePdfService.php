<?php

declare(strict_types=1);

namespace App\Domain\WorkOrders\Services;

use App\Domain\Config\TasksToPerform\Enums\TaskDocumentType;
use App\Models\Article;
use App\Models\Company;
use App\Models\Language;
use App\Models\TaskToPerform;
use App\Models\WorkOrder;
use App\Models\WorkOrderLine;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

final class EstimatePdfService
{
    private const CHAPTER_ARTICLE_CODE = 'CAPITULO';

    public function stream(WorkOrder $estimate): Response
    {
        abort_unless((bool) $estimate->is_estimate, 404);

        $estimate->loadMissing([
            'establishment.company.language',
            'establishment.company.country',
            'establishment.company.province',
            'establishment.billingCompany.country',
            'establishment.billingCompany.province',
            'establishment.language',
            'establishment.responsibleUser:id,name',
            'ownerCompany.country',
            'ownerCompany.province',
            'billingCompany.country',
            'billingCompany.province',
            'currency:id,name,code',
            'type:id,name,code',
            'lines.article.languages.language',
        ]);

        $lang = $this->localeForEstimate($estimate);
        $languageId = $this->languageIdForCode($lang);
        $lines = $estimate->lines;
        $netAmount = $this->netAmount($estimate, $lines);
        $taxRate = (float) ($estimate->tax_rate ?? 0);
        $totalAmount = $estimate->total_amount !== null
            ? (float) $estimate->total_amount
            : ($estimate->total_euros !== null
                ? (float) $estimate->total_euros
                : round($netAmount * (1 + ($taxRate / 100)), 2));

        $billingClient = $this->resolveBillingClient($estimate);
        $issuer = $this->resolveIssuer($estimate);
        $client = $estimate->establishment?->company;

        $pdf = Pdf::loadView('pdf.estimate', [
            'lang' => $lang,
            'issuer' => $issuer,
            'billingClient' => $billingClient,
            'estimateCode' => $estimate->estimate_num ?: $estimate->code,
            'estimateDate' => optional($estimate->created_at)->format('d/m/Y') ?? '',
            'orderType' => $estimate->type?->name ?? '',
            'subject' => $estimate->subject ?? '',
            'establishmentName' => $estimate->establishment?->name ?? '',
            'establishmentCode' => $estimate->establishment?->code ?? '',
            'tasks' => $this->tasksForEstimate($estimate),
            'lineRows' => $this->lineRows($lines, $lang, $languageId),
            'netAmount' => $netAmount,
            'taxRate' => $taxRate,
            'totalAmount' => $totalAmount,
            'currencySymbol' => $this->currencySymbol($estimate->currency?->code),
            'responsibleName' => $estimate->establishment?->responsibleUser?->name ?? '',
            'clientName' => $client?->tradename ?: ($client?->name ?? ''),
        ])
            ->setPaper('a4')
            ->setOption('isRemoteEnabled', true)
            ->setOption('defaultFont', 'DejaVu Sans');

        return $pdf->stream($this->filename($estimate));
    }

    /**
     * Prod: `$presupuesto['establecimiento']['cliente']['idioma']['codigo']`.
     */
    private function localeForEstimate(WorkOrder $estimate): string
    {
        $code = $estimate->establishment?->company?->language?->code
            ?? $estimate->establishment?->language?->code
            ?? config('app.fallback_locale', 'en');

        $code = strtolower(trim((string) $code));

        return $code !== '' ? $code : 'en';
    }

    private function languageIdForCode(string $code): ?int
    {
        $id = Language::query()->where('code', $code)->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * @return array{name: string, tax_id: string, address: string}
     */
    private function resolveIssuer(WorkOrder $estimate): array
    {
        $company = $estimate->ownerCompany ?? $estimate->billingCompany;

        return $this->companyAddressBlock($company);
    }

    /**
     * Prod uses establecimiento.clienteFacturacion for the customer header block.
     *
     * @return array{name: string, tax_id: string, address: string, postal_city: string, country: string}
     */
    private function resolveBillingClient(WorkOrder $estimate): array
    {
        $company = $estimate->establishment?->billingCompany
            ?? $estimate->billingCompany
            ?? $estimate->establishment?->company;

        $block = $this->companyAddressBlock($company);
        $postalCity = trim(implode(' - ', array_filter([
            $company?->postal_code,
            trim(implode(', ', array_filter([
                $company?->city,
                $company?->province?->name,
            ]))),
        ], fn ($v) => filled($v))));

        return [
            ...$block,
            'postal_city' => $postalCity,
            'country' => $company?->country?->name ?? '',
        ];
    }

    /**
     * @return array{name: string, tax_id: string, address: string}
     */
    private function companyAddressBlock(?Company $company): array
    {
        if ($company === null) {
            return ['name' => '', 'tax_id' => '', 'address' => ''];
        }

        $address = trim(implode(', ', array_filter([
            $company->address_line_1,
            $company->address_line_2,
        ], fn ($v) => filled($v))));

        return [
            'name' => (string) ($company->tradename ?: $company->name ?: ''),
            'tax_id' => (string) ($company->tax_id ?? ''),
            'address' => $address,
        ];
    }

    /**
     * @param  Collection<int, WorkOrderLine>  $lines
     * @return list<array{is_chapter: bool, article_label: string, description: string, quantity: string, unit_price: string, amount: string}>
     */
    private function lineRows(Collection $lines, string $lang, ?int $languageId): array
    {
        $rows = [];

        foreach ($lines as $line) {
            $article = $line->article;
            $isChapter = $article !== null
                && strtoupper((string) $article->code) === self::CHAPTER_ARTICLE_CODE;

            $amount = $line->net_amount !== null
                ? (float) $line->net_amount
                : round((float) $line->quantity * (float) $line->unit_price, 2);

            $rows[] = [
                'is_chapter' => $isChapter,
                'article_label' => $this->articleLabel($article, $lang, $languageId),
                'description' => (string) ($line->description ?? ''),
                'quantity' => $this->formatNumber((float) $line->quantity),
                'unit_price' => $this->formatNumber((float) $line->unit_price),
                'amount' => $this->formatNumber($amount),
            ];
        }

        return $rows;
    }

    private function articleLabel(?Article $article, string $lang, ?int $languageId): string
    {
        if ($article === null) {
            return '';
        }

        $translations = $article->languages;

        $translation = $languageId !== null
            ? $translations->firstWhere('language_id', $languageId)
            : null;

        if ($translation === null) {
            $translation = $translations->first(
                fn ($row): bool => strtolower((string) ($row->language?->code ?? '')) === $lang,
            ) ?? $translations->sortBy('language_id')->first();
        }

        return (string) ($translation?->name ?: $article->code ?: '');
    }

    /**
     * @param  Collection<int, WorkOrderLine>  $lines
     */
    private function netAmount(WorkOrder $estimate, Collection $lines): float
    {
        if ($lines->isNotEmpty()) {
            return round((float) $lines->sum(function (WorkOrderLine $line): float {
                if ($line->net_amount !== null) {
                    return (float) $line->net_amount;
                }

                return (float) $line->quantity * (float) $line->unit_price;
            }), 2);
        }

        if ($estimate->net_amount !== null) {
            return (float) $estimate->net_amount;
        }

        if ($estimate->total_euros !== null) {
            return (float) $estimate->total_euros;
        }

        return (float) ($estimate->total_amount ?? 0);
    }

    /**
     * @return Collection<int, TaskToPerform>
     */
    private function tasksForEstimate(WorkOrder $estimate): Collection
    {
        $documentType = $estimate->isEstimate()
            ? TaskDocumentType::Estimate
            : TaskDocumentType::WorkOrder;

        return TaskToPerform::query()
            ->where('document_id', $estimate->id)
            ->where('document_type', $documentType->value)
            ->orderBy('id')
            ->get();
    }

    private function currencySymbol(?string $code): string
    {
        return match (strtoupper((string) $code)) {
            'EUR', '' => '€',
            'GBP' => '£',
            'USD' => '$',
            default => ' '.strtoupper((string) $code),
        };
    }

    private function formatNumber(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function filename(WorkOrder $estimate): string
    {
        $code = $estimate->estimate_num
            ?: $estimate->code
            ?: ('estimate-'.$estimate->id);

        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $code) ?: 'estimate';

        return $safe.'.pdf';
    }
}
