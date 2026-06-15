<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\EmbroideryFee;
use App\Models\FulfillmentPriority;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetadataController extends Controller
{
    /**
     * Get available embroidery types
     */
    public function getEmbroideryTypes(): JsonResponse
    {
        $types = EmbroideryFee::select('embroidery_type')
            ->distinct()
            ->orderBy('embroidery_type')
            ->pluck('embroidery_type');

        return response()->json([
            'status' => 'success',
            'data' => $types
        ]);
    }

    /**
     * Get available fulfillment priorities
     */
    public function getFulfillmentPriorities(): JsonResponse
    {
        $priorities = FulfillmentPriority::select('name')
            ->distinct()
            ->where('active', true)
            ->pluck('name');

        return response()->json([
            'status' => 'success',
            'data' => $priorities
        ]);
    }

    /**
     * Get available shipping methods
     */
    public function getShippingMethods(): JsonResponse
    {
        $methods = [
            [
                'value' => 'standard',
                'label' => 'Standard',
                'description' => 'Standard shipping'
            ],
            [
                'value' => 'priority',
                'label' => 'Priority',
                'description' => 'Priority shipping'
            ]
        ];

        return response()->json([
            'status' => true,
            'data' => $methods
        ]);
    }
}
