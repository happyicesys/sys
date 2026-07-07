<?php

namespace App\Services\Refund;

use App\Models\Customer;
use App\Models\Product;
use App\Models\RefundTicket;
use App\Models\RefundTicketLog;
use App\Models\Vend;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Templated customer emails. Delivery is gated by config('refund.email_enabled')
 * (REFUND_EMAIL_ENABLED, default false) so nothing reaches real customers until
 * you flip it on — when off, the intended send is logged instead.
 *
 * Template wording mirrors the "to be" workflow sheet.
 */
class RefundEmailService
{
    public const T_RECEIVED = 'received';
    public const T_APPROVED = 'approved';
    public const T_AUTO_REFUND = 'auto_refund_triggered';
    public const T_CANCELLED_NO_CHARGE = 'cancelled_no_charge';
    public const T_INFO_REQUIRED = 'info_required';
    public const T_IN_PROGRESS = 'in_progress';
    public const T_COMPLETED = 'completed';

    public function templates(): array
    {
        $signoff = "\n\nBest regards,\nHappyIce Customer Support"
            . "\n\n(This is a computer-generated email, please do not reply. Meanwhile, if you have any questions, please feel free to contact us at refund@happyice.com.sg)";

        return [
            self::T_RECEIVED => [
                'headline' => "We've received your refund request ({reference})",
                'body' => "Dear {name},\n\nThank you for reaching out to us. This is to confirm that we have received your refund request.\n\nYour reference number is {reference}. Please keep this for your records.\n\n{items_block}Our team will review your request against the machine's transaction records and update you by email on the outcome. Within 7 working days." . $signoff,
            ],
            self::T_APPROVED => [
                'headline' => 'This refund request has been approved, and we are now processing the refund to you',
                'body' => "Dear {name},\n\nWe will process your refund to the PayNow or PayPal account you provided. You should receive the refund within 5 working days.\n\nOnce the refund has been completed, we will send you another confirmation email.\n\nWe sincerely appreciate your support and look forward to serving you again in the future. We hope you will continue to enjoy our products and support HappyIce." . $signoff,
            ],
            self::T_AUTO_REFUND => [
                'headline' => 'Automatic Refund Has Been Initiated – Refund Request Resolved',
                'body' => "Dear {name},\n\nUpon verification, our system has confirmed that the product was not dispensed successfully. An automatic refund was initiated at the time of the unsuccessful transaction.\n\nAs the refund has already been initiated automatically, no additional or manual refund is required. Your refund request has been successfully resolved, and this case is now closed.\n\nPlease note that the refunded amount may take approximately 2–3 working days to be reflected in your bank account, depending on your card issuer or payment provider.\n\nWe sincerely appreciate your support and look forward to serving you again in the future. We hope you will continue to enjoy our products and support HappyIce." . $signoff,
            ],
            self::T_CANCELLED_NO_CHARGE => [
                'headline' => 'No charge was captured for your transaction',
                'body' => "Dear {name},\n\nThank you for contacting us.\n\nUpon verification, we found that the transaction was automatically cancelled because the product was not dispensed successfully. As no payment was captured for this transaction, no refund is required.\n\nYou may notice a temporary pending or authorization hold on your bank account. This will normally be released automatically by your bank within a few working days." . $signoff,
            ],
            self::T_INFO_REQUIRED => [
                'headline' => 'Additional information required for your refund',
                'body' => "Dear {name},\n\nWe have reviewed and approved your refund request. However, we were unable to process the refund as the PayNow number provided appears to be invalid or is not registered for PayNow transfers.\n\nTo proceed with the refund, kindly email us at refund@happyice.com.sg with a valid PayNow-registered mobile number or UEN/NRIC-linked PayNow account.\n\nUpon receiving the correct PayNow details, we will process the refund as soon as possible." . $signoff,
            ],
            self::T_IN_PROGRESS => [
                'headline' => 'Your refund is being processed',
                'body' => "Dear {name},\n\nThank you for your patience.\n\nWe would like to inform you that your refund request has been reviewed and is currently being processed. The refunded amount will be transferred to you shortly, and may take a few working days to be reflected depending on your bank or payment provider.\n\nWe apologize for the inconvenience caused." . $signoff,
            ],
            self::T_COMPLETED => [
                'headline' => 'Your refund has been processed',
                'body' => "Dear {name},\n\nWe are pleased to inform you that your refund has been successfully processed.\n\nWe apologize for the inconvenience caused and thank you for your patience and understanding.\n\nWe sincerely appreciate your support and look forward to serving you again in the future." . $signoff,
            ],
        ];
    }

