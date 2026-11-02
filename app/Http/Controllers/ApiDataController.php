<?php

namespace App\Http\Controllers;

use App\Events\OrderCreatedEvent;
use App\Models\MenuAttachment;
use App\Models\Chat;
use App\Models\Delivery;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderNotification;
use App\Models\Rating;
use App\Models\Topping;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\OrderAttachment;
use App\Models\Discount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use App\Mail\OrderConfirmation;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Pusher\Pusher;
use App\Support\PublicMediaUrl;


class ApiDataController extends Controller
{
    public function get_all_menus()
    {
        // Fetch all menus from the database
        $menus = Menu::all();

        // Add website prefix to image URLs
        $menus->each(function ($menu) {
            $menu->image = PublicMediaUrl::make($menu->image);
        });

        // Return a response with the menus
        return response()->json([
            'status' => true,
            'message' => 'Menus retrieved successfully',
            'data' => $menus,
        ], 200);
    }

    public function get_menu_item_by_id($id)
    {
        // Fetch the menu item by its ID with related sizes, toppings, and attachments
        $menuItem = MenuItem::where('id', $id)
            ->with([
                'sizes:id,menu_item_id,size,price,created_at,updated_at',
                'toppings.attachments:id,topping_id,name,price',
            ])
            ->withCount('ratings') // Count the number of ratings
            ->first();

        // Check if the menu item exists
        if (!$menuItem) {
            return response()->json([
                'status' => false,
                'message' => 'Menu item not found',
            ], 404);
        }

        // Add website prefix to the image URL and calculate the average rating
        if ($menuItem->image) {
            $menuItem->image = PublicMediaUrl::make($menuItem->image);
        }
        $menuItem->average_rating = $menuItem->ratings()->avg('score') ?? 0; // Default to 0 if no ratings

        // Transform the menu item data
        $transformedMenuItem = [
            'id' => $menuItem->id,
            'menu_id' => $menuItem->menu_id,
            'title' => $menuItem->title,
            'description' => $menuItem->description,
            'image' => $menuItem->image,
            'price' => $menuItem->price,
            'created_at' => $menuItem->created_at,
            'updated_at' => $menuItem->updated_at,
            'size' => $menuItem->sizes,
            'average_rating' => round($menuItem->average_rating, 1), // Round to 1 decimal
            'rating_count' => $menuItem->ratings_count, // Add rating count
            'topping' => $menuItem->toppings->map(function ($topping) {
                return [
                    'id' => $topping->id,
                    'menuItem' => $topping->menu_item_id,
                    'name' => $topping->name,
                    'required' => $topping->required,
                    'is_optional' => $topping->is_optional,
                    'attachments' => $topping->attachments,
                ];
            }),
        ];

        // Return the response with the transformed menu item data
        return response()->json([
            'status' => true,
            'message' => 'Menu item retrieved successfully',
            'data' => $transformedMenuItem,
        ], 200);
    }


    public function get_menu_items_by_category($id)
    {
        // Fetch the menu items by category ID with related sizes, toppings, and attachments
        $menuItems = MenuItem::where('menu_id', $id)
            ->with([
                'sizes:id,menu_item_id,size,price,created_at,updated_at',
                'toppings.attachments:id,topping_id,name,price',
            ])
            ->withCount('ratings') // Count the number of ratings
            ->get();

        // Add website prefix to image URLs and calculate average rating
        $menuItems->each(function ($menuItem) {
            if ($menuItem->image) {
                $menuItem->image = PublicMediaUrl::make($menuItem->image);
            }

            // Add average rating to the menu item
            $menuItem->average_rating = $menuItem->ratings()->avg('score') ?? 0; // Default to 0 if no ratings
        });

        // Transform the data structure
        $transformedMenuItems = $menuItems->map(function ($menuItem) {
            return [
                'id' => $menuItem->id,
                'menu_id' => $menuItem->menu_id,
                'title' => $menuItem->title,
                'description' => $menuItem->description,
                'image' => $menuItem->image,
                'price' => $menuItem->price,
                'created_at' => $menuItem->created_at,
                'updated_at' => $menuItem->updated_at,
                'size' => $menuItem->sizes,
                'average_rating' => round($menuItem->average_rating, 1), // Round to 1 decimal
                'rating_count' => $menuItem->ratings_count, // Add rating count
                'topping' => $menuItem->toppings->map(function ($topping) {
                    return [
                        'id' => $topping->id,
                        'menuItem' => $topping->menu_item_id,
                        'name' => $topping->name,
                        'required' => $topping->required,
                        'is_optional' => $topping->is_optional,
                        'attachments' => $topping->attachments,
                    ];
                }),
            ];
        });

        // Return response with transformed data
        return response()->json([
            'status' => true,
            'message' => 'Menu items retrieved successfully',
            'data' => $transformedMenuItems,
        ], 200);
    }

    public function get_menu_items_by_rating()
    {
        // Fetch menu items with related sizes, toppings, and attachments
        $menuItems = MenuItem::with([
            'sizes:id,menu_item_id,size,price,created_at,updated_at',
            'toppings.attachments:id,topping_id,name,price',
        ])
            ->withCount('ratings') // Count the number of ratings
            ->get()
            ->each(function ($menuItem) {
                // Calculate average rating for each item
                $menuItem->average_rating = $menuItem->ratings()->avg('score') ?? 0; // Default to 0 if no ratings
            })
            ->sortByDesc('average_rating') // Sort by highest average rating
            ->take(10) // Limit to top 10 items
            ->values(); // Reset keys after sorting

        // Transform the data structure
        $transformedMenuItems = $menuItems->map(function ($menuItem) {
            return [
                'id' => $menuItem->id,
                'title' => $menuItem->title,
                'description' => $menuItem->description,
                'image' => PublicMediaUrl::make($menuItem->image),
                'price' => $menuItem->price,
                'created_at' => $menuItem->created_at,
                'updated_at' => $menuItem->updated_at,
                'size' => $menuItem->sizes,
                'average_rating' => round($menuItem->average_rating, 1), // Rounded average rating
                'rating_count' => $menuItem->ratings_count, // Total number of ratings
                'topping' => $menuItem->toppings->map(function ($topping) {
                    return [
                        'id' => $topping->id,
                        'name' => $topping->name,
                        'required' => $topping->required,
                        'is_optional' => $topping->is_optional,
                        'attachments' => $topping->attachments,
                    ];
                }),
            ];
        });

        // Return the top 10 menu items
        return response()->json([
            'status' => true,
            'message' => 'Top 10 menu items retrieved successfully',
            'data' => $transformedMenuItems->values(),
        ], 200);
    }

