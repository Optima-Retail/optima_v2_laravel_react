<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Web\Companies\CompanyController;
use App\Http\Controllers\Web\Companies\CompanyMemberController;
use App\Http\Controllers\Web\CompanyRelationships\CompanyRelationshipController;
use App\Http\Controllers\Web\Compliments\ComplimentController;
use App\Http\Controllers\Web\Config\ArticleController;
use App\Http\Controllers\Web\Config\BankController;
use App\Http\Controllers\Web\Config\BrandController;
use App\Http\Controllers\Web\Config\ChecklistController;
use App\Http\Controllers\Web\Config\ClientPriorityController;
use App\Http\Controllers\Web\Config\ComplimentTypeController;
use App\Http\Controllers\Web\Config\ContractStatusController;
use App\Http\Controllers\Web\Config\CostCenterController;
use App\Http\Controllers\Web\Config\CountryController;
use App\Http\Controllers\Web\Config\CurrencyController;
use App\Http\Controllers\Web\Config\DelegationController;
use App\Http\Controllers\Web\Config\EstablishmentTypeController;
use App\Http\Controllers\Web\Config\EvaluationStatusController;
use App\Http\Controllers\Web\Config\ExpenseTypeController;
use App\Http\Controllers\Web\Config\FieldHelpController as ConfigFieldHelpController;
use App\Http\Controllers\Web\Config\FormBibleController;
use App\Http\Controllers\Web\Config\FormStatusController;
use App\Http\Controllers\Web\Config\FormTypeController;
use App\Http\Controllers\Web\Config\GlobalServiceTypeController;
use App\Http\Controllers\Web\Config\IncidentPriorityController;
use App\Http\Controllers\Web\Config\IncidentStatusController;
use App\Http\Controllers\Web\Config\IncidentSubtypeController;
use App\Http\Controllers\Web\Config\IncidentTypeController;
use App\Http\Controllers\Web\Config\IndirectCostTypeController;
use App\Http\Controllers\Web\Config\IntegrationController;
use App\Http\Controllers\Web\Config\JobTitleController;
use App\Http\Controllers\Web\Config\LanguageController;
use App\Http\Controllers\Web\Config\NumberingPatternController;
use App\Http\Controllers\Web\Config\OtherExpenseTypeController;
use App\Http\Controllers\Web\Config\PaymentDocuments\PaymentDocumentController;
use App\Http\Controllers\Web\Config\PaymentMethods\PaymentMethodController;
use App\Http\Controllers\Web\Config\ProvinceController;
use App\Http\Controllers\Web\Config\RoleController;
use App\Http\Controllers\Web\Config\SeriesController;
use App\Http\Controllers\Web\Config\ServiceTypeController;
use App\Http\Controllers\Web\Config\TaskToPerformController;
use App\Http\Controllers\Web\Config\TeamController;
use App\Http\Controllers\Web\Config\TechnicianAttendanceConfirmationTypeController;
use App\Http\Controllers\Web\Config\TechnicianIncidentStatusController;
use App\Http\Controllers\Web\Config\TechnicianIncidentTypeController;
use App\Http\Controllers\Web\Config\TimezoneController;
use App\Http\Controllers\Web\Config\UserController;
use App\Http\Controllers\Web\Config\VehicleController;
use App\Http\Controllers\Web\Config\WorkOrderStatusController;
use App\Http\Controllers\Web\Config\WorkOrderTypeController;
use App\Http\Controllers\Web\Contracts\ContractController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\Establishments\EstablishmentController;
use App\Http\Controllers\Web\Estimates\EstimateController;
use App\Http\Controllers\Web\Evaluations\EvaluationController;
use App\Http\Controllers\Web\FieldHelpController;
use App\Http\Controllers\Web\Forms\FormController;
use App\Http\Controllers\Web\FormTemplates\FormTemplateController;
use App\Http\Controllers\Web\Incidents\IncidentController;
use App\Http\Controllers\Web\LocaleController;
use App\Http\Controllers\Web\SavedFilters\SavedFilterController;
use App\Http\Controllers\Web\SwitchCompany\SwitchCompanyController;
use App\Http\Controllers\Web\Technicians\TechnicianIncidentController;
use App\Http\Controllers\Web\WorkOrders\WorkOrderController;
use Illuminate\Support\Facades\Route;

