<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ExpenseType;
use Illuminate\Database\Seeder;

/**
 * Expense types from optimaback TipoGastoEnum / tipos_de_gasto.
 * Legacy IDs preserved for purchase invoices / movements migration.
 */
final class ExpenseTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['id' => 500, 'name' => 'Colaboradores'],
            ['id' => 501, 'name' => 'Alquileres'],
            ['id' => 502, 'name' => 'Gastos de Arranque'],
            ['id' => 503, 'name' => 'Sueldos'],
            ['id' => 504, 'name' => 'Otros Gastos'],
            ['id' => 505, 'name' => 'Colegio'],
            ['id' => 506, 'name' => 'Gastos despacho'],
            ['id' => 507, 'name' => 'Gastos Financieros'],
            ['id' => 508, 'name' => 'Devolucion Pago'],
            ['id' => 509, 'name' => 'Desplazamiento y manutención'],
            ['id' => 510, 'name' => 'Seguridad Social'],
            ['id' => 511, 'name' => 'Autonomos'],
            ['id' => 512, 'name' => 'FIANZAS'],
            ['id' => 513, 'name' => 'Gastos por eventos no repetibles y no operativos (Desarrollo software externo, recruiters, obras oficinas, etc)'],
            ['id' => 514, 'name' => 'Amortizaciones mobiliario y productos para operativa'],
            ['id' => 515, 'name' => 'Trademark'],
            ['id' => 600, 'name' => 'Compra de mercaderias'],
            ['id' => 607, 'name' => 'Trabajos realizados por otras empresas (no profesionales independientes)'],
            ['id' => 621, 'name' => 'Arrendamintos y canones'],
            ['id' => 622, 'name' => 'Reparaciones y conservacion'],
            ['id' => 623, 'name' => 'Servicios de profesionales independientes'],
            ['id' => 624, 'name' => 'Transportes (Gasolina, tarjetas metro, etc)'],
            ['id' => 625, 'name' => 'Primas de seguro'],
            ['id' => 626, 'name' => 'Servicios bancarios y similares'],
            ['id' => 627, 'name' => 'Publicidad, propaganda y relaciones públicas'],
            ['id' => 628, 'name' => 'Suministros (agua, luz, etc)'],
            ['id' => 629, 'name' => 'Otros servicios'],
            ['id' => 631, 'name' => 'Otros tributos (ibi, tasa basuras, etc)'],
            ['id' => 640, 'name' => 'Sueldos y salarios'],
            ['id' => 642, 'name' => 'Seguridad Social a cargo de la empresa (titular y empleados)'],
            ['id' => 649, 'name' => 'Otros gastos sociales'],
            ['id' => 660, 'name' => 'Gastos financieros (intereses y comisiones préstamos)'],
            ['id' => 678, 'name' => 'Gastos extraordinarios'],
            ['id' => 700, 'name' => 'Venta de Mercancías'],
            ['id' => 705, 'name' => 'Prestación de servicios'],
            ['id' => 6231, 'name' => 'Servicios de profesionales sin alta en act economica'],
            ['id' => 6291, 'name' => 'Comunicaciones (ADSL,movil, etc)'],
            ['id' => 6292, 'name' => 'Viajes, estancias y desplazamientos'],
            ['id' => 6293, 'name' => 'Material de oficina'],
            ['id' => 6294, 'name' => 'Material informatico'],
            ['id' => 6621, 'name' => 'Intereses bancarios'],
            ['id' => 6622, 'name' => 'Intereses prestamos bancarios'],
            ['id' => 60099, 'name' => 'Otros aprovisionamientos'],
        ];

        foreach ($types as $type) {
            ExpenseType::query()->updateOrCreate(
                ['id' => $type['id']],
                ['name' => $type['name']],
            );
        }
    }
}
