<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use Carbon\Carbon;

class AutoCompleteOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auto-complete-orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically completes orders after 40 minutes or when delivery time has passed';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $orders = Order::where('status', '!=', 5) // Not completed
            ->where(function ($query) {
                $query->whereNull('delivery_datetime')
                      ->where('created_at', '<=', Carbon::now('Europe/Dublin')->subMinutes(40))
                      ->orWhere('delivery_datetime', '<=', Carbon::now('Europe/Dublin'));
            })
            ->get();

        // Log the number of orders found
        foreach ($orders as $order) {
            $order->update(['status' => 5]); // Mark as completed
        }
    }
}