Route::put('/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware('guest')->group(function (): void {
    Route::get('/', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::get('/forms/public/{public_id}', [FormController::class, 'showPublic'])
    ->where('public_id', '[A-Za-z0-9]+')
    ->name('forms.public');

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::post('/me/company/switch', SwitchCompanyController::class)->name('me.company.switch');

    Route::get('/field-help', [FieldHelpController::class, 'resolve'])->name('field-help.resolve');

    Route::get('/saved-filters', [SavedFilterController::class, 'index'])->name('saved-filters.index');
    Route::post('/saved-filters', [SavedFilterController::class, 'store'])->name('saved-filters.store');
    Route::delete('/saved-filters/{saved_filter}', [SavedFilterController::class, 'destroy'])
        ->whereNumber('saved_filter')
        ->name('saved-filters.destroy');
    Route::patch('/saved-filters/{saved_filter}/default', [SavedFilterController::class, 'setDefault'])
        ->whereNumber('saved_filter')
        ->name('saved-filters.default');

    Route::middleware('permission:companies.view')->group(function (): void {
        Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
        Route::get('/companies/data', [CompanyController::class, 'data'])->name('companies.data');
    });

    Route::middleware('permission:companies.create')->group(function (): void {
        Route::get('/companies/create', [CompanyController::class, 'create'])->name('companies.create');
        Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store');
    });

    Route::middleware('permission:companies.update')->group(function (): void {
        Route::get('/companies/{company}/edit', [CompanyController::class, 'edit'])->name('companies.edit');
        Route::put('/companies/{company}', [CompanyController::class, 'update'])->name('companies.update');
        Route::post('/companies/{company}/users', [CompanyMemberController::class, 'store'])->name('companies.users.store');
        Route::delete('/companies/{company}/users/{user}', [CompanyMemberController::class, 'destroy'])->name('companies.users.destroy');
    });

    Route::middleware('permission:companies.delete')->group(function (): void {
        Route::delete('/companies/{company}', [CompanyController::class, 'destroy'])->name('companies.destroy');
    });

    Route::middleware('company.context')->group(function (): void {
        Route::middleware('permission:company_relationships.view')->group(function (): void {
            Route::get('/clients', [CompanyRelationshipController::class, 'indexClients'])->name('clients.index');
            Route::get('/clients/data', [CompanyRelationshipController::class, 'dataClients'])->name('clients.data');
            Route::get('/clients/{relationship}/establishments', [CompanyRelationshipController::class, 'establishments'])
                ->name('clients.establishments');
            Route::get('/clients/{relationship}/articles', [CompanyRelationshipController::class, 'articles'])
                ->name('clients.articles');
            Route::get('/clients/{relationship}/rates', [CompanyRelationshipController::class, 'rates'])
                ->name('clients.rates');
            Route::get('/suppliers', [CompanyRelationshipController::class, 'indexSuppliers'])->name('suppliers.index');
            Route::get('/suppliers/data', [CompanyRelationshipController::class, 'dataSuppliers'])->name('suppliers.data');
            Route::get('/technicians', [CompanyRelationshipController::class, 'indexTechnicians'])->name('technicians.index');
            Route::get('/technicians/data', [CompanyRelationshipController::class, 'dataTechnicians'])->name('technicians.data');
            Route::get('/technicians/{relationship}/vehicles', [CompanyRelationshipController::class, 'vehicles'])
                ->name('technicians.vehicles');
            Route::get('/technicians/{relationship}/service-types', [CompanyRelationshipController::class, 'technicianServiceTypes'])
                ->name('technicians.service-types');
        });

        Route::middleware('permission:company_relationships.create')->group(function (): void {
            Route::get('/clients/create', [CompanyRelationshipController::class, 'createClient'])->name('clients.create');
            Route::post('/clients', [CompanyRelationshipController::class, 'storeClient'])->name('clients.store');
            Route::get('/suppliers/create', [CompanyRelationshipController::class, 'createSupplier'])->name('suppliers.create');
            Route::post('/suppliers', [CompanyRelationshipController::class, 'storeSupplier'])->name('suppliers.store');
            Route::get('/technicians/create', [CompanyRelationshipController::class, 'createTechnician'])->name('technicians.create');
            Route::post('/technicians', [CompanyRelationshipController::class, 'storeTechnician'])->name('technicians.store');
        });

        Route::middleware('permission:company_relationships.update')->group(function (): void {
            Route::get('/clients/{relationship}/edit', [CompanyRelationshipController::class, 'editClient'])->name('clients.edit');
            Route::put('/clients/{relationship}', [CompanyRelationshipController::class, 'updateClient'])->name('clients.update');
            Route::put('/clients/{relationship}/schedule', [CompanyRelationshipController::class, 'updateSchedule'])->name('clients.schedule.update');
            Route::put('/clients/{relationship}/articles', [CompanyRelationshipController::class, 'syncArticles'])
                ->middleware('permission:articles.update')
                ->name('clients.articles.sync');
            Route::put('/clients/{relationship}/rates', [CompanyRelationshipController::class, 'syncRates'])
                ->middleware('permission:client_rates.create|client_rates.update|client_rates.delete')
                ->name('clients.rates.sync');
            Route::get('/suppliers/{relationship}/edit', [CompanyRelationshipController::class, 'editSupplier'])->name('suppliers.edit');
            Route::put('/suppliers/{relationship}', [CompanyRelationshipController::class, 'updateSupplier'])->name('suppliers.update');
            Route::get('/technicians/{relationship}/edit', [CompanyRelationshipController::class, 'editTechnician'])->name('technicians.edit');
            Route::put('/technicians/{relationship}', [CompanyRelationshipController::class, 'updateTechnician'])->name('technicians.update');
            Route::put('/technicians/{relationship}/vehicles', [CompanyRelationshipController::class, 'syncVehicles'])
                ->middleware('permission:vehicles.create|vehicles.update|vehicles.delete')
                ->name('technicians.vehicles.sync');
            Route::put('/technicians/{relationship}/service-types', [CompanyRelationshipController::class, 'syncTechnicianServiceTypes'])
                ->middleware('permission:technician_service_types.create|technician_service_types.update|technician_service_types.delete')
                ->name('technicians.service-types.sync');
        });

        Route::middleware('permission:company_relationships.delete')->group(function (): void {
            Route::delete('/clients/{relationship}', [CompanyRelationshipController::class, 'destroyClient'])->name('clients.destroy');
            Route::delete('/suppliers/{relationship}', [CompanyRelationshipController::class, 'destroySupplier'])->name('suppliers.destroy');
            Route::delete('/technicians/{relationship}', [CompanyRelationshipController::class, 'destroyTechnician'])->name('technicians.destroy');
        });

        Route::middleware('permission:technician_incidents.view')->group(function (): void {
            Route::get('/technician-incidents', [TechnicianIncidentController::class, 'index'])->name('technician-incidents.index');
            Route::get('/technician-incidents/data', [TechnicianIncidentController::class, 'data'])->name('technician-incidents.data');
            Route::get('/technicians/{relationship}/incidents/data', [TechnicianIncidentController::class, 'dataForTechnician'])
                ->name('technicians.incidents.data');
        });

        Route::middleware('permission:technician_incidents.create')->group(function (): void {
            Route::get('/technician-incidents/create', [TechnicianIncidentController::class, 'create'])->name('technician-incidents.create');
            Route::post('/technician-incidents', [TechnicianIncidentController::class, 'store'])->name('technician-incidents.store');
        });

        Route::middleware('permission:technician_incidents.view')->group(function (): void {
            Route::get('/technician-incidents/{technician_incident}', [TechnicianIncidentController::class, 'show'])->name('technician-incidents.show');
            Route::get(
                '/technician-incidents/{technician_incident}/messages/{technician_incident_message}/file',
                [TechnicianIncidentController::class, 'showMessageFile'],
            )->name('technician-incidents.messages.file');
        });

        Route::middleware('permission:technician_incidents.update')->group(function (): void {
            Route::post('/technician-incidents/{technician_incident}/messages', [TechnicianIncidentController::class, 'storeMessage'])
                ->name('technician-incidents.messages.store');
            Route::patch('/technician-incidents/{technician_incident}/status', [TechnicianIncidentController::class, 'updateStatus'])
                ->name('technician-incidents.status.update');
            Route::post('/technician-incidents/{technician_incident}/verify', [TechnicianIncidentController::class, 'verify'])
                ->name('technician-incidents.verify');
        });

        Route::middleware('permission:establishments.view')->group(function (): void {
            Route::get('/establishments', [EstablishmentController::class, 'index'])->name('establishments.index');
            Route::get('/establishments/data', [EstablishmentController::class, 'data'])->name('establishments.data');
        });

        Route::middleware('permission:establishments.create')->group(function (): void {
            Route::get('/establishments/create', [EstablishmentController::class, 'create'])->name('establishments.create');
            Route::post('/establishments', [EstablishmentController::class, 'store'])->name('establishments.store');
        });

        Route::middleware('permission:establishments.update')->group(function (): void {
            Route::get('/establishments/{establishment}/edit', [EstablishmentController::class, 'edit'])->name('establishments.edit');
            Route::put('/establishments/{establishment}', [EstablishmentController::class, 'update'])->name('establishments.update');
        });

        Route::middleware('permission:establishments.delete')->group(function (): void {
            Route::delete('/establishments/{establishment}', [EstablishmentController::class, 'destroy'])->name('establishments.destroy');
        });

        Route::middleware('permission:contracts.view')->group(function (): void {
            Route::get('/contracts', [ContractController::class, 'index'])->name('contracts.index');
            Route::get('/contracts/data', [ContractController::class, 'data'])->name('contracts.data');
        });

        Route::middleware('permission:contracts.create')->group(function (): void {
            Route::get('/contracts/create', [ContractController::class, 'create'])->name('contracts.create');
            Route::post('/contracts', [ContractController::class, 'store'])->name('contracts.store');
        });

        Route::middleware('permission:contracts.update')->group(function (): void {
            Route::get('/contracts/{contract}/edit', [ContractController::class, 'edit'])->name('contracts.edit');
            Route::put('/contracts/{contract}', [ContractController::class, 'update'])->name('contracts.update');
        });

        Route::middleware('permission:contracts.upload-attachments')->group(function (): void {
            Route::post('/contracts/{contract}/attachments', [ContractController::class, 'storeAttachment'])
                ->name('contracts.attachments.store');
        });

        Route::middleware('permission:contracts.download-attachments')->group(function (): void {
            Route::get('/contracts/{contract}/attachments/{attachment}/download', [ContractController::class, 'downloadAttachment'])
                ->name('contracts.attachments.download');
        });

        Route::middleware('permission:contracts.delete-attachments')->group(function (): void {
            Route::delete('/contracts/{contract}/attachments/{attachment}', [ContractController::class, 'destroyAttachment'])
                ->name('contracts.attachments.destroy');
        });

        Route::middleware('permission:contracts.delete')->group(function (): void {
            Route::delete('/contracts/{contract}', [ContractController::class, 'destroy'])->name('contracts.destroy');
        });

        Route::middleware('permission:estimates.view')->group(function (): void {
            Route::get('/estimates', [EstimateController::class, 'index'])->name('estimates.index');
            Route::get('/estimates/data', [EstimateController::class, 'data'])->name('estimates.data');
        });

        Route::middleware('permission:estimates.create')->group(function (): void {
            Route::get('/estimates/create', [EstimateController::class, 'create'])->name('estimates.create');
            Route::post('/estimates', [EstimateController::class, 'store'])->name('estimates.store');
        });

        Route::middleware('permission:estimates.update')->group(function (): void {
            Route::get('/estimates/{estimate}/edit', [EstimateController::class, 'edit'])
                ->whereNumber('estimate')
                ->name('estimates.edit');
            Route::put('/estimates/{estimate}', [EstimateController::class, 'update'])
                ->whereNumber('estimate')
                ->name('estimates.update');
        });

        Route::middleware('permission:estimates.upload-attachments')->group(function (): void {
            Route::post('/estimates/{estimate}/attachments', [EstimateController::class, 'storeAttachment'])
                ->whereNumber('estimate')
                ->name('estimates.attachments.store');
        });

        Route::middleware('permission:estimates.download-attachments')->group(function (): void {
            Route::get('/estimates/{estimate}/attachments/{attachment}/download', [EstimateController::class, 'downloadAttachment'])
                ->whereNumber('estimate')
                ->whereNumber('attachment')
                ->name('estimates.attachments.download');
        });

        Route::middleware('permission:estimates.delete-attachments')->group(function (): void {
            Route::delete('/estimates/{estimate}/attachments/{attachment}', [EstimateController::class, 'destroyAttachment'])
                ->whereNumber('estimate')
                ->whereNumber('attachment')
                ->name('estimates.attachments.destroy');
        });

        Route::middleware('permission:estimates.delete')->group(function (): void {
            Route::delete('/estimates/{estimate}', [EstimateController::class, 'destroy'])
                ->whereNumber('estimate')
                ->name('estimates.destroy');
        });

        Route::middleware('permission:work_orders.view')->group(function (): void {
            Route::get('/work-orders', [WorkOrderController::class, 'index'])->name('work-orders.index');
            Route::get('/work-orders/data', [WorkOrderController::class, 'data'])->name('work-orders.data');
        });

        Route::middleware('permission:work_orders.create')->group(function (): void {
            Route::get('/work-orders/create', [WorkOrderController::class, 'create'])->name('work-orders.create');
            Route::post('/work-orders', [WorkOrderController::class, 'store'])->name('work-orders.store');
        });

        Route::middleware('permission:work_orders.update')->group(function (): void {
            Route::get('/work-orders/{work_order}/edit', [WorkOrderController::class, 'edit'])
                ->whereNumber('work_order')
                ->name('work-orders.edit');
            Route::put('/work-orders/{work_order}', [WorkOrderController::class, 'update'])
                ->whereNumber('work_order')
                ->name('work-orders.update');
        });

        Route::middleware('permission:work_orders.upload-attachments')->group(function (): void {
            Route::post('/work-orders/{work_order}/attachments', [WorkOrderController::class, 'storeAttachment'])
                ->whereNumber('work_order')
                ->name('work-orders.attachments.store');
        });

        Route::middleware('permission:work_orders.download-attachments')->group(function (): void {
            Route::get('/work-orders/{work_order}/attachments/{attachment}/download', [WorkOrderController::class, 'downloadAttachment'])
                ->whereNumber('work_order')
                ->whereNumber('attachment')
                ->name('work-orders.attachments.download');
        });

        Route::middleware('permission:work_orders.delete-attachments')->group(function (): void {
            Route::delete('/work-orders/{work_order}/attachments/{attachment}', [WorkOrderController::class, 'destroyAttachment'])
                ->whereNumber('work_order')
                ->whereNumber('attachment')
                ->name('work-orders.attachments.destroy');
        });

        Route::middleware('permission:work_orders.delete')->group(function (): void {
            Route::delete('/work-orders/{work_order}', [WorkOrderController::class, 'destroy'])
                ->whereNumber('work_order')
                ->name('work-orders.destroy');
        });

        Route::middleware('permission:evaluations.view')->group(function (): void {
            Route::get('/evaluations', [EvaluationController::class, 'index'])->name('evaluations.index');
            Route::get('/evaluations/data', [EvaluationController::class, 'data'])->name('evaluations.data');
        });

        Route::middleware('permission:evaluations.update')->group(function (): void {
            Route::get('/evaluations/{evaluation}/edit', [EvaluationController::class, 'edit'])
                ->whereNumber('evaluation')
                ->name('evaluations.edit');
            Route::put('/evaluations/{evaluation}', [EvaluationController::class, 'update'])
                ->whereNumber('evaluation')
                ->name('evaluations.update');
        });

        Route::middleware('permission:evaluations.delete')->group(function (): void {
            Route::delete('/evaluations/{evaluation}', [EvaluationController::class, 'destroy'])
                ->whereNumber('evaluation')
                ->name('evaluations.destroy');
        });

        Route::middleware('permission:compliments.view')->group(function (): void {
            Route::get('/compliments', [ComplimentController::class, 'index'])->name('compliments.index');
            Route::get('/compliments/data', [ComplimentController::class, 'data'])->name('compliments.data');
        });

        Route::middleware('permission:compliments.create')->group(function (): void {
            Route::get('/compliments/create', [ComplimentController::class, 'create'])->name('compliments.create');
            Route::post('/compliments', [ComplimentController::class, 'store'])->name('compliments.store');
        });

        Route::middleware('permission:compliments.update')->group(function (): void {
            Route::get('/compliments/{compliment}/edit', [ComplimentController::class, 'edit'])
                ->whereNumber('compliment')
                ->name('compliments.edit');
            Route::put('/compliments/{compliment}', [ComplimentController::class, 'update'])
                ->whereNumber('compliment')
                ->name('compliments.update');
        });

        Route::middleware('permission:compliments.delete')->group(function (): void {
            Route::delete('/compliments/{compliment}', [ComplimentController::class, 'destroy'])
                ->whereNumber('compliment')
                ->name('compliments.destroy');
        });

        Route::middleware('permission:compliments.upload-attachments')->group(function (): void {
            Route::post('/compliments/{compliment}/attachments', [ComplimentController::class, 'storeAttachment'])
                ->whereNumber('compliment')
                ->name('compliments.attachments.store');
        });

        Route::middleware('permission:compliments.download-attachments')->group(function (): void {
            Route::get('/compliments/{compliment}/attachments/{attachment}/download', [ComplimentController::class, 'downloadAttachment'])
                ->whereNumber('compliment')
                ->whereNumber('attachment')
                ->name('compliments.attachments.download');
        });

        Route::middleware('permission:compliments.delete-attachments')->group(function (): void {
            Route::delete('/compliments/{compliment}/attachments/{attachment}', [ComplimentController::class, 'destroyAttachment'])
                ->whereNumber('compliment')
                ->whereNumber('attachment')
                ->name('compliments.attachments.destroy');
        });

        Route::middleware('permission:form_templates.view')->group(function (): void {
            Route::get('/form-templates', [FormTemplateController::class, 'index'])->name('form-templates.index');
            Route::get('/form-templates/data', [FormTemplateController::class, 'data'])->name('form-templates.data');
        });

        Route::middleware('permission:form_templates.create')->group(function (): void {
            Route::get('/form-templates/create', [FormTemplateController::class, 'create'])->name('form-templates.create');
            Route::post('/form-templates', [FormTemplateController::class, 'store'])->name('form-templates.store');
        });

        Route::middleware('permission:form_templates.update')->group(function (): void {
            Route::get('/form-templates/{form_template}/edit', [FormTemplateController::class, 'edit'])
                ->whereNumber('form_template')
                ->name('form-templates.edit');
            Route::put('/form-templates/{form_template}', [FormTemplateController::class, 'update'])
                ->whereNumber('form_template')
                ->name('form-templates.update');
        });

        Route::middleware('permission:form_templates.delete')->group(function (): void {
            Route::delete('/form-templates/{form_template}', [FormTemplateController::class, 'destroy'])
                ->whereNumber('form_template')
                ->name('form-templates.destroy');
        });

        Route::middleware('permission:forms.view')->group(function (): void {
            Route::get('/forms', [FormController::class, 'index'])->name('forms.index');
            Route::get('/forms/data', [FormController::class, 'data'])->name('forms.data');
        });

        Route::middleware('permission:forms.create')->group(function (): void {
            Route::get('/forms/create', [FormController::class, 'create'])->name('forms.create');
            Route::post('/forms', [FormController::class, 'store'])->name('forms.store');
        });

        Route::middleware('permission:forms.update')->group(function (): void {
            Route::get('/forms/{form}/edit', [FormController::class, 'edit'])
                ->whereNumber('form')
                ->name('forms.edit');
            Route::put('/forms/{form}', [FormController::class, 'update'])
                ->whereNumber('form')
                ->name('forms.update');
            Route::post('/forms/{form}/advance', [FormController::class, 'advance'])
                ->whereNumber('form')
                ->name('forms.advance');
        });

        Route::middleware('permission:forms.delete')->group(function (): void {
            Route::delete('/forms/{form}', [FormController::class, 'destroy'])
                ->whereNumber('form')
                ->name('forms.destroy');
        });

        Route::middleware('permission:incidents.view')->group(function (): void {
            Route::get('/incidents', [IncidentController::class, 'index'])->name('incidents.index');
            Route::get('/incidents/data', [IncidentController::class, 'data'])->name('incidents.data');
        });

        Route::middleware('permission:incidents.create')->group(function (): void {
            Route::get('/incidents/create', [IncidentController::class, 'create'])->name('incidents.create');
            Route::post('/incidents', [IncidentController::class, 'store'])->name('incidents.store');
        });

        Route::middleware('permission:incidents.update')->group(function (): void {
            Route::get('/incidents/{incident}/edit', [IncidentController::class, 'edit'])
                ->whereNumber('incident')
                ->name('incidents.edit');
            Route::put('/incidents/{incident}', [IncidentController::class, 'update'])
                ->whereNumber('incident')
                ->name('incidents.update');
            Route::post('/incidents/{incident}/lines', [IncidentController::class, 'storeLine'])
                ->whereNumber('incident')
                ->name('incidents.lines.store');
        });

        Route::middleware('permission:incidents.delete')->group(function (): void {
            Route::delete('/incidents/{incident}', [IncidentController::class, 'destroy'])
                ->whereNumber('incident')
                ->name('incidents.destroy');
        });
    });

    Route::middleware('permission:brands.view')->group(function (): void {
        Route::get('/brands', [BrandController::class, 'index'])->name('brands.index');
        Route::get('/brands/data', [BrandController::class, 'data'])->name('brands.data');
        Route::get('/brands/{brand}/clients', [BrandController::class, 'clients'])->name('brands.clients');
    });

    Route::middleware('permission:brands.create')->group(function (): void {
        Route::get('/brands/create', [BrandController::class, 'create'])->name('brands.create');
        Route::post('/brands', [BrandController::class, 'store'])->name('brands.store');
    });

    Route::middleware('permission:brands.update')->group(function (): void {
        Route::get('/brands/{brand}/edit', [BrandController::class, 'edit'])->name('brands.edit');
        Route::put('/brands/{brand}', [BrandController::class, 'update'])->name('brands.update');
    });

    Route::middleware('permission:brands.send-messages|brands.send-message-files')->group(function (): void {
        Route::post('/brands/{brand}/messages', [BrandController::class, 'storeMessage'])->name('brands.messages.store');
    });

    Route::middleware('permission:brands.download-message-files')->group(function (): void {
        Route::get('/brands/{brand}/messages/{brandMessage}/file', [BrandController::class, 'showMessageFile'])->name('brands.messages.file');
    });

    Route::middleware('permission:brands.delete')->group(function (): void {
        Route::delete('/brands/{brand}', [BrandController::class, 'destroy'])->name('brands.destroy');
    });

    Route::prefix('config')->name('config.')->group(function (): void {
        Route::middleware('permission:users.view')->group(function (): void {
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::get('/users/data', [UserController::class, 'data'])->name('users.data');
        });

        Route::middleware('permission:users.create')->group(function (): void {
            Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
        });

        Route::middleware('permission:users.update')->group(function (): void {
            Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        });

        Route::middleware('permission:users.delete')->group(function (): void {
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        });

        Route::middleware('permission:roles.view')->group(function (): void {
            Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
            Route::get('/roles/data', [RoleController::class, 'data'])->name('roles.data');
        });

        Route::middleware('permission:roles.create')->group(function (): void {
            Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
            Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        });

        Route::middleware('permission:roles.update')->group(function (): void {
            Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
            Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        });

        Route::middleware('permission:roles.delete')->group(function (): void {
            Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
        });

        Route::middleware('permission:work_order_types.view')->group(function (): void {
            Route::get('/work-order-types', [WorkOrderTypeController::class, 'index'])->name('work-order-types.index');
            Route::get('/work-order-types/data', [WorkOrderTypeController::class, 'data'])->name('work-order-types.data');
        });

        Route::middleware('permission:work_order_types.create')->group(function (): void {
            Route::get('/work-order-types/create', [WorkOrderTypeController::class, 'create'])->name('work-order-types.create');
            Route::post('/work-order-types', [WorkOrderTypeController::class, 'store'])->name('work-order-types.store');
        });

        Route::middleware('permission:work_order_types.update')->group(function (): void {
            Route::get('/work-order-types/{work_order_type}/edit', [WorkOrderTypeController::class, 'edit'])->name('work-order-types.edit');
            Route::put('/work-order-types/{work_order_type}', [WorkOrderTypeController::class, 'update'])->name('work-order-types.update');
        });

        Route::middleware('permission:work_order_types.delete')->group(function (): void {
            Route::delete('/work-order-types/{work_order_type}', [WorkOrderTypeController::class, 'destroy'])->name('work-order-types.destroy');
        });

        Route::middleware('permission:service_types.view')->group(function (): void {
            Route::get('/service-types', [ServiceTypeController::class, 'index'])->name('service-types.index');
            Route::get('/service-types/data', [ServiceTypeController::class, 'data'])->name('service-types.data');
        });

        Route::middleware('permission:service_types.create')->group(function (): void {
            Route::get('/service-types/create', [ServiceTypeController::class, 'create'])->name('service-types.create');
            Route::post('/service-types', [ServiceTypeController::class, 'store'])->name('service-types.store');
        });

        Route::middleware('permission:service_types.update')->group(function (): void {
            Route::get('/service-types/{service_type}/edit', [ServiceTypeController::class, 'edit'])->name('service-types.edit');
            Route::put('/service-types/{service_type}', [ServiceTypeController::class, 'update'])->name('service-types.update');
        });

        Route::middleware('permission:service_types.delete')->group(function (): void {
            Route::delete('/service-types/{service_type}', [ServiceTypeController::class, 'destroy'])->name('service-types.destroy');
        });

        Route::middleware('permission:global_service_types.view')->group(function (): void {
            Route::get('/global-service-types', [GlobalServiceTypeController::class, 'index'])->name('global-service-types.index');
            Route::get('/global-service-types/data', [GlobalServiceTypeController::class, 'data'])->name('global-service-types.data');
        });

        Route::middleware('permission:global_service_types.create')->group(function (): void {
            Route::get('/global-service-types/create', [GlobalServiceTypeController::class, 'create'])->name('global-service-types.create');
            Route::post('/global-service-types', [GlobalServiceTypeController::class, 'store'])->name('global-service-types.store');
        });

        Route::middleware('permission:global_service_types.update')->group(function (): void {
            Route::get('/global-service-types/{global_service_type}/edit', [GlobalServiceTypeController::class, 'edit'])->name('global-service-types.edit');
            Route::put('/global-service-types/{global_service_type}', [GlobalServiceTypeController::class, 'update'])->name('global-service-types.update');
        });

        Route::middleware('permission:global_service_types.delete')->group(function (): void {
            Route::delete('/global-service-types/{global_service_type}', [GlobalServiceTypeController::class, 'destroy'])->name('global-service-types.destroy');
        });

        Route::middleware('permission:form_types.view')->group(function (): void {
            Route::get('/form-types', [FormTypeController::class, 'index'])->name('form-types.index');
            Route::get('/form-types/data', [FormTypeController::class, 'data'])->name('form-types.data');
        });

        Route::middleware('permission:form_types.create')->group(function (): void {
            Route::get('/form-types/create', [FormTypeController::class, 'create'])->name('form-types.create');
            Route::post('/form-types', [FormTypeController::class, 'store'])->name('form-types.store');
        });

        Route::middleware('permission:form_types.update')->group(function (): void {
            Route::get('/form-types/{form_type}/edit', [FormTypeController::class, 'edit'])->name('form-types.edit');
            Route::put('/form-types/{form_type}', [FormTypeController::class, 'update'])->name('form-types.update');
        });

        Route::middleware('permission:form_types.delete')->group(function (): void {
            Route::delete('/form-types/{form_type}', [FormTypeController::class, 'destroy'])->name('form-types.destroy');
        });

        Route::middleware('permission:form_bibles.view')->group(function (): void {
            Route::get('/form-bibles', [FormBibleController::class, 'index'])->name('form-bibles.index');
            Route::get('/form-bibles/data', [FormBibleController::class, 'data'])->name('form-bibles.data');
        });

        Route::middleware('permission:form_bibles.create')->group(function (): void {
            Route::get('/form-bibles/create', [FormBibleController::class, 'create'])->name('form-bibles.create');
            Route::post('/form-bibles', [FormBibleController::class, 'store'])->name('form-bibles.store');
        });

        Route::middleware('permission:form_bibles.update')->group(function (): void {
            Route::get('/form-bibles/{form_bible}/edit', [FormBibleController::class, 'edit'])->name('form-bibles.edit');
            Route::put('/form-bibles/{form_bible}', [FormBibleController::class, 'update'])->name('form-bibles.update');
        });

        Route::middleware('permission:form_bibles.delete')->group(function (): void {
            Route::delete('/form-bibles/{form_bible}', [FormBibleController::class, 'destroy'])->name('form-bibles.destroy');
        });

        Route::middleware('permission:technician_attendance_confirmation_types.view')->group(function (): void {
            Route::get('/technician-attendance-confirmation-types', [TechnicianAttendanceConfirmationTypeController::class, 'index'])->name('technician-attendance-confirmation-types.index');
            Route::get('/technician-attendance-confirmation-types/data', [TechnicianAttendanceConfirmationTypeController::class, 'data'])->name('technician-attendance-confirmation-types.data');
        });

        Route::middleware('permission:technician_attendance_confirmation_types.create')->group(function (): void {
            Route::get('/technician-attendance-confirmation-types/create', [TechnicianAttendanceConfirmationTypeController::class, 'create'])->name('technician-attendance-confirmation-types.create');
            Route::post('/technician-attendance-confirmation-types', [TechnicianAttendanceConfirmationTypeController::class, 'store'])->name('technician-attendance-confirmation-types.store');
        });

        Route::middleware('permission:technician_attendance_confirmation_types.update')->group(function (): void {
            Route::get('/technician-attendance-confirmation-types/{confirmation_type}/edit', [TechnicianAttendanceConfirmationTypeController::class, 'edit'])->name('technician-attendance-confirmation-types.edit');
            Route::put('/technician-attendance-confirmation-types/{confirmation_type}', [TechnicianAttendanceConfirmationTypeController::class, 'update'])->name('technician-attendance-confirmation-types.update');
        });

        Route::middleware('permission:technician_attendance_confirmation_types.delete')->group(function (): void {
            Route::delete('/technician-attendance-confirmation-types/{confirmation_type}', [TechnicianAttendanceConfirmationTypeController::class, 'destroy'])->name('technician-attendance-confirmation-types.destroy');
        });

        Route::middleware('permission:payment_methods.view')->group(function (): void {
            Route::get('/payment-methods', [PaymentMethodController::class, 'index'])->name('payment-methods.index');
            Route::get('/payment-methods/data', [PaymentMethodController::class, 'data'])->name('payment-methods.data');
        });

        Route::middleware('permission:payment_methods.create')->group(function (): void {
            Route::get('/payment-methods/create', [PaymentMethodController::class, 'create'])->name('payment-methods.create');
            Route::post('/payment-methods', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
        });

        Route::middleware('permission:payment_methods.update')->group(function (): void {
            Route::get('/payment-methods/{payment_method}/edit', [PaymentMethodController::class, 'edit'])->name('payment-methods.edit');
            Route::put('/payment-methods/{payment_method}', [PaymentMethodController::class, 'update'])->name('payment-methods.update');
        });

        Route::middleware('permission:payment_methods.delete')->group(function (): void {
            Route::delete('/payment-methods/{payment_method}', [PaymentMethodController::class, 'destroy'])->name('payment-methods.destroy');
        });

        Route::middleware('permission:payment_documents.view')->group(function (): void {
            Route::get('/payment-documents', [PaymentDocumentController::class, 'index'])->name('payment-documents.index');
            Route::get('/payment-documents/data', [PaymentDocumentController::class, 'data'])->name('payment-documents.data');
        });

        Route::middleware('permission:payment_documents.create')->group(function (): void {
            Route::get('/payment-documents/create', [PaymentDocumentController::class, 'create'])->name('payment-documents.create');
            Route::post('/payment-documents', [PaymentDocumentController::class, 'store'])->name('payment-documents.store');
        });

        Route::middleware('permission:payment_documents.update')->group(function (): void {
            Route::get('/payment-documents/{payment_document}/edit', [PaymentDocumentController::class, 'edit'])->name('payment-documents.edit');
            Route::put('/payment-documents/{payment_document}', [PaymentDocumentController::class, 'update'])->name('payment-documents.update');
        });

        Route::middleware('permission:payment_documents.delete')->group(function (): void {
            Route::delete('/payment-documents/{payment_document}', [PaymentDocumentController::class, 'destroy'])->name('payment-documents.destroy');
        });

        Route::middleware('permission:client_priorities.view')->group(function (): void {
            Route::get('/client-priorities', [ClientPriorityController::class, 'index'])->name('client-priorities.index');
            Route::get('/client-priorities/data', [ClientPriorityController::class, 'data'])->name('client-priorities.data');
        });

        Route::middleware('permission:client_priorities.create')->group(function (): void {
            Route::get('/client-priorities/create', [ClientPriorityController::class, 'create'])->name('client-priorities.create');
            Route::post('/client-priorities', [ClientPriorityController::class, 'store'])->name('client-priorities.store');
        });

        Route::middleware('permission:client_priorities.update')->group(function (): void {
            Route::get('/client-priorities/{client_priority}/edit', [ClientPriorityController::class, 'edit'])->name('client-priorities.edit');
            Route::put('/client-priorities/{client_priority}', [ClientPriorityController::class, 'update'])->name('client-priorities.update');
        });

        Route::middleware('permission:client_priorities.delete')->group(function (): void {
            Route::delete('/client-priorities/{client_priority}', [ClientPriorityController::class, 'destroy'])->name('client-priorities.destroy');
        });

        Route::middleware('permission:incident_priorities.view')->group(function (): void {
            Route::get('/incident-priorities', [IncidentPriorityController::class, 'index'])->name('incident-priorities.index');
            Route::get('/incident-priorities/data', [IncidentPriorityController::class, 'data'])->name('incident-priorities.data');
        });

        Route::middleware('permission:incident_priorities.create')->group(function (): void {
            Route::get('/incident-priorities/create', [IncidentPriorityController::class, 'create'])->name('incident-priorities.create');
            Route::post('/incident-priorities', [IncidentPriorityController::class, 'store'])->name('incident-priorities.store');
        });

        Route::middleware('permission:incident_priorities.update')->group(function (): void {
            Route::get('/incident-priorities/{incident_priority}/edit', [IncidentPriorityController::class, 'edit'])->name('incident-priorities.edit');
            Route::put('/incident-priorities/{incident_priority}', [IncidentPriorityController::class, 'update'])->name('incident-priorities.update');
        });

        Route::middleware('permission:incident_priorities.delete')->group(function (): void {
            Route::delete('/incident-priorities/{incident_priority}', [IncidentPriorityController::class, 'destroy'])->name('incident-priorities.destroy');
        });

        Route::middleware('permission:incident_types.view')->group(function (): void {
            Route::get('/incident-types', [IncidentTypeController::class, 'index'])->name('incident-types.index');
            Route::get('/incident-types/data', [IncidentTypeController::class, 'data'])->name('incident-types.data');
        });

        Route::middleware('permission:incident_types.create')->group(function (): void {
            Route::get('/incident-types/create', [IncidentTypeController::class, 'create'])->name('incident-types.create');
            Route::post('/incident-types', [IncidentTypeController::class, 'store'])->name('incident-types.store');
        });

        Route::middleware('permission:incident_types.update')->group(function (): void {
            Route::get('/incident-types/{incident_type}/edit', [IncidentTypeController::class, 'edit'])->name('incident-types.edit');
            Route::put('/incident-types/{incident_type}', [IncidentTypeController::class, 'update'])->name('incident-types.update');
        });

        Route::middleware('permission:incident_types.delete')->group(function (): void {
            Route::delete('/incident-types/{incident_type}', [IncidentTypeController::class, 'destroy'])->name('incident-types.destroy');
        });

        Route::middleware('permission:incident_subtypes.view')->group(function (): void {
            Route::get('/incident-types/{incident_type}/subtypes', [IncidentSubtypeController::class, 'forType'])
                ->name('incident-types.subtypes.index');
        });

        Route::put('/incident-types/{incident_type}/subtypes', [IncidentSubtypeController::class, 'syncForType'])
            ->name('incident-types.subtypes.sync');

        Route::middleware('permission:work_order_statuses.view')->group(function (): void {
            Route::get('/work-order-statuses', [WorkOrderStatusController::class, 'index'])->name('work-order-statuses.index');
            Route::get('/work-order-statuses/data', [WorkOrderStatusController::class, 'data'])->name('work-order-statuses.data');
        });

        Route::middleware('permission:work_order_statuses.create')->group(function (): void {
            Route::get('/work-order-statuses/create', [WorkOrderStatusController::class, 'create'])->name('work-order-statuses.create');
            Route::post('/work-order-statuses', [WorkOrderStatusController::class, 'store'])->name('work-order-statuses.store');
        });

        Route::middleware('permission:work_order_statuses.update')->group(function (): void {
            Route::get('/work-order-statuses/{work_order_status}/edit', [WorkOrderStatusController::class, 'edit'])->name('work-order-statuses.edit');
            Route::put('/work-order-statuses/{work_order_status}', [WorkOrderStatusController::class, 'update'])->name('work-order-statuses.update');
        });

        Route::middleware('permission:work_order_statuses.delete')->group(function (): void {
            Route::delete('/work-order-statuses/{work_order_status}', [WorkOrderStatusController::class, 'destroy'])->name('work-order-statuses.destroy');
        });

        Route::redirect('/estimate-statuses', '/config/work-order-statuses');
        Route::redirect('/estimate-statuses/create', '/config/work-order-statuses/create');
        Route::any('/estimate-statuses/{any?}', function () {
            return redirect()->route('config.work-order-statuses.index');
        })->where('any', '.*');

        Route::middleware('permission:technician_incident_statuses.view')->group(function (): void {
            Route::get('/technician-incident-statuses', [TechnicianIncidentStatusController::class, 'index'])->name('technician-incident-statuses.index');
            Route::get('/technician-incident-statuses/data', [TechnicianIncidentStatusController::class, 'data'])->name('technician-incident-statuses.data');
        });

        Route::middleware('permission:technician_incident_statuses.create')->group(function (): void {
            Route::get('/technician-incident-statuses/create', [TechnicianIncidentStatusController::class, 'create'])->name('technician-incident-statuses.create');
            Route::post('/technician-incident-statuses', [TechnicianIncidentStatusController::class, 'store'])->name('technician-incident-statuses.store');
        });

        Route::middleware('permission:technician_incident_statuses.update')->group(function (): void {
            Route::get('/technician-incident-statuses/{technician_incident_status}/edit', [TechnicianIncidentStatusController::class, 'edit'])->name('technician-incident-statuses.edit');
            Route::put('/technician-incident-statuses/{technician_incident_status}', [TechnicianIncidentStatusController::class, 'update'])->name('technician-incident-statuses.update');
        });

        Route::middleware('permission:technician_incident_statuses.delete')->group(function (): void {
            Route::delete('/technician-incident-statuses/{technician_incident_status}', [TechnicianIncidentStatusController::class, 'destroy'])->name('technician-incident-statuses.destroy');
        });

        Route::middleware('permission:checklists.view')->group(function (): void {
            Route::get('/checklists', [ChecklistController::class, 'index'])->name('checklists.index');
            Route::get('/checklists/data', [ChecklistController::class, 'data'])->name('checklists.data');
        });

        Route::middleware('permission:checklists.create')->group(function (): void {
            Route::get('/checklists/create', [ChecklistController::class, 'create'])->name('checklists.create');
            Route::post('/checklists', [ChecklistController::class, 'store'])->name('checklists.store');
        });

        Route::middleware('permission:checklists.update')->group(function (): void {
            Route::get('/checklists/{checklist}/edit', [ChecklistController::class, 'edit'])->name('checklists.edit');
            Route::put('/checklists/{checklist}', [ChecklistController::class, 'update'])->name('checklists.update');
        });

        Route::middleware('permission:checklists.delete')->group(function (): void {
            Route::delete('/checklists/{checklist}', [ChecklistController::class, 'destroy'])->name('checklists.destroy');
        });

        Route::middleware('permission:contract_statuses.view')->group(function (): void {
            Route::get('/contract-statuses', [ContractStatusController::class, 'index'])->name('contract-statuses.index');
            Route::get('/contract-statuses/data', [ContractStatusController::class, 'data'])->name('contract-statuses.data');
        });

        Route::middleware('permission:contract_statuses.create')->group(function (): void {
            Route::get('/contract-statuses/create', [ContractStatusController::class, 'create'])->name('contract-statuses.create');
            Route::post('/contract-statuses', [ContractStatusController::class, 'store'])->name('contract-statuses.store');
        });

        Route::middleware('permission:contract_statuses.update')->group(function (): void {
            Route::get('/contract-statuses/{contract_status}/edit', [ContractStatusController::class, 'edit'])->name('contract-statuses.edit');
            Route::put('/contract-statuses/{contract_status}', [ContractStatusController::class, 'update'])->name('contract-statuses.update');
        });

        Route::middleware('permission:contract_statuses.delete')->group(function (): void {
            Route::delete('/contract-statuses/{contract_status}', [ContractStatusController::class, 'destroy'])->name('contract-statuses.destroy');
        });

        Route::middleware('permission:evaluation_statuses.view')->group(function (): void {
            Route::get('/evaluation-statuses', [EvaluationStatusController::class, 'index'])->name('evaluation-statuses.index');
            Route::get('/evaluation-statuses/data', [EvaluationStatusController::class, 'data'])->name('evaluation-statuses.data');
        });

        Route::middleware('permission:evaluation_statuses.create')->group(function (): void {
            Route::get('/evaluation-statuses/create', [EvaluationStatusController::class, 'create'])->name('evaluation-statuses.create');
            Route::post('/evaluation-statuses', [EvaluationStatusController::class, 'store'])->name('evaluation-statuses.store');
        });

        Route::middleware('permission:evaluation_statuses.update')->group(function (): void {
            Route::get('/evaluation-statuses/{evaluation_status}/edit', [EvaluationStatusController::class, 'edit'])->name('evaluation-statuses.edit');
            Route::put('/evaluation-statuses/{evaluation_status}', [EvaluationStatusController::class, 'update'])->name('evaluation-statuses.update');
        });

        Route::middleware('permission:evaluation_statuses.delete')->group(function (): void {
            Route::delete('/evaluation-statuses/{evaluation_status}', [EvaluationStatusController::class, 'destroy'])->name('evaluation-statuses.destroy');
        });

        Route::middleware('permission:form_statuses.view')->group(function (): void {
            Route::get('/form-statuses', [FormStatusController::class, 'index'])->name('form-statuses.index');
            Route::get('/form-statuses/data', [FormStatusController::class, 'data'])->name('form-statuses.data');
        });

        Route::middleware('permission:form_statuses.create')->group(function (): void {
            Route::get('/form-statuses/create', [FormStatusController::class, 'create'])->name('form-statuses.create');
            Route::post('/form-statuses', [FormStatusController::class, 'store'])->name('form-statuses.store');
        });

        Route::middleware('permission:form_statuses.update')->group(function (): void {
            Route::get('/form-statuses/{form_status}/edit', [FormStatusController::class, 'edit'])->name('form-statuses.edit');
            Route::put('/form-statuses/{form_status}', [FormStatusController::class, 'update'])->name('form-statuses.update');
        });

        Route::middleware('permission:form_statuses.delete')->group(function (): void {
            Route::delete('/form-statuses/{form_status}', [FormStatusController::class, 'destroy'])->name('form-statuses.destroy');
        });

        Route::middleware('permission:articles.view')->group(function (): void {
            Route::get('/articles', [ArticleController::class, 'index'])->name('articles.index');
            Route::get('/articles/data', [ArticleController::class, 'data'])->name('articles.data');
        });

        Route::middleware('permission:articles.create')->group(function (): void {
            Route::get('/articles/create', [ArticleController::class, 'create'])->name('articles.create');
            Route::post('/articles', [ArticleController::class, 'store'])->name('articles.store');
        });

        Route::middleware('permission:articles.update')->group(function (): void {
            Route::get('/articles/{article}/edit', [ArticleController::class, 'edit'])->name('articles.edit');
            Route::put('/articles/{article}', [ArticleController::class, 'update'])->name('articles.update');
        });

        Route::middleware('permission:articles.delete')->group(function (): void {
            Route::delete('/articles/{article}', [ArticleController::class, 'destroy'])->name('articles.destroy');
        });

        Route::middleware('permission:incident_statuses.view')->group(function (): void {
            Route::get('/incident-statuses', [IncidentStatusController::class, 'index'])->name('incident-statuses.index');
            Route::get('/incident-statuses/data', [IncidentStatusController::class, 'data'])->name('incident-statuses.data');
        });

        Route::middleware('permission:incident_statuses.create')->group(function (): void {
            Route::get('/incident-statuses/create', [IncidentStatusController::class, 'create'])->name('incident-statuses.create');
            Route::post('/incident-statuses', [IncidentStatusController::class, 'store'])->name('incident-statuses.store');
        });

        Route::middleware('permission:incident_statuses.update')->group(function (): void {
            Route::get('/incident-statuses/{incident_status}/edit', [IncidentStatusController::class, 'edit'])->name('incident-statuses.edit');
            Route::put('/incident-statuses/{incident_status}', [IncidentStatusController::class, 'update'])->name('incident-statuses.update');
        });

        Route::middleware('permission:incident_statuses.delete')->group(function (): void {
            Route::delete('/incident-statuses/{incident_status}', [IncidentStatusController::class, 'destroy'])->name('incident-statuses.destroy');
        });

        Route::middleware('permission:teams.view')->group(function (): void {
            Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
            Route::get('/teams/data', [TeamController::class, 'data'])->name('teams.data');
        });

        Route::middleware('permission:teams.create')->group(function (): void {
            Route::get('/teams/create', [TeamController::class, 'create'])->name('teams.create');
            Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');
        });

        Route::middleware('permission:teams.update')->group(function (): void {
            Route::get('/teams/{team}/edit', [TeamController::class, 'edit'])->name('teams.edit');
            Route::put('/teams/{team}', [TeamController::class, 'update'])->name('teams.update');
        });

        Route::middleware('permission:teams.delete')->group(function (): void {
            Route::delete('/teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');
        });

        Route::middleware('permission:languages.view')->group(function (): void {
            Route::get('/languages', [LanguageController::class, 'index'])->name('languages.index');
            Route::get('/languages/data', [LanguageController::class, 'data'])->name('languages.data');
        });

        Route::middleware('permission:languages.create')->group(function (): void {
            Route::get('/languages/create', [LanguageController::class, 'create'])->name('languages.create');
            Route::post('/languages', [LanguageController::class, 'store'])->name('languages.store');
        });

        Route::middleware('permission:languages.update')->group(function (): void {
            Route::get('/languages/{language}/edit', [LanguageController::class, 'edit'])->name('languages.edit');
            Route::put('/languages/{language}', [LanguageController::class, 'update'])->name('languages.update');
        });

        Route::middleware('permission:languages.delete')->group(function (): void {
            Route::delete('/languages/{language}', [LanguageController::class, 'destroy'])->name('languages.destroy');
        });

        Route::middleware('permission:job_titles.view')->group(function (): void {
            Route::get('/job-titles', [JobTitleController::class, 'index'])->name('job-titles.index');
            Route::get('/job-titles/data', [JobTitleController::class, 'data'])->name('job-titles.data');
        });

        Route::middleware('permission:job_titles.create')->group(function (): void {
            Route::get('/job-titles/create', [JobTitleController::class, 'create'])->name('job-titles.create');
            Route::post('/job-titles', [JobTitleController::class, 'store'])->name('job-titles.store');
        });

        Route::middleware('permission:job_titles.update')->group(function (): void {
            Route::get('/job-titles/{job_title}/edit', [JobTitleController::class, 'edit'])->name('job-titles.edit');
            Route::put('/job-titles/{job_title}', [JobTitleController::class, 'update'])->name('job-titles.update');
        });

        Route::middleware('permission:job_titles.delete')->group(function (): void {
            Route::delete('/job-titles/{job_title}', [JobTitleController::class, 'destroy'])->name('job-titles.destroy');
        });

        Route::middleware('permission:cost_centers.view')->group(function (): void {
            Route::get('/cost-centers', [CostCenterController::class, 'index'])->name('cost-centers.index');
            Route::get('/cost-centers/data', [CostCenterController::class, 'data'])->name('cost-centers.data');
        });

        Route::middleware('permission:cost_centers.create')->group(function (): void {
            Route::get('/cost-centers/create', [CostCenterController::class, 'create'])->name('cost-centers.create');
            Route::post('/cost-centers', [CostCenterController::class, 'store'])->name('cost-centers.store');
        });

        Route::middleware('permission:cost_centers.update')->group(function (): void {
            Route::get('/cost-centers/{cost_center}/edit', [CostCenterController::class, 'edit'])->name('cost-centers.edit');
            Route::put('/cost-centers/{cost_center}', [CostCenterController::class, 'update'])->name('cost-centers.update');
        });

        Route::middleware('permission:cost_centers.delete')->group(function (): void {
            Route::delete('/cost-centers/{cost_center}', [CostCenterController::class, 'destroy'])->name('cost-centers.destroy');
        });

        Route::middleware('permission:other_expense_types.view')->group(function (): void {
            Route::get('/other-expense-types', [OtherExpenseTypeController::class, 'index'])->name('other-expense-types.index');
            Route::get('/other-expense-types/data', [OtherExpenseTypeController::class, 'data'])->name('other-expense-types.data');
        });

        Route::middleware('permission:other_expense_types.create')->group(function (): void {
            Route::get('/other-expense-types/create', [OtherExpenseTypeController::class, 'create'])->name('other-expense-types.create');
            Route::post('/other-expense-types', [OtherExpenseTypeController::class, 'store'])->name('other-expense-types.store');
        });

        Route::middleware('permission:other_expense_types.update')->group(function (): void {
            Route::get('/other-expense-types/{other_expense_type}/edit', [OtherExpenseTypeController::class, 'edit'])->name('other-expense-types.edit');
            Route::put('/other-expense-types/{other_expense_type}', [OtherExpenseTypeController::class, 'update'])->name('other-expense-types.update');
        });

        Route::middleware('permission:other_expense_types.delete')->group(function (): void {
            Route::delete('/other-expense-types/{other_expense_type}', [OtherExpenseTypeController::class, 'destroy'])->name('other-expense-types.destroy');
        });

        Route::middleware('permission:technician_incident_types.view')->group(function (): void {
            Route::get('/technician-incident-types', [TechnicianIncidentTypeController::class, 'index'])->name('technician-incident-types.index');
            Route::get('/technician-incident-types/data', [TechnicianIncidentTypeController::class, 'data'])->name('technician-incident-types.data');
        });

        Route::middleware('permission:technician_incident_types.create')->group(function (): void {
            Route::get('/technician-incident-types/create', [TechnicianIncidentTypeController::class, 'create'])->name('technician-incident-types.create');
            Route::post('/technician-incident-types', [TechnicianIncidentTypeController::class, 'store'])->name('technician-incident-types.store');
        });

        Route::middleware('permission:technician_incident_types.update')->group(function (): void {
            Route::get('/technician-incident-types/{technician_incident_type}/edit', [TechnicianIncidentTypeController::class, 'edit'])->name('technician-incident-types.edit');
            Route::put('/technician-incident-types/{technician_incident_type}', [TechnicianIncidentTypeController::class, 'update'])->name('technician-incident-types.update');
        });

        Route::middleware('permission:technician_incident_types.delete')->group(function (): void {
            Route::delete('/technician-incident-types/{technician_incident_type}', [TechnicianIncidentTypeController::class, 'destroy'])->name('technician-incident-types.destroy');
        });

        Route::middleware('permission:expense_types.view')->group(function (): void {
            Route::get('/expense-types', [ExpenseTypeController::class, 'index'])->name('expense-types.index');
            Route::get('/expense-types/data', [ExpenseTypeController::class, 'data'])->name('expense-types.data');
        });

        Route::middleware('permission:expense_types.create')->group(function (): void {
            Route::get('/expense-types/create', [ExpenseTypeController::class, 'create'])->name('expense-types.create');
            Route::post('/expense-types', [ExpenseTypeController::class, 'store'])->name('expense-types.store');
        });

        Route::middleware('permission:expense_types.update')->group(function (): void {
            Route::get('/expense-types/{expense_type}/edit', [ExpenseTypeController::class, 'edit'])->name('expense-types.edit');
            Route::put('/expense-types/{expense_type}', [ExpenseTypeController::class, 'update'])->name('expense-types.update');
        });

        Route::middleware('permission:expense_types.delete')->group(function (): void {
            Route::delete('/expense-types/{expense_type}', [ExpenseTypeController::class, 'destroy'])->name('expense-types.destroy');
        });

        Route::middleware('permission:indirect_cost_types.view')->group(function (): void {
            Route::get('/indirect-cost-types', [IndirectCostTypeController::class, 'index'])->name('indirect-cost-types.index');
            Route::get('/indirect-cost-types/data', [IndirectCostTypeController::class, 'data'])->name('indirect-cost-types.data');
        });

        Route::middleware('permission:indirect_cost_types.create')->group(function (): void {
            Route::get('/indirect-cost-types/create', [IndirectCostTypeController::class, 'create'])->name('indirect-cost-types.create');
            Route::post('/indirect-cost-types', [IndirectCostTypeController::class, 'store'])->name('indirect-cost-types.store');
        });

        Route::middleware('permission:indirect_cost_types.update')->group(function (): void {
            Route::get('/indirect-cost-types/{indirect_cost_type}/edit', [IndirectCostTypeController::class, 'edit'])->name('indirect-cost-types.edit');
            Route::put('/indirect-cost-types/{indirect_cost_type}', [IndirectCostTypeController::class, 'update'])->name('indirect-cost-types.update');
        });

        Route::middleware('permission:indirect_cost_types.delete')->group(function (): void {
            Route::delete('/indirect-cost-types/{indirect_cost_type}', [IndirectCostTypeController::class, 'destroy'])->name('indirect-cost-types.destroy');
        });

        Route::middleware('permission:tasks_to_perform.view')->group(function (): void {
            Route::get('/tasks-to-perform', [TaskToPerformController::class, 'index'])->name('tasks-to-perform.index');
            Route::get('/tasks-to-perform/data', [TaskToPerformController::class, 'data'])->name('tasks-to-perform.data');
        });

        Route::middleware('permission:tasks_to_perform.create')->group(function (): void {
            Route::get('/tasks-to-perform/create', [TaskToPerformController::class, 'create'])->name('tasks-to-perform.create');
            Route::post('/tasks-to-perform', [TaskToPerformController::class, 'store'])->name('tasks-to-perform.store');
        });

        Route::middleware('permission:tasks_to_perform.update')->group(function (): void {
            Route::get('/tasks-to-perform/{task_to_perform}/edit', [TaskToPerformController::class, 'edit'])->name('tasks-to-perform.edit');
            Route::put('/tasks-to-perform/{task_to_perform}', [TaskToPerformController::class, 'update'])->name('tasks-to-perform.update');
        });

        Route::middleware('permission:tasks_to_perform.delete')->group(function (): void {
            Route::delete('/tasks-to-perform/{task_to_perform}', [TaskToPerformController::class, 'destroy'])->name('tasks-to-perform.destroy');
        });

        Route::middleware('permission:vehicles.view')->group(function (): void {
            Route::get('/vehicles', [VehicleController::class, 'index'])->name('vehicles.index');
            Route::get('/vehicles/data', [VehicleController::class, 'data'])->name('vehicles.data');
        });

        Route::middleware('permission:vehicles.create')->group(function (): void {
            Route::get('/vehicles/create', [VehicleController::class, 'create'])->name('vehicles.create');
            Route::post('/vehicles', [VehicleController::class, 'store'])->name('vehicles.store');
        });

        Route::middleware('permission:vehicles.update')->group(function (): void {
            Route::get('/vehicles/{vehicle}/edit', [VehicleController::class, 'edit'])->name('vehicles.edit');
            Route::put('/vehicles/{vehicle}', [VehicleController::class, 'update'])->name('vehicles.update');
        });

        Route::middleware('permission:vehicles.delete')->group(function (): void {
            Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy'])->name('vehicles.destroy');
        });

        Route::middleware('permission:integrations.view')->group(function (): void {
            Route::get('/integrations', [IntegrationController::class, 'index'])->name('integrations.index');
            Route::get('/integrations/data', [IntegrationController::class, 'data'])->name('integrations.data');
        });

        Route::middleware('permission:integrations.create')->group(function (): void {
            Route::get('/integrations/create', [IntegrationController::class, 'create'])->name('integrations.create');
            Route::post('/integrations', [IntegrationController::class, 'store'])->name('integrations.store');
        });

        Route::middleware('permission:integrations.update')->group(function (): void {
            Route::get('/integrations/{integration}/edit', [IntegrationController::class, 'edit'])->name('integrations.edit');
            Route::put('/integrations/{integration}', [IntegrationController::class, 'update'])->name('integrations.update');
        });

        Route::middleware('permission:integrations.delete')->group(function (): void {
            Route::delete('/integrations/{integration}', [IntegrationController::class, 'destroy'])->name('integrations.destroy');
        });

        Route::middleware('permission:field_helps.view')->group(function (): void {
            Route::get('/field-helps', [ConfigFieldHelpController::class, 'index'])->name('field-helps.index');
            Route::get('/field-helps/data', [ConfigFieldHelpController::class, 'data'])->name('field-helps.data');
        });

        Route::middleware('permission:field_helps.create')->group(function (): void {
            Route::get('/field-helps/create', [ConfigFieldHelpController::class, 'create'])->name('field-helps.create');
            Route::post('/field-helps', [ConfigFieldHelpController::class, 'store'])->name('field-helps.store');
        });

        Route::middleware('permission:field_helps.update')->group(function (): void {
            Route::get('/field-helps/{field_help}/edit', [ConfigFieldHelpController::class, 'edit'])->name('field-helps.edit');
            Route::put('/field-helps/{field_help}', [ConfigFieldHelpController::class, 'update'])->name('field-helps.update');
        });

        Route::middleware('permission:field_helps.delete')->group(function (): void {
            Route::delete('/field-helps/{field_help}', [ConfigFieldHelpController::class, 'destroy'])->name('field-helps.destroy');
        });

        Route::middleware('permission:establishment_types.view')->group(function (): void {
            Route::get('/establishment-types', [EstablishmentTypeController::class, 'index'])->name('establishment-types.index');
            Route::get('/establishment-types/data', [EstablishmentTypeController::class, 'data'])->name('establishment-types.data');
        });

        Route::middleware('permission:establishment_types.create')->group(function (): void {
            Route::get('/establishment-types/create', [EstablishmentTypeController::class, 'create'])->name('establishment-types.create');
            Route::post('/establishment-types', [EstablishmentTypeController::class, 'store'])->name('establishment-types.store');
        });

        Route::middleware('permission:establishment_types.update')->group(function (): void {
            Route::get('/establishment-types/{establishment_type}/edit', [EstablishmentTypeController::class, 'edit'])->name('establishment-types.edit');
            Route::put('/establishment-types/{establishment_type}', [EstablishmentTypeController::class, 'update'])->name('establishment-types.update');
        });

        Route::middleware('permission:establishment_types.delete')->group(function (): void {
            Route::delete('/establishment-types/{establishment_type}', [EstablishmentTypeController::class, 'destroy'])->name('establishment-types.destroy');
        });

        Route::middleware('permission:compliment_types.view')->group(function (): void {
            Route::get('/compliment-types', [ComplimentTypeController::class, 'index'])->name('compliment-types.index');
            Route::get('/compliment-types/data', [ComplimentTypeController::class, 'data'])->name('compliment-types.data');
        });

        Route::middleware('permission:compliment_types.create')->group(function (): void {
            Route::get('/compliment-types/create', [ComplimentTypeController::class, 'create'])->name('compliment-types.create');
            Route::post('/compliment-types', [ComplimentTypeController::class, 'store'])->name('compliment-types.store');
        });

        Route::middleware('permission:compliment_types.update')->group(function (): void {
            Route::get('/compliment-types/{compliment_type}/edit', [ComplimentTypeController::class, 'edit'])->name('compliment-types.edit');
            Route::put('/compliment-types/{compliment_type}', [ComplimentTypeController::class, 'update'])->name('compliment-types.update');
        });

        Route::middleware('permission:compliment_types.delete')->group(function (): void {
            Route::delete('/compliment-types/{compliment_type}', [ComplimentTypeController::class, 'destroy'])->name('compliment-types.destroy');
        });

        Route::middleware('permission:banks.view')->group(function (): void {
            Route::get('/banks', [BankController::class, 'index'])->name('banks.index');
            Route::get('/banks/data', [BankController::class, 'data'])->name('banks.data');
        });

        Route::middleware('permission:banks.create')->group(function (): void {
            Route::get('/banks/create', [BankController::class, 'create'])->name('banks.create');
            Route::post('/banks', [BankController::class, 'store'])->name('banks.store');
        });

        Route::middleware('permission:banks.update')->group(function (): void {
            Route::get('/banks/{bank}/edit', [BankController::class, 'edit'])->name('banks.edit');
            Route::put('/banks/{bank}', [BankController::class, 'update'])->name('banks.update');
        });

        Route::middleware('permission:banks.delete')->group(function (): void {
            Route::delete('/banks/{bank}', [BankController::class, 'destroy'])->name('banks.destroy');
        });

        Route::middleware('permission:timezones.view')->group(function (): void {
            Route::get('/timezones', [TimezoneController::class, 'index'])->name('timezones.index');
            Route::get('/timezones/data', [TimezoneController::class, 'data'])->name('timezones.data');
        });

        Route::middleware('permission:timezones.create')->group(function (): void {
            Route::get('/timezones/create', [TimezoneController::class, 'create'])->name('timezones.create');
            Route::post('/timezones', [TimezoneController::class, 'store'])->name('timezones.store');
        });

        Route::middleware('permission:timezones.update')->group(function (): void {
            Route::get('/timezones/{timezone}/edit', [TimezoneController::class, 'edit'])->name('timezones.edit');
            Route::put('/timezones/{timezone}', [TimezoneController::class, 'update'])->name('timezones.update');
        });

        Route::middleware('permission:timezones.delete')->group(function (): void {
            Route::delete('/timezones/{timezone}', [TimezoneController::class, 'destroy'])->name('timezones.destroy');
        });

        Route::middleware('permission:countries.view')->group(function (): void {
            Route::get('/countries', [CountryController::class, 'index'])->name('countries.index');
            Route::get('/countries/data', [CountryController::class, 'data'])->name('countries.data');
        });

        Route::middleware('permission:countries.create')->group(function (): void {
            Route::get('/countries/create', [CountryController::class, 'create'])->name('countries.create');
            Route::post('/countries', [CountryController::class, 'store'])->name('countries.store');
        });

        Route::middleware('permission:countries.update')->group(function (): void {
            Route::get('/countries/{country}/edit', [CountryController::class, 'edit'])->name('countries.edit');
            Route::put('/countries/{country}', [CountryController::class, 'update'])->name('countries.update');
        });

        Route::middleware('permission:countries.delete')->group(function (): void {
            Route::delete('/countries/{country}', [CountryController::class, 'destroy'])->name('countries.destroy');
        });

        Route::middleware('permission:provinces.view')->group(function (): void {
            Route::get('/countries/{country}/provinces', [ProvinceController::class, 'forCountry'])
                ->name('countries.provinces.index');
        });

        Route::put('/countries/{country}/provinces', [ProvinceController::class, 'syncForCountry'])
            ->name('countries.provinces.sync');

        Route::middleware('permission:series.view')->group(function (): void {
            Route::get('/series', [SeriesController::class, 'index'])->name('series.index');
            Route::get('/series/data', [SeriesController::class, 'data'])->name('series.data');
        });

        Route::middleware('permission:series.create')->group(function (): void {
            Route::get('/series/create', [SeriesController::class, 'create'])->name('series.create');
            Route::post('/series', [SeriesController::class, 'store'])->name('series.store');
        });

        Route::middleware('permission:series.update')->group(function (): void {
            Route::get('/series/{series}/edit', [SeriesController::class, 'edit'])->name('series.edit');
            Route::put('/series/{series}', [SeriesController::class, 'update'])->name('series.update');
        });

        Route::middleware('permission:series.delete')->group(function (): void {
            Route::delete('/series/{series}', [SeriesController::class, 'destroy'])->name('series.destroy');
        });

        Route::middleware('permission:numbering_patterns.view|numbering_patterns.create|numbering_patterns.update')->group(function (): void {
            Route::get('/numbering-patterns/resource/{resource}/configure', [NumberingPatternController::class, 'configure'])
                ->name('numbering-patterns.configure');
        });

        Route::middleware('permission:numbering_patterns.create|numbering_patterns.update')->group(function (): void {
            Route::put('/numbering-patterns/resource/{resource}', [NumberingPatternController::class, 'upsertByResource'])
                ->name('numbering-patterns.upsert-resource');
        });

        Route::middleware('permission:numbering_patterns.view')->group(function (): void {
            Route::get('/numbering-patterns', [NumberingPatternController::class, 'index'])->name('numbering-patterns.index');
            Route::get('/numbering-patterns/data', [NumberingPatternController::class, 'data'])->name('numbering-patterns.data');
        });

        Route::middleware('permission:numbering_patterns.create')->group(function (): void {
            Route::get('/numbering-patterns/create', [NumberingPatternController::class, 'create'])->name('numbering-patterns.create');
            Route::post('/numbering-patterns', [NumberingPatternController::class, 'store'])->name('numbering-patterns.store');
        });

        Route::middleware('permission:numbering_patterns.update')->group(function (): void {
            Route::get('/numbering-patterns/{numbering_pattern}/edit', [NumberingPatternController::class, 'edit'])->name('numbering-patterns.edit');
            Route::put('/numbering-patterns/{numbering_pattern}', [NumberingPatternController::class, 'update'])->name('numbering-patterns.update');
        });

        Route::middleware('permission:numbering_patterns.delete')->group(function (): void {
            Route::delete('/numbering-patterns/{numbering_pattern}', [NumberingPatternController::class, 'destroy'])->name('numbering-patterns.destroy');
        });

        Route::middleware('permission:currencies.view')->group(function (): void {
            Route::get('/currencies/data', [CurrencyController::class, 'data'])->name('currencies.data');
            Route::get('/currencies', [CurrencyController::class, 'index'])->name('currencies.index');
        });

        Route::middleware('permission:currencies.create')->group(function (): void {
            Route::get('/currencies/create', [CurrencyController::class, 'create'])->name('currencies.create');
            Route::post('/currencies', [CurrencyController::class, 'store'])->name('currencies.store');
        });

        Route::middleware('permission:currencies.update')->group(function (): void {
            Route::get('/currencies/{currency}/edit', [CurrencyController::class, 'edit'])->name('currencies.edit');
            Route::put('/currencies/{currency}', [CurrencyController::class, 'update'])->name('currencies.update');
        });

        Route::middleware('permission:currencies.delete')->group(function (): void {
            Route::delete('/currencies/{currency}', [CurrencyController::class, 'destroy'])->name('currencies.destroy');
        });

        Route::middleware('permission:delegations.view')->group(function (): void {
            Route::get('/delegations', [DelegationController::class, 'index'])->name('delegations.index');
            Route::get('/delegations/data', [DelegationController::class, 'data'])->name('delegations.data');
        });

        Route::middleware('permission:delegations.create')->group(function (): void {
            Route::get('/delegations/create', [DelegationController::class, 'create'])->name('delegations.create');
            Route::post('/delegations', [DelegationController::class, 'store'])->name('delegations.store');
        });

        Route::middleware('permission:delegations.update')->group(function (): void {
            Route::get('/delegations/{delegation}/edit', [DelegationController::class, 'edit'])->name('delegations.edit');
            Route::put('/delegations/{delegation}', [DelegationController::class, 'update'])->name('delegations.update');
        });

        Route::middleware('permission:delegations.delete')->group(function (): void {
            Route::delete('/delegations/{delegation}', [DelegationController::class, 'destroy'])->name('delegations.destroy');
        });

    });
});
