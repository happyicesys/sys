# Complete Cache Audit Report

## All Cache::remember Implementations Found

### Active Caching (3 locations)

#### 1. VendController::getAllDCVends ⚠️ NEEDS REVIEW
**Location**: `app/Http/Controllers/VendController.php:1212`
**Cache Key**: `dcvends:{operatorCode}`
**TTL**: 300 seconds (5 minutes)

**What it caches**:
- Customer lists for DC vend operators
- Vend assignments and channel information
- Product thumbnails

**Analysis**:
```php
$customers = Cache::remember($cacheKey, 300, function () use ($request) {
    return Customer::query()
        ->with(['deliveryAddress', 'photos', 'vend' => function ($query) {
            $query->with(['vendChannels', 'vendChannels.product.thumbnail']);
        }])
        // ... complex query
        ->get();
});
```

**Real-time Requirements**:
- ⚠️ **Vend assignments**: When operator assigns/unassigns vend to customer
- ⚠️ **Channel information**: When products are added/removed from vend
- ⚠️ **Product thumbnails**: When product images are updated
- ✅ **Customer basic info**: Changes infrequently

**Recommendation**:
- **CONSIDER REMOVING CACHE** or reduce TTL to 1-2 minutes
- This is used by DC vend operators who need to see current vend assignments
- If a vend is assigned to a customer, operators need to see it immediately

**Impact if cached**:
- Operators may not see newly assigned vends for up to 5 minutes
- Channel updates won't reflect for 5 minutes

---

#### 2. VendDataController::getVendMediaContent ✅ OK TO CACHE
**Location**: `app/Http/Controllers/Api/V1/VendDataController.php:80`
**Cache Key**: `vend_media:{code}`
**TTL**: 600 seconds (10 minutes)

**What it caches**:
- Media content URLs (images/videos) for vends

**Analysis**:
```php
$imgUrl = Cache::remember($cacheKey, 600, function () use ($code) {
    $vend = Vend::with('mediaContents')->where('code', $code)->firstOrFail();
    return $vend->mediaContents->first()->full_url ?? null;
});
```

**Real-time Requirements**:
- ✅ Media URLs rarely change
- ✅ Not critical for operations
- ✅ Static content

**Recommendation**: **KEEP CACHE**
- Media content is static and changes infrequently
- 10-minute cache is acceptable

---

#### 3. PaymentController::getPaymentMerchantsApi ✅ OK TO CACHE
**Location**: `app/Http/Controllers/PaymentController.php:222`
**Cache Key**: `payment_merchants:{countryCode}:{paymentGatewayName}`
**TTL**: 600 seconds (10 minutes)

**What it caches**:
- Payment method configurations
- Payment merchant information
- Payment gateway settings

**Analysis**:
```php
$dataArr = Cache::remember($cacheKey, 600, function () use ($countryCode, $paymentGatewayName) {
    $paymentMethods = PaymentMethod::query()
        ->with(['paymentGateway', 'paymentMerchant'])
        ->whereHas('paymentGateway', ...)
        ->where('is_active', true)
        ->get();
    // ... map to array
});
```

**Real-time Requirements**:
- ✅ Payment methods rarely change
- ✅ Admin-configured settings
- ✅ Not operational data

**Recommendation**: **KEEP CACHE**
- Payment configurations change very infrequently
- 10-minute cache is acceptable
- Should add cache invalidation when payment methods are updated

---

### Commented Out / Disabled Caching (2 locations)

#### 4. DashboardController (Line 60, 478) - COMMENTED OUT
**Status**: Not active, commented out
**No action needed**

---

## Summary and Recommendations

### ⚠️ REMOVE OR REDUCE CACHE

**1. VendController::getAllDCVends**
- **Current**: 5-minute cache
- **Issue**: Vend assignments and channel info need to be current for operators
- **Recommendation**:
  - **Option A**: Remove cache entirely (best for real-time accuracy)
  - **Option B**: Reduce to 30-60 seconds with cache invalidation on vend assignment changes
  - **Option C**: Keep cache but add immediate invalidation when:
    - Vend is assigned/unassigned to customer
    - Vend channels are updated
    - Products are mapped/unmapped

**Why it matters**:
- DC vend operators use this to see which customers have vends
- When they assign a vend, they expect to see it immediately
- Channel information affects operational decisions

---

### ✅ KEEP CACHE (No Changes Needed)

**2. VendDataController::getVendMediaContent** - OK
- Static media content
- 10-minute cache is appropriate

**3. PaymentController::getPaymentMerchantsApi** - OK
- Payment configurations change rarely
- 10-minute cache is appropriate
- Consider adding cache invalidation on payment method updates

---

## Final Recommendations

### Immediate Action Required

1. **Review VendController::getAllDCVends** with the team:
   - Ask: "Do operators need to see vend assignments immediately?"
   - Ask: "How often do vend assignments change?"
   - Ask: "Is 5-minute delay acceptable for this data?"

### If Real-time is Required

Remove cache from `getAllDCVends`:

```php
// Remove this:
$cacheKey = "dcvends:{$request->operatorCode}";
$customers = Cache::remember($cacheKey, 300, function () use ($request) {
    // ... query
});

// Replace with direct query:
$customers = Customer::query()
    ->with([...])
    // ... rest of query
    ->get();
```

### Cache Invalidation Strategy (If Keeping Cache)

Add observers to clear cache when data changes:

**VendObserver**:
```php
public function updated(Vend $vend)
{
    if ($vend->customer && $vend->customer->operator) {
        Cache::forget("dcvends:{$vend->customer->operator->code}");
    }
}
```

**VendChannelObserver**:
```php
public function updated(VendChannel $vendChannel)
{
    if ($vendChannel->vend && $vendChannel->vend->customer && $vendChannel->vend->customer->operator) {
        Cache::forget("dcvends:{$vendChannel->vend->customer->operator->code}");
    }
}
```

---

## Complete Cache Inventory

| Location | Endpoint | Cache Key | TTL | Status | Action |
|----------|----------|-----------|-----|--------|--------|
| VendController:1212 | getAllDCVends | `dcvends:{operatorCode}` | 5min | ⚠️ Review | Remove or reduce TTL |
| VendDataController:80 | getVendMediaContent | `vend_media:{code}` | 10min | ✅ OK | Keep |
| PaymentController:222 | getPaymentMerchantsApi | `payment_merchants:{country}:{gateway}` | 10min | ✅ OK | Keep |
| DashboardController:60 | (commented) | - | - | ❌ Disabled | No action |
| DashboardController:478 | (commented) | - | - | ❌ Disabled | No action |

**Total Active Caches**: 3
**Needs Review**: 1 (getAllDCVends)
**OK to Keep**: 2
