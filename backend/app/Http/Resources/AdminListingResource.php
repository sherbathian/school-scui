<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminListingResource extends JsonResource
{
    public const PROFILE_FIELDS = [
        'id', 'name', 'group_id', 'institute_type', 'short_name', 'branch_name', 'branch_code', 'tagline', 'description',
        'city_id', 'area_id', 'address_line', 'phone', 'email', 'website_url', 'gender_type', 'medium',
        'established_year', 'latitude', 'longitude', 'whatsapp', 'social_links', 'seo_title', 'seo_description',
    ];

    public function toArray(Request $request): array
    {
        return $this->resource->only([
            ...self::PROFILE_FIELDS, 'slug', 'status', 'listing_status', 'source', 'verified_at', 'published_at',
        ]);
    }
}
