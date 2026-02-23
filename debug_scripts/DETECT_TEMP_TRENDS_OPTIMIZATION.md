# DetectTempTrends Optimization Analysis

## Current Issues Identified

### 🔴 **Critical Performance Problems**

#### 1. **N+1 Query Problem in analyzeOperationErrors() & analyzePreventiveMaintenance()**
**Lines 129-157**

```php
$vends = $this->getTargetVends(); // Returns array of IDs
foreach ($vends as $vendId) {
    // Each iteration makes 4-8 separate queries!
    $this->checkThresholdDuration(...);      // 2-3 queries
    $this->checkDualThresholdDuration(...);  // 2-4 queries
    $this->checkNotReached(...);             // 2-4 queries
    $this->checkLowestAbove(...);            // 2-3 queries
}
```

**Problem:** For 100 vends, this makes **400-800 queries**!

---

#### 2. **Multiple Queries for Same Data**
**Lines 161, 174-176, 201-204**

```php
// Query 1: Get latest temp
$latest = VendTemp::where('vend_id', $vendId)->where('type', $type)->latest()->first();

// Query 2: Get historical temps (same table!)
$count = VendTemp::where('vend_id', $vendId)->where('type', $type)
    ->where('created_at', '>=', now()->subMinutes($maxMin + 15))
    ->get();

// Query 3: Get last good temp (same table again!)
$lastGood = VendTemp::where('vend_id', $vendId)->where('type', $type)
    ->where('value', $op, $threshRaw)
    ->latest('created_at')
    ->first();
```

**Problem:** 3 separate queries to `vend_temps` for the same vend/type!

---

#### 3. **Inefficient Data Loading in checkThresholdDuration()**
**Line 174-176**

```php
$count = VendTemp::where('vend_id', $vendId)->where('type', $type)
    ->where('created_at', '>=', now()->subMinutes($maxMin + 15))
    ->orderByDesc('created_at')->get();  // ← Loads ALL records!

foreach ($count as $rec) {  // ← Iterates in PHP
    $v = $rec->value / $scale;
    $m = ($operator === '<') ? ($v < $tempC) : ($v > $tempC);
    if (!$m) break;
    $startTime = $rec->created_at;
}
```

**Problem:** Loads potentially hundreds of records into memory just to find where condition breaks.

---

#### 4. **Duplicate Queries in checkDualThresholdDuration()**
**Lines 219-220, 279-280**

```php
// Fetches latest temps
$t1 = VendTemp::where('vend_id', $vendId)->where('type', TYPE_CHAMBER)->latest()->first();
$t2 = VendTemp::where('vend_id', $vendId)->where('type', TYPE_EVAPORATOR)->latest()->first();

// Then later calls checkMinMaxInWindow which queries AGAIN
$q1 = VendTemp::where('vend_id', $vendId)->where('type', TYPE_CHAMBER)->where(...);
$q2 = VendTemp::where('vend_id', $vendId)->where('type', TYPE_EVAPORATOR)->where(...);
```

**Problem:** Queries same vend's temps multiple times.

---

#### 5. **Separate Queries for T1 and T2 in checkNotReached()**
**Lines 304-307, 326-329**

```php
// 4 separate queries for same vend!
$minT1_12 = VendTemp::where('vend_id', $vendId)->where('type', TYPE_CHAMBER)
    ->where('created_at', '>=', now()->subHours(12))->min('value');
$minT2_12 = VendTemp::where('vend_id', $vendId)->where('type', TYPE_EVAPORATOR)
    ->where('created_at', '>=', now()->subHours(12))->min('value');
$minT1_8 = VendTemp::where('vend_id', $vendId)->where('type', TYPE_CHAMBER)
    ->where('created_at', '>=', now()->subHours(8))->min('value');
$minT2_8 = VendTemp::where('vend_id', $vendId)->where('type', TYPE_EVAPORATOR)
    ->where('created_at', '>=', now()->subHours(8))->min('value');
```

**Problem:** Can be combined into 1-2 queries with CASE statements.

---

#### 6. **Bug in checkLowestAbove()**
**Line 394**

```php
if ($v1 > $thresholds[2] && $v2 > $thresholds[2])
    $sev = 3;
elseif ($v1 > $thresholds[1] && $v2 > $thresholds[1])
    $sev = 2;
$sev = 1;  // ← BUG! This always overwrites to 1
```

**Problem:** Severity is always 1, never 2 or 3!

---

#### 7. **Missing Indexes**