    public function search($text)
    {
        // Perform the search in the MenuItem model
        $menuItems = MenuItem::where('title', 'LIKE', "%{$text}%") // Search by title
            ->orWhere('description', 'LIKE', "%{$text}%") // Search by description
            ->with([
                'sizes:id,menu_item_id,size,price,created_at,updated_at',
                'toppings.attachments:id,topping_id,name,price',
            ])
            ->withCount('ratings') // Count the number of ratings
            ->get()
            ->each(function ($menuItem) {
                // Calculate average rating for each item
                $menuItem->average_rating = $menuItem->ratings()->avg('score') ?? 0; // Default to 0 if no ratings
            })
            ->sortByDesc('average_rating') // Sort by highest average rating
            ->values(); // Reset keys after sorting

        // Transform the data structure
        $transformedMenuItems = $menuItems->map(function ($menuItem) {
            return [
                'id' => $menuItem->id,
                'title' => $menuItem->title,
                'description' => $menuItem->description,
                'image' => PublicMediaUrl::make($menuItem->image),
                'price' => $menuItem->price,
                'created_at' => $menuItem->created_at,
                'updated_at' => $menuItem->updated_at,
                'size' => $menuItem->sizes,
                'average_rating' => round($menuItem->average_rating, 1), // Rounded average rating
                'rating_count' => $menuItem->ratings_count, // Total number of ratings
                'topping' => $menuItem->toppings->map(function ($topping) {
                    return [
                        'id' => $topping->id,
                        'name' => $topping->name,
                        'required' => $topping->required,
                        'is_optional' => $topping->is_optional,
                        'attachments' => $topping->attachments,
                    ];
                }),
            ];
        });

        // Return response
        return response()->json([
            'status' => true,
            'message' => 'Search results retrieved successfully',
            'data' => $transformedMenuItems,
        ], 200);
    }

    public function place_order(Request $request)
    {
        // Validate the request
        $validated = $request->all();

        DB::beginTransaction();

        try {
            // Clean the date by removing "- Today" or other suffixes
            $dateStr = $validated['date'];
            $date = explode(' - ', $dateStr)[0]; // Get "27/11" or "27.11"
            $time = $validated['time']; // Get "20:45"



            // Normalize date separator - convert slash to dot if needed
            $date = str_replace('/', '.', $date);

            // Add current year to the date since format is only day.month
            $currentYear = \Carbon\Carbon::now('Europe/Dublin')->format('Y');
            $dateWithYear = "{$date}.{$currentYear}";

            // Combine the date and time, ensuring correct format
            try {
                // Parse with d.m.Y H:i format (e.g., "27.11.2025 20:45")
                $deliveryDateTime = \Carbon\Carbon::createFromFormat('d.m.Y H:i', "{$dateWithYear} {$time}", 'Europe/Dublin');

                // If the date is in the past (for today), set to today
                $now = \Carbon\Carbon::now('Europe/Dublin');
                if ($deliveryDateTime->lt($now)) {
                    // If it's today's date but time has passed, set to tomorrow
                    $todayFormatted = $now->format('d.m');
                    if ($date === $todayFormatted) {
                        $deliveryDateTime->addDay();
                    } else {
                        // Otherwise just use today
                        $deliveryDateTime = $now->copy()->setTimeFromTimeString($time);
                    }
                }
            } catch (\Exception $e) {

                throw new \Exception('Invalid date or time format. Date: ' . $date . ', Time: ' . $time . '. Error: ' . $e->getMessage());
            }

            $paymentDetails = $request->input('payment_details'); // Extract payment details if they exist
            $paymentId = $paymentDetails['paymentIntentId'] ?? null;

            // Calculate subtotal from order items (before discount and fees)
            // Use subtotal from request if provided, otherwise calculate from items
            if (isset($validated['subtotal']) && $validated['subtotal'] > 0) {
                $subtotal = floatval($validated['subtotal']);
            } else {
                $subtotal = 0;
                foreach ($validated['order_items'] as $item) {
                    $subtotal += ($item['price'] * $item['quantity']);
                }
            }

            // Validate minimum order value (only for delivery)
            $deliveryType = $validated['delivery_type'] ?? 'delivery';
            $this->validateMinimumOrder($subtotal, $deliveryType);

            // Use discount from request if provided, otherwise calculate it
            $discountAmount = 0;
            if (isset($validated['discount']) && $validated['discount'] > 0) {
                // Use discount from frontend request
                $discountAmount = floatval($validated['discount']);
            } else {
                // Fallback: Calculate discount based on day of week
                $dayOfWeek = strtolower($deliveryDateTime->format('l')); // e.g., 'monday', 'tuesday'
                $discountData = $this->calculateDiscount($subtotal, $dayOfWeek);
                $discountAmount = $discountData['discount_amount'] ?? 0;
            }

            // Use total_price from request (already includes discount, delivery fee, service fee)
            $finalTotal = $validated['total_price'];

            // 1. Create the Order
            $order = Order::create([
                'subtotal' => $subtotal,
                'discount' => $discountAmount,
                'total_price' => $finalTotal,
                'customer_id' => $validated['customer'],
                'status' => 1, // Pendding
                'description' => Order::withSource($validated['description'] ?? null, 'mobile'),
                'phone_number' => $validated['phone_number'],
                'deliveryman_id' => null, // Default null
                'payment_method' => $validated['payment_method'],
                'area_code' => $validated['area_code'] ?? '',
                'address' => $validated['houseadress'] ?? '',
                'longitude' => $validated['longitude'] ?? '0',
                'latitude' => $validated['latitude'] ?? '0',
                'delivery_datetime' => $deliveryDateTime,
                'type' => strtolower($validated['delivery_type']) === 'delivery' ? 1 : 2, // 1 = Delivery, 2 = Collection
                'payment_id' => $paymentId,
            ]);

            // 3. Add Order Items
            foreach ($validated['order_items'] as $item) {
                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'menu_item_id' => $item['food'],
                    'quantity' => $item['quantity'],
                    'size_id' => $item['size'] ?? null, // Nullable
                    'price' => $item['price'],
                ]);

                // 4. Add Attachments for each Order Item
                if (!empty($item['attachments'])) {
                    foreach ($item['attachments'] as $attachment) {
                        $attachmentModel = MenuAttachment::find($attachment['id']);

                        // If attachment not found by ID, try to find by name (for drinks that might be menu items)
                        if (!$attachmentModel) {
                            // This might be a menu item ID instead of attachment ID
                            // Try to find a menu item and create/find corresponding attachment
                            $menuItem = MenuItem::find($attachment['id']);
                            if ($menuItem) {
                                // Try to find an attachment with the same name
                                $attachmentModel = MenuAttachment::where('name', $menuItem->title)
                                    ->orWhere('name', $menuItem->name)
                                    ->first();

                                // If still not found, log a warning but continue
                                if (!$attachmentModel) {

                                    // Skip this attachment or create a placeholder
                                    continue;
                                }
                            } else {

                                continue;
                            }
                        }

                        OrderMenuAttachment::create([
                            'order_item_id' => $orderItem->id,
                            'attachment_id' => $attachmentModel->id,
                            'quantity' => $attachment['quantity'],
                            'topping_id' => $attachmentModel->topping_id ?? null,
                        ]);
                    }
                }
            }

