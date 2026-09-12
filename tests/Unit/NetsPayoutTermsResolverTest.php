<?php

namespace Tests\Unit;

use App\Services\CardSettlement\Payout\NetsPayoutTermsResolver;
use Tests\TestCase;

/**
 * NETS payout schedule (Brian's NETS settlement standard, 2026-09-12): which
 * gateway consolidates a card type, and how many banking days later the money
 * lands.
 *
 * Pinned here because the rules are a config table evaluated top-down, and the
 * one ordering mistake that matters is silent: WeChat cross-border settles
 * through POS and sits ABOVE the CROSS BORDER catch-all, so moving it below
 * would quietly re-route it to COS at the wrong MDR rather than fail.
 *
 * The products and issuers below are the real spellings from the live reports
 * (98,000 lines, Jul 31 - Sep 9 2026), not invented ones.
 */
class NetsPayoutTermsResolverTest extends TestCase
{
    private function resolver(): NetsPayoutTermsResolver
    {
        return new NetsPayoutTermsResolver;
    }

    public function test_visa_and_mastercard_settle_t_plus_2_through_dbs_card_centre(): void
    {
        foreach (['VISA', 'MasterCard'] as $issuer) {
            $terms = $this->resolver()->resolve('Scheme Credit/Debit', $issuer);

            $this->assertNotNull($terms, "no schedule for {$issuer}");
            $this->assertSame('DBS CARD CENTER', $terms->gateway);
            $this->assertSame(2, $terms->termDays);
            $this->assertSame('T+2', $terms->termLabel());
            $this->assertSame('2.5%', $terms->mdrRate);
        }
    }

    public function test_every_eftpos_issuer_settles_t_plus_1_through_cos(): void
    {
        // NETS card and NETS QR wallets share one schedule; "Unknown" is a real
        // value in the files and must not fall through to null.
        $issuers = [
            'DBS Card', 'DBS PayLah', 'OCBC Card', 'OCBC PayAnyone',
            'UOB Card', 'UOB Mighty', 'HSBC Card', 'Maybank Card', 'SC Card', 'Unknown',
        ];

        foreach ($issuers as $issuer) {
            $terms = $this->resolver()->resolve('EFTPOS', $issuer);

            $this->assertNotNull($terms, "no schedule for EFTPOS / {$issuer}");
            $this->assertSame('COS', $terms->gateway);
            $this->assertSame(1, $terms->termDays);
            $this->assertSame('0.8%', $terms->mdrRate);
        }
    }

    public function test_flashpay_follows_the_nets_schedule(): void
    {
        $terms = $this->resolver()->resolve('FLASHPAY', 'NETS FlashPay');

        $this->assertSame('COS', $terms->gateway);
        $this->assertSame(1, $terms->termDays);
    }

    public function test_wechat_cross_border_settles_through_pos_not_the_cross_border_catch_all(): void
    {
        $terms = $this->resolver()->resolve('CROSS BORDER', 'WeChat Pay');

        $this->assertSame('POS', $terms->gateway);
        $this->assertSame(1, $terms->termDays);
        $this->assertSame('0.8%', $terms->mdrRate);
    }

    public function test_other_cross_border_schemes_settle_through_cos_at_the_higher_mdr(): void
    {
        foreach (['UnionPay Card', 'BHIM', 'Alipay+', 'PayNet QR', 'RINTISQR', 'JALINQR', 'ARTAJASAQR'] as $issuer) {
            $terms = $this->resolver()->resolve('CROSS BORDER', $issuer);

            $this->assertNotNull($terms, "no schedule for CROSS BORDER / {$issuer}");
            $this->assertSame('COS', $terms->gateway);
            $this->assertSame('1.8%', $terms->mdrRate);
        }
    }

    public function test_product_matching_ignores_case_and_padding(): void
    {
        $this->assertSame('COS', $this->resolver()->resolve('  eftpos ', 'dbs card')->gateway);
    }

    public function test_an_unmapped_product_resolves_to_null_rather_than_a_neighbours_schedule(): void
    {
        $this->assertNull($this->resolver()->resolve('SOME NEW RAIL', 'Whoever'));
        $this->assertNull($this->resolver()->resolve(null, 'VISA'));
        $this->assertNull($this->resolver()->resolve('', null));
    }
}
