# AI writing assist

Cursor-style **Ctrl+K / ⌘K** on text fields, plus **Fill form** for whole pages. Configure under **Settings → AI**.

## Enable

1. Settings → AI → add a model + API key → turn **On** → Save.
2. Focus any text field and press **Ctrl+K** (⌘K on Mac), or click the sparkle that appears beside the field.
3. Type what you want → Enter → **Accept** or **Reject**, or follow up in the same panel.

## Fill an entire form

When **Fill form** is enabled:

1. Focus a field inside a form (or open a page with one main form).
2. Press **Ctrl+Shift+K** (⌘⇧K) to open Fill form.
3. Describe the person or record in plain language, e.g. *“Fatima Ahmadi, female, from Herat, phone 0700…”*.
4. Review the preview → **Accept all** or **Reject**.

Privacy: only field **labels / types / options** and your description are sent — not current field values, passwords, or the rest of the page.

Opt in / out:

- Wrap a region with `data-ai-form` when multiple forms exist
- Skip a control or section with `data-ai-ignore`
- Optional stable keys: `data-ai-field="first_name"`

## Providers

Built-in: OpenAI, Anthropic, DeepSeek, Groq, Mistral, Google (Gemini), OpenRouter, Together, Fireworks, Ollama, and **Custom / Other** (any OpenAI-compatible base URL).

You can keep several models (up to the configured max). Mark one as **Default**.

## Provider failover

When **Provider failover** is On, a failed primary call (timeout, 5xx, rejected key) automatically tries the next saved models that have API keys — in the Models list order. Failed attempts are still written to Usage logs; a successful failover is flagged.

This is handled inside Kit (PHP) — no LangChain / Python stack required.

## System prompts

**Settings → AI → Prompts**:

- **Base instructions** — optional text prepended to every action
- Per-action prompts (grammar, improve, fill form, …) — leave blank to use the built-in default; **Use default** clears an override

## Auto

**Settings → AI → Assist** toggles field editing, Fill form, provider failover, and Ctrl+K actions (including **Auto** mode).

## Tasks (task management AI)

**Settings → AI → Tasks**:

- Enable **Task management AI** (header **AI** opens General Chat)
- **Allow creating & updating records** — off by default; turns on catalog create/update tools (resources with `require_approval` still pause for Approve)
- **Allow deleting records** — off by default; high impact; deletes pause for Approve when catalog requires it (default)
- **Allow analytics (read-only SQL)** — off by default; NL→SELECT reporting over the database (General Chat + **AI Analytics** page); requires `ai.analytics`
- **Observability (Langfuse)** — optional; store public/secret keys and base URL (encrypted secret). When On with both keys, Kit traces General Chat + Ctrl+K assist. Use **Test connection** on the Tasks tab. Match Base URL to your Langfuse region (US: `https://us.cloud.langfuse.com`). Traces may take a few seconds to appear.

When create and delete are off, chat can still list/read registered resources. Analytics is separate.

## Risk controls

- Capability toggles gate which tools exist; permissions still apply per user.
- Catalog `require_approval` decides which create/update/delete verbs pause for **Approve / Reject** (users/roles require create+update+delete; other modules default to delete only).
- Server-side `AiRiskGuard` blocks jailbreak / instruction-override phrases on **General Chat**, **Ctrl+K assist**, **Fill form**, and **analytics** prompts (`config/ai.php` → `risk`).
- User content is fenced as untrusted data before it reaches models; agents include a shared Safety appendix.
- KitAgent / analytics agents refuse prompt exfiltration and non-SELECT SQL.
- Blocked chat attempts are logged as usage action `chat_blocked`.
- Optional: `KIT_AI_BLOCK_JAILBREAK=false` to disable phrase blocking (not recommended).

## Security model

**Principle:** the model suggests; PHP enforces. The agent cannot act above the signed-in user.

| Layer | Gate |
|---|---|
| Org toggles | Settings → AI → Tasks (chat / create / delete / analytics) |
| AI permissions | `ai.view` / `ai.chat` / `ai.assist` / `ai.analytics` / `ai.manage` |
| Resource permissions | Catalog `permission` / `permissions` map (`field_reports.view`, …) |
| Row scope | Catalog `user_scoped` + `user_id_column` (Eloquent + analytics SQL) |
| Privilege grants | Catalog `privilege_fields` + `PrivilegeGrantGuard` (subset of actor’s grants) |
| Protected rows | Catalog `protected_names` (rename/delete/privilege sync) |
| Human approval | Catalog `require_approval` verbs |
| Analytics SQL | SELECT-only `SqlGuard`, table allowlist, deny columns, optional `DB_AI_READONLY_CONNECTION` |