    /**
     * Fill {reference} / {name} / {amount} tokens with the ticket's own values so
     * the copy the customer receives (and the audit-trail record) is concrete.
     */
    protected function interpolate(string $text, RefundTicket $ticket): string
    {
        $name = trim((string) $ticket->contact_name);
        $amountCents = (int) ($ticket->claimed_amount_cents ?? 0);

        return strtr($text, [
            '{reference}' => (string) $ticket->reference,
            '{name}' => $name !== '' ? $name : 'Customer',
            '{amount}' => number_format($amountCents / 100, 2),
        ]);
    }

    /**
     * The stable, per-ticket subject shared by EVERY email in the refund
     * conversation, so a customer's inbox groups them into one thread by subject
     * alone (belt-and-suspenders with the Message-ID header threading below).
     * The RF reference keeps different tickets in different threads.
     *
     * Cutover: a ticket whose thread was already started under the OLD per-status
     * subject scheme has that subject frozen on email_thread_subject — we keep
     * using it so their existing inbox thread isn't broken. Brand-new tickets get
     * the new generic subject. The generic subject is deterministic (derived from
     * the reference), so it stays identical across a ticket's emails even if the
     * first one was never delivered (email disabled) and thus never stored.
     */
    protected function threadSubject(RefundTicket $ticket): string
    {
        if (filled($ticket->email_thread_subject)) {
            return $ticket->email_thread_subject;
        }

        return $this->interpolate('Happy Ice - Refund Request ({reference})', $ticket);
    }

