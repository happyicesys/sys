# Testing Guide for API Performance Optimizations

## Quick Verification Steps

### 1. Check for Syntax Errors

```bash
# Verify no PHP syntax errors
php artisan route:list

# Clear cache to ensure changes are loaded
php artisan cache:clear
php artisan config:clear
```

### 2. Test Pagination (ClientController)

**Test getTransactions endpoint:**
```bash
# Default pagination (50 per page)
curl -X POST http://localhost/api/client/transactions \
  -H "Content-Type: application/json" \
  -d '{"date_from": "2025-01-01", "date_to": "2025-11-23"}'

# Custom pagination (25 per page)
curl -X POST http://localhost/api/client/transactions \
  -H "Content-Type: application/json" \
  -d '{"per_page": 25}'

# Maximum pagination (100 per page)
curl -X POST http://localhost/api/client/transactions \
  -H "Content-Type: application/json" \
  -d '{"per_page": 100}'
```

**Expected Response Format:**
```json
{
  "data": [...],
  "current_page": 1,
  "last_page": 5,
  "per_page": 50,
  "total": 234,
  "links": {...}
}
```

**Test getChannels endpoint:**
```bash
curl -X POST http://localhost/api/client/channels \
  -H "Content-Type: application/json" \
  -d '{"per_page": 50}'
```

### 3. Test Caching (VendController)

**Test getAllDCVends caching:**
```bash
# First request (cache miss - should be slower)
time curl -X POST http://localhost/api/client/dcvends \
  -H "Content-Type: application/json" \
  -d '{"operatorCode": "HIPL"}'

# Second request (cache hit - should be much faster)
time curl -X POST http://localhost/api/client/dcvends \
  -H "Content-Type: application/json" \
  -d '{"operatorCode": "HIPL"}'
```

**Test getVendAllChannelThumbnails caching:**
```bash
# Replace VM001 with actual vend code
time curl http://localhost/api/vends/VM001/thumbnails

# Second request should be much faster
time curl http://localhost/api/vends/VM001/thumbnails
```

### 4. Test Batch Operations (CustomerController)

**Monitor query count using Laravel Debugbar:**

1. Ensure Laravel Debugbar is installed:
```bash
composer require barryvdh/laravel-debugbar --dev
```

2. Access the endpoint and check the Queries tab in Debugbar:
```bash
# This should show only 3 queries regardless of number of people
POST /api/v1/customers/people
```

**Expected:**
- ✅ 3 queries total (batch load customers, batch load ops job items, external API call)
- ❌ NOT 2N+1 queries

### 5. Verify Cache Keys in Redis

```bash
# Connect to Redis and check cache keys
redis-cli

# List all cache keys
KEYS *

# Check specific cache keys
GET laravel_cache:dcvends:HIPL
GET laravel_cache:vend_thumbnails:VM001

# Check TTL (time to live)
TTL laravel_cache:dcvends:HIPL
TTL laravel_cache:vend_thumbnails:VM001
```

**Expected TTL:**
- `dcvends:*` → ~300 seconds (5 minutes)
- `vend_thumbnails:*` → ~600 seconds (10 minutes)

## Performance Benchmarking

### Using Apache Bench

```bash
# Test concurrent requests to cached endpoint
ab -n 100 -c 10 http://localhost/api/client/dcvends?operatorCode=HIPL

# Expected results:
# - Requests per second: Should be high (>100/sec)
# - Time per request: Should be low (<100ms for cached)
```

### Using Laravel Telescope (Recommended)

1. Install Telescope:
```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

2. Access Telescope dashboard:
```
http://localhost/telescope
```

3. Monitor:
- **Requests** tab: Response times
- **Queries** tab: Number of queries per request
- **Cache** tab: Cache hits/misses

## Validation Checklist

- [ ] All routes are accessible (no 404 errors)
- [ ] Pagination returns correct format with metadata
- [ ] `per_page` parameter works (test with 25, 50, 100)
- [ ] `per_page` validation works (test with 0, 101, -1)
- [ ] Cache is working (second request is faster)
- [ ] Cache keys are created in Redis
- [ ] Cache TTL is correct (5 min for dcvends, 10 min for thumbnails)
- [ ] Batch operations use only 3 queries
- [ ] No syntax errors in modified files
- [ ] No breaking changes for existing functionality

## Troubleshooting

### Issue: Pagination not working

**Solution:**
```bash
# Clear route cache
php artisan route:clear

# Clear config cache
php artisan config:clear
```

### Issue: Cache not working

**Check Redis connection:**
```bash
# In .env file
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Test Redis connection
redis-cli ping
# Should return: PONG
```

**Clear cache:**
```bash
php artisan cache:clear
```

### Issue: High query count

**Enable query logging:**
```php
// In AppServiceProvider boot() method
DB::listen(function($query) {
    Log::info($query->sql, $query->bindings);
});
```

Then check `storage/logs/laravel.log` for all queries.

## Next Steps After Testing

1. ✅ Verify all optimizations work correctly
2. 📝 Update API documentation for pagination changes
3. 🔔 Notify API consumers of breaking changes
4. 🚀 Deploy to staging environment
5. 📊 Monitor production performance metrics
6. 🔄 Implement cache invalidation observers (optional but recommended)

## Cache Invalidation (Optional)

If you want automatic cache clearing when data changes, create model observers:

```bash
# Generate observers
php artisan make:observer CustomerObserver --model=Customer
php artisan make:observer VendObserver --model=Vend
php artisan make:observer VendChannelObserver --model=VendChannel
```

Then implement the cache clearing logic as documented in the implementation plan.
