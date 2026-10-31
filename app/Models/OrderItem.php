<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;
    protected $fillable = [
        'order_id',
        'menu_item_id',
        'quantity',
        'size_id',
        'price',
    ];
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function menu_item()
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function size()
    {
        return $this->belongsTo(Size::class);
    }

    public function orderAttachments()
    {
        return $this->hasMany(OrderAttachment::class);
    }

    public function kitchenCopies(): array
    {
        $itemQty = max((int) $this->quantity, 1);
        $sections = $this->orderedSections();
        $choiceGroups = [];
        $lines = [];

        $pizzaGroups = array_values(array_filter(
            $sections['pizzas'],
            fn ($pizza) => ($pizza['items'] ?? []) !== [] || ($pizza['extras'] ?? []) !== []
        ));

        foreach ($pizzaGroups as $index => $pizza) {
            if ($index > 0) {
                $lines[] = ['separator' => true, 'name' => '---'];
            }

            $this->appendSection($lines, null, $pizza['items'] ?? []);

            $pizzaExtras = $pizza['extras'] ?? [];
            if ($pizzaExtras !== []) {
                $this->appendSection($lines, null, $pizzaExtras);
            }
        }

        foreach ($sections['included'] as $included) {
            $line = $included + ['header' => false];
            if (str_contains(strtolower($included['name'] ?? ''), 'chip')) {
                $line['spacer_before'] = true;
            }
            $lines[] = $line;
        }

        $this->appendSection($lines, null, $sections['dips']['items'] ?? []);
        $this->appendSection($lines, null, $sections['drinks']['items'] ?? []);

        if ($lines !== []) {
            $choiceGroups[] = $lines;
        }

        return [
            'quantity' => $itemQty,
            'price' => (float) $this->price * $itemQty,
            'choice_groups' => $choiceGroups,
        ];
    }

    protected function orderedSections(): array
    {
        $pizzas = [];
        $dips = ['label' => null, 'items' => []];
        $drinks = ['label' => null, 'items' => []];
        $included = [];
        $used = [];

        $toppings = $this->menu_item?->toppings ?? collect();

        foreach ($toppings as $topping) {
            $selected = $this->orderAttachments
                ->filter(fn ($attachment) => (int) $attachment->topping_id === (int) $topping->id)
                ->filter(fn ($attachment) => filled($attachment->attachment?->name));

            if ($selected->isEmpty()) {
                continue;
            }

            $items = [];
            foreach ($selected as $attachment) {
                $used[] = $attachment->id;
                $items[] = $this->choiceFromAttachment($attachment, str_contains(strtolower($topping->name), 'extra'));
            }
            $items = $this->mergeByName($items);

            $type = $this->classifyTopping($topping);
            $label = $this->cleanGroupLabel($topping->name);

            if ($type === 'extras') {
                $pizzaNo = $this->pizzaIndex($topping->name);
                if (!isset($pizzas[$pizzaNo])) {
                    $pizzas[$pizzaNo] = ['label' => $label, 'items' => [], 'extras' => []];
                }
                foreach ($items as $item) {
                    $item['show_price'] = true;
                    $pizzas[$pizzaNo]['extras'][] = $item;
                }
                $pizzas[$pizzaNo]['extras'] = $this->mergeByName($pizzas[$pizzaNo]['extras']);
                continue;
            }

            if ($type === 'dips') {
                $dips['label'] = $label ?: 'Dip';
                $dips['items'] = $this->mergeByName(array_merge($dips['items'], $items));
                continue;
            }

            if ($type === 'drinks') {
                $drinks['label'] = $label ?: 'Can';
                $drinks['items'] = $this->mergeByName(array_merge($drinks['items'], $items));
                continue;
            }

            if ($type === 'included') {
                $included = $this->mergeByName(array_merge($included, $items));
                continue;
            }

            $pizzaNo = $this->pizzaIndex($topping->name);
            if (!isset($pizzas[$pizzaNo])) {
                $pizzas[$pizzaNo] = ['label' => $label, 'items' => [], 'extras' => []];
            }
            $pizzas[$pizzaNo]['items'] = $this->mergeByName(array_merge($pizzas[$pizzaNo]['items'], $items));
        }

        foreach ($this->orderAttachments as $attachment) {
            if (in_array($attachment->id, $used, true) || !filled($attachment->attachment?->name)) {
                continue;
            }

            $type = $this->classifyAttachment($attachment);
            $choice = $this->choiceFromAttachment($attachment, $type === 'extras');

            if ($type === 'extras') {
                $choice['show_price'] = true;
                $pizzaNo = $this->pizzaIndex($attachment->topping?->name);
                if (!isset($pizzas[$pizzaNo])) {
                    $pizzas[$pizzaNo] = ['label' => 'Toppings', 'items' => [], 'extras' => []];
                }
                $pizzas[$pizzaNo]['extras'] = $this->mergeByName(array_merge($pizzas[$pizzaNo]['extras'] ?? [], [$choice]));
            } elseif ($type === 'dips') {
                $dips['items'] = $this->mergeByName(array_merge($dips['items'], [$choice]));
                $dips['label'] = $dips['label'] ?: 'Dip';
            } elseif ($type === 'drinks') {
                $drinks['items'] = $this->mergeByName(array_merge($drinks['items'], [$choice]));
                $drinks['label'] = $drinks['label'] ?: 'Can';
            } elseif ($type === 'included') {
                $included = $this->mergeByName(array_merge($included, [$choice]));
            } else {
                $pizzas[1]['label'] = $pizzas[1]['label'] ?? 'Toppings';
                $pizzas[1]['items'] = $this->mergeByName(array_merge($pizzas[1]['items'] ?? [], [$choice]));
                $pizzas[1]['extras'] = $pizzas[1]['extras'] ?? [];
            }
        }

        ksort($pizzas);

        $existing = strtolower(collect($pizzas)->pluck('items')->flatten(1)->pluck('name')->implode(' '));
        foreach ($this->includedFromDescription($existing) as $item) {
            $included = $this->mergeByName(array_merge($included, [$item]));
        }

        return [
            'pizzas' => $pizzas,
            'dips' => $dips,
            'drinks' => $drinks,
            'included' => $included,
        ];
    }

    protected function choiceFromAttachment($attachment, bool $showPrice = false): array
    {
        $qty = max((int) $attachment->quantity, 1);

        return [
            'name' => trim((string) $attachment->attachment->name),
            'quantity' => $qty,
            'price' => (float) ($attachment->attachment->price ?? 0) * $qty,
            'show_price' => $showPrice,
        ];
    }

    protected function mergeByName(array $items): array
    {
        $merged = [];
        foreach ($items as $item) {
            $key = strtolower(preg_replace('/\s+/', ' ', trim($item['name'])));
            if (isset($merged[$key])) {
                $merged[$key]['quantity'] += (int) $item['quantity'];
                $merged[$key]['price'] = (float) ($merged[$key]['price'] ?? 0) + (float) ($item['price'] ?? 0);
                continue;
            }
            $merged[$key] = $item;
        }

        return array_values($merged);
    }

    protected function classifyTopping($topping): string
    {
        $name = strtolower((string) ($topping->name ?? ''));

        if (str_contains($name, 'extra')) {
            return 'extras';
        }
        if (str_contains($name, 'include')) {
            return 'included';
        }
        if (preg_match('/drink|cans?|milkshake/', $name)) {
            return 'drinks';
        }
        if (str_contains($name, 'dip') || str_contains($name, 'sauce')) {
            return 'dips';
        }

        return 'toppings';
    }

    protected function cleanGroupLabel(string $name): string
    {
        $label = strtolower(trim($name));
        $label = preg_replace('/\boptions?\b/', '', $label);
        $label = preg_replace('/\s+/', ' ', (string) $label);
        $label = trim($label, " \t-");

        if (preg_match('/(second|third|fourth).*(toppings?)/', $label)) {
            return 'Toppings';
        }

        if (preg_match('/^(\d+)\s*x?\s*(toppings?|dips?|cans?|milkshakes?)$/', $label, $match)) {
            $noun = $match[2];
            if ((int) $match[1] > 1 && !str_ends_with($noun, 's')) {
                $noun .= 's';
            }

            return $match[1] . ' x ' . $noun;
        }

        if (preg_match('/can drink|cans?$/', $label)) {
            return 'Can';
        }

        return ucfirst($label);
    }

    protected function pizzaCountHeader(): ?string
    {
        $text = $this->plainDescription();

        if (preg_match('/(\d+\s*x\s*\d+\s*[”"]?\s*pizza)/i', $text, $match)) {
            return trim($match[1]);
        }

        return null;
    }

    protected function extrasAreSauces(array $extras): bool
    {
        if ($extras === []) {
            return false;
        }

        return collect($extras)->every(function ($item) {
            return (bool) preg_match('/\b(garlic|bbq|mayo|ketchup|chilli|chili|dip|sauce)\b/i', $item['name']);
        });
    }

    protected function pizzaIndex(?string $toppingName): int
    {
        $name = strtolower((string) $toppingName);

        if (preg_match('/fourth|fourt|4th/', $name)) {
            return 4;
        }
        if (preg_match('/third|3rd/', $name)) {
            return 3;
        }
        if (preg_match('/second|2nd/', $name)) {
            return 2;
        }

        return 1;
    }

    protected function classifyAttachment($attachment): string
    {
        $topping = strtolower((string) ($attachment->topping?->name ?? ''));
        $name = strtolower((string) ($attachment->attachment?->name ?? ''));

        if (str_contains($topping, 'extra')) {
            return 'extras';
        }

        if (str_contains($topping, 'include')) {
            return 'included';
        }

        if (
            $this->looksLikeDrink($name)
            || str_contains($topping, 'drink')
            || str_contains($topping, 'can')
            || str_contains($topping, 'milkshake')
        ) {
            return 'drinks';
        }

        if (str_contains($topping, 'dip') || str_contains($topping, 'sauce')) {
            return 'dips';
        }

        return 'toppings';
    }

    protected function appendSection(array &$lines, ?string $header, array $items): void
    {
        if ($items === []) {
            return;
        }

        if ($header) {
            $lines[] = [
                'name' => $header,
                'quantity' => 1,
                'header' => true,
            ];
        }

        foreach ($items as $item) {
            $lines[] = $item + ['header' => false];
        }
    }

    protected function splitList(array $items, int $groups, bool $split): array
    {
        if (!$split) {
            return $items;
        }

        $copy = [];
        foreach ($items as $item) {
            $qty = (int) $item['quantity'];
            if ($qty >= $groups && $qty % $groups === 0) {
                $qty = intdiv($qty, $groups);
            }

            $copy[] = [
                'name' => $item['name'],
                'quantity' => max($qty, 1),
            ];
        }

        return $copy;
    }

    protected function plainDescription(): string
    {
        return trim(html_entity_decode(strip_tags((string) ($this->menu_item?->description ?? ''))));
    }

    protected function toppingsHeader(): ?string
    {
        $text = $this->plainDescription();

        if (preg_match('/(\d+\s*x\s*\d+\s*[”"]?\s*pizza)/i', $text, $match)) {
            return rtrim(trim($match[1]), '- ') . '-';
        }

        if (preg_match('/(\d+\s*[”"]?\s*pizza)/i', $text, $match)) {
            return rtrim(trim($match[1]), '- ') . '-';
        }

        if (preg_match('/(\d+\s*x\s*toppings?)/i', $text, $match)) {
            return trim($match[1]);
        }

        return null;
    }

    protected function dipsHeader(): ?string
    {
        $text = $this->plainDescription();

        if (preg_match('/(\d+\s*x\s*dips?)/i', $text, $match)) {
            return trim($match[1]);
        }

        if (preg_match('/\bdips?\b/i', $text)) {
            return 'Dips';
        }

        return null;
    }

    protected function drinksHeader(): ?string
    {
        $text = $this->plainDescription();

        if (preg_match('/(\d+\s*x\s*cans?)/i', $text, $match)) {
            return trim($match[1]);
        }

        if (preg_match('/(\d+\s*x\s*milkshakes?)/i', $text, $match)) {
            return trim($match[1]);
        }

        if (preg_match('/\bcans?\b/i', $text)) {
            return 'Can';
        }

        return null;
    }

    protected function includedFromDescription(string $existing): array
    {
        $text = strtolower($this->plainDescription());
        $items = [];

        $alreadyHas = fn (string $needle) => str_contains($existing, strtolower($needle));

        $add = function (string $name, int $qty) use (&$items, $alreadyHas) {
            if ($alreadyHas($name)) {
                return;
            }
            $items[] = [
                'name' => $qty > 1 ? $qty . ' x ' . $name : $name,
                'quantity' => 1,
            ];
        };

        if (preg_match('/(\d+)\s*x\s*chips/', $text, $match)) {
            $add('chips', (int) $match[1]);
        } elseif (preg_match('/\bchips\b/', $text)) {
            $add('Chips', 1);
        }

        if (preg_match('/(\d+)\s*x\s*cookies/', $text, $match) && !$alreadyHas('cookie')) {
            $add('cookies', (int) $match[1]);
        } elseif (preg_match('/\bcookies\b/', $text) && !$alreadyHas('cookie')) {
            $add('Cookies', 1);
        }

        if (preg_match('/goujons?/', $text) && !$alreadyHas('goujon')) {
            $add('Chicken goujons', 1);
        }

        if (preg_match('/chicken\s*wings/', $text) && !$alreadyHas('wing')) {
            $add('Chicken wings', 1);
        }

        if (preg_match('/doner\s*kebab/', $text) && !$alreadyHas('kebab')) {
            $add('Doner kebab', 1);
        }

        return $items;
    }

    protected function looksLikeDrink(?string $name): bool
    {
        return (bool) preg_match('/\b(can|coke|coca-cola|pepsi|fanta|sprite|7up|7-up|water|bottle|drink|milkshake)\b/i', (string) $name);
    }
}
