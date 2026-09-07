<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V3;

use App\Domain\Companies\Support\ActiveCompany;
use App\Http\Requests\Web\Companies\SwitchCompanyRequest;
use App\Http\Resources\Api\V3\CompanyResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MeCompanyController extends BaseApiController
{
    public function __construct(
        private readonly ActiveCompany $activeCompany,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->success($this->activeCompany->membershipsForUser($request->user()));
    }

    public function switch(SwitchCompanyRequest $request): JsonResponse
    {
        $company = $this->activeCompany->switch(
            $request->user(),
            $request->integer('company_id'),
        );

        return $this->success(
            new CompanyResource($company),
            'company_switched_successfully',
        );
    }
}