Need indexes on `vend_temps`:
- `(vend_id, type, created_at)` - for time-range queries
- `(vend_id, type, value)` - for threshold queries
- `(type, created_at)` - for global trend analysis

---

## Optimization Strategy

### Phase 1: Quick Wins (Immediate)
1. ✅ Fix the bug in `checkLowestAbove()` (line 394)
2. ✅ Add missing database indexes
3. ✅ Batch VendSmartAlert updates (reduce write queries)

### Phase 2: Query Optimization (High Impact)
1. ✅ Combine multiple queries into single queries with CASE statements
2. ✅ Use SQL aggregations instead of PHP loops
3. ✅ Pre-fetch all temps for a vend in one query, reuse data

### Phase 3: Structural Optimization (Medium Impact)
1. ✅ Batch process vends (chunk by 50-100)
2. ✅ Cache frequently accessed data
3. ✅ Add timeout and retry logic

---

## Expected Performance Improvement

### Current Performance (Estimated)
- **Per Vend:** ~100-200ms (multiple queries)
- **100 Vends:** ~10-20 seconds
- **Total Queries:** 400-800 queries

### After Optimization (Estimated)
- **Per Vend:** ~10-20ms (batched queries)
- **100 Vends:** ~1-2 seconds
- **Total Queries:** 50-100 queries

**Expected Improvement:** 80-90% faster, 80-90% fewer queries

---

## Specific Optimizations

### Optimization 1: Fix checkLowestAbove() Bug
```php
// BEFORE (BUG!)
if ($v1 > $thresholds[2] && $v2 > $thresholds[2])
    $sev = 3;
elseif ($v1 > $thresholds[1] && $v2 > $thresholds[1])
    $sev = 2;
$sev = 1;  // ← Always overwrites!

// AFTER (FIXED)
if ($v1 > $thresholds[2] && $v2 > $thresholds[2])
    $sev = 3;
elseif ($v1 > $thresholds[1] && $v2 > $thresholds[1])
    $sev = 2;
elseif ($v1 > $thresholds[0] && $v2 > $thresholds[0])
    $sev = 1;
```

### Optimization 2: Combine checkNotReached() Queries
```php
// BEFORE: 4 separate queries
$minT1_12 = VendTemp::where(...)->min('value');
$minT2_12 = VendTemp::where(...)->min('value');
$minT1_8 = VendTemp::where(...)->min('value');
$minT2_8 = VendTemp::where(...)->min('value');

// AFTER: 1 query with aggregations
$temps = VendTemp::where('vend_id', $vendId)
    ->whereIn('type', [TYPE_CHAMBER, TYPE_EVAPORATOR])
    ->where('created_at', '>=', now()->subHours(12))
    ->selectRaw('
        type,
        MIN(CASE WHEN created_at >= ? THEN value END) as min_12h,
        MIN(CASE WHEN created_at >= ? THEN value END) as min_8h
    ', [now()->subHours(12), now()->subHours(8)])
    ->groupBy('type')
    ->get()
    ->keyBy('type');
```

### Optimization 3: Pre-fetch Temps for Vend
```php
// Fetch all needed temps in one query
$temps = VendTemp::where('vend_id', $vendId)
    ->where('created_at', '>=', now()->subHours(72))
    ->orderByDesc('created_at')
    ->get()
    ->groupBy('type');

// Reuse this data for all checks
```

### Optimization 4: Batch Process Vends
```php
// Instead of processing all vends at once
foreach ($vends as $vendId) { ... }

// Process in chunks
foreach (array_chunk($vends, 50) as $chunk) {
    // Pre-fetch all temps for chunk
    // Process chunk
}
```

---

## Implementation Priority

1. **Critical (Do First):**
   - Fix checkLowestAbove() bug
   - Add database indexes

2. **High Impact (Do Next):**
   - Combine queries in checkNotReached()
   - Optimize checkThresholdDuration()

3. **Medium Impact (Do After):**
   - Batch vend processing
   - Pre-fetch temps strategy

---

## Risk Assessment

### Low Risk:
- ✅ Adding indexes (only affects performance)
- ✅ Fixing bug (corrects wrong behavior)
- ✅ Combining queries (same logic, fewer queries)

### Medium Risk:
- ⚠️ Changing query structure (need thorough testing)
- ⚠️ Batch processing (need to verify order doesn't matter)

### Mitigation:
- Test with real data
- Keep old methods commented for comparison
- Deploy during low-traffic period
