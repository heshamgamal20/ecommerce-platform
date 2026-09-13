<?php
namespace App\Modules\Tax\Presentation\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Modules\Tax\Application\UseCases\ManageTaxRules;
use App\Modules\Tax\Presentation\Http\Requests\TaxRuleManagementRequest;
use Illuminate\Http\JsonResponse;
final class TaxRuleController extends Controller
{
    public function index(TaxRuleManagementRequest $request, ManageTaxRules $useCase): JsonResponse { return response()->json(['data' => $useCase->list($request->user())]); }
    public function store(TaxRuleManagementRequest $request, ManageTaxRules $useCase): JsonResponse { return response()->json(['data' => $useCase->store($request->user(), $request->validated())], 201); }
    public function show(TaxRuleManagementRequest $request, int $id, ManageTaxRules $useCase): JsonResponse { return response()->json(['data' => $useCase->show($request->user(), $id)]); }
    public function update(TaxRuleManagementRequest $request, int $id, ManageTaxRules $useCase): JsonResponse { return response()->json(['data' => $useCase->update($request->user(), $id, $request->validated())]); }
    public function destroy(TaxRuleManagementRequest $request, int $id, ManageTaxRules $useCase): JsonResponse { $useCase->remove($request->user(), $id); return response()->json(null, 204); }
}
