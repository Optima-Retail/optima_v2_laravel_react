<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Web\Companies\CompanyController;
use App\Http\Controllers\Web\Companies\CompanyMemberController;
use App\Http\Controllers\Web\Companies\CompanyRelationshipController;
use App\Http\Controllers\Web\Companies\ContractController;
use App\Http\Controllers\Web\Companies\EstablishmentController;
use App\Http\Controllers\Web\Companies\EvaluationController;
use App\Http\Controllers\Web\Companies\IncidentController;
use App\Http\Controllers\Web\Companies\SwitchCompanyController;
use App\Http\Controllers\Web\Config\BankController;
use App\Http\Controllers\Web\Config\BrandController;
use App\Http\Controllers\Web\Config\ClientPriorityController;
use App\Http\Controllers\Web\Config\ContractStatusController;
use App\Http\Controllers\Web\Config\CountryController;
use App\Http\Controllers\Web\Config\CurrencyController;
use App\Http\Controllers\Web\Config\DelegationController;
use App\Http\Controllers\Web\Config\EstablishmentTypeController;
use App\Http\Controllers\Web\Config\EvaluationStatusController;
use App\Http\Controllers\Web\Config\FieldHelpController as ConfigFieldHelpController;
use App\Http\Controllers\Web\Config\IncidentPriorityController;
use App\Http\Controllers\Web\Config\IncidentStatusController;
use App\Http\Controllers\Web\Config\IncidentSubtypeController;
use App\Http\Controllers\Web\Config\IncidentTypeController;
use App\Http\Controllers\Web\Config\IntegrationController;
use App\Http\Controllers\Web\Config\LanguageController;
use App\Http\Controllers\Web\Config\NumberingPatternController;
use App\Http\Controllers\Web\Config\ProvinceController;
use App\Http\Controllers\Web\Config\RatingTypeController;
use App\Http\Controllers\Web\Config\RoleController;
use App\Http\Controllers\Web\Config\SeriesController;
use App\Http\Controllers\Web\Config\TeamController;
use App\Http\Controllers\Web\Config\TimezoneController;
use App\Http\Controllers\Web\Config\UserController;
use App\Http\Controllers\Web\Config\WorkOrderStatusController;
use App\Http\Controllers\Web\Config\WorkOrderTypeController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\FieldHelpController;
use App\Http\Controllers\Web\LocaleController;
use Illuminate\Support\Facades\Route;

Route::put('/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware('guest')->group(function (): void {
    Route::get('/', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::post('/me/company/switch', SwitchCompanyController::class)->name('me.company.switch');

    Route::get('/field-help', [FieldHelpController::class, 'resolve'])->name('field-help.resolve');

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
            Route::get('/suppliers', [CompanyRelationshipController::class, 'indexSuppliers'])->name('suppliers.index');
            Route::get('/suppliers/data', [CompanyRelationshipController::class, 'dataSuppliers'])->name('suppliers.data');
        });

        Route::middleware('permission:company_relationships.create')->group(function (): void {
            Route::get('/clients/create', [CompanyRelationshipController::class, 'createClient'])->name('clients.create');
            Route::post('/clients', [CompanyRelationshipController::class, 'storeClient'])->name('clients.store');
            Route::get('/suppliers/create', [CompanyRelationshipController::class, 'createSupplier'])->name('suppliers.create');
            Route::post('/suppliers', [CompanyRelationshipController::class, 'storeSupplier'])->name('suppliers.store');
        });

        Route::middleware('permission:company_relationships.update')->group(function (): void {
            Route::get('/clients/{relationship}/edit', [CompanyRelationshipController::class, 'editClient'])->name('clients.edit');
            Route::put('/clients/{relationship}', [CompanyRelationshipController::class, 'updateClient'])->name('clients.update');
            Route::get('/suppliers/{relationship}/edit', [CompanyRelationshipController::class, 'editSupplier'])->name('suppliers.edit');
            Route::put('/suppliers/{relationship}', [CompanyRelationshipController::class, 'updateSupplier'])->name('suppliers.update');
        });

        Route::middleware('permission:company_relationships.delete')->group(function (): void {
            Route::delete('/clients/{relationship}', [CompanyRelationshipController::class, 'destroyClient'])->name('clients.destroy');
            Route::delete('/suppliers/{relationship}', [CompanyRelationshipController::class, 'destroySupplier'])->name('suppliers.destroy');
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

        Route::middleware('permission:rating_types.view')->group(function (): void {
            Route::get('/rating-types', [RatingTypeController::class, 'index'])->name('rating-types.index');
            Route::get('/rating-types/data', [RatingTypeController::class, 'data'])->name('rating-types.data');
        });

        Route::middleware('permission:rating_types.create')->group(function (): void {
            Route::get('/rating-types/create', [RatingTypeController::class, 'create'])->name('rating-types.create');
            Route::post('/rating-types', [RatingTypeController::class, 'store'])->name('rating-types.store');
        });

        Route::middleware('permission:rating_types.update')->group(function (): void {
            Route::get('/rating-types/{rating_type}/edit', [RatingTypeController::class, 'edit'])->name('rating-types.edit');
            Route::put('/rating-types/{rating_type}', [RatingTypeController::class, 'update'])->name('rating-types.update');
        });

        Route::middleware('permission:rating_types.delete')->group(function (): void {
            Route::delete('/rating-types/{rating_type}', [RatingTypeController::class, 'destroy'])->name('rating-types.destroy');
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
