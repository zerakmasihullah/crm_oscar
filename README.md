# Oscar CMS

Takeaway order management for **Oscar's Pizza & Kebab** — live orders, menu, delivery, promotions, and staff.

## Stack

- Laravel 13 + Fortify + Sanctum
- Inertia 3 + Vue 3 + TypeScript + Tailwind 4
- Oscar brand theme (orange `#EA7516`)
- Spatie Permission + Activitylog
- Stripe · FCM · Pusher for order flow

## Setup

```bash
cp .env.example .env
# Set DB_* and APP_KEY — use a dedicated DB (e.g. oscar_cms)
composer install
php artisan key:generate
npm install
php artisan migrate --seed
npm run build
php artisan serve
```

## Login (seeded)

| Field | Value |
|-------|--------|
| Email | `admin@example.com` |
| Password | `password` |

## Customer storefront

The public ordering site (formerly the separate Next.js `website` app) lives in this repo as Inertia Vue pages:

- `/` — menu & ordering
- `/checkout`, `/my-orders`, `/order-success`
- `/support`, `/terms-of-use`, `/privacy-statement`
- Admin CMS entry: `/admin` → login or dashboard

Frontend: `resources/js/pages/storefront/` + `resources/js/components/storefront/`  
API: same app `/api/*` (`routes/oscar_api.php`)

## Brand assets

Food imagery and logo live under `public/brand/` and `public/images/auth/hero.jpg`.  
Storefront assets: `public/storefront/`.