            // Store Notification
            OrderNotification::create([
                'customer_id' => $order->customer_id,
                'order_id' => $order->id,
                'read_status' => false, // Default to unread
            ]);

            DB::commit();

            // Send FCM notifications
            try {
                // Send notification to customer
                $customer = User::find($order->customer_id);
                if ($customer) {
                    \App\Helpers\FcmNotificationHelper::sendOrderNotification($customer, $order->id, 'placed');
                }

                // Send notification to admins
                \App\Helpers\FcmNotificationHelper::notifyAdminsAboutNewOrder($order->id);
            } catch (\Exception $e) {
                // Log FCM error but don't fail the order
                Log::error('FCM notification failed: ' . $e->getMessage());
            }

            // Broadcast order event to trigger sound notification
            \App\Helpers\BroadcastHelper::broadcastOrderCreated($order->id);

            // Send confirmation email to customer
            try {
                $order->load(['customer', 'order_items.menu_item', 'order_items.size', 'order_items.orderAttachments.attachment']);
                if ($order->customer && $order->customer->email) {
                    Mail::to($order->customer->email)->send(new OrderConfirmation($order));
                }
            } catch (\Exception $e) {
                // Log email error but don't fail the order

            }

            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully!',
                'order_id' => $order->id,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();


            return response()->json([
                'success' => false,
                'message' => 'Failed to place the order: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getNotifications(Request $request)
    {
        $customerId = $request->query(key: 'customer');

        if (!$customerId || !is_numeric($customerId)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing customer ID.'
            ], 400);
        }

        // Fetch notifications for the given customer
        $notifications = OrderNotification::where('customer_id', $customerId)
            ->with(['customer', 'order']) // Assuming relationships exist
            ->get();

        // Format the response
        $response = $notifications->map(function ($notification) {
            return [
                'id' => $notification->id,
                'customer_details' => [
                    'id' => $notification->customer->id,
                    'username' => $notification->customer->username,
                    'email' => $notification->customer->email,
                    'name' => $notification->customer->name,
                ],
                'order_details' => [
                    'id' => $notification->order->id,
                    'status' => $notification->order->status,
                    'discount' => $notification->order->discount,
                    'description' => $notification->order->description,
                    'total_price' => $notification->order->total_price,
                ],
                'created_at' => $notification->created_at->toIso8601String(),
                'updated_at' => $notification->updated_at->toIso8601String(),
                'read_status' => $notification->read_status,
                'customer' => $notification->customer_id,
                'order' => $notification->order_id,
            ];
        });

        return response()->json($response, 200);
    }

    public function get_all_menu_items()
    {
        // Fetch all menu items with related data and include rating count
        $menuItems = MenuItem::with([
            'sizes:id,menu_item_id,size,price,created_at,updated_at',
            'toppings.attachments:id,topping_id,name,price',
        ])
            ->withCount('ratings') // Count the number of ratings
            ->get();

        // Add website prefix to image URLs and calculate average rating
        $menuItems->each(function ($menuItem) {
            if ($menuItem->image) {
                $menuItem->image = PublicMediaUrl::make($menuItem->image);
            }

            // Add average rating to the menu item
            $menuItem->average_rating = $menuItem->ratings()->avg('score') ?? 0; // Default to 0 if no ratings
        });

        // Transform the data structure
        $transformedMenuItems = $menuItems->map(function ($menuItem) {
            return [
                'id' => $menuItem->id,
                'menu' => $menuItem->menu,
                'size' => $menuItem->sizes,
                'average_rating' => round($menuItem->average_rating, 1), // Round to 1 decimal
                'rating_count' => $menuItem->ratings_count, // Add rating count
                'topping' => $menuItem->toppings->map(function ($topping) {
                    return [
                        'id' => $topping->id,
                        'menuItem' => $topping->menu_item_id,
                        'name' => $topping->name,
                        'required' => $topping->required,
                        'is_optional' => $topping->is_optional,
                        'attachments' => $topping->attachments,
                    ];
                }),
                'created_at' => $menuItem->created_at,
                'updated_at' => $menuItem->updated_at,
                'title' => $menuItem->title,
                'description' => $menuItem->description,
                'image' => $menuItem->image,
                'price' => $menuItem->price,
                'menu_id' => $menuItem->menu_id,
            ];
        });

        // Return response with transformed data
        return response()->json([
            'status' => true,
            'message' => 'Menu items retrieved successfully',
            'data' => $transformedMenuItems,
        ], 200);
    }

    public function rate_menu_item(Request $request)
    {
        // Validate the request data
        $validatedData = $request->validate([
            'food_menu' => 'required|exists:menu_items,id',
            'user' => 'required|exists:users,id',
            'score' => 'required|integer|min:1|max:5',
        ]);

        // Check if the user already rated this menu item
        $rating = Rating::updateOrCreate(
            [
                'menu_item_id' => $validatedData['food_menu'], // Use `food_menu` as `menu_item_id`
                'user_id' => $validatedData['user'],           // Use `user` as `user_id`
            ],
            [
                'score' => $validatedData['score'],           // Use `score` directly
            ]
        );

        return response()->json([
            'status' => true,
            'message' => 'Rating submitted successfully',
            'data' => $rating,
        ], 200);
    }

    public function get_general_rating()
    {
        // Get total count of all ratings
        $totalReviews = Rating::count();

        // Calculate overall average rating from all ratings
        $overallRating = Rating::avg('score') ?? 0;

        return response()->json([
            'status' => true,
            'message' => 'General rating retrieved successfully',
            'data' => [
                'total_reviews' => $totalReviews,
                'overall_rating' => round($overallRating, 1), // Round to 1 decimal place (e.g., 4.3)
            ],
        ], 200);
    }

