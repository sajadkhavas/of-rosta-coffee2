<?php

$root = dirname(__DIR__);
$failures = [];

$read = static function (string $relativePath) use ($root, &$failures): string {
    $path = $root.'/'.$relativePath;
    $content = is_file($path) ? file_get_contents($path) : false;

    if ($content === false) {
        $failures[] = 'Missing or unreadable checkout file: '.$relativePath;

        return '';
    }

    return $content;
};

$requireContains = static function (
    string $relativePath,
    string $needle,
    string $message,
) use ($read, &$failures): void {
    if (! str_contains($read($relativePath), $needle)) {
        $failures[] = $message;
    }
};

$config = $read('config/rosta.php');
foreach (['quote_ttl_minutes', 'reservation_ttl_minutes', 'idempotency_ttl_hours'] as $setting) {
    if (! str_contains($config, "'{$setting}'")) {
        $failures[] = 'Missing checkout setting: '.$setting;
    }
}

$migration = 'database/migrations/2026_07_21_030001_create_transactional_checkout_tables.php';
foreach ([
    "Schema::create('checkout_quotes'",
    "Schema::create('checkout_quote_items'",
    "Schema::create('orders'",
    "Schema::create('sub_orders'",
    "Schema::create('order_items'",
    "Schema::create('inventory_reservations'",
    "Schema::create('order_idempotency_keys'",
] as $table) {
    $requireContains($migration, $table, 'Missing checkout schema: '.$table);
}
$requireContains(
    $migration,
    "foreignUlid('order_id')->unique()",
    'Single-roastery orders must have exactly one sub-order.',
);
$requireContains(
    $migration,
    "unique(['user_id', 'key'])",
    'Order idempotency keys must be customer scoped.',
);
$requireContains(
    $migration,
    "unique(['quote_id', 'variant_id'])",
    'A quote cannot contain duplicate variants.',
);

$requireContains(
    'app/Services/Checkout/QuoteService.php',
    'cart.multiple_roasteries',
    'Cart validation must reject multiple roasteries.',
);
$requireContains(
    'app/Services/Checkout/QuoteService.php',
    'availableQuantity()',
    'Quote validation must use authoritative available stock.',
);
$requireContains(
    'app/Services/Checkout/QuoteService.php',
    "'unit_price' => \$variant->price",
    'Quote unit prices must come from Laravel.',
);
foreach (['product_snapshot', 'variant_snapshot', 'roast_batch_snapshot'] as $snapshot) {
    $requireContains(
        'app/Services/Checkout/QuoteService.php',
        "'{$snapshot}'",
        'Missing immutable checkout snapshot: '.$snapshot,
    );
}
$requireContains(
    'app/Services/Checkout/QuoteService.php',
    'private const int MAX_MONEY',
    'Checkout must define the frozen safe money boundary.',
);
$requireContains(
    'app/Services/Checkout/QuoteService.php',
    'multiplyMoney(',
    'Line totals must use checked multiplication.',
);
$requireContains(
    'app/Services/Checkout/QuoteService.php',
    'addMoney(',
    'Quote totals must use checked addition.',
);
$requireContains(
    'app/Services/Checkout/CouponService.php',
    '$subtotal % 10_000',
    'Percentage discounts must avoid integer overflow.',
);

$requireContains(
    'app/Services/Checkout/OrderService.php',
    'User::query()->lockForUpdate()',
    'Order creation must serialize requests per customer.',
);
$requireContains(
    'app/Services/Checkout/OrderService.php',
    "->where('user_id', \$user->id)",
    'Order ownership and idempotency must be customer scoped.',
);
$requireContains(
    'app/Services/Checkout/OrderService.php',
    'order.idempotency_conflict',
    'Order idempotency keys must be payload-bound.',
);
$requireContains(
    'app/Services/Checkout/OrderService.php',
    "'stock_reserved' => \$variant->stock_reserved + \$quoteItem->quantity",
    'Order creation must reserve stock atomically.',
);
$requireContains(
    'app/Services/Checkout/OrderService.php',
    'InventoryReservation::query()->create',
    'Every order line must have an explicit inventory reservation.',
);
$requireContains(
    'app/Services/Checkout/OrderService.php',
    "'consumed_at' => now()",
    'Checkout quotes must be consumed exactly once.',
);

$requireContains(
    'app/Services/Checkout/OrderCancellationService.php',
    'OrderStatus::AwaitingPayment',
    'Direct cancellation must have a bounded source state.',
);
$requireContains(
    'app/Services/Checkout/OrderCancellationService.php',
    'stock_reserved - $reservation->quantity',
    'Cancellation and expiry must release stock reservations.',
);
$requireContains(
    'app/Services/Checkout/OrderCancellationService.php',
    'releaseCouponReservation(',
    'Cancellation and expiry must release coupon capacity.',
);
$requireContains(
    'app/Services/Checkout/ReservationExpiryService.php',
    'ReservationStatus::Active->value',
    'Reservation expiry must target active reservations only.',
);

foreach ([
    'throttle:cart-validate',
    'throttle:checkout-quote',
    'throttle:order-create',
] as $limiter) {
    $requireContains(
        'routes/api.php',
        $limiter,
        'Missing checkout rate limiter: '.$limiter,
    );
}
$requireContains(
    'routes/console.php',
    'rosta.checkout.expire-reservations',
    'Reservation expiry must be scheduled.',
);
$requireContains(
    'routes/console.php',
    'withoutOverlapping()',
    'Checkout recovery jobs must not overlap.',
);

if ($failures !== []) {
    fwrite(STDERR, "Checkout contract audit failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- '.$failure."\n");
    }

    exit(1);
}

fwrite(STDOUT, "Checkout contract audit passed.\n");
