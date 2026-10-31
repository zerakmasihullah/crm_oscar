<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Concerns\PaginatesInertia;
use App\Http\Controllers\Controller;
use App\Mail\NotificationEmail;
use App\Mail\ReservationNotification;
use App\Models\Reservation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class ReservationController extends Controller
{
    use AuthorizesMisPermissions;
    use PaginatesInertia;

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'reservations.view');

        $search = trim((string) $request->query('search', ''));

        $items = Reservation::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Reservation $reservation) => $this->serialize($reservation));

        return Inertia::render('resources/reservations/Index', [
            'items' => $this->inertiaPagination($items),
            'filters' => [
                'search' => $search !== '' ? $search : null,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizePermission($request, 'reservations.create');

        return Inertia::render('resources/reservations/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'reservations.create');

        $reservation = Reservation::query()->create($this->validated($request));

        return redirect()
            ->route('reservations.show', $reservation)
            ->with('success', __('Created.'));
    }

    public function show(Request $request, Reservation $reservation): Response
    {
        $this->authorizePermission($request, 'reservations.view');

        return Inertia::render('resources/reservations/Show', [
            'item' => $this->serialize($reservation),
        ]);
    }

    public function edit(Request $request, Reservation $reservation): Response
    {
        $this->authorizePermission($request, 'reservations.edit');

        return Inertia::render('resources/reservations/Edit', [
            'item' => $this->serialize($reservation),
        ]);
    }

    public function update(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorizePermission($request, 'reservations.edit');

        $previousAccepted = (bool) $reservation->accepted;
        $validated = $this->validated($request);
        $reservation->update($validated);

        if (
            array_key_exists('accepted', $validated)
            && (bool) $validated['accepted'] !== $previousAccepted
        ) {
            $this->sendAcceptanceEmail($reservation, (bool) $validated['accepted']);
        }

        return redirect()
            ->route('reservations.show', $reservation)
            ->with('success', __('Updated.'));
    }

    public function destroy(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorizePermission($request, 'reservations.delete');

        $reservation->delete();

        return redirect()
            ->route('reservations.index')
            ->with('success', __('Deleted.'));
    }

    public function toggleAccepted(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorizePermission($request, 'reservations.edit');

        $validated = $request->validate([
            'accepted' => ['required', 'boolean'],
        ]);

        $accepted = (bool) $validated['accepted'];
        $reservation->update(['accepted' => $accepted]);
        $this->sendAcceptanceEmail($reservation, $accepted);

        return back()->with('success', __('Updated.'));
    }

    protected function sendAcceptanceEmail(Reservation $reservation, bool $accepted): void
    {
        if (! filled($reservation->email)) {
            return;
        }

        if ($accepted) {
            $subject = 'Your Reservation Accepted';
            $body = '
                <p>Congratulations! We are pleased to inform you that your reservation has been <strong>approved</strong>.</p>
                <p>Thank you for choosing us.</p>
                <p>Best regards,</p>
                <p>Oscar Pizza Kabab House</p>
            ';
        } else {
            $subject = 'Your Reservation is Rejected';
            $body = '
                <p>We are sorry to inform you that we are unable to accept new reservations at this time.</p>
                <p>If you have any questions or need further assistance, please don\'t hesitate to contact us.</p>
                <p>Best regards,</p>
                <p>Oscar Pizza Kabab House</p>
            ';
        }

        try {
            if (class_exists(NotificationEmail::class)) {
                Mail::to($reservation->email)->send(new NotificationEmail($subject, $body));

                return;
            }

            if (class_exists(ReservationNotification::class)) {
                Mail::to($reservation->email)->send(new ReservationNotification($reservation));
            }
        } catch (\Throwable) {
            // Keep the status update even if mail fails.
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'person' => ['required', 'string', 'max:255'],
            'reservation_date' => ['required', 'date'],
            'reservation_time' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
            'accepted' => ['sometimes', 'boolean'],
            'status' => ['nullable', 'string', 'max:255'],
        ]);

        if ($request->has('accepted')) {
            $validated['accepted'] = $request->boolean('accepted');
        }

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(Reservation $reservation): array
    {
        return [
            'id' => $reservation->id,
            'name' => $reservation->name,
            'email' => $reservation->email,
            'phone' => $reservation->phone,
            'person' => $reservation->person,
            'reservation_date' => $reservation->reservation_date?->format('Y-m-d'),
            'reservation_time' => $reservation->reservation_time,
            'message' => $reservation->message,
            'accepted' => (bool) $reservation->accepted,
            'status' => $reservation->status,
            'created_at' => $reservation->created_at?->toIso8601String(),
            'updated_at' => $reservation->updated_at?->toIso8601String(),
        ];
    }
}
