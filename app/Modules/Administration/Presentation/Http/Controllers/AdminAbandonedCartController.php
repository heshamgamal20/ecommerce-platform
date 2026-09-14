<?php
namespace App\Modules\Administration\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Administration\Presentation\Http\Requests\AbandonedCartRequest;
use App\Modules\Customer\Application\UseCases\ListAbandonedCarts;
use Illuminate\Http\JsonResponse;

final class AdminAbandonedCartController extends Controller
{
    public function index(AbandonedCartRequest $request, ListAbandonedCarts $useCase): JsonResponse
    {
        $data = $request->validated();
        return response()->json([
            'data' => $useCase->execute((int) ($data['days'] ?? 30), (int) ($data['per_page'] ?? 25)),
        ]);
    }
}
