<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\HandbookDocument;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class HandbookDocumentSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        foreach ($this->documents() as $document) {
            HandbookDocument::query()->updateOrCreate(
                ['slug' => $document['slug']],
                [
                    'title' => $document['title'],
                    'body' => $document['body'],
                ],
            );
        }
    }

    /**
     * @return list<array{title: string, slug: string, body: string}>
     */
    private function documents(): array
    {
        return [
            [
                'title' => 'Returns and refunds',
                'slug' => 'returns-and-refunds',
                'body' => <<<'TXT'
Northshore Supply accepts returns within 14 days of delivery. The 14 days start on the day Royal Mail marks the parcel as delivered, not the day you open it, and not the day you found time to email us. If tracking never updates to delivered, we count 14 days from the last “out for delivery” scan. Items must be unused, unwashed, and in the original packaging with any hang tags still attached. A tote that has been to the shops, a blanket that has been on a sofa, or a mug that has held coffee is used.

Sale and outlet items are final sale and cannot be refunded or exchanged. “Sale” means the product page showed a reduced price at the moment you paid. A later price drop does not turn a full-price order into a sale order. A code that reduced a full-price line is a discount, not a sale; that line can still be returned unused under the 14-day rule. Outlet means the product title or badge included the word outlet when you paid.

To start a return, email us the order number and the items you are sending back. We reply with a returns address in Newcastle. Do not post to the invoice address or to a marketplace locker. You pay the return postage unless we sent the wrong item or the goods arrived damaged. We do not collect returns from your door and we do not book a courier on your card. Drop the parcel at a Royal Mail counter and keep the receipt until you see the refund.

Once we receive and inspect the return, refunds go back to the original payment method within 5 business days. PayPal refunds follow PayPal’s own posting time after we release them. We do not offer store credit, gift cards in place of a refund, or partial refunds for change of mind on a used item. If an item fails inspection (worn, stained, missing packaging, or missing the notebook’s belly band), we send it back to you at your cost and we do not refund.

A multi-line order can be returned in parts. Each parcel you send us is inspected on its own. We do not wait for a second box before refunding the first. If you return only some lines, shipping you originally paid is not refunded unless the whole order comes back unused and the original dispatch was not free.

We do not refund “because it was cheaper the next day”. We do not refund digital notes, gift messages, or the time you spent waiting for Royal Mail. We do not refund into a different card or a friend’s PayPal.

Returns posted after day 14 are refused. If the parcel still arrives, we can send it back to you; we do not store unsolicited goods beyond 30 days. After 30 days we may dispose of them. Email us before you post if you are unsure which rule applies: unused 14-day return, damaged-or-wrong 48-hour window, or 12-month warranty.
TXT,
            ],
            [
                'title' => 'Shipping',
                'slug' => 'shipping',
                'body' => <<<'TXT'
UK orders usually leave our Newcastle warehouse in 1 business day and arrive in 3–5 business days with Royal Mail. A business day is Monday to Friday excluding UK bank holidays. We do not dispatch on weekends. An order placed after 14:00 on a packing day is packed the next open warehouse day. Saturday 13:00 can still pack that afternoon; Saturday 14:30 waits until Tuesday.

Standard shipping is free on UK orders over £40. Under £40 it is £3.95. The £40 threshold is the basket total after discounts, including UK VAT, excluding the shipping line itself. Adding a notebook to cross £40 is valid. We do not offer next-day, named-day, timed, or Saturday-guaranteed delivery. Anyone selling “Northshore next day” on a listing site is not us.

We ship one parcel per order. We do not split a paid order across two dispatches because one line is in a later bin. If a line is missing at pack time, we cancel that line, refund it to the original payment method, and send the rest. You will see two emails: a refund for the missing line and a tracking email for what went out.

Parcels are handed to Royal Mail in the afternoon. Tracking is not available at the moment you pay; it appears in a second email after handover. The first checkout email is only a payment receipt. We cannot upgrade a parcel that has already left the warehouse and we cannot recall a van.

Remote UK postcodes (some Scottish islands, Scilly, and addresses that Royal Mail treats as offshore) still use the same £3.95 / free-over-£40 table at checkout. They may take longer than 5 business days. That delay is not a lost parcel and not a refund of postage.

We pack in a cardboard mailer or a recycled carton. We do not ship in branded gift boxes. Fragile stickers on a mug carton are a request to Royal Mail, not insurance. Postage you paid is not a declared-value insurance policy. If Royal Mail loses the parcel, follow the lost-parcels document; we do not pay extra compensation above the order value.

A shipping quote in an old email is not a live price. Checkout is the only figure we honour.
TXT,
            ],
            [
                'title' => 'Opening hours',
                'slug' => 'opening-hours',
                'body' => <<<'TXT'
The Northshore Supply warehouse counter in Newcastle is open Tuesday to Saturday, 10:00–18:00. We are closed Sunday and Monday, and we close on UK bank holidays even when they fall on a Tuesday to Saturday. Christmas week hours are posted on the site banner; if the banner is empty, use these hours.

The online shop accepts orders at all hours. Those orders are packed on the next open warehouse day. A Saturday 17:00 order is not packed until Tuesday. A bank-holiday Friday order waits until the next Tuesday if Monday is also closed for us (we are always closed Monday).

The counter is a collection and returns desk, not a full shop floor. We do not keep a browseable display of every colour. If you want a specific tote or blanket, email us the day before so we can pull it from the rack. Walk-in purchases are limited to what is on the small shelf behind the till that morning.

Last collection at the counter is 17:45. We do not hold a till open after 18:00. If you arrive after closing, collect on the next open day; we do not leave parcels outside, in the yard, or with a neighbouring unit.

Staff break is 13:00–13:20. The counter stays open with one person. Packing may pause. That pause does not change the 14:00 cut-off for same-day packing of new paid orders.

We do not open early for photographers, wedding planners, or “I drove from Manchester”. Email ahead for a pull-from-rack, then arrive in hours.

If severe weather closes the yard, we put a notice on the site. Click-and-collect ready emails still count their 14-day shelf from the original ready email unless we write to extend it.
TXT,
            ],
            [
                'title' => 'What is in stock',
                'slug' => 'whats-in-stock',
                'body' => <<<'TXT'
We sell four lines only: canvas tote bags, wool blankets, enamel mugs, and field notebooks. If a product is not in those four lines, we do not stock it and we cannot special-order it. We do not sell apparel, furniture, candles, food, or third-party brands. A request for “the same tote in leather” is a no.

Stock shown on a product page is a count taken each morning at 08:00. It can be wrong by the afternoon if the site sells through. “Sold out” means we will not restock that colour. “Low stock” is not a reservation; another customer can buy the last unit while you are on the checkout page. We do not freeze stock at add-to-basket.

We do not hold stock for abandoned baskets. We do not match a competitor’s range. Dye lots of the same colour name can differ slightly between batches; that is not a defect. Read the colour-and-batch-variation document before you order two blankets a month apart.

Tote bags come in one size. Blankets come in throw size only. Mugs are 350 ml. Notebooks are A5, lined, 80 pages. We do not custom-print names, logos, or wedding dates on any line. We do not number notebooks as a limited series unless the product title says so.

Tote colours currently in the core range are navy, oat, and black. Blanket colours are charcoal, cream, and rust. Mug colours are navy and cream. Notebook covers are navy and kraft. Any other colour name on an old screenshot is either outlet, sold out, or never existed.

We do not sell seconds on the public site. A tiny slub in canvas is first quality. A hole or stain from our rack is not; that unit should not have been packed.

If a page is up but checkout fails with “out of stock”, believe checkout. Emailing us “please pack it anyway” does not override an empty bin.
TXT,
            ],
            [
                'title' => 'Order tracking',
                'slug' => 'order-tracking',
                'body' => <<<'TXT'
Every paid order gets a number like #1001. Use that number in every email. We cannot look up an order with only a name, a postcode, a phone number, or a description of the tote. Two customers named Adele in Newcastle will both be asked for the number.

Tracking appears in a confirmation email after the parcel is handed to Royal Mail, usually the afternoon it leaves the warehouse. Until that email arrives, the order is still on our rack even if you have paid. PayPal or card statements are not tracking. A bank line that says NORTHSHORE is a payment, not a dispatch.

Royal Mail updates can lag by a day. “Item is at the delivery office” is Royal Mail language, not ours. We cannot see inside their network beyond the tracking link we forwarded. We cannot ping a specific postman.

If tracking shows delivered and you have no parcel, wait until the next working day, then email us the order number and photos of any “sorry we missed you” card. We do not open a lost-parcel claim on the same afternoon as the first delivery attempt. Check communal hallways and building reception.

If you used a workplace address, ask reception. Many “missing” parcels are in a mail room that does not email you. That is not a warehouse fault.

We do not add extra tracking events of our own. If the Royal Mail page 404s, email us the order number; we can resend the same link. We cannot invent a second carrier.

International tracking, when it exists, still starts only after UK handover. The first days after payment with no scan are normal for EU and US orders.
TXT,
            ],
            [
                'title' => 'Damaged or wrong item',
                'slug' => 'damaged-or-wrong-item',
                'body' => <<<'TXT'
If a parcel arrives damaged or we sent the wrong item, email a photo within 48 hours of delivery. The photo must show the item, the packaging, and the order number written on paper in the same frame. A photo of only the product is not enough. A video without the order number is not enough. The 48 hours follow the same delivery clock as returns: Royal Mail delivered timestamp.

We reship the correct item or refund in full. You do not return the damaged or wrong goods unless we ask. If we ask, we email a prepaid label; you do not pay postage on a fault we caused. Do not drop a damaged mug in a charity shop until we have said we do not need it back; we may want the batch code on the carton.

Wear, stains, dishwasher marks, or damage reported after 48 hours is treated as a normal return and must meet the 14-day unused rule. A mug chipped in your kitchen is not a warehouse fault. A blanket that picked up a moth hole in a cupboard is care, not packing.

If only one item in a multi-line order is wrong, we replace or refund that line. We do not cancel the whole order or pick up the items that arrived correctly. Shipping on the original order is not refunded when the rest of the order was as sold.

Wrong colour is a wrong item only if the packing slip and the product in the box disagree, or if the slip matches the box but not what you paid for. “Navy looked more electric on my monitor” is batch variation, not a wrong item.

If the carton is crushed but the goods are fine, that is not a damaged item. You may still write to us; we will not reship a good tote because the mailer is ugly.

A missing notebook from a three-line order, with packing slip showing three lines, is a missing item: treat it as wrong/short pack, same 48-hour photo of the slip and the open box.
TXT,
            ],
            [
                'title' => 'International orders',
                'slug' => 'international-orders',
                'body' => <<<'TXT'
We ship to the EU and the US. We do not ship to the rest of the world, and we do not ship to UK or US military BFPO addresses. We do not ship to the Channel Islands as if they were UK mainland; those checkouts must use the international path or they are refused. Delivery is 7–14 business days after dispatch, not after payment.

The customer pays any import VAT, duty, or brokerage. We do not prepay customs and we do not write a lower value on the commercial invoice. The invoice value is what you paid for the goods, excluding UK-to-you postage when the carrier requires goods-only. If customs return a parcel, we refund the goods minus the original outbound postage; we do not refund duties you already paid to a broker.

We do not ship to PO boxes outside the UK. A hotel, office, Amazon locker, or “poste restante” abroad is not a valid address for us. International orders cannot be redirected after dispatch. A “please send to my sister in Lyon instead” after payment is a no.

Free UK shipping over £40 does not apply abroad. International postage is calculated at checkout and is not refundable after dispatch unless we sent the wrong item or Royal Mail confirms loss.

Some EU buyers are surprised to pay import VAT on top of our UK-VAT-inclusive catalogue price. That is how this checkout works today. We do not run a net-of-VAT consumer checkout. See VAT invoices and wholesale if you are a business.

If a US order sits in customs, we cannot call CBP for you. Provide the tracking to your broker. Delay in customs is not a missed preorder ship date and not a 14-day late-preorder cancellation.

We do not mark parcels as documents or gifts to dodge duty. Asking us to in the notes gets the notes ignored.
TXT,
            ],
            [
                'title' => 'Payment',
                'slug' => 'payment',
                'body' => <<<'TXT'
We accept Visa, Mastercard, and PayPal. We do not accept American Express, Discover, bank transfer, crypto, cheque, Klarna, Clearpay, or cash on delivery. Apple Pay and Google Pay work only when they charge a Visa or Mastercard. An Amex sitting behind Apple Pay will fail.

Prices on the site include UK VAT at the current rate. We do not remove VAT for UK buyers. EU and US buyers still see a UK-VAT-inclusive catalogue price; extra import tax is separate and charged by their customs authority, not by us.

Failed payments cancel the order immediately. Stock is not held for a second attempt. If PayPal shows pending, wait for paid or failed; do not place a duplicate order. Two paid duplicates are two orders; see changing-or-cancelling.

We do not take payment over the phone and we do not send payment links in email. Anyone asking you to pay Northshore Supply via a new IBAN, a crypto wallet, or a “verification fee” is not us. Our checkout is https on northshore.example only (this is a demo shop; treat other domains as hostile).

We do not store full card numbers on our warehouse PCs. Refunds go to the original method. We cannot refund a Visa onto a Mastercard because the Visa has expired; the scheme still posts to the original instrument or the issuer handles it.

Currency is GBP. Your bank may add a foreign-transaction fee. That fee is not part of our total and is not refundable by us.

A “hold” or pre-authorisation that drops off is not a completed order. If you have no order number, you have no order.
TXT,
            ],
            [
                'title' => 'Warranty',
                'slug' => 'warranty',
                'body' => <<<'TXT'
Enamel mugs and metal hardware on totes (D-rings, strap rivets) carry a 12-month warranty against manufacturing defects from the delivery date. The warranty is for the original buyer and the original order number. It does not transfer with a gift unless the giver emails us the order number before the claim.

Wool blankets and canvas bodies are sold as-is regarding gradual wear. They have no 12-month fabric warranty. A seam that splits in the first 30 days of normal use, with photos, is treated as a manufacturing defect even on canvas; write us with the order number.

Fabric wear, pilling on wool, scratches on enamel, rust from dishwashers, and fading in sunlight are not covered. Putting a mug in a dishwasher, oven, fire, or on a hob voids the mug warranty. Microwave use voids it. A cracked enamel rim from a drop is impact, not manufacturing, unless we agree the metal was already split at pack time (rare; 48-hour damaged-item is the usual path).

Warranty claims need the order number and a photo of the defect. We repair or replace. We do not refund under warranty unless we cannot replace the item with the same line and colour. If that colour is marked “Sold out”, we replace with the closest in-stock colour or refund that line at the price you paid, not today’s sale price.

The 14-day unused return window and the 12-month warranty are different. A change of mind is not a warranty claim. A mug you used for 11 months then put through a dishwasher is not a warranty claim.

We may ask you to post the defective hardware to Newcastle with a prepaid label. Do not bin it until we say so. If you cannot find the order number, we cannot start a warranty file; card statements are not enough.

Third-party repairs void the remaining warranty on that item. Glue on a rivet is a repair.
TXT,
            ],
            [
                'title' => 'Preorders',
                'slug' => 'preorders',
                'body' => <<<'TXT'
Preorder items ship on the date printed on the product page, not the date you paid. If a basket mixes a preorder line with an in-stock line, the whole order waits for the preorder date. We do not split those baskets. If you need the in-stock mug this week, pay for it in a separate checkout.

You may cancel a preorder within 24 hours of purchase. After 24 hours the order is locked. We do not cancel or refund a locked preorder except where the 14-day return window applies after delivery, and sale preorders remain final sale after they arrive.

If we miss the printed ship date by more than 14 days, we email you. You may then cancel for a full refund including postage, even on a sale preorder. That is the only extra cancellation right for late preorders. Silence from you after that email means we still ship when the run arrives.

A preorder is not a restock waitlist. When the printed run sells out, we do not take further names. “Notify me” on a sold-out page is not a preorder and not a promise.

If the mill delays a blanket run, we update the product page date. The 14-day late clock restarts from the new printed date only if we emailed you the change. A date change in small grey text you missed still counts as a published date; we still email for delays over 14 days past the date that was showing when you paid.

Preorder payment is captured in full at checkout, not on ship day. That is not a deposit. Refunds for valid cancellations are the full capture.

We do not let you swap a preorder colour after the 24-hour cancel window. Colour changes are a cancel (if still allowed) plus a new order, stock permitting.
TXT,
            ],
            [
                'title' => 'Gift returns',
                'slug' => 'gift-returns',
                'body' => <<<'TXT'
The 14-day unused return window is for the original buyer. A gift recipient can return an unused item in original packaging, but the refund goes to the original payment method, not to the recipient, and not to a new card the recipient names.

The recipient must have the order number or the checkout email. We do not refund a gift from a photo of wrapping paper, a birthday card, or a till receipt from another shop. Sale and outlet gifts are still final sale.

We do not swap a gift for a different colour as a “gift receipt” service. The recipient uses the same returns process as any other unused item, or the buyer starts the return. We do not speak to both parties about the same refund without the order number in the thread.

If you want the recipient to choose, do not buy until they have picked the colour. We do not issue gift receipts, gift cards, or store credit. We do not hold a colour behind the till for a surprise visit.

A gift sent to a different UK address still uses the recipient’s delivery date for the 14-day clock. The buyer should tell the recipient the deadline. We will not extend the window because the gift was opened on Christmas Day if Royal Mail delivered on 20 December.

Damaged gifts follow the 48-hour photo rule. The recipient may email us; we still refund or reship against the original order. Reships go to the original delivery address unless the buyer (with the order number) asks for the buyer’s address instead, and only if the reship has not been packed.

We do not tell the recipient the price if the buyer ticked “hide prices on slip”. We still need the order number internally.
TXT,
            ],
            [
                'title' => 'Exchanges',
                'slug' => 'exchanges',
                'body' => <<<'TXT'
We do not run a same-day exchange counter for a different colour. Return the unused item under the 14-day rule, take the refund, and place a new order for the colour you want. There is no “swap on the desk while I wait” for posted orders.

If the colour you want is sold out, we will not “hold the refund” until it returns. Sold out colours are not restocked. If the colour is merely out of stock this morning, you still take the refund and you buy later at the then-current price, which may be sale or full.

Warehouse counter visitors may inspect an item before they buy it at the till. That is not an exchange of a parcel that was already posted. You cannot bring a posted oat tote and walk out with navy without a return plus a new till sale.

Wrong-item and damaged-item cases are replacements, not exchanges. Those follow the damaged-or-wrong-item rules, including the 48-hour photo window. A replacement may be the same colour; if that colour is sold out, see that document for closest colour or refund.

We do not exchange for a different line (tote for blanket) as a courtesy. Refund and repurchase.

Size is not a reason to exchange: totes, throws, mugs, and A5 notebooks each have one size. “I thought the throw was a bedspread” is a 14-day unused return if it qualifies, not an exchange for a size we do not make.

Sale items are not exchangeable. Final sale means you keep it or you do not buy it.
TXT,
            ],
            [
                'title' => 'Click and collect',
                'slug' => 'click-and-collect',
                'body' => <<<'TXT'
Click and collect is only from the Newcastle warehouse counter. Choose “Collect in Newcastle” at checkout. We email you when the order is on the collection shelf, usually the same open warehouse day if you paid before 14:00. After 14:00, expect the next open warehouse day.

Bring the order number and a photo ID that matches the checkout name. We do not hand a collection to a friend unless you email us their full name before they arrive. We do not leave collections with the loading-bay staff, in a van, or under a pallet.

Uncollected orders stay on the shelf for 14 days from the “ready to collect” email. After 14 days we cancel the order and refund the goods, not a shipping charge, because collect orders have no shipping charge. We email once at day 10 as a courtesy; missing that email does not extend the shelf.

Click and collect is not available for international checkouts or for PO box addresses. You cannot convert a posted order into a collection after it has been handed to Royal Mail. You can convert an unlocked posted order (label not printed) if you email us in time; we change the method and you collect when we say it is ready.

If you lose the ready email, the order number and ID are enough. We will not describe the tote colour over the counter to a queue.

Sale collect orders are still final sale once you have taken them from the desk. Inspect before you leave. A collect sale tote carried to the car is accepted.

Parking is street only. See warehouse-visits. If you cannot come in person, send a named friend; do not ask us to post a collect order after it has sat on the shelf unless it is still within the 14-day shelf and we agree to convert it back to Royal Mail. Conversion back to post may add the £3.95 under-£40 fee if the goods total is under £40.
TXT,
            ],
            [
                'title' => 'Lost parcels',
                'slug' => 'lost-parcels',
                'body' => <<<'TXT'
A parcel is not lost on the day tracking first says “delivery attempted”. Wait one working day after the first attempt, then check neighbours and any safe-place note you left with Royal Mail. A second attempt is normal.

If tracking shows delivered and you still have nothing after that working day, email the order number, the tracking link, and a photo of the delivery card if there is one. We open a Royal Mail enquiry. Enquiries take up to 10 working days. We do not reship while an enquiry is open. Daily “any news” emails do not shorten Royal Mail.

If Royal Mail confirms loss, we reship when the line is in stock or refund that order. We do not pay extra compensation above the order value. We do not pay for your time off work to wait in.

Leaving “porch is fine” in the checkout note means you accept porch delivery. A porch theft after “delivered” is not a warehouse fault and is not a free reship. Use click and collect if you cannot take a parcel in person. A Ring camera clip of a stranger taking a parcel is a police matter; we will share the order number with you for your report; we still do not automatically reship.

If tracking is stuck for 15 days with no scan after handover, email us. We start an enquiry without waiting for a delivered scan. That is the exception to “not lost on day one”.

International loss follows the same enquiry idea through the last carrier we can see. Customs seizures are not loss. A seizure is a refusal to import; see international orders.

Do not file a chargeback for loss while an enquiry is open. We pause and wait for the scheme if you do.
TXT,
            ],
            [
                'title' => 'Changing or cancelling an order',
                'slug' => 'changing-or-cancelling-an-order',
                'body' => <<<'TXT'
You can cancel or change a paid order only while it is still on our rack. Email the order number. If we have already printed a Royal Mail label, the order is locked and must go out. After dispatch, use the 14-day return window. “I changed my mind at lunch” the same day still works if the label is not printed; after 14:00 many orders are already labelled.

We can change a UK delivery address only before the label is printed. We cannot change an international address after you pay. We cannot add a line to a paid order; place a second order. We cannot merge two paid orders into one parcel.

We do not change colour after payment. Cancel if still unlocked, then buy the colour you want. If the new colour costs more, you pay the new checkout total. If it costs less, you get the new price, not a credit of the difference onto a locked order.

Duplicate checkout clicks that create two paid orders are both real orders. Email us immediately. We cancel the duplicate only if it is still unlocked. If both have labelled, you receive two parcels and you return one under the unused rule if it qualifies.

Preorder cancellations have their own 24-hour rule; that document wins for preorder lines. A mixed basket locked for preorder is not cancellable line-by-line after 24 hours.

We do not cancel because you found a cheaper tote on another site. We do not cancel because Royal Mail is slow.

If you typed the wrong email at checkout, you may not get tracking. Write us from a mailbox you control, quote the order number from PayPal or your card, and we will add a note. We still cannot look up by name alone.
TXT,
            ],
            [
                'title' => 'Gift wrapping',
                'slug' => 'gift-wrapping',
                'body' => <<<'TXT'
We do not offer gift wrapping, gift boxes, tissue, or printed ribbons. Orders ship in a plain cardboard mailer or a recycled carton with a packing slip that shows the line names and the order number.

You may add a short gift note at checkout in the order notes field, maximum 120 characters. We write it on a plain card. We do not print photos, handwriting fonts, or the prices on that card; the packing slip still has prices unless you tick “hide prices on slip”, which only hides unit prices, not the order number, not the shop name.

We do not ship as if we were the gift giver. The Royal Mail sender line is Northshore Supply. Recipients can see who posted it. If that ruins a surprise, wrap after delivery or collect in Newcastle and wrap at home.

If you need a wrapped gift, wrap it yourself after delivery. A request in the notes for “please wrap” is ignored. We do not run back to add tissue because you emailed an hour after paying.

Hide-prices-on-slip is not available on click-and-collect packing slips printed for the shelf; the till still shows the total if staff have to look up a problem. Assume a friend collecting for you may see there was a price.

We do not include extra blank cards, stickers, or “happy birthday” stamps.

A gift note that asks us to lie on the customs form is ignored. International gifts still have a commercial invoice.
TXT,
            ],
            [
                'title' => 'Product care',
                'slug' => 'product-care',
                'body' => <<<'TXT'
Canvas totes: shake out grit and wipe with a damp cloth. Do not machine-wash. Do not tumble-dry. Do not iron on high over screen-print if a limited run had ink (core totes are plain canvas). A tote that has been washed is used and fails a change-of-mind return. Leather-look patches are not leather; wipe only.

Wool blankets: air them; do not wash. Do not dry-clean unless a specialist says the dye is stable; we do not pay for cleaning. Moth holes from home storage are not a manufacturing defect. Keep them in the cotton bag we ship, away from radiators and damp sheds. Pilling on the fold is wear.

Enamel mugs: hand-wash only. Not dishwasher safe, not microwave safe, not hob safe, not oven safe. A dishwasher-stripped rim is not covered by warranty. Sudden boiling water into a fridge-cold mug can stress enamel; that is use, not a packing chip. Wooden or metal lids are not sold with the mug; do not force one.

Field notebooks: paper is not waterproof. We do not replace a notebook that was left in a coat pocket in the rain. Ink bleed from a fountain pen is not a defect. Do not peel the cover to “see if it’s leather”.

Hardware: a drop of oil on a sticky D-ring is fine. Vinegar soaks are not. Salt water on metal wants a rinse and dry; we do not warrant rust from a beach week.

If you follow none of this and the item fails in week one, we still look at photos. Abuse is a no. A stitch that parts on day two of gentle use is a defect; write us.

We do not publish a washing-machine workaround. Staff who say “I always 30-degree mine” on Instagram are not policy.
TXT,
            ],
            [
                'title' => 'Restocks',
                'slug' => 'restocks',
                'body' => <<<'TXT'
“Sold out” means that colour is finished. We will not restock it and we do not keep a waiting list. Do not email us to be told when it returns; it will not. Do not ask us to reverse a sold-out badge because you saw one unit on a shop-floor photo from March.

“Out of stock” without “sold out” means the bin is empty this morning and we may pack more from an incoming delivery. We do not promise a date. We do not take paid orders against a future delivery except through a listed preorder. Refreshing the page at midnight does not queue you first.

We do not notify by SMS. We do not hold the last unit behind the till for a phone call. We do not accept a bank transfer to “reserve” a colour.

If you want a colour that is in stock, buy it. A screenshot from last week is not a price or stock guarantee. A cart left open overnight is not a hold.

Influencers do not get hidden restocks. If a colour returns, it will be a new product page or a preorder, public.

We may reuse a colour name years later on a new dye lot. That is a new item, not a restock of your 2024 oat tote, and it may not match. See colour-and-batch-variation.

Asking wholesale for the last public sold-out colour does not work. Wholesale gets the current account catalogue, not dead consumer colours.
TXT,
            ],
            [
                'title' => 'Wholesale',
                'slug' => 'wholesale',
                'body' => <<<'TXT'
We do not run a public wholesale list. Shops and cafés can email wholesale@northshore.example with a company name, VAT number, and the lines they want. We reply only if we are taking new accounts that quarter. A consumer Gmail asking for “mate’s rates” is not an application.

Minimum first order for a wholesale account is £400 excluding shipping. Wholesale prices are not the sale prices on the public site. Wholesale orders are not covered by the 14-day unused consumer return window. Faulty packing is still a warehouse problem; photograph within 5 business days of delivery, not 48 hours, because shop deliveries sit in a back room.

We do not drop-ship for other brands. We do not supply Amazon sellers. We do not print your logo on our totes. We do not allow our photos on a marketplace listing that pretends to be Northshore Supply.

Wholesale invoices are due in 14 days. Failed or late payment pauses the account. We do not offer consignment, sale-or-return, or seasonal returns of unsold navy.

Shipping to a shop is quoted on the invoice, not the consumer £3.95 table. Pallet delivery is possible above a weight we will state in the account email; it is not a default.

If we stop a colour, wholesale gets the same sold-out rule. We do not manufacture a short run for one café.

Consumer checkout emails are not wholesale invoices. See VAT invoices if you paid retail by mistake; we do not convert a retail order into wholesale pricing after dispatch.
TXT,
            ],
            [
                'title' => 'VAT invoices',
                'slug' => 'vat-invoices',
                'body' => <<<'TXT'
The checkout email is your receipt and includes UK VAT. If your accounts team needs a VAT invoice, email the order number and the legal name and address to put on the invoice. We issue invoices for the original buyer only. We do not add a VAT number that was not in the original order unless you are a wholesale account.

We do not backdate invoices. We do not split one order into two invoices for two companies. We do not reissue an invoice to a different VAT number after dispatch. We do not show a net price on a consumer invoice.

EU buyers who want to reverse-charge VAT must not use the consumer checkout. We do not currently run a B2B net-of-VAT checkout on the public site. Pay the catalogue price or use the wholesale path if you have an account.

We are not able to refund “the VAT line only” on a consumer order. The price is the price.

If your accountant wants our registration number, it is on the invoice we send when you ask. It is not in Instagram highlights.

A lost checkout email can be resent if you have the order number. That resend is a receipt, still not a full tax invoice until you ask.

Click-and-collect still includes VAT. Paying cash at the counter (we rarely take cash; card is preferred) still gets a till receipt; ask for the invoice email the same day.
TXT,
            ],
            [
                'title' => 'Discounts and codes',
                'slug' => 'discounts-and-codes',
                'body' => <<<'TXT'
We do not have a student discount, a key-worker discount, an NHS code, or a newsletter-only code that staff can read out on the phone. If a code field appears at checkout, it is for a printed campaign on the site. Invalid codes fail as “code not applied”. We do not debug a code from a screenshot of a stranger’s story.

Codes do not stack. A code is not valid on sale or outlet lines. A code is not valid on shipping. We do not apply a code after you have paid. We do not apply a code to a second order because you forgot it on the first.

We do not price-match Amazon, department stores, or our own last season. We do not honour a screenshot of a code from a stranger’s Instagram. We do not match a paid ad that used the wrong figure.

Affiliate or influencer codes we have not published on northshore.example are not valid. Staff will not “just take 10% off” on the counter. A journalist with a deadline still pays checkout.

If a live campaign says 15% off totes, it does not apply to blankets unless the banner says so. Read the banner expiry date. Expiry is UK time, end of that calendar day.

We may cancel an order that used a leaked staff code. You will get a refund and an explanation. Do not share warehouse packing codes; they are not for customers.

Wholesale pricing and consumer codes are different worlds. A café cannot use INSTAGRAM15 on a wholesale invoice.
TXT,
            ],
            [
                'title' => 'Warehouse visits',
                'slug' => 'warehouse-visits',
                'body' => <<<'TXT'
You may collect or return at the Newcastle counter during opening hours. You may not tour the racking, take photographs of other customers’ orders, or wait in the packing hall. You may not film a “day in the life” reel without a written yes from the manager, which we usually refuse.

There is no customer parking inside the yard. Use street parking. We do not sign for couriers on your behalf while you pop to the shop next door. We do not keep your dog on a lead at the till.

Children must stay with you at the counter. Dogs stay outside except assistance dogs. Bicycles: lock them on the street, not through our pallet rack.

We do not store your personal parcels or accept Amazon returns addressed to you. The counter is only for Northshore Supply orders. We do not print your boarding pass.

Returns dropped at the counter still need the order number. We inspect later that day or the next open morning; the refund clock of 5 business days starts after inspection, not when you hand the bag over.

If you are rude to staff, we can refuse the visit and ask you to use email only. That is rare and documented.

The unit number is on your ready-to-collect email. We do not publish a what3words on social that overrides that email. If the yard gate is shut, wait; do not climb it.
TXT,
            ],
            [
                'title' => 'Complaints',
                'slug' => 'complaints',
                'body' => <<<'TXT'
Email complaints@northshore.example with the order number and what you want us to do: reship, refund, or an explanation. We reply within 5 business days. We do not handle complaints on Instagram DMs, TikTok comments, Trustpilot replies from random staff, or Google reviews; those accounts are not the order mailbox.

If you are unhappy with the first reply, say so in the same thread. We do not have a second “escalation phone tree”. The warehouse manager reads the same mailbox. Calling the counter to skip the queue does not work; the person at the till cannot override policy.

We do not offer goodwill vouchers, extra tote bags, or partial refunds to close a complaint that is already covered by the written policy (for example a sale item that cannot be refunded). We do not pay for your lunch while you wait for Royal Mail.

A chargeback does not speed this up. If you open a chargeback, we pause the complaint and wait for the card scheme. We will send the packing scan and the policy to the bank. You can still lose a chargeback on a final-sale item.

If we made a documented packing error, we fix it under damaged-or-wrong-item. A complaint is for when you think we have not applied that, or you want an explanation in writing.

We keep complaint emails with the order number. We do not discuss your order with a newspaper without your say-so, and we do not discuss another customer’s order with you.

Repeated abusive mail gets a single warning, then we stop replying except for legal process.
TXT,
            ],
            [
                'title' => 'Colour and batch variation',
                'slug' => 'colour-and-batch-variation',
                'body' => <<<'TXT'
Tote canvas and wool take dye in batches. Navy from March will not be pixel-identical to navy from November. That variation is not a defect and not a wrong item. Oat can read greige or yellow depending on the mill lot.

Product photos are shot in daylight in Newcastle. Screens vary. Night mode on a phone makes rust look neon. “It looked brighter on my phone” is not grounds for a replacement under the 48-hour wrong-item rule.

If you need two blankets to match, buy them in the same order. We cannot promise a later order will match an earlier dye lot. We cannot open a sealed wholesale carton from June to match your January consumer throw.

A thread pull or a small slub in canvas is normal in this fabric. We do not replace for slubs. A hole, a stain from our warehouse, or a broken strap is a defect: use damaged-item within 48 hours or warranty if it fails later in a manufacturing way.

Enamel speckle is a finish, not dirt. Do not scour it off. Cream mugs may show more speckle than navy; that is the process.

We do not send a “colour sample swatch” in the post. The smallest buy is a notebook or mug if you want a cheap look at a navy family; even then, canvas navy is not mug navy.

If two items in one order clearly mismatch in a way that looks like two different colour names, photograph both with the packing slip within 48 hours. That can be a wrong-item pack. If they are the same SKU and only a batch shift, we will say so and we will not reship.
TXT,
            ],
            [
                'title' => 'Delivery instructions',
                'slug' => 'delivery-instructions',
                'body' => <<<'TXT'
Checkout notes are printed on the packing slip. Royal Mail may ignore them. “Leave in the shed” is a request, not a contract. We cannot force a neighbour delivery. We cannot add a Royal Mail preferred-neighbour after the label exists.

We do not offer signature-required as a paid extra. If a parcel needs a signature because of Royal Mail’s own rules, that is their process. We do not refund because you were at work and missed the van.

A gated building should be listed in the address line, not only in the notes. If the courier cannot get in, that is an attempted delivery, not a lost parcel. Put the buzzer code in the address if you are willing to have it on a slip; we cannot promise the carrier hides it.

We do not call you 10 minutes before delivery. We do not reroute a van. We do not ask the driver to wait while you come down from the sixth floor. For a guaranteed handover, use click and collect in Newcastle.

Safe place: if you write “porch is fine”, see lost-parcels for theft. If you write nothing, Royal Mail follows their default for that round.

We do not deliver to a what3words instead of a postcode. UK orders need a postcode. International orders need a structured address the carrier accepts.

If you are moving house, change the address only while the order is unlocked. After dispatch, you are in Royal Mail’s redirect products, not ours, and we do not pay for their redirect.
TXT,
            ],
        ];
    }
}
