<?php

use Illuminate\Support\Facades\Http;

function createPremiumAccess($data) {
    try {
        $response = Http::post('localhost:8000/api/my-courses/premium', $data);
        $data = $response->json();
        return $data;
    } catch(\Throwable $th) {
        return [
            'status' => 'error',
            'http_code' => 500,
            'message' => 'service course unavailable'
        ];
    }
}