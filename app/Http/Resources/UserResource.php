<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'name' => (string) $this->name,
            'email' => (string) $this->email,
            'role' => (string) $this->role,
            'is_active' => (bool) $this->is_active,
            'branch_id' => (int) $this->branch_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'branch' => new BranchResource($this->whenLoaded('branch')),
        ];
    }
}
