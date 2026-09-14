<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\HandbookPage;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class HandbookPageSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        foreach ($this->pages() as $page) {
            HandbookPage::query()->updateOrCreate(
                ['slug' => $page['slug']],
                [
                    'title' => $page['title'],
                    'body' => $page['body'],
                ],
            );
        }
    }

    /**
     * @return list<array{title: string, slug: string, body: string}>
     */
    private function pages(): array
    {
        return [
            [
                'title' => 'Returns and refunds',
                'slug' => 'returns-and-refunds',
                'body' => <<<'TXT'
Northshore Supply accepts returns within 14 days of delivery. Items must be unused and in original packaging. Sale and outlet items are final sale and cannot be refunded. Once we receive and inspect the return, refunds go back to the original payment method within 5 business days. We do not offer store credit.
TXT,
            ],
            [
                'title' => 'Shipping',
                'slug' => 'shipping',
                'body' => <<<'TXT'
UK orders usually leave our warehouse in 1 business day and arrive in 3–5 business days. We do not dispatch on weekends or UK bank holidays. Standard shipping is free over £40; under £40 it is £3.95. We do not offer next-day shipping.
TXT,
            ],
            [
                'title' => 'Opening hours',
                'slug' => 'opening-hours',
                'body' => <<<'TXT'
The Northshore Supply warehouse counter in Newcastle is open Tuesday to Saturday, 10:00–18:00. We are closed Sunday and Monday. The online shop accepts orders at all hours; those orders are packed on the next open warehouse day.
TXT,
            ],
            [
                'title' => 'What is in stock',
                'slug' => 'whats-in-stock',
                'body' => <<<'TXT'
We sell canvas tote bags, wool blankets, enamel mugs, and field notebooks. If a product is not in those four lines, we do not stock it and we cannot special-order it. Stock shown on a product page is updated each morning. “Sold out” means we will not restock that colour.
TXT,
            ],
            [
                'title' => 'Order tracking',
                'slug' => 'order-tracking',
                'body' => <<<'TXT'
Every paid order gets a number like #1001. Tracking appears in the confirmation email after the parcel is handed to Royal Mail, usually the afternoon it leaves the warehouse. We cannot look up an order with only a name. We need the order number or the email used at checkout.
TXT,
            ],
            [
                'title' => 'Damaged or wrong item',
                'slug' => 'damaged-or-wrong-item',
                'body' => <<<'TXT'
If a parcel arrives damaged or we sent the wrong item, email a photo within 48 hours of delivery. We reship the correct item or refund in full; you do not return the damaged goods unless we ask. Wear, stains, or damage reported after 48 hours is treated as a normal return and must meet the 14-day unused rule.
TXT,
            ],
            [
                'title' => 'International orders',
                'slug' => 'international-orders',
                'body' => <<<'TXT'
We ship to the EU and the US. Delivery is 7–14 business days. The customer pays any import VAT or duty; we do not prepay customs. We do not ship to PO boxes outside the UK. International orders cannot be redirected after dispatch.
TXT,
            ],
            [
                'title' => 'Payment',
                'slug' => 'payment',
                'body' => <<<'TXT'
We accept Visa, Mastercard, and PayPal. We do not accept American Express, bank transfer, crypto, or cash on delivery. Prices include UK VAT. Failed payments cancel the order; stock is not held.
TXT,
            ],
            [
                'title' => 'Warranty',
                'slug' => 'warranty',
                'body' => <<<'TXT'
Enamel mugs and hardware carry a 12-month warranty against manufacturing defects. Fabric wear, scratches, and dishwasher damage are not covered. Warranty claims need the order number and a photo of the defect. We repair or replace; we do not refund under warranty unless we cannot replace the item.
TXT,
            ],
            [
                'title' => 'Preorders',
                'slug' => 'preorders',
                'body' => <<<'TXT'
Preorder items ship on the date printed on the product page, not the date you paid. You may cancel a preorder within 24 hours of purchase. After 24 hours the order is locked and we do not cancel or refund it except where the 14-day return window applies after delivery.
TXT,
            ],
        ];
    }
}
