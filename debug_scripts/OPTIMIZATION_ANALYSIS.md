# Job Optimization Analysis

## Failed Jobs
1. **SyncVendTransactionTotalsJson** - Runtime: ~30-32 seconds, Timeout: 30s
2. **SaveVendChannelsJson** - Runtime: ~10-12 seconds, Timeout: 10s

## Issues Identified

### 1. SyncVendTransactionTotalsJson

#### Problems:
- **Multiple clones of the same query** (lines 54-64, 128-138)
  - `todayTxns->clone()` called 5 times for vend, 5 times for customer
  - Each clone executes a separate query

- **N+1 Query Problem in calculateSuccessfulItemCount** (lines 201-230)
  - Calls `->get()` which loads ALL transactions
  - Then iterates in memory with `->sum(function())`
  - Loads `vendChannelError` relationship for each transaction

- **Multiple separate VendRecord queries** (lines 76-80, 150-154)
  - `daysVendRecords(1,1)->get()`
  - `daysVendRecords(3,0)->get()`
  - `daysVendRecords(7,0)->get()`
  - `daysVendRecords(29,0)->get()`
  - `lifetimeVendRecords`
  - Each executes a separate query

- **VendChannelErrorLog query with nested whereHas** (lines 66-71, 140-145)
  - Nested relationship query can be slow

- **Missing composite indexes**:
  - `vend_transactions` needs: `(vend_id, transaction_datetime)` and `(customer_id, transaction_datetime)`
  - `vend_records` needs: `(vend_id, date)` and `(customer_id, date)`
  - `vend_channel_error_logs` needs: `(vend_channel_id, created_at, vend_transaction_id)`

#### Solutions:
1. **Use single query with aggregations** instead of multiple clones
2. **Calculate successful count in SQL** instead of loading all records
3. **Batch VendRecord queries** or use UNION
4. **Add missing indexes**
5. **Increase timeout** to 60s as temporary measure

### 2. SaveVendChannelsJson

#### Problems:
- **Eager loading with deep nesting** (lines 44-51)
  - Loads 7 relationships at once
  - `vendChannels.vendChannelErrorLogs.vendChannelError` is 3 levels deep

- **ProductLimit query in loop** (lines 149-170)
  - While it's optimized with whereIn, still a separate query

- **Collection mapping with relationship access** (lines 88-128)
  - Accesses `$channel->product->sellingPrices` in loop
  - Accesses `$channel->vendChannelErrorLogs` in nested map

- **Missing indexes**:
  - `vend_channels` needs: `(vend_id, is_active)`
  - `product_limits` needs: `(product_id, date, created_at)`

#### Solutions:
1. **Review if all relationships are needed** - some might be unused
2. **Add missing indexes**
3. **Consider caching ProductLimits** if they don't change frequently
4. **Increase timeout** to 20s as temporary measure

## Recommended Actions

### Immediate (Quick Wins):
1. Add missing database indexes
2. Increase job timeouts temporarily
3. Optimize calculateSuccessfulItemCount to use SQL aggregation

### Medium Term:
1. Refactor SyncVendTransactionTotalsJson to use fewer queries
2. Review SaveVendChannelsJson eager loading
3. Consider implementing ShouldBeUnique for these jobs

### Long Term:
1. Consider caching frequently accessed totals
2. Implement incremental updates instead of full recalculation
3. Move heavy calculations to scheduled jobs instead of real-time
