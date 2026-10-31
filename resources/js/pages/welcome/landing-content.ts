/**
 * Landing page copy — Oscar takeaway operations CMS.
 */
export const featureGroups: Array<{
    id: string;
    titleKey: string;
    title: string;
    leadKey: string;
    lead: string;
    items: Array<{
        titleKey: string;
        title: string;
        bodyKey: string;
        body: string;
    }>;
}> = [
    {
        id: 'orders',
        titleKey: 'landing.group.access.title',
        title: 'Orders & service',
        leadKey: 'landing.group.access.lead',
        lead: 'Takeaway flow from order in to food out.',
        items: [
            {
                titleKey: 'landing.feat.auth.title',
                title: 'Live order board',
                bodyKey: 'landing.feat.auth.body',
                body: 'See new app, web, and counter orders the moment they land.',
            },
            {
                titleKey: 'landing.feat.rbac.title',
                title: 'Kitchen & delivery roles',
                bodyKey: 'landing.feat.rbac.body',
                body: 'Owner, kitchen, and delivery staff with the right permissions.',
            },
            {
                titleKey: 'landing.feat.users.title',
                title: 'Staff & customers',
                bodyKey: 'landing.feat.users.body',
                body: 'Manage accounts, delivery riders, and customer profiles.',
            },
            {
                titleKey: 'landing.feat.sessions.title',
                title: 'Shift-safe sessions',
                bodyKey: 'landing.feat.sessions.body',
                body: 'Revoke sessions and keep the till locked down after close.',
            },
        ],
    },
    {
        id: 'menu',
        titleKey: 'landing.group.i18n.title',
        title: 'Menu & pricing',
        leadKey: 'landing.group.i18n.lead',
        lead: 'Pizza, kebab, sides, sizes, toppings — one menu system.',
        items: [
            {
                titleKey: 'landing.feat.i18n.title',
                title: 'Menus & items',
                bodyKey: 'landing.feat.i18n.body',
                body: 'Categories, sizes, toppings, and attachments for every dish.',
            },
            {
                titleKey: 'landing.feat.translations.title',
                title: 'Discounts & deals',
                bodyKey: 'landing.feat.translations.body',
                body: 'Run promos and minimums that match your takeaway offers.',
            },
            {
                titleKey: 'landing.feat.jalali.title',
                title: 'Delivery zones & fees',
                bodyKey: 'landing.feat.jalali.body',
                body: 'Set delivery rules so checkout matches what the kitchen can serve.',
            },
        ],
    },
    {
        id: 'ops',
        titleKey: 'landing.group.ops.title',
        title: 'Ops & growth',
        leadKey: 'landing.group.ops.lead',
        lead: 'Keep the shop running after the rush.',
        items: [
            {
                titleKey: 'landing.feat.backups.title',
                title: 'Reservations',
                bodyKey: 'landing.feat.backups.body',
                body: 'Table bookings alongside takeaway volume.',
            },
            {
                titleKey: 'landing.feat.audit.title',
                title: 'Promotions & email',
                bodyKey: 'landing.feat.audit.body',
                body: 'Push offers to newsletter subscribers and track sends.',
            },
            {
                titleKey: 'landing.feat.notify.title',
                title: 'Push notifications',
                bodyKey: 'landing.feat.notify.body',
                body: 'FCM alerts when orders place, update, or complete.',
            },
            {
                titleKey: 'landing.feat.attachments.title',
                title: 'Payments',
                bodyKey: 'landing.feat.attachments.body',
                body: 'Stripe checkout for app and online orders.',
            },
            {
                titleKey: 'landing.feat.seo.title',
                title: 'Ratings & feedback',
                bodyKey: 'landing.feat.seo.body',
                body: 'See how dishes land with customers after service.',
            },
            {
                titleKey: 'landing.feat.email.title',
                title: 'Backups & audit',
                bodyKey: 'landing.feat.email.body',
                body: 'Protect order history with backups and activity logs.',
            },
        ],
    },
];

export const highlights: Array<{
    titleKey: string;
    title: string;
    bodyKey: string;
    body: string;
    tag: string;
}> = [
    {
        tag: '01',
        titleKey: 'landing.hi.ship.title',
        title: 'Orders land in one place',
        bodyKey: 'landing.hi.ship.body',
        body: 'App, web, and counter tickets feed the same kitchen board.',
    },
    {
        tag: '02',
        titleKey: 'landing.hi.ai.title',
        title: 'Built for takeaway pace',
        bodyKey: 'landing.hi.ai.body',
        body: 'Status changes, delivery assign, and completion without clutter.',
    },
    {
        tag: '03',
        titleKey: 'landing.hi.field.title',
        title: 'Riders stay in sync',
        bodyKey: 'landing.hi.field.body',
        body: 'Delivery staff see their runs; customers get status updates.',
    },
    {
        tag: '04',
        titleKey: 'landing.hi.i18n.title',
        title: 'Menu that matches the board',
        bodyKey: 'landing.hi.i18n.body',
        body: 'Sizes, toppings, and deals stay consistent from CMS to app.',
    },
    {
        tag: '05',
        titleKey: 'landing.hi.ops.title',
        title: 'Ops after the rush',
        bodyKey: 'landing.hi.ops.body',
        body: 'Promotions, reservations, backups, and staff access in one admin.',
    },
    {
        tag: '06',
        titleKey: 'landing.hi.clone.title',
        title: 'Oscar brand throughout',
        bodyKey: 'landing.hi.clone.body',
        body: 'Your colours, logo, and kitchen language — not a generic kit shell.',
    },
];

