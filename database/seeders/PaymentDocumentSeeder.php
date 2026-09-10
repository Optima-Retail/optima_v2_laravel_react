<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PaymentDocument;
use Illuminate\Database\Seeder;

/** Legacy DocumentoPagoEnum IDs preserved. */
final class PaymentDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $documents = [
            ['id' => 1, 'name' => 'Transferencia'],
            ['id' => 2, 'name' => 'Confirming Estandard'],
            ['id' => 4, 'name' => 'Targeta Crédito'],
            ['id' => 5, 'name' => 'Giro Bancario'],
            ['id' => 6, 'name' => 'Cheque / Pagaré'],
            ['id' => 10, 'name' => 'Financiación'],
            ['id' => 11, 'name' => 'Confirming P. P.'],
            ['id' => 12, 'name' => 'Retención'],
            ['id' => 13, 'name' => 'Importado'],
        ];

        foreach ($documents as $document) {
            PaymentDocument::query()->updateOrCreate(
                ['id' => $document['id']],
                [
                    'name' => $document['name'],
                    'is_active' => true,
                ],
            );
        }
    }
}