### Catalog security flags

When adding a module (`kit:scaffold` or manual entry in `config/ai_resources.php`):

| Flag | Default (scaffold) | Meaning |
|---|---|---|
| `user_scoped` / `user_id_column` | from scaffold | Own-rows only for CRUD + analytics |
| `require_approval` | `['delete']` | Verbs that need Approve in chat |
| `privilege_fields` | `[]` | Fields that assign authority (`roles`, `permissions`, …) and must be ⊆ actor’s grants |
| `protected_names` | `[]` | Row names that cannot be renamed/deleted or privilege-edited by non-holders |
| `sensitive` | `[]` | Attributes stripped from GetRecord / denied in analytics |
| `related_tables` | `[]` | Extra analytics tables unlocked with this resource’s view permission |

Enforcement code must not branch on resource keys (`users`, `roles`, …). Opt into stricter behavior via flags only.

Production: set `DB_AI_READONLY_CONNECTION` (SELECT-only DB user). Kit refuses analytics in production when that env is missing (`KIT_AI_REQUIRE_READONLY_ANALYTICS`, default true).

## Where it works

Works on normal text inputs and textareas app-wide.

Skipped by default: password, date/time pickers, file, checkbox, radio, hidden, and anything marked `data-ai-ignore`.

Number, email, url, tel, and search fields are included — add `data-ai-ignore` on a field to opt out.

## Usage logs

**Settings → AI → Usage logs** lists every assist call with:

- Time, user, action, model
- Token counts (prompt / completion / total)
- Estimated USD spend (from `config/ai.php` pricing — estimates, not invoices)
- Duration (ms)
- Short previews of instruction / input / output (not full secrets)

Summary cards cover today, this month, and all-time tokens + cost.

## Files

- `resources/js/components/AiInlineEdit.vue` — floating Ctrl+K panel
- `resources/js/components/AiFormFill.vue` — whole-form fill panel
- `resources/js/components/AiChatPanel.vue` — General Chat agent panel
- `resources/js/components/AiChatMessageContent.vue` — clickable Open links in chat
- `resources/js/composables/useAiInlineEdit.ts`
- `resources/js/composables/useAiFormFill.ts`
- `resources/js/composables/useAiChat.ts`
- `app/Services/Ai/AiAssistService.php`
- `app/Services/Ai/AiChatService.php`
- `app/Services/Ai/AiRiskGuard.php`
- `app/Services/Ai/AiResourceCrudService.php`
- `app/Services/Ai/AiAnalyticsService.php`
- `app/Services/Ai/AiAnalyticsReportService.php`
- `app/Services/Ai/AiChatAttachmentService.php`
- `config/ai_resources.php`
- `config/langfuse.php`
- `app/Ai/Agents/KitAgent.php`
- `app/Ai/AgentToolRegistry.php`
- `app/Support/AiSettings.php`
- Settings page: `resources/js/pages/settings/Ai.vue`

## General Chat agent

Header **AI** opens General Chat (side panel). One **catalog-driven** agent can CRUD any registered section (field reports, users, roles, and anything you add to [`config/ai_resources.php`](../config/ai_resources.php)). Optional **analytics** converts questions to read-only SQL. Writing assist (Ctrl+K) and Fill form stay separate.

Tool results include markdown **Open** links; the panel renders them as clickable Inertia links.

### Enable

1. Settings → AI → turn **On**, add a model + API key.
2. Open **Tasks** → enable **Task management AI**.
3. Turn on create/update, delete, and/or analytics as needed (all default Off except chat itself).
4. Grant AI access on the role (`ai.view` / `ai.chat` / `ai.assist` / `ai.analytics` / `ai.manage`), plus module permissions (`field_reports.*`, `users.*`, `roles.*`, …).
5. Ask e.g. *“Create a field report titled Site visit in Kabul”* or *“Create a role Finance Clerk with expenses view permissions”*.
6. Multi-step works in one message. Deletes pause for **Approve / Reject**.
7. Attach PDFs/images/TXT/CSV via the paperclip — ask to summarize, extract fields, or save into catalog resources (e.g. create users from a list).

### File attachments

