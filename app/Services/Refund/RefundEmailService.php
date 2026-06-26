<?php

namespace App\Services\Refund;

use App\Models\RefundTicket;
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
    public const T_AUTO_REFUND = 'auto_refund_triggered';
    public const T_CANCELLED_NO_CHARGE = 'cancelled_no_charge';
    public const T_INFO_REQUIRED = 'info_required';
    public const T_COMPLETED = 'completed';

    public function templates(): array
    {
        $signoff = "\n\nBest regards,\nHappyIce Customer Support";

        return [
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
            self::T_COMPLETED => [
                'subject' => 'Your refund has been processed',
                'body' => "Dear Customer,\n\nWe are pleased to inform you that your refund has been successfully processed.\n\nWe apologize for the inconvenience caused and thank you for your patience and understanding.\n\nWe sincerely appreciate your support and look forward to serving you again in the future." . $signoff,
            ],
        ];
    }

    public function send(RefundTicket $ticket, string $templateKey): bool
    {
        $templates = $this->templates();
        if (!isset($templates[$templateKey])) {
            return false;
        }

        $tpl = $templates[$templateKey];
        $to = $ticket->contact_email;
        $enabled = (bool) config('refund.email_enabled', false);
        $sent = false;

        if ($enabled && $to) {
            try {
                Mail::html(nl2br(e($tpl['body'])), function ($message) use ($to, $tpl) {
                    $message->to($to)->subject($tpl['subject']);
                });
                $sent = true;
            } catch (\Throwable $e) {
                Log::error('Refund email send failed', ['ticket' => $ticket->reference, 'error' => $e->getMessage()]);
                $sent = false;
            }
        } else {
            Log::info('Refund email (logged, not sent)', [
                'ticket' => $ticket->reference,
                'to' => $to,
                'template' => $templateKey,
                'subject' => $tpl['subject'],
                'reason' => $enabled ? 'no contact email' : 'REFUND_EMAIL_ENABLED is off',
            ]);
        }

        $ticket->update([
            'last_email_template' => $templateKey,
            'last_email_sent_at' => $sent ? now() : $ticket->last_email_sent_at,
        ]);

        return $sent;
    }
}
