<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Origin extends Model
{
    use HasUlids;

    protected $fillable = ['name', 'slug', 'country_code'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
