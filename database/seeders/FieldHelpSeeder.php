<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Config\FieldHelps\Services\FieldHelpService;
use App\Models\FieldHelp;
use Illuminate\Database\Seeder;

/**
 * Curated field-help definitions for Optima FM forms (companies, relationships, establishments, brands).
 *
 * Idempotent: re-running updates existing rows by key via FieldHelpService.
 */
final class FieldHelpSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(FieldHelpService::class);

        foreach ($this->definitions() as $definition) {
            $existing = FieldHelp::query()->where('key', $definition['key'])->first();

            if ($existing) {
                $service->update($existing, $definition);

                continue;
            }

            $service->create($definition);
        }
    }

    /**
     * @return list<array{
     *     key: string,
     *     context: string,
     *     sort_order: int,
     *     translations: array{
     *         en: array{title: string, description: string},
     *         es: array{title: string, description: string}
     *     }
     * }>
     */
    private function definitions(): array
    {
        return [
            // ── Companies ──────────────────────────────────────────────────
            [
                'key' => 'companies.tax_id',
                'context' => 'companies',
                'sort_order' => 10,
                'translations' => [
                    'en' => [
                        'title' => 'Tax ID (NIF / CIF / VAT)',
                        'description' => 'Legal tax identification of the company. In Spain this is typically the NIF or CIF; for EU entities use the VAT number. Used on invoices, contracts, and fiscal reports.',
                    ],
                    'es' => [
                        'title' => 'NIF / CIF / IVA',
                        'description' => 'Identificación fiscal legal de la empresa. En España suele ser el NIF o CIF; para entidades de la UE use el NIF-IVA. Se usa en facturas, contratos e informes fiscales.',
                    ],
                ],
            ],
            [
                'key' => 'companies.kind',
                'context' => 'companies',
                'sort_order' => 20,
                'translations' => [
                    'en' => [
                        'title' => 'Company kind',
                        'description' => 'High-level role of this legal entity in Optima (for example client group, supplier, or internal company). It drives which relationship workflows and billing rules apply.',
                    ],
                    'es' => [
                        'title' => 'Tipo de empresa',
                        'description' => 'Rol principal de esta entidad legal en Optima (por ejemplo grupo cliente, proveedor o empresa interna). Condiciona los flujos de relación y las reglas de facturación aplicables.',
                    ],
                ],
            ],
            [
                'key' => 'companies.person_type',
                'context' => 'companies',
                'sort_order' => 30,
                'translations' => [
                    'en' => [
                        'title' => 'Person type',
                        'description' => 'Whether the party is a natural person or a legal entity. Affects tax document requirements, invoice headers, and some Spanish fiscal validations.',
                    ],
                    'es' => [
                        'title' => 'Tipo de persona',
                        'description' => 'Indica si es persona física o jurídica. Afecta a los documentos fiscales exigidos, la cabecera de factura y algunas validaciones tributarias en España.',
                    ],
                ],
            ],
            [
                'key' => 'companies.residence_country_id',
                'context' => 'companies',
                'sort_order' => 40,
                'translations' => [
                    'en' => [
                        'title' => 'Country of residence',
                        'description' => 'Fiscal residence country of the company. Used for VAT treatment, withholding rules, and whether EU reverse-charge or domestic invoicing applies.',
                    ],
                    'es' => [
                        'title' => 'País de residencia',
                        'description' => 'País de residencia fiscal de la empresa. Determina el tratamiento de IVA, retenciones y si aplica inversión del sujeto pasivo UE o facturación nacional.',
                    ],
                ],
            ],
            [
                'key' => 'companies.legacy_erp_id',
                'context' => 'companies',
                'sort_order' => 50,
                'translations' => [
                    'en' => [
                        'title' => 'Legacy ERP ID',
                        'description' => 'Identifier of this company in the previous ERP or Optima v1. Keep stable for migrations, historical invoice matching, and dual-running integrations.',
                    ],
                    'es' => [
                        'title' => 'ID ERP legado',
                        'description' => 'Identificador de esta empresa en el ERP anterior u Optima v1. Manténgalo estable para migraciones, cruce de facturas históricas e integraciones en paralelo.',
                    ],
                ],
            ],

            // ── Company relationships ──────────────────────────────────────
            [
                'key' => 'company_relationships.owner_reference',
                'context' => 'company_relationships',
                'sort_order' => 10,
                'translations' => [
                    'en' => [
                        'title' => 'Owner reference',
                        'description' => 'Internal code your organisation uses for this related party (customer or supplier account code). Appears on work orders, invoices, and exports so finance and operations share the same reference.',
                    ],
                    'es' => [
                        'title' => 'Referencia del propietario',
                        'description' => 'Código interno que su organización usa para esta parte relacionada (cuenta de cliente o proveedor). Aparece en órdenes de trabajo, facturas y exportaciones para alinear finanzas y operaciones.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.related_reference',
                'context' => 'company_relationships',
                'sort_order' => 20,
                'translations' => [
                    'en' => [
                        'title' => 'Related party reference',
                        'description' => 'Code that the related company uses for you (their vendor or customer number). Useful when the client’s portal or EDI requires their own account ID on documents.',
                    ],
                    'es' => [
                        'title' => 'Referencia de la parte relacionada',
                        'description' => 'Código que la empresa relacionada usa para usted (su número de proveedor o cliente). Útil cuando el portal o EDI del cliente exige su propio ID de cuenta en los documentos.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.classification',
                'context' => 'company_relationships',
                'sort_order' => 30,
                'translations' => [
                    'en' => [
                        'title' => 'Relationship classification',
                        'description' => 'Business classification of the link (for example key account, franchisee, or preferred supplier). Used for reporting, SLA templates, and commercial rules without changing the legal company record.',
                    ],
                    'es' => [
                        'title' => 'Clasificación de la relación',
                        'description' => 'Clasificación comercial del vínculo (por ejemplo cuenta clave, franquiciado o proveedor preferente). Sirve para reporting, plantillas de SLA y reglas comerciales sin alterar la ficha legal de la empresa.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.rating_type_id',
                'context' => 'company_relationships',
                'sort_order' => 40,
                'translations' => [
                    'en' => [
                        'title' => 'Rating type',
                        'description' => 'Scorecard or rating scheme applied to this relationship (quality, service level, or commercial rating). Determines which metrics feed Optima score and customer score.',
                    ],
                    'es' => [
                        'title' => 'Tipo de valoración',
                        'description' => 'Esquema de puntuación o rating aplicado a esta relación (calidad, nivel de servicio o valoración comercial). Define qué métricas alimentan la puntuación Optima y la del cliente.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.integration_external_id',
                'context' => 'company_relationships',
                'sort_order' => 50,
                'translations' => [
                    'en' => [
                        'title' => 'Integration external ID',
                        'description' => 'Stable ID of this relationship in an external system (retailer portal, GMAO, or accounting platform). Required for bidirectional sync of work orders and invoices.',
                    ],
                    'es' => [
                        'title' => 'ID externo de integración',
                        'description' => 'ID estable de esta relación en un sistema externo (portal del retailer, GMAO o plataforma contable). Necesario para la sincronización bidireccional de OT y facturas.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.reported_customer_relationship_id',
                'context' => 'company_relationships',
                'sort_order' => 60,
                'translations' => [
                    'en' => [
                        'title' => 'Reported customer relationship',
                        'description' => 'When this link is a subsidiary or franchise of a larger customer, point to the parent customer relationship used for consolidated reporting and brand-level KPIs.',
                    ],
                    'es' => [
                        'title' => 'Relación de cliente reportada',
                        'description' => 'Si este vínculo es una filial o franquicia de un cliente mayor, indique la relación de cliente padre usada para reporting consolidado y KPIs a nivel de marca.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.archetype',
                'context' => 'company_relationships',
                'sort_order' => 70,
                'translations' => [
                    'en' => [
                        'title' => 'Commercial archetype',
                        'description' => 'Preset commercial profile (for example mall retailer, supermarket chain, or industrial site). Applies default billing, grouping, and compliance flags when onboarding the relationship.',
                    ],
                    'es' => [
                        'title' => 'Arquetipo comercial',
                        'description' => 'Perfil comercial predefinido (por ejemplo retailer de centro comercial, cadena de supermercados o centro industrial). Aplica flags por defecto de facturación, agrupación y cumplimiento al dar de alta la relación.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.quote_close_days',
                'context' => 'company_relationships',
                'sort_order' => 80,
                'translations' => [
                    'en' => [
                        'title' => 'Quote close days',
                        'description' => 'Maximum days a quotation stays open before it is marked expired or requires renegotiation. Aligns sales follow-up with the client’s typical approval cycle.',
                    ],
                    'es' => [
                        'title' => 'Días de cierre de presupuesto',
                        'description' => 'Días máximos que un presupuesto permanece abierto antes de marcarse como caducado o exigir renegociación. Alinea el seguimiento comercial con el ciclo de aprobación típico del cliente.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.recurring_meeting_frequency',
                'context' => 'company_relationships',
                'sort_order' => 90,
                'translations' => [
                    'en' => [
                        'title' => 'Recurring meeting frequency',
                        'description' => 'How often operational review meetings with this client or supplier should be scheduled (weekly, monthly, quarterly). Used by account managers to plan FM performance reviews.',
                    ],
                    'es' => [
                        'title' => 'Frecuencia de reuniones recurrentes',
                        'description' => 'Cada cuánto deben programarse las reuniones de revisión operativa con este cliente o proveedor (semanal, mensual, trimestral). Lo usan los account managers para planificar revisiones de rendimiento FM.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.sales_feedback_meeting_frequency',
                'context' => 'company_relationships',
                'sort_order' => 100,
                'translations' => [
                    'en' => [
                        'title' => 'Sales feedback meeting frequency',
                        'description' => 'Cadence of commercial feedback meetings (pipeline, claims, upsell). Separate from operational reviews so sales and site operations stay on different agendas.',
                    ],
                    'es' => [
                        'title' => 'Frecuencia de reuniones de feedback comercial',
                        'description' => 'Periodicidad de las reuniones de feedback comercial (pipeline, reclamaciones, upsell). Separada de las revisiones operativas para que ventas y operaciones de centro mantengan agendas distintas.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.group_preventives_by',
                'context' => 'company_relationships',
                'sort_order' => 110,
                'translations' => [
                    'en' => [
                        'title' => 'Group preventives by',
                        'description' => 'Criteria used to batch preventive maintenance work orders for invoicing or planning (by store, by region, by asset type, or by period). Controls how PM tickets roll up on the client invoice.',
                    ],
                    'es' => [
                        'title' => 'Agrupar preventivos por',
                        'description' => 'Criterio para agrupar las OT de mantenimiento preventivo en facturación o planificación (por tienda, zona, tipo de activo o periodo). Define cómo se consolidan los preventivos en la factura del cliente.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.group_correctives_by',
                'context' => 'company_relationships',
                'sort_order' => 120,
                'translations' => [
                    'en' => [
                        'title' => 'Group correctives by',
                        'description' => 'Criteria used to batch corrective (reactive) work orders for invoicing. Many retail clients group by store and month; others require one invoice line per ticket.',
                    ],
                    'es' => [
                        'title' => 'Agrupar correctivos por',
                        'description' => 'Criterio para agrupar las OT correctivas (reactivas) en facturación. Muchos retailers agrupan por tienda y mes; otros exigen una línea de factura por cada ticket.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.group_zero_cost_work_orders',
                'context' => 'company_relationships',
                'sort_order' => 130,
                'translations' => [
                    'en' => [
                        'title' => 'Group zero-cost work orders',
                        'description' => 'When enabled, work orders with zero billable amount (warranty, courtesy, or included in contract) are grouped together instead of creating separate zero-value invoice lines.',
                    ],
                    'es' => [
                        'title' => 'Agrupar OT a coste cero',
                        'description' => 'Si está activo, las OT con importe facturable cero (garantía, cortesía o incluidas en contrato) se agrupan en lugar de generar líneas de factura a cero por separado.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.load_materials_on_corrective',
                'context' => 'company_relationships',
                'sort_order' => 140,
                'translations' => [
                    'en' => [
                        'title' => 'Load materials on corrective',
                        'description' => 'Automatically attach catalogue materials or stock consumption to corrective work orders for this client. Disable when the client only pays labour or supplies materials themselves.',
                    ],
                    'es' => [
                        'title' => 'Cargar materiales en correctivo',
                        'description' => 'Adjunta automáticamente materiales de catálogo o consumo de stock a las OT correctivas de este cliente. Desactívelo si el cliente solo paga mano de obra o aporta él los materiales.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.group_preventive_and_corrective',
                'context' => 'company_relationships',
                'sort_order' => 150,
                'translations' => [
                    'en' => [
                        'title' => 'Group preventive and corrective',
                        'description' => 'Allow preventive and corrective work orders to share the same invoice grouping batch. Turn off when the client requires separate PM and CM invoice documents.',
                    ],
                    'es' => [
                        'title' => 'Agrupar preventivo y correctivo',
                        'description' => 'Permite que preventivos y correctivos compartan el mismo lote de agrupación de factura. Desactívelo si el cliente exige documentos de factura separados para PM y CM.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.requires_purchase_order',
                'context' => 'company_relationships',
                'sort_order' => 160,
                'translations' => [
                    'en' => [
                        'title' => 'Requires purchase order',
                        'description' => 'A valid client purchase order (PO) must be present before the work order can be closed or invoiced. Typical for large retail chains with strict AP controls.',
                    ],
                    'es' => [
                        'title' => 'Requiere pedido de compra (PO)',
                        'description' => 'Debe existir un pedido de compra (PO) válido del cliente antes de cerrar o facturar la OT. Habitual en grandes cadenas retail con control estricto de cuentas a pagar.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.requires_requester',
                'context' => 'company_relationships',
                'sort_order' => 170,
                'translations' => [
                    'en' => [
                        'title' => 'Requires requester',
                        'description' => 'Every work order must name the person who requested the intervention (store manager, facilities contact). Needed for client audit trails and SLA clock start.',
                    ],
                    'es' => [
                        'title' => 'Requiere solicitante',
                        'description' => 'Cada OT debe indicar quién solicitó la intervención (gerente de tienda, contacto de facilities). Necesario para auditorías del cliente y el arranque del reloj de SLA.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.requires_justification',
                'context' => 'company_relationships',
                'sort_order' => 180,
                'translations' => [
                    'en' => [
                        'title' => 'Requires justification',
                        'description' => 'Technicians or planners must enter a free-text justification (cause, scope, or exception) before completing or invoicing certain work. Supports quality and cost-control reviews.',
                    ],
                    'es' => [
                        'title' => 'Requiere justificación',
                        'description' => 'Los técnicos o planificadores deben introducir una justificación en texto libre (causa, alcance o excepción) antes de completar o facturar ciertos trabajos. Apoya revisiones de calidad y control de costes.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.send_invoices_individually',
                'context' => 'company_relationships',
                'sort_order' => 190,
                'translations' => [
                    'en' => [
                        'title' => 'Send invoices individually',
                        'description' => 'Each invoice is emailed as a separate document instead of a consolidated pack. Use when the client’s AP portal accepts one PDF per invoice number.',
                    ],
                    'es' => [
                        'title' => 'Enviar facturas individualmente',
                        'description' => 'Cada factura se envía por correo como documento separado en lugar de un paquete consolidado. Úselo cuando el portal de AP del cliente acepta un PDF por número de factura.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.is_quality_control_contactable',
                'context' => 'company_relationships',
                'sort_order' => 200,
                'translations' => [
                    'en' => [
                        'title' => 'Quality control contactable',
                        'description' => 'Quality or compliance teams may contact this party for audits, mystery visits, or post-intervention surveys. Disable for relationships that must not receive QC outreach.',
                    ],
                    'es' => [
                        'title' => 'Contactable por control de calidad',
                        'description' => 'Los equipos de calidad o compliance pueden contactar a esta parte para auditorías, visitas misterio o encuestas post-intervención. Desactívelo si la relación no debe recibir comunicaciones de QC.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.requires_client_informed_check',
                'context' => 'company_relationships',
                'sort_order' => 210,
                'translations' => [
                    'en' => [
                        'title' => 'Requires client informed check',
                        'description' => 'Before closing the work order, the technician must confirm the store or client contact was informed of the intervention outcome. Common contractual requirement in retail FM.',
                    ],
                    'es' => [
                        'title' => 'Requiere check de cliente informado',
                        'description' => 'Antes de cerrar la OT, el técnico debe confirmar que el contacto de tienda o cliente fue informado del resultado de la intervención. Exigencia contractual habitual en FM retail.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.requires_intervention_scheduled_check',
                'context' => 'company_relationships',
                'sort_order' => 220,
                'translations' => [
                    'en' => [
                        'title' => 'Requires intervention scheduled check',
                        'description' => 'Forces confirmation that the visit was pre-scheduled with the site (appointment agreed) before field work is marked as done. Reduces no-access and after-hours disputes.',
                    ],
                    'es' => [
                        'title' => 'Requiere check de intervención programada',
                        'description' => 'Obliga a confirmar que la visita se acordó previamente con el centro (cita concertada) antes de marcar el trabajo de campo como hecho. Reduce disputas por falta de acceso y fuera de horario.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.requires_budget_approval_limit',
                'context' => 'company_relationships',
                'sort_order' => 230,
                'translations' => [
                    'en' => [
                        'title' => 'Requires budget approval limit',
                        'description' => 'Work above a configured amount needs prior budget approval from the client before execution or invoicing. Enforces the agreed spending threshold per ticket or per store.',
                    ],
                    'es' => [
                        'title' => 'Requiere límite de aprobación de presupuesto',
                        'description' => 'Los trabajos por encima de un importe configurado necesitan aprobación previa de presupuesto del cliente antes de ejecutar o facturar. Aplica el umbral de gasto acordado por ticket o por tienda.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.is_intercompany',
                'context' => 'company_relationships',
                'sort_order' => 240,
                'translations' => [
                    'en' => [
                        'title' => 'Intercompany relationship',
                        'description' => 'Marks billing between companies of the same corporate group. Intercompany invoices may use special tax treatment, transfer pricing, and internal cost centres.',
                    ],
                    'es' => [
                        'title' => 'Relación intercompañía',
                        'description' => 'Marca la facturación entre empresas del mismo grupo corporativo. Las facturas intercompañía pueden usar tratamiento fiscal especial, precios de transferencia y centros de coste internos.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.optima_score',
                'context' => 'company_relationships',
                'sort_order' => 250,
                'translations' => [
                    'en' => [
                        'title' => 'Optima score',
                        'description' => 'Internal performance score Optima assigns to this relationship (SLA compliance, reopen rate, documentation quality). Used by account managers; not usually shown to the client.',
                    ],
                    'es' => [
                        'title' => 'Puntuación Optima',
                        'description' => 'Puntuación interna de rendimiento que Optima asigna a esta relación (cumplimiento de SLA, tasa de reapertura, calidad documental). Uso interno de account managers; normalmente no se muestra al cliente.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.customer_score',
                'context' => 'company_relationships',
                'sort_order' => 260,
                'translations' => [
                    'en' => [
                        'title' => 'Customer score',
                        'description' => 'Score or rating provided by the customer (portal NPS, satisfaction surveys, or contractual KPI). Combined with Optima score for relationship health.',
                    ],
                    'es' => [
                        'title' => 'Puntuación del cliente',
                        'description' => 'Puntuación o rating aportado por el cliente (NPS de portal, encuestas de satisfacción o KPI contractual). Se combina con la puntuación Optima para la salud de la relación.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.average_score',
                'context' => 'company_relationships',
                'sort_order' => 270,
                'translations' => [
                    'en' => [
                        'title' => 'Average score',
                        'description' => 'Aggregated score blending Optima and customer ratings for dashboards and account prioritisation. Usually calculated; edit only if you need a manual override.',
                    ],
                    'es' => [
                        'title' => 'Puntuación media',
                        'description' => 'Puntuación agregada que combina las valoraciones Optima y del cliente para cuadros de mando y priorización de cuentas. Suele calcularse; edítela solo si necesita un override manual.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.is_field_technician',
                'context' => 'company_relationships',
                'sort_order' => 280,
                'translations' => [
                    'en' => [
                        'title' => 'Field technician',
                        'description' => 'Indicates this related party is a field technician (own staff or subcontractor) who can be assigned to work orders, receive mobile jobs, and report completions.',
                    ],
                    'es' => [
                        'title' => 'Técnico de campo',
                        'description' => 'Indica que esta parte relacionada es un técnico de campo (propio o subcontrata) que puede asignarse a OT, recibir trabajos en móvil y reportar cierres.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.is_creditor',
                'context' => 'company_relationships',
                'sort_order' => 290,
                'translations' => [
                    'en' => [
                        'title' => 'Creditor',
                        'description' => 'Treat this party as a creditor / payable supplier in finance flows. Enables purchase invoices, payment runs, and creditor statements against the relationship.',
                    ],
                    'es' => [
                        'title' => 'Acreedor',
                        'description' => 'Trata a esta parte como acreedor / proveedor a pagar en los flujos financieros. Habilita facturas de compra, remesas de pago y extractos de acreedor sobre la relación.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.has_garnishment',
                'context' => 'company_relationships',
                'sort_order' => 300,
                'translations' => [
                    'en' => [
                        'title' => 'Has garnishment',
                        'description' => 'Legal embargos or wage/payment garnishments apply to this supplier. Payment automation should route amounts according to garnishment rules before paying the creditor.',
                    ],
                    'es' => [
                        'title' => 'Tiene embargo',
                        'description' => 'Existen embargos legales o retenciones de pago sobre este proveedor. La automatización de pagos debe aplicar las reglas de embargo antes de abonar al acreedor.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.whatsapp_messaging_authorized',
                'context' => 'company_relationships',
                'sort_order' => 310,
                'translations' => [
                    'en' => [
                        'title' => 'WhatsApp messaging authorised',
                        'description' => 'Explicit consent to send operational WhatsApp messages (appointment reminders, status updates). Required under privacy rules; do not enable without documented authorisation.',
                    ],
                    'es' => [
                        'title' => 'Mensajería WhatsApp autorizada',
                        'description' => 'Consentimiento explícito para enviar mensajes operativos por WhatsApp (citas, actualizaciones de estado). Exigido por privacidad; no lo active sin autorización documentada.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.legacy_status_id',
                'context' => 'company_relationships',
                'sort_order' => 320,
                'translations' => [
                    'en' => [
                        'title' => 'Legacy status ID',
                        'description' => 'Status code carried over from the previous system. Kept for migration mapping and historical filters; prefer the current Optima status for new workflows.',
                    ],
                    'es' => [
                        'title' => 'ID de estado legado',
                        'description' => 'Código de estado heredado del sistema anterior. Se mantiene para mapeo de migración y filtros históricos; use el estado actual de Optima en los flujos nuevos.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.invoice_at_month_end',
                'context' => 'company_relationships',
                'sort_order' => 330,
                'translations' => [
                    'en' => [
                        'title' => 'Invoice at month end',
                        'description' => 'Bill completed work in a month-end cycle instead of immediately on closure. Aligns with retail clients that close AP once per calendar month.',
                    ],
                    'es' => [
                        'title' => 'Facturar a fin de mes',
                        'description' => 'Factura los trabajos completados en un ciclo de fin de mes en lugar de al cierre inmediato. Alineado con retailers que cierran AP una vez por mes natural.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.auto_send_invoices',
                'context' => 'company_relationships',
                'sort_order' => 340,
                'translations' => [
                    'en' => [
                        'title' => 'Auto-send invoices',
                        'description' => 'Automatically email or push invoices to the client when they are issued or when the billing batch completes. Disable if finance must review documents first.',
                    ],
                    'es' => [
                        'title' => 'Envío automático de facturas',
                        'description' => 'Envía o publica automáticamente las facturas al cliente cuando se emiten o al completar el lote de facturación. Desactívelo si finanzas debe revisar los documentos antes.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.send_debt_reminders',
                'context' => 'company_relationships',
                'sort_order' => 350,
                'translations' => [
                    'en' => [
                        'title' => 'Send debt reminders',
                        'description' => 'Include this relationship in automated overdue-invoice reminder campaigns. Turn off for strategic accounts handled manually by credit control.',
                    ],
                    'es' => [
                        'title' => 'Enviar recordatorios de deuda',
                        'description' => 'Incluye esta relación en las campañas automáticas de recordatorio de facturas vencidas. Desactívelo en cuentas estratégicas gestionadas manualmente por riesgo o cobros.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.is_franchise',
                'context' => 'company_relationships',
                'sort_order' => 360,
                'translations' => [
                    'en' => [
                        'title' => 'Franchise',
                        'description' => 'Marks the related party as a franchisee of a brand. Franchise sites often inherit brand billing rules but keep their own tax ID and local contacts.',
                    ],
                    'es' => [
                        'title' => 'Franquicia',
                        'description' => 'Marca a la parte relacionada como franquiciado de una marca. Las tiendas franquicia suelen heredar reglas de facturación de la marca pero mantienen su propio NIF y contactos locales.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.is_available_24h',
                'context' => 'company_relationships',
                'sort_order' => 370,
                'translations' => [
                    'en' => [
                        'title' => 'Available 24h',
                        'description' => 'This party supports or requires 24/7 coverage (emergency call-outs, night shifts). Affects technician routing and SLA clocks outside standard business hours.',
                    ],
                    'es' => [
                        'title' => 'Disponible 24 h',
                        'description' => 'Esta parte admite o exige cobertura 24/7 (urgencias, turnos nocturnos). Afecta al enrutado de técnicos y a los relojes de SLA fuera del horario laboral estándar.',
                    ],
                ],
            ],
            [
                'key' => 'company_relationships.has_health_and_safety',
                'context' => 'company_relationships',
                'sort_order' => 380,
                'translations' => [
                    'en' => [
                        'title' => 'Health & safety requirements',
                        'description' => 'Health and safety documentation or induction is mandatory before technicians work for this party (PRL, site rules). Blocks job assignment until H&S packs are valid.',
                    ],
                    'es' => [
                        'title' => 'Requisitos de PRL / seguridad',
                        'description' => 'Es obligatoria documentación o inducción de prevención de riesgos antes de que los técnicos trabajen para esta parte (PRL, normas de centro). Bloquea la asignación hasta que los packs estén vigentes.',
                    ],
                ],
            ],

            // ── Establishments ─────────────────────────────────────────────
            [
                'key' => 'establishments.store_code',
                'context' => 'establishments',
                'sort_order' => 10,
                'translations' => [
                    'en' => [
                        'title' => 'Store code',
                        'description' => 'Primary store or site code used by the retailer and Optima (for example 0123 or ES-MAD-045). Primary key for matching work orders, visits, and invoice lines to the location.',
                    ],
                    'es' => [
                        'title' => 'Código de tienda',
                        'description' => 'Código principal de tienda o centro usado por el retailer y Optima (por ejemplo 0123 o ES-MAD-045). Clave para cruzar OT, visitas y líneas de factura con la ubicación.',
                    ],
                ],
            ],
            [
                'key' => 'establishments.alternate_store_code',
                'context' => 'establishments',
                'sort_order' => 20,
                'translations' => [
                    'en' => [
                        'title' => 'Alternate store code',
                        'description' => 'Secondary code used by another system or brand format (legacy POS code, franchise code). Helps integrations that still send the old store identifier.',
                    ],
                    'es' => [
                        'title' => 'Código de tienda alternativo',
                        'description' => 'Código secundario de otro sistema o formato de marca (código POS legado, código de franquicia). Facilita integraciones que aún envían el identificador antiguo de tienda.',
                    ],
                ],
            ],
            [
                'key' => 'establishments.emails',
                'context' => 'establishments',
                'sort_order' => 30,
                'translations' => [
                    'en' => [
                        'title' => 'Site emails',
                        'description' => 'General contact emails for the establishment (store mailbox). Used for appointment notices and non-billing operational communication.',
                    ],
                    'es' => [
                        'title' => 'Emails del centro',
                        'description' => 'Correos de contacto generales del establecimiento (buzón de tienda). Se usan para avisos de cita y comunicación operativa no facturable.',
                    ],
                ],
            ],
            [
                'key' => 'establishments.recipient_emails',
                'context' => 'establishments',
                'sort_order' => 40,
                'translations' => [
                    'en' => [
                        'title' => 'Recipient emails',
                        'description' => 'Specific addresses that should receive work-order reports, closing certificates, or invoice copies for this site. Prefer named recipients over shared store inboxes when possible.',
                    ],
                    'es' => [
                        'title' => 'Emails destinatarios',
                        'description' => 'Direcciones concretas que deben recibir partes de OT, certificados de cierre o copias de factura de este centro. Prefiera destinatarios nominativos frente al buzón genérico de tienda cuando sea posible.',
                    ],
                ],
            ],
            [
                'key' => 'establishments.billing_company_id',
                'context' => 'establishments',
                'sort_order' => 50,
                'translations' => [
                    'en' => [
                        'title' => 'Billing company',
                        'description' => 'Legal company invoiced for work at this establishment when it differs from the operational client (central billing entity, franchisee, or regional company).',
                    ],
                    'es' => [
                        'title' => 'Empresa de facturación',
                        'description' => 'Empresa legal a la que se factura el trabajo de este establecimiento cuando difiere del cliente operativo (entidad central de facturación, franquiciado o sociedad regional).',
                    ],
                ],
            ],
            [
                'key' => 'establishments.tax_included',
                'context' => 'establishments',
                'sort_order' => 60,
                'translations' => [
                    'en' => [
                        'title' => 'Prices tax included',
                        'description' => 'When enabled, agreed rates and work-order amounts for this site are treated as VAT-inclusive. Affects how net and tax are split on invoices.',
                    ],
                    'es' => [
                        'title' => 'Precios con impuestos incluidos',
                        'description' => 'Si está activo, las tarifas acordadas y los importes de OT de este centro se tratan con IVA incluido. Afecta a cómo se desglosan base e impuestos en la factura.',
                    ],
                ],
            ],
            [
                'key' => 'establishments.legacy_erp_id',
                'context' => 'establishments',
                'sort_order' => 70,
                'translations' => [
                    'en' => [
                        'title' => 'Legacy ERP ID',
                        'description' => 'Identifier of this establishment in the previous ERP. Keep unchanged to preserve historical cost centres and migrated work-order links.',
                    ],
                    'es' => [
                        'title' => 'ID ERP legado',
                        'description' => 'Identificador de este establecimiento en el ERP anterior. No lo cambie para preservar centros de coste históricos y enlaces de OT migradas.',
                    ],
                ],
            ],
            [
                'key' => 'establishments.integration_external_id',
                'context' => 'establishments',
                'sort_order' => 80,
                'translations' => [
                    'en' => [
                        'title' => 'Integration external ID',
                        'description' => 'External system key for this site (retailer CAFM, IoT platform, or ticketing tool). Required for automatic store matching on inbound tickets.',
                    ],
                    'es' => [
                        'title' => 'ID externo de integración',
                        'description' => 'Clave de sistema externo para este centro (CAFM del retailer, plataforma IoT o tool de ticketing). Necesaria para emparejar automáticamente la tienda en tickets entrantes.',
                    ],
                ],
            ],
            [
                'key' => 'establishments.has_site_health_and_safety',
                'context' => 'establishments',
                'sort_order' => 90,
                'translations' => [
                    'en' => [
                        'title' => 'Site health & safety',
                        'description' => 'This location has specific site H&S rules or documents that technicians must acknowledge before entry (loading dock rules, PPE, asbestos register).',
                    ],
                    'es' => [
                        'title' => 'PRL / seguridad del centro',
                        'description' => 'Esta ubicación tiene normas o documentos de PRL propios que los técnicos deben aceptar antes de entrar (muelle, EPI, registro de amianto).',
                    ],
                ],
            ],
            [
                'key' => 'establishments.has_customer_health_and_safety',
                'context' => 'establishments',
                'sort_order' => 100,
                'translations' => [
                    'en' => [
                        'title' => 'Customer health & safety',
                        'description' => 'The customer mandates their own corporate H&S pack (online induction, badge, insurance certificates) in addition to site-specific rules.',
                    ],
                    'es' => [
                        'title' => 'PRL / seguridad del cliente',
                        'description' => 'El cliente exige su propio pack corporativo de PRL (inducción online, acreditación, seguros) además de las normas específicas del centro.',
                    ],
                ],
            ],
            [
                'key' => 'establishments.is_client_priority',
                'context' => 'establishments',
                'sort_order' => 110,
                'translations' => [
                    'en' => [
                        'title' => 'Client priority site',
                        'description' => 'Flag high-priority stores (flagship, VIP, contractual priority). Dispatch and SLA escalations should favour these sites when capacity is constrained.',
                    ],
                    'es' => [
                        'title' => 'Centro prioritario del cliente',
                        'description' => 'Marca tiendas de alta prioridad (flagship, VIP, prioridad contractual). El despacho y las escaladas de SLA deben favorecer estos centros cuando la capacidad es limitada.',
                    ],
                ],
            ],
            [
                'key' => 'establishments.is_ulez_zone',
                'context' => 'establishments',
                'sort_order' => 120,
                'translations' => [
                    'en' => [
                        'title' => 'ULEZ / low-emission zone',
                        'description' => 'Site sits inside a low-emission or congestion zone (ULEZ, ZBE). Planners should assign compliant vehicles and may need to bill access charges.',
                    ],
                    'es' => [
                        'title' => 'Zona ULEZ / bajas emisiones',
                        'description' => 'El centro está dentro de una zona de bajas emisiones o congestión (ULEZ, ZBE). Los planificadores deben asignar vehículos compatibles y pueden tener que repercutir peajes o tasas.',
                    ],
                ],
            ],
            [
                'key' => 'establishments.notes_alert',
                'context' => 'establishments',
                'sort_order' => 130,
                'translations' => [
                    'en' => [
                        'title' => 'Notes alert',
                        'description' => 'Short alert shown prominently to technicians and planners (access code changes, dock closed, alarm hours). Keep concise; visible on mobile job cards.',
                    ],
                    'es' => [
                        'title' => 'Alerta de notas',
                        'description' => 'Aviso breve mostrado de forma destacada a técnicos y planificadores (cambio de acceso, muelle cerrado, horario de alarma). Manténgalo conciso; visible en las fichas de trabajo móvil.',
                    ],
                ],
            ],
            [
                'key' => 'establishments.internal_notes_alert',
                'context' => 'establishments',
                'sort_order' => 140,
                'translations' => [
                    'en' => [
                        'title' => 'Internal notes alert',
                        'description' => 'Internal-only alert for Optima staff (credit hold, sensitive client, disputed site). Never sent to the customer or shown on client-facing documents.',
                    ],
                    'es' => [
                        'title' => 'Alerta de notas internas',
                        'description' => 'Alerta solo interna para personal de Optima (bloqueo de crédito, cliente sensible, centro en disputa). No se envía al cliente ni aparece en documentos externos.',
                    ],
                ],
            ],

            // ── Brands ─────────────────────────────────────────────────────
            [
                'key' => 'brands.loyalty_meeting_frequency',
                'context' => 'brands',
                'sort_order' => 10,
                'translations' => [
                    'en' => [
                        'title' => 'Loyalty meeting frequency',
                        'description' => 'How often brand-level loyalty or account-retention meetings should run across the estate. Guides national account managers, not individual store reviews.',
                    ],
                    'es' => [
                        'title' => 'Frecuencia de reuniones de fidelización',
                        'description' => 'Cada cuánto deben celebrarse reuniones de fidelización o retención a nivel de marca en toda la red. Orientado a account managers nacionales, no a revisiones de tienda individual.',
                    ],
                ],
            ],
            [
                'key' => 'brands.is_quality_control_contactable',
                'context' => 'brands',
                'sort_order' => 20,
                'translations' => [
                    'en' => [
                        'title' => 'Quality control contactable',
                        'description' => 'Brand-level permission for quality teams to contact brand stakeholders for audits and campaigns. Overrides or complements per-relationship QC flags depending on policy.',
                    ],
                    'es' => [
                        'title' => 'Contactable por control de calidad',
                        'description' => 'Permiso a nivel de marca para que calidad contacte a interlocutores de marca en auditorías y campañas. Complementa o prevalece sobre los flags de QC por relación según la política.',
                    ],
                ],
            ],
            [
                'key' => 'brands.send_debt_reminders',
                'context' => 'brands',
                'sort_order' => 30,
                'translations' => [
                    'en' => [
                        'title' => 'Send debt reminders',
                        'description' => 'Default for establishments under this brand: include them in overdue reminder flows unless a relationship opts out. Useful for chains with centralised credit control.',
                    ],
                    'es' => [
                        'title' => 'Enviar recordatorios de deuda',
                        'description' => 'Valor por defecto para establecimientos de esta marca: incluirlos en recordatorios de mora salvo que una relación lo desactive. Útil en cadenas con control de crédito centralizado.',
                    ],
                ],
            ],
            [
                'key' => 'brands.corporation_company_id',
                'context' => 'brands',
                'sort_order' => 40,
                'translations' => [
                    'en' => [
                        'title' => 'Corporation company',
                        'description' => 'Legal holding or corporation company that owns or represents this brand. Used for corporate reporting, master agreements, and default billing hierarchy.',
                    ],
                    'es' => [
                        'title' => 'Empresa corporativa',
                        'description' => 'Sociedad holding o corporativa que posee o representa esta marca. Se usa en reporting corporativo, contratos marco y la jerarquía de facturación por defecto.',
                    ],
                ],
            ],
        ];
    }
}
