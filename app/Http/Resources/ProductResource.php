<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'sku' => (string) $this->sku,
            'name' => (string) $this->name,
            'purchase_price' => (float) $this->purchase_price,
            'selling_price' => (float) $this->selling_price,
            'stock' => (int) $this->stock,
            'branch_id' => (int) $this->branch_id,
            'category_id' => (int) $this->category_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'category' => new CategoryResource($this->whenLoaded('category')),
        ];
    }
}