    public function markAsRead($id)
    {
        // Find the notification by ID
        $notification = OrderNotification::find($id);

        if ($notification) {
            // Update the read_status to true
            $notification->update(['read_status' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read.',
                'notification' => $notification,
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Notification not found.',
        ], 404);
    }

    public function customer_orders($customer_id)
    {
        $orders = Order::where('customer_id', $customer_id)
            ->with(['order_items.menu_item', 'order_items.size', 'order_items.orderAttachments.attachment'])
            ->get();

        // Format the orders to include items and attachments
        $orders->each(function ($order) {
            $order->items = $order->order_items->map(function ($item) {
                $attachments = $item->orderAttachments->map(function ($orderAttachment) {
                    return [
                        'id' => $orderAttachment->attachment_id,
                        'name' => $orderAttachment->attachment->name ?? 'Unknown',
                        'price' => $orderAttachment->attachment->price ?? 0,
                        'quantity' => $orderAttachment->quantity,
                    ];
                });

                return [
                    'id' => $item->id,
                    'menu_item_id' => $item->menu_item_id,
                    'name' => $item->menu_item->title ?? $item->menu_item->name ?? 'Unknown',
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'size' => $item->size ? [
                        'id' => $item->size->id,
                        'name' => $item->size->name,
                    ] : null,
                    'attachments' => $attachments,
                ];
            });
            unset($order->order_items); // Remove the original relation to avoid confusion
        });

        return response()->json([
            'status' => true,
            'message' => 'Orders retrieved successfully',
            'data' => $orders,
        ], 200);
    }

    public function delivery_orders($delivery_man_id)
    {

        $orders = Order::where('deliveryman_id', $delivery_man_id)->where('status', '!=', 5)->where('status', '!=', 3)->where('type', 1)->get();
        $orders->each(function ($order) {
            if ($order->customer_id) {
                $customer = $order->customer;
                $order->customer_name = $customer->name ?? 'N/A';
                $order->latitude = $order->latitude ?? 'N/A';
                $order->longitude = $order->longitude ?? 'N/A';
                $order->area_code = $order->area_code ?? 'N/A';
                $order->phone_number = $order->phone_number ?? 'N/A';
                unset($order->customer);
            }
        });
        return response()->json([
            'status' => true,
            'message' => 'Orders retrieved successfully',
            'data' => $orders,
        ], 200);
    }



    public function orderDeliveryCompleted(Request $request, $orderId)
    {
        // Find the order by ID
        $order = Order::find($orderId);

        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Order not found',
            ], 404);
        }

        // Update the order status to 5
        $order->status = 5;
        $order->save();

        // Load customer relationship
        $order->load(['customer']);

        // Send FCM notification to customer
        if ($order->customer) {
            try {
                \App\Helpers\FcmNotificationHelper::sendOrderNotification($order->customer, $order->id, 5);
            } catch (\Exception $e) {
                Log::error('Failed to send FCM notification for order completion:', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Order delivery status updated to completed',
            'order' => $order,
        ], 200);
    }

    public function getActiveStaff()
    {
        // Fetch users where is_active and is_staff are true
        $users = User::where('is_active', true)
            ->where('role', 3)
            ->select('id', 'name', 'image')
            ->get();
        $users->each(function ($user) {
            $user->image = PublicMediaUrl::make($user->image);
        });
        // Return the response as JSON
        return response()->json([
            'success' => true,
            'data' => $users,
        ], 200);
    }


    public function send_message(Request $request)
    {
        // Validate the request
        $request->validate([
            'sender' => 'required|exists:users,id', // Sender ID in the request body
            'receiver' => 'required|exists:users,id',
            'message' => 'nullable|string',
            'attachment' => 'nullable|file|max:2048',
        ]);

        // Handle attachment upload
        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('chat-attachments', 'public');
        }

        // Create the chat message
        $chat = Chat::create([
            'sender' => $request->sender,
            'receiver' => $request->receiver,
            'message' => $request->message,
            'attachment' => $attachmentPath,
            'is_sent' => true,
        ]);

        // Send FCM notification to receiver
        try {
            $sender = User::find($request->sender);
            $receiver = User::find($request->receiver);

            if ($sender && $receiver && !empty($receiver->fcm_token)) {
                \App\Helpers\FcmNotificationHelper::sendMessageNotification($receiver, $sender, $request->message ?? 'New message');
            }
        } catch (\Exception $e) {
            Log::error('Failed to send FCM notification for message:', [
                'sender_id' => $request->sender,
                'receiver_id' => $request->receiver,
                'error' => $e->getMessage()
            ]);
        }

        // Add the full URL to the attachment if it exists
        if ($chat->attachment) {
            $chat->attachment = PublicMediaUrl::make($chat->attachment);
        }

        // Return the response
        return response()->json([
            'status' => true,
            'message' => 'Message sent successfully',
            'data' => $chat,
        ], 201);
    }


    public function receive_message($user1_id, $user2_id)
    {
        // Fetch chat messages between the two users
        $messages = \App\Models\Chat::where(function ($query) use ($user1_id, $user2_id) {
            $query->where('sender', $user1_id)
                ->where('receiver', $user2_id);
        })
            ->orWhere(function ($query) use ($user1_id, $user2_id) {
                $query->where('sender', $user2_id)
                    ->where('receiver', $user1_id);
            })
            ->orderBy('created_at', 'asc')
            ->get();

        if ($messages->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No messages found between these users',
            ], 404);
        }

        $messages->transform(function ($message) {
            if ($message->attachment) {
                $message->attachment = PublicMediaUrl::make($message->attachment);
            }
            return $message;
        });

        return response()->json([
            'status' => true,
            'message' => 'Messages retrieved successfully',
            'data' => $messages,
        ], 200);
    }

    public function all_fess()
    {
        // Fetch all fees from the database
        $fees = Delivery::all()->first();

        // Return a response with the fees
        return response()->json([
            'status' => true,
            'message' => 'Fees retrieved successfully',
            'data' => $fees,
        ], 200);
    }
    public function delete_account(Request $request)
    {
        $authUser = $request->user();
        if (! $authUser) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        // Only allow deleting the authenticated account (ignore spoofed user_id).
        $user = User::find($authUser->id);
        if (! $user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found',
            ], 404);
        }

        if ($user->canAccessAdminPanel()) {
            return response()->json([
                'status' => false,
                'message' => 'Staff accounts cannot be deleted from the app',
            ], 403);
        }

        try {
            DB::transaction(function () use ($user) {
                // Remove profile image from disk when stored locally.
                if (! empty($user->image) && ! str_starts_with((string) $user->image, 'http')) {
                    try {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($user->image);
                    } catch (\Throwable) {
                        // ignore storage cleanup failures
                    }
                }

                // Drop push + API sessions so the account cannot keep receiving data.
                $user->tokens()->delete();

                // Keep order rows for legal/tax retention, but strip contact PII.
                Order::query()
                    ->where('customer_id', $user->id)
                    ->update([
                        'customer_name' => 'Deleted User',
                        'phone_number' => null,
                        'address' => null,
                    ]);

                OrderNotification::query()
                    ->where('customer_id', $user->id)
                    ->delete();

                Chat::query()
                    ->where(function ($q) use ($user) {
                        $q->where('sender', $user->id)
                            ->orWhere('receiver', $user->id);
                    })
                    ->delete();

                // Anonymise the user record so it cannot be used to identify the person.
                $user->forceFill([
                    'name' => 'Deleted User',
                    'email' => 'deleted_'.$user->id.'_'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(10)).'@deleted.local',
                    'phone_number' => null,
                    'area_code' => null,
                    'address' => null,
                    'latitude' => null,
                    'longitude' => null,
                    'image' => null,
                    'fcm_token' => null,
                    'google_id' => null,
                    'apple_id' => null,
                    'password' => \Illuminate\Support\Str::random(64),
                    'is_active' => false,
                    'remember_token' => null,
                    'disabled_at' => now(),
                ])->save();

                $user->delete();
            });
        } catch (\Throwable $e) {
            Log::error('delete_account failed: '.$e->getMessage(), [
                'user_id' => $authUser->id,
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Could not delete account',
            ], 500);
        }

        return response()->json([
            'status' => true,
            'message' => 'Account deleted successfully',
        ], 200);
    }

    /**
     * Get all drinks/addons from the database
     * Returns attachments that can be used as drinks/add-ons
     * First tries to get from a special "Drinks" topping, then falls back to menu items
     */
    public function get_drinks()
    {
        try {
            // First, try to get attachments from a "Drinks" topping
            // You may need to create a special topping for drinks in your admin panel
            $drinksTopping = \App\Models\Topping::where('name', 'LIKE', '%Drink%')
                ->orWhere('name', 'LIKE', '%drink%')
                ->first();

            if ($drinksTopping) {
                $attachments = MenuAttachment::where('topping_id', $drinksTopping->id)
                    ->select('id', 'name', 'price')
                    ->orderBy('name', 'asc')
                    ->get();

                if ($attachments->count() > 0) {
                    // Format prices to ensure they're numeric
                    $formattedAttachments = $attachments->map(function ($attachment) {
                        $price = is_numeric($attachment->price)
                            ? (float)$attachment->price
                            : (is_string($attachment->price) ? (float)str_replace(['€', ' ', ','], '', $attachment->price) : 0);

                        return [
                            'id' => $attachment->id,
                            'name' => $attachment->name,
                            'price' => $price,
                        ];
                    });

                    return response()->json([
                        'status' => true,
                        'message' => 'Drinks retrieved successfully',
                        'data' => $formattedAttachments,
                    ], 200);
                }
            }

            // Fallback: Find the "Minerals" category and create attachments from menu items
            $drinksCategory = Menu::where('id', 13)->first();

            if ($drinksCategory) {
                // Fetch menu items from the drinks category
                $menuItems = MenuItem::where('menu_id', $drinksCategory->id)
                    ->where('status', 1)
                    ->select('id', 'title', 'price')
                    ->orderBy('title', 'asc')
                    ->get();

                // For each menu item, find or create a corresponding attachment
                $formattedDrinks = $menuItems->map(function ($menuItem) {
                    // Try to find an existing attachment with the same name
                    $attachment = MenuAttachment::where('name', $menuItem->title)
                        ->orWhere('name', 'LIKE', '%' . $menuItem->title . '%')
                        ->first();

                    if (!$attachment) {
                        // If no attachment exists, return the menu item data
                        // The backend will handle the lookup by name when placing orders
                        $price = is_numeric($menuItem->price)
                            ? (float)$menuItem->price
                            : (is_string($menuItem->price) ? (float)str_replace(['€', ' ', ','], '', $menuItem->price) : 0);

                        return [
                            'id' => $menuItem->id, // This is a menu_item_id, not attachment_id
                            'name' => $menuItem->title,
                            'price' => $price,
                            'is_menu_item' => true, // Flag to indicate this is a menu item, not attachment
                        ];
                    }

                    $price = is_numeric($attachment->price)
                        ? (float)$attachment->price
                        : (is_string($attachment->price) ? (float)str_replace(['€', ' ', ','], '', $attachment->price) : 0);

                    return [
                        'id' => $attachment->id,
                        'name' => $attachment->name,
                        'price' => $price,
                    ];
                });

                return response()->json([
                    'status' => true,
                    'message' => 'Drinks retrieved successfully',
                    'data' => $formattedDrinks,
                ], 200);
            }

            // If no drinks category found, return empty array
            return response()->json([
                'status' => true,
                'message' => 'No drinks category found',
                'data' => [],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error retrieving drinks: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    public function create_checkout_session(Request $request)
    {
        try {
            Stripe::setApiKey(env('STRIPE_SECRET'));

            $orderData = $request->input('order_data');
            $items = $request->input('items');
            $subtotal = $request->input('subtotal', 0);
            $deliveryFee = $request->input('deliveryFee', 0);
            $serviceFee = $request->input('serviceFee', 0);
            $total = $request->input('total', 0);

            // Parse date and time for validation
            $dateStr = $orderData['date'] ?? '';
            $time = $orderData['time'] ?? '';
            $date = explode(' - ', $dateStr)[0];
            $date = str_replace('/', '.', $date);
            $currentYear = \Carbon\Carbon::now('Europe/Dublin')->format('Y');
            $dateWithYear = "{$date}.{$currentYear}";

            try {
                $deliveryDateTime = \Carbon\Carbon::createFromFormat('d.m.Y H:i', "{$dateWithYear} {$time}", 'Europe/Dublin');
                $now = \Carbon\Carbon::now('Europe/Dublin');
                if ($deliveryDateTime->lt($now)) {
                    $todayFormatted = $now->format('d.m');
                    if ($date === $todayFormatted) {
                        $deliveryDateTime->addDay();
                    } else {
                        $deliveryDateTime = $now->copy()->setTimeFromTimeString($time);
                    }
                }
            } catch (\Exception $e) {
                $deliveryDateTime = \Carbon\Carbon::now('Europe/Dublin');
            }

            // Calculate subtotal from order items (before discount and fees)
            // Use subtotal from request if provided, otherwise calculate from items
            if (isset($orderData['subtotal']) && $orderData['subtotal'] > 0) {
                $subtotal = floatval($orderData['subtotal']);
            } else {
                $subtotal = 0;
                if (isset($orderData['order_items']) && is_array($orderData['order_items'])) {
                    foreach ($orderData['order_items'] as $item) {
                        $subtotal += ($item['price'] * $item['quantity']);
                    }
                }
            }

            // Validate minimum order value (only for delivery)
            $deliveryType = $orderData['delivery_type'] ?? 'delivery';
            $this->validateMinimumOrder($subtotal, $deliveryType);

            // Use discount from request if provided, otherwise calculate it
            $discountAmount = 0;
            if (isset($orderData['discount']) && $orderData['discount'] > 0) {
                // Use discount from frontend request
                $discountAmount = floatval($orderData['discount']);
            } else {
                // Fallback: Calculate discount based on day of week
                $dayOfWeek = strtolower($deliveryDateTime->format('l'));
                $discountData = $this->calculateDiscount($subtotal, $dayOfWeek);
                $discountAmount = $discountData['discount_amount'] ?? 0;
            }

            // Use total from session data (already includes discount, delivery fee, service fee)
            $finalTotal = $total;

            // Store order data in metadata (order will be created in webhook after payment success)
            // Note: Stripe metadata has 500 char limit per key, so we store as JSON string
            $orderMetadata = [
                'subtotal' => $subtotal,
                'discount' => $discountAmount,
                'total_price' => $finalTotal,
                'customer_id' => $orderData['customer'] ?? null,
                'description' => Order::withSource($orderData['description'] ?? null, 'mobile'),
                'phone_number' => $orderData['phone_number'] ?? null,
                'area_code' => $orderData['area_code'] ?? '',
                'address' => $orderData['houseadress'] ?? '',
                'longitude' => $orderData['longitude'] ?? '0',
                'latitude' => $orderData['latitude'] ?? '0',
                'delivery_datetime' => $deliveryDateTime->toIso8601String(),
                'delivery_type' => strtolower($orderData['delivery_type'] ?? 'delivery'),
                'order_items' => $orderData['order_items'] ?? [],
            ];

            // Build line items for Stripe
            $lineItems = [];

            // Calculate total of all menu items before discount (for proportional discount application)
            $totalMenuItemsPrice = 0;
            foreach ($items as $item) {
                $totalMenuItemsPrice += $item['totalPrice'];
            }

            // Apply discount ONLY to menu items (food), not to service fee or delivery fee
            // Discount is applied proportionally across all menu items
            $discountRatio = 0;
            if ($discountAmount > 0 && $totalMenuItemsPrice > 0) {
                $discountRatio = $discountAmount / $totalMenuItemsPrice;
            }

            // Add menu items with discount applied
            foreach ($items as $item) {
                $originalUnitPrice = $item['totalPrice'] / $item['quantity'];
                // Apply discount proportionally to this item
                $discountedUnitPrice = $originalUnitPrice * (1 - $discountRatio);
                $unitPriceInCents = (int)round($discountedUnitPrice * 100);

                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'eur',
                        'product_data' => [
                            'name' => $item['name'],
                        ],
                        'unit_amount' => max(50, $unitPriceInCents), // Minimum 50 cents per item
                    ],
                    'quantity' => $item['quantity'],
                ];
            }

            // Add service fee (unchanged - discount does not apply to fees)
            if ($serviceFee > 0) {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'eur',
                        'product_data' => [
                            'name' => 'Service Fee',
                        ],
                        'unit_amount' => (int)round($serviceFee * 100),
                    ],
                    'quantity' => 1,
                ];
            }

            // Add delivery fee (unchanged - discount does not apply to fees)
            if ($deliveryFee > 0) {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'eur',
                        'product_data' => [
                            'name' => 'Delivery Fee',
                        ],
                        'unit_amount' => (int)round($deliveryFee * 100),
                    ],
                    'quantity' => 1,
                ];
            }

            // Note: Discount is applied only to food items (menu items), not to service or delivery fees
            // Stripe doesn't support negative unit_amount values, so discount is applied by reducing item prices

            // Get frontend URL from env or default
            $frontendUrl = env('FRONTEND_URL', 'https://oscarlimerick.com');

            // Get customer information if customer_id is provided
            $customerEmail = null;
            $customerName = null;
            if (!empty($orderData['customer'])) {
                $customer = User::find($orderData['customer']);
                if ($customer) {
                    $customerEmail = $customer->email;
                    $customerName = $customer->name;
                }
            }

            // Create Stripe checkout session with customer info and Ireland payment methods
            // Store order data in metadata (order will be created after payment success)
            // Note: Stripe metadata has 500 char limit per key, so we split large JSON into chunks
            $orderDataJson = json_encode($orderMetadata);
            $orderDataMetadata = $this->splitMetadataForStripe($orderDataJson);
            
            $sessionData = [
                'payment_method_types' => ['card', 'link'],
                'line_items' => $lineItems,
                'mode' => 'payment',
                'success_url' => $frontendUrl . '/order-success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $frontendUrl . '/',
                'metadata' => array_merge([
                    'customer_id' => (string)($orderData['customer'] ?? ''),
                ], $orderDataMetadata),
            ];

            // Add customer name to metadata if available
            if ($customerName) {
                $sessionData['metadata']['customer_name'] = $customerName;
            }

            // Add customer email if available (pre-fills email in Stripe checkout)
            if ($customerEmail) {
                $sessionData['customer_email'] = $customerEmail;
            }

            $session = Session::create($sessionData);

            // Order will be created in webhook handler after payment success

            return response()->json([
                'success' => true,
                'url' => $session->url,
                'session_id' => $session->id,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create checkout session: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get order by Stripe session ID
     */
    public function get_order_by_session($sessionId)
    {
        try {
            // First, try to find order by payment_id (which might be the session ID or payment intent)
            $order = Order::where('payment_id', $sessionId)
                ->with(['order_items.menu_item', 'order_items.size', 'order_items.orderAttachments.attachment'])
                ->first();

            // If not found, try to get from Stripe to find the order_id in metadata or create from order_data
            if (!$order) {
                Stripe::setApiKey(env('STRIPE_SECRET'));
                $session = \Stripe\Checkout\Session::retrieve($sessionId);

                // Verify payment was successful
                if ($session->payment_status !== 'paid') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Payment not completed for this session',
                    ], 400);
                }

                // Double-check: Order might have been created by webhook while we were retrieving session
                $order = Order::where('payment_id', $sessionId)
                    ->with(['order_items.menu_item', 'order_items.size', 'order_items.orderAttachments.attachment'])
                    ->first();

                // Check if order_id is in metadata (legacy checkout flow)
                if (!$order && $session && isset($session->metadata['order_id'])) {
                    $order = Order::where('id', $session->metadata['order_id'])
                        ->with(['order_items.menu_item', 'order_items.size', 'order_items.orderAttachments.attachment'])
                        ->first();
                }

                // If order not found and order_data exists in metadata, create order from metadata
                // Use database lock to prevent race condition with webhook
                // Reconstruct order_data from chunks if it was split (handles both single and multi-chunk formats)
                $orderDataJson = $this->reconstructMetadataFromStripe($session->metadata);
                if (!$order && $orderDataJson) {
                    $orderMetadata = json_decode($orderDataJson, true);
                    
                    if ($orderMetadata) {
                        // Use lock to prevent race condition with webhook handler
                        DB::beginTransaction();
                        try {
                            // Final check: Order might have been created by webhook during transaction
                            $existingOrder = Order::where('payment_id', $sessionId)->lockForUpdate()->first();
                            if ($existingOrder) {
                                DB::rollBack();
                                $order = $existingOrder->load(['order_items.menu_item', 'order_items.size', 'order_items.orderAttachments.attachment']);
                            } else {
                                // Parse delivery datetime
                                $deliveryDateTime = \Carbon\Carbon::parse($orderMetadata['delivery_datetime']);

                                // Create the order
                                $order = Order::create([
                                'subtotal' => $orderMetadata['subtotal'],
                                'discount' => $orderMetadata['discount'],
                                'total_price' => $orderMetadata['total_price'],
                                'customer_id' => $orderMetadata['customer_id'] ?? null,
                                'status' => 1, // Pending
                                'description' => Order::withSource($orderMetadata['description'] ?? null, 'mobile'),
                                'phone_number' => $orderMetadata['phone_number'] ?? null,
                                'deliveryman_id' => null,
                                'payment_method' => 'online',
                                'area_code' => $orderMetadata['area_code'] ?? '',
                                'address' => $orderMetadata['address'] ?? '',
                                'longitude' => $orderMetadata['longitude'] ?? '0',
                                'latitude' => $orderMetadata['latitude'] ?? '0',
                                'delivery_datetime' => $deliveryDateTime,
                                'type' => strtolower($orderMetadata['delivery_type'] ?? 'delivery') === 'delivery' ? 1 : 2,
                                'payment_id' => $sessionId, // Store session ID for order lookup
                            ]);

                            // Add order items
                            if (isset($orderMetadata['order_items']) && is_array($orderMetadata['order_items'])) {
                                foreach ($orderMetadata['order_items'] as $item) {
                                    $orderItem = OrderItem::create([
                                        'order_id' => $order->id,
                                        'menu_item_id' => $item['food'],
                                        'quantity' => $item['quantity'],
                                        'size_id' => $item['size'] ?? null,
                                        'price' => $item['price'],
                                    ]);

                                    // Add attachments
                                    if (!empty($item['attachments'])) {
                                        foreach ($item['attachments'] as $attachment) {
                                            $attachmentModel = MenuAttachment::find($attachment['id']);

                                            if (!$attachmentModel) {
                                                $menuItem = MenuItem::find($attachment['id']);
                                                if ($menuItem) {
                                                    $attachmentModel = MenuAttachment::where('name', $menuItem->title)
                                                        ->orWhere('name', 'LIKE', '%' . $menuItem->title . '%')
                                                        ->first();
                                                }
                                            }

                                            if ($attachmentModel) {
                                                OrderMenuAttachment::create([
                                                    'order_item_id' => $orderItem->id,
                                                    'attachment_id' => $attachmentModel->id,
                                                    'quantity' => $attachment['quantity'],
                                                    'topping_id' => $attachmentModel->topping_id ?? null,
                                                ]);
                                            }
                                        }
                                    }
                                }
                            }

                                // Create OrderNotification record
                                OrderNotification::create([
                                    'customer_id' => $order->customer_id,
                                    'order_id' => $order->id,
                                    'read_status' => false, // Default to unread
                                ]);

                                DB::commit();

                                // Load relationships for response
                                $order->load(['order_items.menu_item', 'order_items.size', 'order_items.orderAttachments.attachment']);

                                // Send FCM notifications
                                try {
                                    $customer = $order->customer;
                                    if ($customer) {
                                        \App\Helpers\FcmNotificationHelper::sendOrderNotification($customer, $order->id, 'placed');
                                    }
                                    \App\Helpers\FcmNotificationHelper::notifyAdminsAboutNewOrder($order->id);
                                } catch (\Exception $e) {
                                    Log::error('FCM notification failed:', [
                                        'order_id' => $order->id,
                                        'error' => $e->getMessage()
                                    ]);
                                }
                                
                                // Send confirmation email to customer
                                try {
                                    $order->load(['customer', 'order_items.menu_item', 'order_items.size', 'order_items.orderAttachments.attachment']);
                                    if ($order->customer && $order->customer->email) {
                                        Mail::to($order->customer->email)->send(new OrderConfirmation($order));
                                    }
                                } catch (\Exception $e) {
                                    Log::error('Order confirmation email failed: ' . $e->getMessage());
                                }
                                
                                // Broadcast order event to trigger sound notification
                                \App\Helpers\BroadcastHelper::broadcastOrderCreated($order->id);
                            }
                        } catch (\Exception $e) {
                            DB::rollBack();
                            Log::error('Error creating order in get_order_by_session:', [
                                'session_id' => $sessionId,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString()
                            ]);
                            // Try to find order one more time in case webhook created it
                            $order = Order::where('payment_id', $sessionId)
                                ->with(['order_items.menu_item', 'order_items.size', 'order_items.orderAttachments.attachment'])
                                ->first();
                            if (!$order) {
                                throw $e;
                            }
                        }
                    }
                }

                // Fallback: try payment intent
                if (!$order && isset($session->payment_intent)) {
                    $order = Order::where('payment_id', $session->payment_intent)
                        ->with(['order_items.menu_item', 'order_items.size', 'order_items.orderAttachments.attachment'])
                        ->first();
                }
            }

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found for this session',
                ], 404);
            }

            // Format the order similar to customer_orders
            $order->items = $order->order_items->map(function ($item) {
                $attachments = $item->orderAttachments->map(function ($orderAttachment) {
                    return [
                        'id' => $orderAttachment->attachment_id,
                        'name' => $orderAttachment->attachment->name ?? 'Unknown',
                        'price' => $orderAttachment->attachment->price ?? 0,
                        'quantity' => $orderAttachment->quantity,
                    ];
                });

                return [
                    'id' => $item->id,
                    'menu_item_id' => $item->menu_item_id,
                    'name' => $item->menu_item->title ?? $item->menu_item->name ?? 'Unknown',
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'size' => $item->size ? [
                        'id' => $item->size->id,
                        'name' => $item->size->name,
                    ] : null,
                    'attachments' => $attachments,
                ];
            });
            unset($order->order_items);

            return response()->json([
                'success' => true,
                'message' => 'Order retrieved successfully',
                'data' => $order,
            ], 200);
        } catch (\Exception $e) {


            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve order: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calculate discount for an order based on day
     */
    private function calculateDiscount($subtotal, $dayOfWeek)
    {
        $discounts = Discount::where('is_active', true)->get();
        $bestDiscount = null;
        $maxDiscountAmount = 0;

        foreach ($discounts as $discount) {
            // Check if discount is valid for the day of week
            if (!$discount->isValidFor($dayOfWeek)) {
                continue;
            }

            // Check if order meets minimum purchase requirement
            if (!$discount->meetsMinimumPurchase($subtotal)) {
                continue;
            }

            // Calculate discount amount
            $discountAmount = $discount->calculateDiscount($subtotal);
            if ($discountAmount > $maxDiscountAmount) {
                $maxDiscountAmount = $discountAmount;
                $bestDiscount = $discount;
            }
        }

        return [
            'discount' => $bestDiscount,
            'discount_amount' => $maxDiscountAmount,
        ];
    }

    /**
     * Validate minimum order value (only for delivery orders)
     */
    private function validateMinimumOrder($subtotal, $deliveryType = 'delivery')
    {
        // Skip validation for collection orders
        if (strtolower($deliveryType) === 'collection') {
            return;
        }
        
        $delivery = Delivery::first();
        $minOrderValue = $delivery ? ($delivery->min_order ?? 0) : 0;
        
        if ($minOrderValue > 0 && $subtotal < $minOrderValue) {
            throw new \Exception("Minimum order value is €" . number_format($minOrderValue, 2) . ". Your order total is €" . number_format($subtotal, 2) . ".");
        }
    }

    /**
     * Get settings (min order value)
     */
    public function get_settings()
    {
        try {
            $delivery = Delivery::first();
            $settings = [
                'min_order_value' => $delivery ? ($delivery->min_order ?? 0) : 0,
            ];

            return response()->json([
                'success' => true,
                'data' => $settings,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve settings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Return Stripe publishable key for the client (never the secret).
     */
    public function get_stripe_key()
    {
        $publishable = env('STRIPE_KEY');

        if (!$publishable) {
            return response()->json([
                'status' => false,
                'message' => 'Stripe publishable key is not configured',
            ], 500);
        }

        return response()->json([
            'status' => true,
            'message' => 'Stripe key retrieved successfully',
            'data' => [
                'publishable_key' => $publishable,
                'stripe_publishable_key' => $publishable,
                'stripe_key' => $publishable,
            ],
        ], 200);
    }

    /**
     * Create a Stripe PaymentIntent for mobile Payment Sheet.
     * Amount is expected in major currency units (e.g. euros).
     */
    public function create_payment_intent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.5',
            'currency' => 'nullable|string|size:3',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $secret = env('STRIPE_SECRET');
        $publishable = env('STRIPE_KEY');

        if (!$secret) {
            return response()->json([
                'status' => false,
                'message' => 'Stripe secret key is not configured',
            ], 500);
        }

        try {
            Stripe::setApiKey($secret);
            $currency = strtolower((string) ($request->input('currency') ?: 'eur'));
            $amountCents = (int) round(((float) $request->input('amount')) * 100);

            $intent = \Stripe\PaymentIntent::create([
                'amount' => $amountCents,
                'currency' => $currency,
                'payment_method_types' => ['card'],
                'metadata' => [
                    'source' => 'oscar_mobile',
                ],
            ]);

            return response()->json([
                'status' => true,
                'message' => 'PaymentIntent created',
                'data' => [
                    'client_secret' => $intent->client_secret,
                    'payment_intent_id' => $intent->id,
                    'publishable_key' => $publishable,
                    'amount' => $intent->amount,
                    'currency' => $intent->currency,
                    'status' => $intent->status,
                ],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('create_payment_intent failed: '.$e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to create payment intent',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get active discounts
     */
    public function get_active_discounts()
    {
        try {
            $discounts = Discount::where('is_active', true)
                ->get()
                ->map(function ($discount) {
                    return [
                        'id' => $discount->id,
                        'name' => $discount->name,
                        'discount_type' => $discount->discount_type,
                        'discount_value' => $discount->discount_value,
                        'minimum_purchase_amount' => $discount->minimum_purchase_amount,
                        'days_of_week' => $discount->days_of_week,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $discounts,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve discounts: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Split JSON string into chunks that fit Stripe's 500 character metadata limit
     * 
     * @param string $jsonString The JSON string to split
     * @return array Array of metadata fields ['order_data' => '...'] or ['order_data_1' => '...', 'order_data_2' => '...']
     */
    private function splitMetadataForStripe($jsonString)
    {
        $maxLength = 500;
        
        // If the string fits in one chunk, return it as-is (backward compatible)
        if (strlen($jsonString) <= $maxLength) {
            return ['order_data' => $jsonString];
        }
        
        // Split into chunks
        $chunks = [];
        $chunkIndex = 1;
        $offset = 0;
        
        while ($offset < strlen($jsonString)) {
            $chunk = substr($jsonString, $offset, $maxLength);
            $chunks["order_data_{$chunkIndex}"] = $chunk;
            $offset += $maxLength;
            $chunkIndex++;
        }
        
        return $chunks;
    }

    /**
     * Reconstruct JSON string from Stripe metadata chunks
     * 
     * @param array $metadata The metadata array from Stripe session
     * @return string|null The reconstructed JSON string, or null if not found
     */
    private function reconstructMetadataFromStripe($metadata)
    {
        // Check if it's a single chunk (backward compatibility)
        if (isset($metadata['order_data'])) {
            return $metadata['order_data'];
        }
        
        // Reconstruct from multiple chunks
        $chunks = [];
        $chunkIndex = 1;
        
        while (isset($metadata["order_data_{$chunkIndex}"])) {
            $chunks[] = $metadata["order_data_{$chunkIndex}"];
            $chunkIndex++;
        }
        
        if (empty($chunks)) {
            return null;
        }
        
        return implode('', $chunks);
    }
}