    /**
     * The affected items the customer flagged, grouped so identical rows collapse
     * into one line with a quantity. Each row carries the product thumbnail (when
     * available), name, channel and unit price. Falls back to the free-text manual
     * summary when the ticket has no structured line items.
     *
     * @return array{rows: array<int, array>, manual_summary: ?string, total: string}
     */
    protected function affectedItems(RefundTicket $ticket): array
    {
        $ticket->loadMissing('items');

        $groups = [];
        foreach ($ticket->items as $it) {
            // Include the product NAME in the key so two genuinely different items
            // never collapse into one line when both lack a product_id / channel
            // (e.g. unmapped channels) but happen to share a price. Identical items
            // still merge and carry a quantity.
            $key = ($it->product_id ?? 'x') . '|' . ($it->vend_channel_code ?? '') . '|'
                . (int) $it->unit_price_cents . '|' . mb_strtolower(trim((string) $it->product_name));
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'product_id' => $it->product_id,
                    'name' => $it->product_name ?: 'Item',
                    'channel' => $it->vend_channel_code,
                    'price_cents' => (int) $it->unit_price_cents,
                    'qty' => 0,
                ];
            }
            $groups[$key]['qty']++;
        }
        $rows = array_values($groups);

        // Resolve thumbnails in one query.
        $ids = array_filter(array_column($rows, 'product_id'));
        if ($ids) {
            $thumbs = Product::with('thumbnail')->whereIn('id', $ids)->get()
                ->mapWithKeys(fn ($p) => [$p->id => $p->thumbnail?->full_url]);
            foreach ($rows as &$r) {
                $r['image_url'] = $r['product_id'] ? ($thumbs[$r['product_id']] ?? null) : null;
            }
            unset($r);
        }

        $total = (int) ($ticket->claimed_amount_cents ?? 0);
        if (!$total && $ticket->entered_amount_cents) {
            $total = (int) $ticket->entered_amount_cents;
        }

        return [
            'rows' => $rows,
            'manual_summary' => $rows ? null : ($ticket->manual_items_summary ?: null),
            'total' => number_format($total / 100, 2),
        ];
    }

    /** Friendly label for a reason code (mirrors RefundFormController::REASON_CODES). */
    protected function reasonLabel(?string $code): ?string
    {
        if (!$code) {
            return null;
        }

        return [
            'not_dispensed' => 'Product did not dispense',
            'partial' => 'Only part of my order dispensed',
            'wrong_item' => 'Wrong item dispensed',
            'quality' => 'Quality issue',
            'double_charge' => 'Charged twice',
            'other' => 'Other',
        ][$code] ?? ucfirst(str_replace('_', ' ', $code));
    }

    /**
     * Human label for the chosen purchase day, resolved to the real date and
     * anchored on the submission date — mirrors the admin panel, e.g.
     * "Today (260704)" / "Yesterday (260703)" / "01 Jul 2026" for a custom pick.
     */
    protected function dayLabel(RefundTicket $ticket): ?string
    {
        $day = $ticket->entered_day;
        if (!$day) {
            return null;
        }

        if ($day === 'today' || $day === 'yesterday') {
            $label = ucfirst($day);
            if ($ticket->created_at) {
                $date = $day === 'today'
                    ? $ticket->created_at->copy()
                    : $ticket->created_at->copy()->subDay();
                $label .= ' (' . $date->format('ymd') . ')';
            }

            return $label;
        }

        // Custom YYYY-MM-DD pick — show a friendly date.
        try {
            return \Carbon\Carbon::parse($day)->format('d M Y');
        } catch (\Exception $e) {
            return $day;
        }
    }

    /** Site (customer) name for the ticket's machine — public context, scopes off. */
    protected function siteName(RefundTicket $ticket): ?string
    {
        if (!$ticket->vend_id) {
            return null;
        }
        $vend = Vend::withoutGlobalScopes()->find($ticket->vend_id);
        if (!$vend || !$vend->customer_id) {
            return null;
        }

        return optional(Customer::withoutGlobalScopes()->find($vend->customer_id))->name;
    }

    /**
     * Customer-facing summary of what was submitted — mirrors the "Customer
     * submission" panel on the admin ticket, trimmed to fields that make sense
     * to the customer. Returns [label => value] pairs, blanks omitted.
     *
     * @return array<string, string>
     */
    protected function submissionRows(RefundTicket $ticket): array
    {
        $rows = [];
        $rows['Reference'] = (string) $ticket->reference;
        if ($ticket->vend_code) {
            $rows['Machine ID'] = (string) $ticket->vend_code;
        }
        if ($site = $this->siteName($ticket)) {
            $rows['Site'] = $site;
        }
        if ($day = $this->dayLabel($ticket)) {
            $rows['Purchase date'] = $day;
        }
        if ($ticket->entered_amount_cents !== null) {
            $rows['Amount paid'] = '$' . number_format((int) $ticket->entered_amount_cents / 100, 2);
        }
        if (($ticket->claimed_amount_cents ?? 0) > 0) {
            $rows['Refund amount'] = '$' . number_format((int) $ticket->claimed_amount_cents / 100, 2);
        }
        if ($reason = $this->reasonLabel($ticket->reason_code)) {
            $rows['Reason'] = $reason;
        }
        if (trim((string) $ticket->reason_text) !== '') {
            $rows['Note'] = trim((string) $ticket->reason_text);
        }

        // How they'll be refunded.
        if ($ticket->is_auto_refund_channel) {
            $rows['Refund method'] = 'Automatic to your card';
        } elseif ($ticket->refund_method === 'paypal') {
            $rows['Refund to'] = trim('PayPal ' . ($ticket->payout_destination ?? ''));
        } elseif ($ticket->refund_method === 'paynow' && $ticket->payout_destination) {
            $rows['Refund to'] = 'PayNow ' . $ticket->payout_destination;
        }

        if ($ticket->created_at) {
            $rows['Submitted'] = $ticket->created_at->format('Y-m-d H:i');
        }

        return $rows;
    }

    /** Plain-text rendering of the request summary. */
    protected function summaryText(RefundTicket $ticket): string
    {
        $rows = $this->submissionRows($ticket);
        if (!$rows) {
            return '';
        }
        $lines = ['Request summary:'];
        foreach ($rows as $label => $value) {
            $lines[] = '- ' . $label . ': ' . $value;
        }

        return implode("\n", $lines) . "\n\n";
    }

    /** Styled HTML card of the request summary for the delivered email. */
    protected function summaryHtml(RefundTicket $ticket): string
    {
        $rows = $this->submissionRows($ticket);
        if (!$rows) {
            return '';
        }
        $rowsHtml = '';
        foreach ($rows as $label => $value) {
            // Free-text notes can be long — give them a full-width, left-aligned row
            // so they don't wrap awkwardly against the right edge.
            if ($label === 'Note') {
                $rowsHtml .= '<tr><td colspan="2" style="padding:7px 0;vertical-align:top;">'
                    . '<div style="color:#64748b;font-size:13px;margin-bottom:2px;">Note</div>'
                    . '<div style="color:#0f172a;font-size:13px;font-weight:700;">' . e($value) . '</div>'
                    . '</td></tr>';
                continue;
            }
            $rowsHtml .= '<tr>'
                . '<td style="padding:7px 0;color:#64748b;font-size:13px;vertical-align:top;white-space:nowrap;">' . e($label) . '</td>'
                . '<td style="padding:7px 0 7px 16px;color:#0f172a;font-size:13px;font-weight:700;text-align:right;vertical-align:top;">' . e($value) . '</td>'
                . '</tr>';
        }

        return '<div style="border:1px solid #e2e8f0;border-radius:12px;padding:6px 16px 12px;margin:6px 0 14px;background:#f8fafc;">'
            . '<div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#64748b;padding:10px 0 2px;">Your request summary</div>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">' . $rowsHtml . '</table>'
            . '</div>';
    }

    /** Plain-text rendering of the affected items (audit-trail / text fallback). */
    protected function itemsText(RefundTicket $ticket): string
    {
        $data = $this->affectedItems($ticket);
        if (!$data['rows'] && !$data['manual_summary']) {
            return '';
        }

        $lines = ["Items reported:"];
        if ($data['rows']) {
            foreach ($data['rows'] as $r) {
                $chan = $r['channel'] ? ' (Channel #' . $r['channel'] . ')' : '';
                $lines[] = '- ' . $r['name'] . $chan . ' — $' . number_format($r['price_cents'] / 100, 2) . ' x ' . $r['qty'];
            }
        } else {
            $lines[] = '- ' . $data['manual_summary'];
        }

        return implode("\n", $lines) . "\n\n";
    }

    /** Styled HTML card of the affected items for the delivered email. */
    protected function itemsHtml(RefundTicket $ticket): string
    {
        $data = $this->affectedItems($ticket);
        if (!$data['rows'] && !$data['manual_summary']) {
            return '';
        }

        $rowsHtml = '';
        if ($data['rows']) {
            foreach ($data['rows'] as $r) {
                $name = e($r['name']);
                $chan = $r['channel'] ? '<div style="color:#0e7490;font-size:12px;margin-top:2px;">Channel #' . e($r['channel']) . '</div>' : '';
                $price = number_format($r['price_cents'] / 100, 2);
                $qty = (int) $r['qty'];
                $thumb = !empty($r['image_url'])
                    ? '<img src="' . e($r['image_url']) . '" width="46" height="46" alt="" style="width:46px;height:46px;border-radius:10px;object-fit:cover;display:block;background:#f1f5f9;border:1px solid #e2e8f0;" />'
                    : '<div style="width:46px;height:46px;border-radius:10px;background:#f1f5f9;border:1px solid #e2e8f0;text-align:center;line-height:46px;font-size:20px;">🧊</div>';
                $rowsHtml .= '<tr>'
                    . '<td style="padding:8px 12px 8px 0;vertical-align:middle;width:46px;">' . $thumb . '</td>'
                    . '<td style="padding:8px 0;vertical-align:middle;"><div style="font-weight:700;color:#0f172a;">' . $name . '</div>' . $chan . '</td>'
                    . '<td style="padding:8px 0;vertical-align:middle;text-align:right;white-space:nowrap;"><div style="font-weight:700;color:#0f172a;">$' . $price . '</div><div style="color:#64748b;font-size:12px;margin-top:2px;">Qty ' . $qty . '</div></td>'
                    . '</tr>';
            }
        } else {
            $rowsHtml .= '<tr><td colspan="3" style="padding:10px 0;color:#334155;">' . e($data['manual_summary']) . '</td></tr>';
        }

        return '<div style="border:1px solid #e2e8f0;border-radius:12px;padding:6px 16px 10px;margin:6px 0 18px;background:#f8fafc;">'
            . '<div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#64748b;padding:10px 0 2px;">Affected items</div>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">' . $rowsHtml . '</table>'
            . '</div>';
    }

    /**
     * Wrap the plain body in a branded HTML shell, replacing the {items_block}
     * placeholder with the styled items card (HTML) — the same placeholder is
     * swapped for a text list in the stored/plain-text copy.
     */
    /**
     * Header colour keyed to the ticket stage this email represents, mirroring the
     * status-badge palette in the admin panel (Refund/Show.vue `statusClass`):
     *   yellow = received / awaiting, green = approved / resolved, red = rejected.
     * Returns [background, subtitle-tint, title-text]. White title text reads on
     * the green/red solids; the yellow uses dark text for contrast.
     */
    protected function headerTheme(string $templateKey): array
    {
        // green (approved) — keeps the existing teal brand tone.
        $green = ['#0f766e', '#99f6e4', '#ffffff'];
        // yellow (received / info still needed) — dark text for legibility.
        $yellow = ['#facc15', '#713f12', '#422006'];
        // red (rejected / no charge captured).
        $red = ['#dc2626', '#fecaca', '#ffffff'];

        return [
            self::T_RECEIVED => $yellow,
            self::T_INFO_REQUIRED => $yellow,
            self::T_APPROVED => $green,
            self::T_IN_PROGRESS => $green,
            self::T_COMPLETED => $green,
            self::T_AUTO_REFUND => $red,
            self::T_CANCELLED_NO_CHARGE => $red,
        ][$templateKey] ?? $green;
    }

    protected function renderHtml(string $bodyPlain, string $itemsHtml, string $headline = '', string $templateKey = ''): string
    {
        [$headerBg, $subtitleColor, $titleColor] = $this->headerTheme($templateKey);

        $parts = explode('{items_block}', $bodyPlain);
        $inner = '';
        foreach ($parts as $i => $part) {
            $inner .= nl2br(e($part));
            if ($i < count($parts) - 1) {
                $inner .= $itemsHtml;
            }
        }

        // Bold status headline as the first line of the body — the per-email
        // "what happened" line (received / approved / processed…) that used to be
        // the subject. The subject is now a stable per-ticket thread subject, so
        // this headline is what tells the customer the stage of THIS message.
        $headlineHtml = $headline !== ''
            ? '<div style="font-size:16px;font-weight:800;color:#0f172a;margin:0 0 16px;line-height:1.4;">' . e($headline) . '</div>'
            : '';

        return '<div style="background:#f1f5f9;padding:20px 0;">'
            . '<div style="max-width:560px;margin:0 auto;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;color:#0f172a;">'
            . '<div style="background:' . $headerBg . ';padding:20px 24px;border-radius:14px 14px 0 0;">'
            . '<div style="color:' . $titleColor . ';font-size:19px;font-weight:800;letter-spacing:-.01em;">HappyIce</div>'
            . '<div style="color:' . $subtitleColor . ';font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;margin-top:2px;">Smart Frozen Vending &amp; Solution</div>'
            . '</div>'
            . '<div style="background:#ffffff;border:1px solid #e2e8f0;border-top:0;border-radius:0 0 14px 14px;padding:24px;font-size:14px;line-height:1.65;">'
            . $headlineHtml
            . $inner
            . '</div>'
            . '</div></div>';
    }

    /**
     * A unique, well-formed Message-ID (bare, without angle brackets) for one
     * outgoing refund email, anchored on the ticket reference for readability.
     * The domain is taken from the configured refund/global From address so the
     * id is valid for the sending domain.
     */
    protected function buildMessageId(RefundTicket $ticket): string
    {
        $ref = preg_replace('/[^A-Za-z0-9._-]/', '', (string) $ticket->reference) ?: 'refund';

        return strtolower($ref) . '.' . bin2hex(random_bytes(8)) . '@' . $this->messageIdDomain();
    }

    /** Host part for generated Message-IDs, derived from the sender address. */
    protected function messageIdDomain(): string
    {
        $from = config('refund.mail.from_address')
            ?: config('mail.from.address')
            ?: 'happyice.com.sg';
        $at = strrchr((string) $from, '@');

        return $at ? substr($at, 1) : 'happyice.com.sg';
    }

    /**
     * Interpolated, ready-to-read preview of a template for a specific ticket —
     * same subject/plain-text body that send() would store on the audit trail, so
     * the UI can show the real {name}/{reference} values before the action fires.
     * Returns null for unknown keys.
     *
     * @return array{subject: string, body: string}|null
     */
    public function preview(RefundTicket $ticket, string $templateKey): ?array
    {
        $templates = $this->templates();
        if (!isset($templates[$templateKey])) {
            return null;
        }

        $tpl = $templates[$templateKey];
        $subject = $this->threadSubject($ticket);
        $headline = $this->interpolate($tpl['headline'], $ticket);
        $rawBody = $this->interpolate($tpl['body'], $ticket);

        $blockText = str_contains($rawBody, '{items_block}')
            ? ($this->summaryText($ticket) . $this->itemsText($ticket))
            : '';

        // Mirror send(): the bold headline is the first line of the body.
        return [
            'subject' => $subject,
            'body' => $headline . "\n\n" . str_replace('{items_block}', $blockText, $rawBody),
        ];
    }

    /**
     * The exact delivered HTML for a template — the branded shell, bold headline
     * and item cards — WITHOUT sending. Backs the local in-browser email preview
     * so the design can be eyeballed and iterated on. Returns null for unknown
     * keys. Mirrors the rendering in send() (kept in step with it).
     */
    public function previewHtml(RefundTicket $ticket, string $templateKey): ?string
    {
        $templates = $this->templates();
        if (!isset($templates[$templateKey])) {
            return null;
        }

        $tpl = $templates[$templateKey];
        $headline = $this->interpolate($tpl['headline'], $ticket);
        $rawBody = $this->interpolate($tpl['body'], $ticket);
        $blockHtml = str_contains($rawBody, '{items_block}')
            ? ($this->summaryHtml($ticket) . $this->itemsHtml($ticket))
            : '';

        return $this->renderHtml($rawBody, $blockHtml, $headline, $templateKey);
    }

    public function send(RefundTicket $ticket, string $templateKey): bool
    {
        $templates = $this->templates();
        if (!isset($templates[$templateKey])) {
            return false;
        }

        $tpl = $templates[$templateKey];
        // Subject = the stable per-ticket thread subject (shared by every email
        // for this ticket). Headline = the per-email "what happened" line that
        // used to be the subject; it now leads the body in bold.
        $subject = $this->threadSubject($ticket);
        $headline = $this->interpolate($tpl['headline'], $ticket);
        $rawBody = $this->interpolate($tpl['body'], $ticket);

        // Only the acknowledgement lists the affected items; other templates carry
        // no {items_block} placeholder, so both swaps below are no-ops for them.
        $hasItemsBlock = str_contains($rawBody, '{items_block}');
        // The acknowledgement block = a request-summary card + the affected-items card.
        $blockHtml = $hasItemsBlock ? ($this->summaryHtml($ticket) . $this->itemsHtml($ticket)) : '';
        $blockText = $hasItemsBlock ? ($this->summaryText($ticket) . $this->itemsText($ticket)) : '';

        // Plain-text copy (stored on the audit trail): headline first, then the
        // body with the placeholder swapped for the text summary.
        $body = $headline . "\n\n" . str_replace('{items_block}', $blockText, $rawBody);
        // Delivered copy: branded HTML shell with the bold headline + styled cards.
        $html = $this->renderHtml($rawBody, $blockHtml, $headline, $templateKey);

        // --- Email threading -------------------------------------------------
        // Two independent mechanisms keep the whole refund conversation in ONE
        // inbox thread:
        //   1. Subject: every email for a ticket shares $subject (above), so
        //      clients that group by subject (Gmail) thread them regardless.
        //   2. Headers: the first DELIVERED email's REAL Message-ID (the one the
        //      transport actually assigned — see below) is stored as the thread
        //      root; every later email points In-Reply-To / References at it, which
        //      is what Outlook / Apple Mail thread on.
        // If the root isn't stored yet (acknowledgement not delivered — e.g.
        // REFUND_EMAIL_ENABLED was off), the header link is simply skipped and the
        // shared subject still holds the thread together.
        $rootMessageId = $ticket->email_message_id;
        $isReply = !empty($rootMessageId);
        // Our own well-formed Message-ID for this outgoing message. For SMTP this
        // is also what the recipient sees; API transports may reassign it, which is
        // why the delivered id is read back from the SentMessage after sending.
        $thisMessageId = $this->buildMessageId($ticket);

        $to = $ticket->contact_email;
        $enabled = (bool) config('refund.email_enabled', false);
        $sent = false;
        $error = null;
        // The Message-ID that will be stored as the thread root. Defaults to the
        // one we set; overwritten below with the transport-assigned id if the
        // mailer reports a different one (API mailers do).
        $deliveredMessageId = $thisMessageId;

        if ($enabled && $to) {
            try {
                // Refund mail gets its own sender identity (and optionally its own
                // mailer) so it doesn't go out as the shared "HISG Alert" mailbox.
                $mailer = config('refund.mail.mailer');
                $fromAddress = config('refund.mail.from_address');
                $fromName = config('refund.mail.from_name');

                $builder = $mailer ? Mail::mailer($mailer) : Mail::mailer();
                $sentMessage = $builder->html($html, function ($message) use ($to, $subject, $fromAddress, $fromName, $thisMessageId, $isReply, $rootMessageId) {
                    $message->to($to)->subject($subject);
                    if ($fromAddress) {
                        $message->from($fromAddress, $fromName ?: null);
                    } elseif ($fromName) {
                        // No dedicated address configured — keep the global From
                        // address but still relabel the sender name.
                        $message->from(config('mail.from.address'), $fromName);
                    }

                    // Give this message a stable Message-ID (bare id, no angle
                    // brackets — Symfony wraps them), and, when this is a follow-up,
                    // point In-Reply-To / References at the stored thread root so
                    // mail clients group it into the same conversation.
                    $headers = $message->getSymfonyMessage()->getHeaders();
                    $headers->remove('Message-ID');
                    $headers->addIdHeader('Message-ID', $thisMessageId);
                    if ($isReply && $rootMessageId) {
                        $headers->addIdHeader('In-Reply-To', $rootMessageId);
                        $headers->addIdHeader('References', $rootMessageId);
                    }
                });
                $sent = true;

                // Read back the id the transport actually assigned. SMTP keeps the
                // one we set; API mailers reassign it. Storing the real delivered id
                // means follow-up emails' In-Reply-To / References point at a
                // message that genuinely exists in the customer's mailbox.
                if ($sentMessage && $sentMessage->getMessageId()) {
                    $deliveredMessageId = $sentMessage->getMessageId();
                }
            } catch (\Throwable $e) {
                $error = $e->getMessage();
                Log::error('Refund email send failed', ['ticket' => $ticket->reference, 'error' => $error]);
                $sent = false;
            }
        } else {
            Log::info('Refund email (logged, not sent)', [
                'ticket' => $ticket->reference,
                'to' => $to,
                'template' => $templateKey,
                'subject' => $subject,
                'reason' => !$enabled ? 'REFUND_EMAIL_ENABLED is off' : 'no contact email',
            ]);
        }

        // Every attempt — delivered or logged-only — is recorded on the ticket's
        // audit trail with the full content, so Ops can see exactly what the
        // customer was told (and when) via the "View email" popup on the timeline.
        $note = $sent
            ? 'Email sent: ' . $subject
            : ($error
                ? 'Email failed: ' . $subject
                : 'Email queued (delivery off): ' . $subject);

        // Recording the audit line (and the last-email stamp) must never break the
        // caller — a customer submission or a workflow action should still succeed
        // even if this write fails (e.g. the meta column migration hasn't run yet).
        try {
            RefundTicketLog::create([
                'refund_ticket_id' => $ticket->id,
                'actor_id' => null,
                'actor_label' => 'System',
                'action' => 'email',
                'from_status' => null,
                'to_status' => null,
                'note' => $note,
                'meta' => [
                    'kind' => 'email',
                    'template' => $templateKey,
                    'to' => $to,
                    'subject' => $subject,
                    'body' => $body,
                    'delivered' => $sent,
                    'delivery_enabled' => $enabled,
                    'error' => $error,
                ],
            ]);

            $updates = [
                'last_email_template' => $templateKey,
                'last_email_sent_at' => $sent ? now() : $ticket->last_email_sent_at,
            ];
            // Record the thread root the first time an email is actually delivered,
            // so every subsequent workflow email replies onto this one. Store the
            // transport-assigned (delivered) id, not the pre-generated one.
            if ($sent && empty($ticket->email_message_id)) {
                $updates['email_message_id'] = $deliveredMessageId;
                $updates['email_thread_subject'] = $subject;
            }
            $ticket->update($updates);
        } catch (\Throwable $e) {
            Log::error('Refund email audit log failed', ['ticket' => $ticket->reference, 'error' => $e->getMessage()]);
        }

        return $sent;
    }
}
