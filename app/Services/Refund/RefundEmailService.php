<?php

namespace App\Services\Refund;

use App\Models\Product;
use App\Models\RefundTicket;
use App\Models\RefundTicketLog;
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
                'subject' => "We've received your refund request ({reference})",
                'body' => "Dear {name},\n\nThank you for reaching out to us. This is to confirm that we have received your refund request.\n\nYour reference number is {reference}. Please keep this for your records.\n\n{items_block}Our team will review your request against the machine's transaction records and update you by email on the outcome. Within 7 working days." . $signoff,
            ],
            self::T_AUTO_REFUND => [
                'subject' => 'Your refund has already been processed automatically',
                'body' => "Dear {name},\n\nThank you for contacting us.\n\nUpon verification, our system has confirmed that the product was not dispensed successfully. An automatic refund has already been initiated, and the refund request submitted is therefore not required.\n\nPlease note that the refunded amount may take approximately 2–3 working days to be reflected in your bank account, depending on your card issuer or payment provider." . $signoff,
            ],
            self::T_CANCELLED_NO_CHARGE => [
                'subject' => 'No charge was captured for your transaction',
                'body' => "Dear {name},\n\nThank you for contacting us.\n\nUpon verification, we found that the transaction was automatically cancelled because the product was not dispensed successfully. As no payment was captured for this transaction, no refund is required.\n\nYou may notice a temporary pending or authorization hold on your bank account. This will normally be released automatically by your bank within a few working days." . $signoff,
            ],
            self::T_INFO_REQUIRED => [
                'subject' => 'Additional information required for your refund',
                'body' => "Dear {name},\n\nWe have reviewed and approved your refund request. However, we were unable to process the refund as the PayNow number provided appears to be invalid or is not registered for PayNow transfers.\n\nTo proceed with the refund, kindly email us at refund@happyice.com.sg with a valid PayNow-registered mobile number or UEN/NRIC-linked PayNow account.\n\nUpon receiving the correct PayNow details, we will process the refund as soon as possible." . $signoff,
            ],
            self::T_IN_PROGRESS => [
                'subject' => 'Your refund is being processed',
                'body' => "Dear {name},\n\nThank you for your patience.\n\nWe would like to inform you that your refund request has been reviewed and is currently being processed. The refunded amount will be transferred to you shortly, and may take a few working days to be reflected depending on your bank or payment provider.\n\nWe apologize for the inconvenience caused." . $signoff,
            ],
            self::T_COMPLETED => [
                'subject' => 'Your refund has been processed',
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
            $key = ($it->product_id ?? 'x') . '|' . ($it->vend_channel_code ?? '') . '|' . (int) $it->unit_price_cents;
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
        $lines[] = 'Total amount paid: $' . $data['total'];

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
            . '<div style="border-top:1px solid #e2e8f0;margin-top:6px;padding:10px 0 2px;text-align:right;font-size:13px;color:#0f172a;"><span style="color:#64748b;">Total amount paid</span> &nbsp;<b>$' . $data['total'] . '</b></div>'
            . '</div>';
    }

    /**
     * Wrap the plain body in a branded HTML shell, replacing the {items_block}
     * placeholder with the styled items card (HTML) — the same placeholder is
     * swapped for a text list in the stored/plain-text copy.
     */
    protected function renderHtml(string $bodyPlain, string $itemsHtml): string
    {
        $parts = explode('{items_block}', $bodyPlain);
        $inner = '';
        foreach ($parts as $i => $part) {
            $inner .= nl2br(e($part));
            if ($i < count($parts) - 1) {
                $inner .= $itemsHtml;
            }
        }

        return '<div style="background:#f1f5f9;padding:20px 0;">'
            . '<div style="max-width:560px;margin:0 auto;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;color:#0f172a;">'
            . '<div style="background:#0f766e;padding:20px 24px;border-radius:14px 14px 0 0;">'
            . '<div style="color:#ffffff;font-size:19px;font-weight:800;letter-spacing:-.01em;">HappyIce</div>'
            . '<div style="color:#99f6e4;font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;margin-top:2px;">Smart Frozen Vending &amp; Solution</div>'
            . '</div>'
            . '<div style="background:#ffffff;border:1px solid #e2e8f0;border-top:0;border-radius:0 0 14px 14px;padding:24px;font-size:14px;line-height:1.65;">'
            . $inner
            . '</div>'
            . '</div></div>';
    }

    public function send(RefundTicket $ticket, string $templateKey): bool
    {
        $templates = $this->templates();
        if (!isset($templates[$templateKey])) {
            return false;
        }

        $tpl = $templates[$templateKey];
        $subject = $this->interpolate($tpl['subject'], $ticket);
        $rawBody = $this->interpolate($tpl['body'], $ticket);

        // Only the acknowledgement lists the affected items; other templates carry
        // no {items_block} placeholder, so both swaps below are no-ops for them.
        $hasItemsBlock = str_contains($rawBody, '{items_block}');
        $itemsHtml = $hasItemsBlock ? $this->itemsHtml($ticket) : '';
        $itemsText = $hasItemsBlock ? $this->itemsText($ticket) : '';

        // Plain-text copy (stored on the audit trail): placeholder -> text list.
        $body = str_replace('{items_block}', $itemsText, $rawBody);
        // Delivered copy: branded HTML shell with the styled items card injected.
        $html = $this->renderHtml($rawBody, $itemsHtml);

        $to = $ticket->contact_email;
        $enabled = (bool) config('refund.email_enabled', false);
        $sent = false;
        $error = null;

        if ($enabled && $to) {
            try {
                // Refund mail gets its own sender identity (and optionally its own
                // mailer) so it doesn't go out as the shared "HISG Alert" mailbox.
                $mailer = config('refund.mail.mailer');
                $fromAddress = config('refund.mail.from_address');
                $fromName = config('refund.mail.from_name');

                $builder = $mailer ? Mail::mailer($mailer) : Mail::mailer();
                $builder->html($html, function ($message) use ($to, $subject, $fromAddress, $fromName) {
                    $message->to($to)->subject($subject);
                    if ($fromAddress) {
                        $message->from($fromAddress, $fromName ?: null);
                    } elseif ($fromName) {
                        // No dedicated address configured — keep the global From
                        // address but still relabel the sender name.
                        $message->from(config('mail.from.address'), $fromName);
                    }
                });
                $sent = true;
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

            $ticket->update([
                'last_email_template' => $templateKey,
                'last_email_sent_at' => $sent ? now() : $ticket->last_email_sent_at,
            ]);
        } catch (\Throwable $e) {
            Log::error('Refund email audit log failed', ['ticket' => $ticket->reference, 'error' => $e->getMessage()]);
        }

        return $sent;
    }
}
