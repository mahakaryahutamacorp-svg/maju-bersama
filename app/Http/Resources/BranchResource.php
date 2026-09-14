<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
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
            'code' => (string) $this->code,
            'name' => (string) $this->name,
            'address' => (string) $this->address,
            'phone' => (string) $this->phone,
            'is_active' => (bool) $this->is_active,
            'products_count' => $this->whenCounted('products'),
            'users_count' => $this->whenCounted('users'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'parent' => new BranchResource($this->whenLoaded('parent')),
            'children' => BranchResource::collection($this->whenLoaded('children')),
            'products' => ProductResource::collection($this->whenLoaded('products')),
            'users' => UserResource::collection($this->whenLoaded('users')),
        ];
    }
}
