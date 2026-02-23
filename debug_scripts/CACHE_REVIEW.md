# Cache Review - Real-time Data Requirements

## Cached Endpoints Analysis

### ❌ SHOULD NOT BE CACHED (Need Real-time Data)

#### 1. VendController::getVendAllChannelThumbnails
**Current**: Cached for 10 minutes
**Issue**:
- Shows channel quantities and product mappings
- When user updates product mapping, UI should reflect immediately
- Channel stock levels need to be real-time for operations

**Action**: REMOVE CACHE

#### 2. VendController::getVendParameters
**Current**: Cached for 10 minutes
**Issue**:
- Contains campaign settings and APK parameters
- When admin updates campaigns, vends should get new settings immediately

**Action**: REMOVE CACHE (or reduce to 1 minute with immediate invalidation)

#### 3. VendDataController::getBindedVends
**Current**: Cached for 5 minutes
**Issue**:
- Shows current vend-customer bindings
- When operator binds/unbinds vends, should reflect immediately

**Action**: REMOVE CACHE

---

### ✅ OK TO CACHE (Static/Slow-changing Data)

#### 4. VendController::getAllDCVends
**Current**: Cached for 5 minutes
**Data**: Customer list with vend assignments
**Justification**: Customer data changes infrequently
**Keep**: YES - but consider reducing TTL to 2-3 minutes

#### 5. VendDataController::getVendMediaContent
**Current**: Cached for 10 minutes
**Data**: Media content URLs (images/videos)
**Justification**: Media URLs rarely change
**Keep**: YES

#### 6. PaymentController::getPaymentMerchantsApi
**Current**: Cached for 10 minutes
**Data**: Payment method configurations
**Justification**: Payment methods rarely change
**Keep**: YES

---

### ⚠️ NEEDS REVIEW

#### 7. ClientController::getTransactions (Pagination only, no cache)
**Status**: No cache, only pagination
**Keep**: YES - correct approach

#### 8. ClientController::getChannels (Pagination only, no cache)
**Status**: No cache, only pagination
**Keep**: YES - correct approach

#### 9. CustomerController::syncNextDeliveryDate (Batch operations, no cache)
**Status**: No cache, batch operations only
**Keep**: YES - correct approach

#### 10. VoucherController::getVoucherDetails (Batch loading, no cache)
**Status**: No cache, batch loading only
**Keep**: YES - correct approach

---

## Summary

**Remove Cache From:**
1. ❌ `VendController::getVendAllChannelThumbnails` - Product mappings need real-time updates
2. ❌ `VendController::getVendParameters` - Campaign settings need real-time updates
3. ❌ `VendDataController::getBindedVends` - Vend bindings need real-time updates

**Keep Cache For:**
1. ✅ `VendController::getAllDCVends` - Customer data (consider reducing TTL)
2. ✅ `VendDataController::getVendMediaContent` - Media URLs
3. ✅ `PaymentController::getPaymentMerchantsApi` - Payment methods

**Already Correct (No Cache):**
1. ✅ `ClientController::getTransactions` - Pagination only
2. ✅ `ClientController::getChannels` - Pagination only
3. ✅ `CustomerController::syncNextDeliveryDate` - Batch operations
4. ✅ `VoucherController::getVoucherDetails` - Batch loading
