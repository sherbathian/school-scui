<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminListingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource->only([
            'id', 'name', 'slug', 'status', 'institute_type', 'short_name', 'branch_name',
            'tagline', 'description', 'city_id', 'area_id', 'address_line', 'phone', 'email', 'website_url',
            'listing_status', 'source', 'verified_at', 'published_at',
        ]);
    }
}
