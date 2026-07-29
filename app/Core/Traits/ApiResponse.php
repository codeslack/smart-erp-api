<?php

namespace App\Core\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

trait ApiResponse
{
    public function success(
        mixed $data = null,
        string $message = 'Success',
        int $status = 200
    ): JsonResponse {

        if (
            $data instanceof ResourceCollection
        ) {

            $response = $data
                ->response()
                ->getData(true);

            return response()->json([
                'success' => true,
                'message' => $message,
                'data'    => $response['data'] ?? [],
                'links'   => $response['links'] ?? null,
                'meta'    => $response['meta'] ?? null,
            ], $status);
        }

        if (
            $data instanceof JsonResource
        ) {

            $data = $data->resolve();
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    public function error(
        string $message = 'Error',
        mixed $errors = null,
        int $status = 422
    ): JsonResponse {

        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $status);
    }
}