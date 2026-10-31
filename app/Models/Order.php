<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Cashier;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'customer_name',
        'address',
        'status',
        'type',
        'discount',
        'subtotal',
        'total_price',
        'payment_method',
        'description',
        'phone_number',
        'deliveryman_id',
        'area_code',
        'longitude',
        'latitude',
        'payment_id',
    ];

    protected $casts = [
        'delivery_datetime' => 'datetime',
    ];



    public function order_items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function deliveryman()
    {
        return $this->belongsTo(User::class, 'deliveryman_id');
    }

    public function orderNotifications()
    {
        return $this->hasMany(OrderNotification::class);
    }

    public function getStripePaymentStatus()
    {
        // Ensure you have a payment_intent_id stored
        // if (!$this->stripe_payment_intent_id) {
        //     return 'No Payment Intent';
        // }

        // Retrieve the payment intent from Stripe
        try {
            $paymentIntent = Cashier::stripe()->paymentIntents->retrieve($this->payment_id);
            return $paymentIntent->status; // Return the Stripe status (e.g., succeeded, requires_payment_method)
        } catch (\Exception $e) {
            // Handle errors (e.g., if payment intent doesn't exist or API fails)
            return 'Error fetching status';
        }
    }

    public static function withSource(?string $description, string $source): string
    {
        $clean = self::stripSource($description);

        return '[src:' . $source . ']' . ($clean !== '' ? ' ' . $clean : '');
    }

    public static function stripSource(?string $description): string
    {
        return trim((string) preg_replace('/\[src:(website|mobile|admin)\]\s*/i', '', (string) $description));
    }

    public static function readSource(?string $description): ?string
    {
        if (preg_match('/\[src:(website|mobile|admin)\]/i', (string) $description, $match)) {
            return strtolower($match[1]);
        }

        return null;
    }

    public function getSourceKeyAttribute(): string
    {
        if ($tagged = self::readSource($this->description)) {
            return $tagged;
        }

        if (filled($this->longitude) || filled($this->latitude)) {
            return 'mobile';
        }

        return 'website';
    }

    public function getSourceLabelAttribute(): string
    {
        return match ($this->source_key) {
            'mobile' => 'Mobile App',
            'admin' => 'Admin',
            default => 'Website',
        };
    }

    public function getNotesTextAttribute(): string
    {
        return self::stripSource($this->description);
    }

}