- Up to 5 files per message (default max 10 MB each) — PDF, JPEG/PNG/WebP/GIF, TXT, CSV.
- Stored under `storage/app/ai-chat/{user_id}/…` and sent to the model as Laravel AI attachments (vision/document).
- Limits: `config/ai.php` → `chat_attachments`.

### Registering a new section (no new tool classes)

Prefer **`php artisan kit:scaffold Expense`** — it appends an entry to `config/ai_resources.php` automatically. Then add routes + `mis_permissions` as usual.

Or add a catalog entry manually:

```php
'expenses' => [
    'label' => 'Expenses',
    'model' => \App\Models\Expense::class,
    'permission' => 'expenses',
    'index_route' => 'expenses.index',
    'edit_route' => 'expenses.edit',
    'fillable' => ['name', 'amount'],
    'rules' => [
        'create' => [/* ... */],
        'update' => [/* ... */],
    ],
    // search, display, user_scoped, sensitive, require_approval, privilege_fields, …
],
```

Generic tools (`ListResources`, `ListRecords`, `GetRecord`, `CreateRecord`, `UpdateRecord`, `DeleteRecord`) pick it up immediately.

### Analytics

With **Allow analytics** on, `RunAnalyticsQuery` turns a natural-language question into a guarded **SELECT**. Writes are rejected; client-supplied SQL is not accepted from the chat tool. Deny-lists live under `config/ai.php` → `analytics`. Chat analytics needs `ai.analytics` plus table/module view permissions (not `ai.chat` alone).

### AI Analytics page

**AI Analytics** (`/ai/analytics`) is a dedicated dashboard builder:

1. Grant `ai.view` (open page) and/or `ai.analytics` (generate reports). Enable **Allow analytics** under Settings → AI → Tasks.
2. Open **AI Analytics** from the main nav.
3. Describe what you want (e.g. *“Give me a clear overview of our users”*).
4. Kit plans several safe queries, runs them, and renders **KPIs**, **charts**, **tables**, and **insight cards**. Progress steps stream live while the report builds.

API: `POST /ai/analytics/generate` with `{ "prompt": "…" }` → structured JSON report. Add `?stream=1` (or `Accept: text/event-stream`) for SSE progress events (`step`, `done`, `error`). Usage is logged as action `analytics_report`.

SQL is authored for the active DB dialect (MySQL vs SQLite). Common SQLite date idioms are rewritten for MySQL automatically.

### AI access (Roles)

Settings → Users access → **Roles** → edit permissions. The **AI access** block is pinned first:

| Permission | Who it is for |
|---|---|
| *(none)* | Cannot use AI |
| `ai.view` | See AI Analytics page and usage logs (read-only) |
| `ai.chat` | Use General Chat |
| `ai.assist` | Use Ctrl+K writing assist and form fill |
| `ai.analytics` | Generate AI Analytics reports |
| `ai.manage` | Change AI settings, models, and prompts |

Org kill-switches under Settings → AI still apply on top of these grants.

### Data access

**Settings → AI → Data access** lists every catalog model/table (`config/ai_resources.php`).

For each resource you can toggle **View / Create / Edit / Delete** (or use presets: Full, Read only, Off). Defaults are all **On**.

- **View Off** — AI cannot use that model/table at all (chat tools + analytics), for anyone.
- **Read only** — AI can list/read, but not create/update/delete.
- Search + filters scale to large catalogs (100+ resources).

Org Data access sits above role permissions: a role with `users.edit` still cannot change users via AI if Edit is Off here.

### Files (agent)

- `config/ai_resources.php` — resource catalog
- `app/Support/AiResourceCatalog.php`
- `app/Services/Ai/AiResourceCrudService.php`
- `app/Services/Ai/AiAnalyticsService.php` / `SqlGuard.php`
- `app/Services/Ai/AiAnalyticsReportService.php` / `AnalyticsVisualizationBuilder.php`
- `app/Ai/Agents/AnalyticsPlanAgent.php` / `AnalyticsInsightAgent.php`
- `app/Http/Controllers/AiAnalyticsController.php`
- `resources/js/pages/AiAnalytics.vue`
- `resources/js/components/ai-analytics/AnalyticsChart.vue`
- `app/Ai/Tools/Catalog/*` — generic CRUD + analytics tools
- `app/Ai/Agents/KitAgent.php` / `SqlAuthorAgent.php`
- `app/Support/AiChatLinks.php`
- `resources/js/components/AiChatMessageContent.vue`
- `app/Http/Controllers/AiChatController.php`
- `app/Support/AiSdkBridge.php`
