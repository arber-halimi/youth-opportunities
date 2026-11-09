<?php

namespace App\Http\Traits\Helpers;

use App\Http\Resources\AbstractJsonCollection;
use App\Http\Resources\AbstractJsonResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

trait ApiResponseTrait
{
    protected function okWithResource(AbstractJsonResource $resource): JsonResponse
    {
        return $this->resourceResponse($resource, 'returned', Response::HTTP_OK);
    }

    protected function created(AbstractJsonResource $resource): JsonResponse
    {
        return $this->resourceResponse($resource, 'created', Response::HTTP_CREATED);
    }

    protected function okWithCollection(AbstractJsonCollection $collection): JsonResponse
    {
        $response = $collection->response();

        $response->setData([
            'message' => $collection->getModelName().' list returned.',
            'result' => $response->getData(),
        ]);

        return $response;
    }

    protected function noContent(): Response
    {
        return response()->noContent();
    }

    private function resourceResponse(
        AbstractJsonResource $resource,
        string $action,
        int $status,
    ): JsonResponse {
        return response()->json([
            'message' => $resource->getModelName().' '.$action.'.',
            'result' => $resource->resolve(),
        ], $status);
    }
}
