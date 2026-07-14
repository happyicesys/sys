<?php

namespace App\Services\Banking;

/**
 * Constants extracted from the "CIMB BizChannel Bulk Transaction Template"
 * Excel workbook (BIC Code / DropDwn sheets). Single source of truth for:
 *
 *   - the Singapore bank BIC/SWIFT directory (bank code → BIC → name),
 *   - PayNow proxy types (detail column E alternative to a BIC),
 *   - allowed purpose codes, settlement modes, posting indicators,
 *     service codes (header fields).
 *
 * The BankBicSeeder uses NAME_TO_BIC to stamp banks.bic_code for the rows
 * seeded by SingaporeBankSeeder; the Banks admin page can override per bank.
 */
class CimbBankDirectory
{
    /** Header field: 1=Giro Collection, 2=Bulk Payment, 3=Payroll. */
    public const SERVICE_CODES = [
        '1' => 'Giro Collection',
        '2' => 'Bulk Payment',
        '3' => 'Payroll',
    ];

    /** Header field: B=GIRO, R=FAST, F=PayNow FAST, G=PayNow GIRO. */
    public const SETTLEMENT_MODES = [
        'B' => 'GIRO',
        'R' => 'FAST',
        'F' => 'PayNow FAST',
        'G' => 'PayNow GIRO',
    ];

    /** Header field: C=Consolidated (one debit), I=Individual (one per row). */
    public const POSTING_INDICATORS = [
        'C' => 'Consolidated',
        'I' => 'Individual',
    ];

    /** Detail column F. COMC = commercial payment (commissions / loc fees). */
    public const PURPOSE_CODES = [
        'COMC' => 'Commercial Payment',
        'COLL' => 'Collection Payment',
        'SALA' => 'Salary Payment',
        'OTHR' => 'Other',
    ];

    /** Detail column E when paying a PayNow proxy instead of a bank account. */
    public const PROXY_TYPES = [
        'MOB'  => 'Mobile Number',
        'NRIC' => 'ID Number',
        'UEN'  => 'Unique Entity Number',
        'VPA'  => 'Virtual Payment Address',
    ];

    /**
     * Best-effort PayNow proxy type from a proxy value, for legacy "Paynow" rows
     * that have no explicit proxy_type. Returns '' when it can't be classified
     * confidently. Explicit banks.proxy_type should be preferred over this.
     *
     *  - MOB : SG mobile, 8 digits starting 8/9 (optionally +65 prefixed)
     *  - NRIC: [STFGM] + 7 digits + letter (e.g. S1234567A)
     *  - UEN : business/entity numbers ending in a letter — 8/9-digit ROB/ROC
     *          (e.g. 201434411Z, 53012345A) and the T/S/R 10-char formats
     *          (e.g. T08LL1234A).
     */
    public static function detectProxyType(?string $value): string
    {
        $v = strtoupper(trim((string) $value));
        if ($v === '') {
            return '';
        }
        $compact = preg_replace('/[\s\-]/', '', $v);

        if (preg_match('/^(?:\+?65)?[89]\d{7}$/', $compact)) {
            return 'MOB';
        }
        if (preg_match('/^[STFGM]\d{7}[A-Z]$/', $compact)) {
            return 'NRIC';
        }
        if (preg_match('/^\d{8,9}[A-Z]$/', $compact) || preg_match('/^[TSR]\d{2}[A-Z]{2}\d{4}[A-Z]$/', $compact)) {
            return 'UEN';
        }
        return '';
    }

    /**
     * Resolve CIMB detail column E for a beneficiary: the bank BIC (account
     * transfer) if present, else the explicit PayNow proxy type, else a best-effort
     * detection from the proxy value. Returns '' when nothing resolves.
     */
    public static function resolveColE(?string $bicCode, ?string $proxyType, ?string $accountValue): string
    {
        $bic = trim((string) $bicCode);
        if ($bic !== '') {
            return $bic;
        }
        $proxy = strtoupper(trim((string) $proxyType));
        if ($proxy !== '' && isset(self::PROXY_TYPES[$proxy])) {
            return $proxy;
        }
        return self::detectProxyType($accountValue);
    }

