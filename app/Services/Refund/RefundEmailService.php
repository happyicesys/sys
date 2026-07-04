<?php

namespace App\Services\Refund;

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
        $signoff = "\n\nBest regards,\nHappyIce Customer Support";

        return [
            self::T_RECEIVED => [
                'subject' => "We've received your refund request ({reference})",
                'body' => "Dear {name},\n\nThank you for reaching out to us. This is to confirm that we have received your refund request.\n\nYour reference number is {reference}. Please keep this for your records.\n\nOur team will review your request against the machine's transaction records and update you by email on the outcome. This usually takes a few working days.\n\nIf you have any questions in the meantime, please feel free to contact us." . $signoff,
            ],
            self::T_AUTO_REFUND => [
                'subject' => 'Your refund has already been processed automatically',
                'body' => "Dear Customer,\n\nThank you for contacting us.\n\nUpon verification, our system has confirmed that the product was not dispensed successfully. An automatic refund has already been initiated, and the refund request submitted is therefore not required.\n\nPlease note that the refunded amount may take approximately 2–3 working days to be reflected in your bank account, depending on your card issuer or payment provider.\n\nIf you have any questions or require further assistance, please feel free to contact us." . $signoff,
            ],
            self::T_CANCELLED_NO_CHARGE => [
                'subject' => 'No charge was captured for your transaction',
                'body' => "Dear Customer,\n\nThank you for contacting us.\n\nUpon verification, we found that the transaction was automatically cancelled because the product was not dispensed successfully. As no payment was captured for this transaction, no refund is required.\n\nYou may notice a temporary pending or authorization hold on your bank account. This will normally be released automatically by your bank within a few working days.\n\nIf you believe you have been charged or require further clarification, please do not hesitate to contact us." . $signoff,
            ],
            self::T_INFO_REQUIRED => [
                'subject' => 'Additional information required for your refund',
                'body' => "Dear Customer,\n\nWe have reviewed and approved your refund request. However, we were unable to process the refund as the PayNow number provided appears to be invalid or is not registered for PayNow transfers.\n\nTo proceed with the refund, kindly reply to this email with a valid PayNow-registered mobile number or UEN/NRIC-linked PayNow account.\n\nUpon receiving the correct PayNow details, we will process the refund as soon as possible." . $signoff,
            ],
            self::T_IN_PROGRESS => [
                'subject' => 'Your refund is being processed',
                'body' => "Dear Customer,\n\nThank you for your patience.\n\nWe would like to inform you that your refund request has been reviewed and is currently being processed. The refunded amount will be transferred to you shortly, and may take a few working days to be reflected depending on your bank or payment provider.\n\nWe apologize for the inconvenience caused. If you have any questions in the meantime, please feel free to contact us." . $signoff,
            ],
            self::T_COMPLETED => [
                'subject' => 'Your refund has been processed',
                'body' => "Dear Customer,\n\nWe are pleased to inform you that your refund has been successfully processed.\n\nWe apologize for the inconvenience caused and thank you for your patience and understanding.\n\nWe sincerely appreciate your support and look forward to serving you again in the future." . $signoff,
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

    public function send(RefundTicket $ticket, string $templateKey): bool
    {
        $templates = $this->templates();
        if (!isset($templates[$templateKey])) {
            return false;
        }

        $tpl = $templates[$templateKey];
        $subject = $this->interpolate($tpl['subject'], $ticket);
        $body = $this->interpolate($tpl['body'], $ticket);
        $to = $ticket->contact_email;
        $enabled = (bool) config('refund.email_enabled', false);
        $sent = false;
        $error = null;

        if ($enabled && $to) {
            try {
                Mail::html(nl2br(e($body)), function ($message) use ($to, $subject) {
                    $message->to($to)->subject($subject);
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
