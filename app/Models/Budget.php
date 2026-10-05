<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    protected $fillable = [
        'department',
        'budget_amount',
        'committed_amount',
    ];

    protected $casts = [
        'budget_amount' => 'float',
        'committed_amount' => 'float',
    ];

    public function remainingAmount(): float
    {
        return (float) $this->budget_amount - (float) $this->committed_amount;
    }
}