    /**
     * Singapore FAST/GIRO participant directory — bank code => [BIC, name].
     * Verbatim from the template's "BIC Code" sheet (trailing spaces trimmed).
     */
    public const BIC_DIRECTORY = [
        '7047' => ['BKKBSGSGXXX', 'Bangkok Bank Public Company Limited'],
        '7056' => ['BNINSGSGXXX', 'PT Bank Negara Indonesia (Persero) TBK'],
        '7065' => ['BOFASG2XXXX', 'Bank of America, National Association'],
        '7083' => ['BKCHSGSGXXX', 'Bank of China Limited'],
        '7092' => ['BEASSGSGXXX', 'Bank of East Asia, The'],
        '7108' => ['BKIDSGSGXXX', 'Bank of India'],
        '7126' => ['BOTKSGSXXXX', 'Bank of Tokyo-Mitsubishi UFJ, The'],
        '7135' => ['CRLYSGSGXXX', 'Crédit Agricole Corporate And Investment Bank'],
        '7153' => ['CHASSGSGXXX', 'JPMorgan Chase Bank, N.A.'],
        '7171' => ['DBSSSGSGXXX', 'DBS Bank Ltd'],
        '7214' => ['CITISGSGXXX', 'Citibank NA Singapore Branch'],
        '7232' => ['HSBCSGSGXXX', 'Hongkong and Shanghai Banking Corporation Limited, Singapore Branch, The'],
        '7241' => ['IDIBSGSGXXX', 'Indian Bank'],
        '7250' => ['IOBASGSGXXX', 'Indian Overseas Bank'],
        '7287' => ['HLBBSGSGXXX', 'HL Bank'],
        '7302' => ['MBBESGS2XXX', 'Maybank Singapore Limited'],
        '7339' => ['OCBCSGSGXXX', 'Oversea-Chinese Banking Corporation Ltd'],
        '7357' => ['UCBASGSGXXX', 'UCO Bank'],
        '7366' => ['RHBBSGSGXXX', 'RHB Bank Berhad'],
        '7375' => ['UOVBSGSGXXX', 'United Overseas Bank Ltd'],
        '7418' => ['BNPASGSGXXX', 'BNP Paribas'],
        '7463' => ['DEUTSGSGXXX', 'Deutsche Bank AG'],
        '7472' => ['SMBCSGSGXXX', 'Sumitomo Mitsui Banking Corporation'],
        '7490' => ['KOEXSGSGXXX', 'Korea Exchange Bank'],
        '7621' => ['MHCBSGSGXXX', 'Mizuho Bank Limited'],
        '7685' => ['UBSWSGSGXXX', 'UBS AG'],
        '7764' => ['FCBKSGSGXXX', 'First Commercial Bank'],
        '7791' => ['SBINSGSGXXX', 'State Bank Of India'],
        '7852' => ['SOGESGSGXXX', 'Societe Generale'],
        '7931' => ['ANZBSGSXXXX', 'Australia and New Zealand Banking Group'],
        '7986' => ['CIBBSGSGXXX', 'CIMB Bank Berhad'],
        '8077' => ['NATASGSGXXX', 'National Australia Bank Ltd'],
        '8235' => ['ICBCSGSGXXX', 'Mega International Commercial Bank Co. Ltd'],
        '8350' => ['BCITSGSGXXX', 'Intesa Sanpaolo SpA'],
        '8527' => ['ESSESGSGXXX', 'Skandinaviska Enskilda Banken AB'],
        '8606' => ['COBASGSXXXX', 'Commerzbank Aktiengesellschaft'],
        '8712' => ['ICBKSGSGXXX', 'Industrial & Commercial Bank Of China'],
        '9186' => ['ICICSGSGXXX', 'ICICI Bank Limited'],
        '9201' => ['CITISGSLXXX', 'Citibank Singapore Limited'],
        '9326' => ['QNBASGSGXXX', 'Qatar National Bank SAQ'],
        '9353' => ['CTCBSGSGXXX', 'Chinatrust Commercial Bank Co. Ltd'],
        '9496' => ['SCBLSG22XXX', 'Standard Chartered Bank (Singapore) Limited'],
        '9548' => ['HSBCSGS2XXX', 'HSBC Bank (Singapore) Ltd'],
        '9636' => ['MBBESGSGXXX', 'Malayan Banking Berhad'],
        '9733' => ['TRBUSGSGXXX', 'Trust Bank Singapore Limited'],
        '9742' => ['ANTPSGSGXXX', 'Anext Bank Pte. Ltd.'],
        '9751' => ['GXSPSGSGXXX', 'GXS Bank Pte. Ltd.'],
        '9779' => ['SSPISGSGXXX', 'Maribank Singapore Private Limited'],
        'NULL' => ['SIEISGS1XXX', 'Singapura Finance Ltd'],
    ];

    /**
     * banks.name (as seeded by SingaporeBankSeeder) → BIC. Retail-facing
     * names don't match the directory's legal names 1:1, so the mapping is
     * explicit. POSB is DBS; Citibank/HSBC/Maybank retail entities use their
     * Singapore-incorporated BICs. Finance companies without a FAST BIC in
     * the template (Sing Investments, Hong Leong Finance) are omitted — set
     * them manually on the Banks page if needed.
     */
    public const NAME_TO_BIC = [
        'DBS Bank' => 'DBSSSGSGXXX',
        'POSB Bank' => 'DBSSSGSGXXX',
        'OCBC Bank' => 'OCBCSGSGXXX',
        'United Overseas Bank (UOB)' => 'UOVBSGSGXXX',
        'Standard Chartered Bank' => 'SCBLSG22XXX',
        'Citibank Singapore' => 'CITISGSLXXX',
        'HSBC Singapore' => 'HSBCSGS2XXX',
        'Maybank Singapore' => 'MBBESGS2XXX',
        'CIMB Bank Singapore' => 'CIBBSGSGXXX',
        'Bank of China (Singapore)' => 'BKCHSGSGXXX',
        'RHB Bank Singapore' => 'RHBBSGSGXXX',
        'Trust Bank Singapore' => 'TRBUSGSGXXX',
        'GXS Bank' => 'GXSPSGSGXXX',
        'Singapura Finance' => 'SIEISGS1XXX',
        'Maribank' => 'SSPISGSGXXX',
        'Anext Bank' => 'ANTPSGSGXXX',
    ];

    /** BIC for a banks.name row, or null when unknown. */
    public static function bicForBankName(?string $name): ?string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }
        if (isset(self::NAME_TO_BIC[$name])) {
            return self::NAME_TO_BIC[$name];
        }
        // Best-effort fallback against the directory's legal names.
        foreach (self::BIC_DIRECTORY as [$bic, $legal]) {
            if (strcasecmp($legal, $name) === 0) {
                return $bic;
            }
        }

        return null;
    }

    /** All known BICs (validation helper). */
    public static function allBics(): array
    {
        return array_values(array_unique(array_map(fn ($row) => $row[0], self::BIC_DIRECTORY)));
    }
}
