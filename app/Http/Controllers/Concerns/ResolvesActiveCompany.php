<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Domain\Companies\Support\ActiveCompany;
use App\Models\Company;
use Illuminate\Http\Request;

trait ResolvesActiveCompany
{
    protected function activeCompany(Request $request): Company
    {
        $company = $request->attributes->get('activeCompany');

        if ($company instanceof Company) {
            return $company;
        }

        $resolved = app(ActiveCompany::class)->forUser($request->user());

        abort_if($resolved === null, 403);

        return $resolved;
    }
}
