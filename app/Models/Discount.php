<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'discount_type',
        'discount_value',
        'minimum_purchase_amount',
        'days_of_week',
        'is_active',
    ];

    protected $casts = [
        'days_of_week' => 'array',
        'discount_value' => 'decimal:2',
        'minimum_purchase_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Check if discount is valid for a given day
     */
    public function isValidFor($dayOfWeek)
    {
        // Check if active
        if (!$this->is_active) {
            return false;
        }

        // Check day of week - if no days specified, applies to all days
        if ($this->days_of_week && count($this->days_of_week) > 0) {
            if (!in_array(strtolower($dayOfWeek), array_map('strtolower', $this->days_of_week))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if discount is valid for a given order total (minimum purchase requirement)
     */
    public function meetsMinimumPurchase($orderTotal)
    {
        // If no minimum purchase amount is set, discount applies to all orders
        if ($this->minimum_purchase_amount === null || $this->minimum_purchase_amount == 0) {
            return true;
        }

        // Check if order total meets or exceeds minimum purchase amount
        return $orderTotal >= $this->minimum_purchase_amount;
    }

    /**
     * Calculate discount amount for a given order total
     */
    public function calculateDiscount($orderTotal)
    {
        if ($this->discount_type === 'percentage') {
            return ($orderTotal * $this->discount_value) / 100;
        } else {
            return min($this->discount_value, $orderTotal);
        }
    }
}
