<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

trait ApiResponse
{
    protected function successResponse(
        mixed $data = null,
        string $message = 'Operación realizada correctamente.',
        int $status = 200,
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            if ($data instanceof LengthAwarePaginator) {
                $response['data'] = $data->items();
                $response['meta'] = [
                    'current_page' => $data->currentPage(),
                    'per_page'     => $data->perPage(),
                    'total'        => $data->total(),
                    'last_page'    => $data->lastPage(),
                ];
            } else {
                $response['data'] = $data;
            }
        }

        return response()->json($response, $status);
    }

    protected function errorResponse(
        string $message = 'No se pudo realizar la operación.',
        mixed $errors = null,
        int $status = 400,
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }

    protected function notFoundResponse(string $message = 'Recurso no encontrado.'): JsonResponse
    {
        return $this->errorResponse($message, null, 404);
    }

    protected function forbiddenResponse(string $message = 'No tiene permisos para realizar esta acción.'): JsonResponse
    {
        return $this->errorResponse($message, null, 403);
    }

    protected function createdResponse(mixed $data, string $message = 'Recurso creado correctamente.'): JsonResponse
    {
        return $this->successResponse($data, $message, 201);
    }
}