export const aiCapabilities: Array<{
    titleKey: string;
    title: string;
    bodyKey: string;
    body: string;
}> = [
    {
        titleKey: 'landing.ai.cap.inline.title',
        title: 'Order status assist',
        bodyKey: 'landing.ai.cap.inline.body',
        body: 'Optional AI helps draft customer replies and promo copy.',
    },
    {
        titleKey: 'landing.ai.cap.fill.title',
        title: 'Faster menu entry',
        bodyKey: 'landing.ai.cap.fill.body',
        body: 'Describe a dish and fill form fields for new menu items.',
    },
    {
        titleKey: 'landing.ai.cap.chat.title',
        title: 'Ops chat',
        bodyKey: 'landing.ai.cap.chat.body',
        body: 'Ask about recent orders and catalog records with approve-gated deletes.',
    },
    {
        titleKey: 'landing.ai.cap.analytics.title',
        title: 'Sales insights',
        bodyKey: 'landing.ai.cap.analytics.body',
        body: 'Natural language questions over order and menu data.',
    },
    {
        titleKey: 'landing.ai.cap.files.title',
        title: 'Menu media',
        bodyKey: 'landing.ai.cap.files.body',
        body: 'Attach food photos and docs to the records that need them.',
    },
    {
        titleKey: 'landing.ai.cap.providers.title',
        title: 'Your models',
        bodyKey: 'landing.ai.cap.providers.body',
        body: 'Bring OpenAI, Anthropic, Gemini, or local models when AI is on.',
    },
];

export const audiences: Array<{
    titleKey: string;
    title: string;
    bodyKey: string;
    body: string;
}> = [
    {
        titleKey: 'landing.aud.product.title',
        title: 'Shop owners',
        bodyKey: 'landing.aud.product.body',
        body: 'See the whole takeaway — orders, menu, staff — without juggling apps.',
    },
    {
        titleKey: 'landing.aud.agency.title',
        title: 'Kitchen managers',
        bodyKey: 'landing.aud.agency.body',
        body: 'Keep tickets moving and toppings accurate during peak hours.',
    },
    {
        titleKey: 'landing.aud.ngo.title',
        title: 'Delivery teams',
        bodyKey: 'landing.aud.ngo.body',
        body: 'Assigned runs and clear order detail on the road.',
    },
    {
        titleKey: 'landing.aud.rtl.title',
        title: 'Front-of-house',
        bodyKey: 'landing.aud.rtl.body',
        body: 'Counter orders and reservations next to online demand.',
    },
];

export const commands: Array<{ cmd: string; bodyKey: string; body: string }> = [
    {
        cmd: 'php artisan migrate --seed',
        bodyKey: 'landing.cmd.install',
        body: 'Install Oscar CMS tables and seed the owner account.',
    },
    {
        cmd: 'php artisan mis:sync-permissions',
        bodyKey: 'landing.cmd.perms',
        body: 'Sync permissions after you add modules.',
    },
    {
        cmd: 'php artisan promotions:send-scheduled',
        bodyKey: 'landing.cmd.scaffold',
        body: 'Send due promotion emails to subscribers.',
    },
    {
        cmd: 'php artisan orders:auto-complete',
        bodyKey: 'landing.cmd.offline',
        body: 'Auto-complete stale orders on the schedule.',
    },
    {
        cmd: 'php artisan backup:run',
        bodyKey: 'landing.cmd.backup',
        body: 'Back up the takeaway database and files.',
    },
];

export const stackItems: string[] = [
    'Laravel 13 + Fortify + Sanctum',
    'Inertia · Vue 3 · TypeScript · Vite',
    'Orders, menu, delivery, promotions',
    'Stripe · FCM · Pusher',
    'Spatie Permission + Activitylog',
    'Oscar brand theme',
];

export const notIncluded: string[] = [
    'Customer-facing storefront UI',
    'Filament admin panels',
    'Multi-tenant franchise SaaS',
];

export const faqs: Array<{
    qKey: string;
    q: string;
    aKey: string;
    a: string;
}> = [
    {
        qKey: 'landing.faq.1.q',
        q: 'What is Oscar CMS?',
        aKey: 'landing.faq.1.a',
        a: 'The operations desk for Oscar takeaway — orders, menu, delivery, promotions, and staff in one system.',
    },
    {
        qKey: 'landing.faq.2.q',
        q: 'Who uses it?',
        aKey: 'landing.faq.2.a',
        a: 'Owners, kitchen, delivery riders, and front-of-house during service.',
    },
    {
        qKey: 'landing.faq.3.q',
        q: 'Does it replace the customer app?',
        aKey: 'landing.faq.3.a',
        a: 'No. The mobile/API stack places orders; this CMS runs the shop that serves them.',
    },
    {
        qKey: 'landing.faq.4.q',
        q: 'Can we keep Oscar branding?',
        aKey: 'landing.faq.4.a',
        a: 'Yes — orange brand colours, logo, and takeaway language are the default.',
    },
    {
        qKey: 'landing.faq.5.q',
        q: 'What about payments and push?',
        aKey: 'landing.faq.5.a',
        a: 'Stripe checkout and FCM notifications are wired for order flow.',
    },
    {
        qKey: 'landing.faq.6.q',
        q: 'How do I get started?',
        aKey: 'landing.faq.6.a',
        a: 'Set .env, migrate --seed, npm run build, php artisan serve. Demo: admin@example.com / password.',
    },
];
