# Redirect Loop Fix Summary

## Problem Statement

Users accessing URLs like `https://eclectyc.energy/?redirect=%2F` were experiencing redirect loops with the following pattern in Apache logs:

```
302  GET /?redirect=%2F HTTP/1.0
301  GET /login?redirect=%2F HTTP/1.0
302  GET /?redirect=%2F HTTP/1.0
```

## Root Cause

The issue occurred when URLs with `redirect` query parameters (like `/?redirect=%2F`) were accessed directly via:
- Bookmarks
- Cached URLs
- Search engine results
- Shared links

While the application's authentication and redirect sanitization logic was working correctly, having a `redirect` parameter on the root URL created ambiguity and could lead to confusion or loops in certain scenarios.

## Solution

Implemented a new `RedirectParameterCleanupMiddleware` that:

1. **Intercepts requests** to specific paths that should never have redirect parameters:
   - `/` (root/dashboard)
   - `/logout`
   - `/dashboard`

2. **Strips redirect parameters** from these URLs using 301 (permanent) redirects to:
   - Update browser bookmarks automatically
   - Update search engine caches
   - Provide clean, canonical URLs

3. **Preserves redirect parameters** where they're valid and expected:
   - `/login?redirect=...` - needed for post-login navigation
   - Other admin and protected routes where redirects might be legitimate

## Implementation Details

### New Files

1. **app/Http/Middleware/RedirectParameterCleanupMiddleware.php**
   - New middleware class
   - Checks if current path is in the cleanup list
   - Removes redirect parameter and issues 301 redirect if found
   - Allows request to continue if no cleanup needed

2. **Tests**
   - `tests/test_redirect_cleanup_middleware.php` - Unit tests for the new middleware (10 tests)
   - `tests/test_redirect_loop_scenario.php` - Test for the specific reported scenario
   - `tests/test_complete_redirect_flow.php` - End-to-end flow test
   - `tests/test_authenticated_redirect_param.php` - Documentation test

### Modified Files

1. **public/index.php**
   - Added import for `RedirectParameterCleanupMiddleware`
   - Added middleware to application stack after routing middleware
   - Placed strategically so it runs before route-specific middleware

## Middleware Execution Order

In Slim Framework, middleware runs in reverse order of how it's added. The execution flow is:

```
Request → CORS → Error → Twig → Auth Globals → Cleanup → Routing → Route Middleware → Controller
```

The cleanup middleware runs:
- **After** routing (so it knows which route was matched)
- **Before** route-specific middleware like AuthMiddleware
- **Before** the controller is executed

This placement ensures:
- Clean URLs before authentication checks
- No interference with valid redirect parameters in login flow
- Minimal performance impact (only processes matched routes)

## Test Coverage

All tests passing:
- 13 tests for redirect sanitization (existing)
- 9 tests for auth middleware redirect handling (existing)
- 10 tests for new cleanup middleware
- **Total: 32 tests passing**

## Security Considerations

1. **Open Redirect Protection**: The existing `AuthController::sanitizeRedirect()` method continues to prevent open redirect vulnerabilities
2. **Whitelist Approach**: Only specific paths are cleaned, minimizing risk of breaking legitimate use cases
3. **301 vs 302**: Uses 301 (permanent) redirect to update caches and bookmarks
4. **Query Parameter Preservation**: Other query parameters are preserved when removing redirect parameter

## Usage Examples

### Before (Problematic URLs)
```
https://eclectyc.energy/?redirect=%2F
https://eclectyc.energy/logout?redirect=%2Fdashboard
https://eclectyc.energy/dashboard?tab=overview&redirect=%2F
```

### After (Clean URLs)
```
https://eclectyc.energy/
https://eclectyc.energy/logout
https://eclectyc.energy/dashboard?tab=overview
```

### Preserved (Valid Redirects)
```
https://eclectyc.energy/login?redirect=%2Fdashboard
https://eclectyc.energy/admin/users?redirect=%2F
```

## Deployment Notes

1. **No Database Changes**: This fix is code-only
2. **No Breaking Changes**: All existing functionality preserved
3. **Immediate Effect**: Works as soon as deployed
4. **Browser Cache**: 301 redirects will update browser bookmarks over time
5. **Rollback**: Simple - just remove the middleware registration from index.php

## Verification

To verify the fix is working:

1. Access `/?redirect=%2F` - should redirect to `/` with 301
2. Check Apache logs - should see single 301 redirect, no loops
3. Access `/login?redirect=%2Fdashboard` - should work normally
4. Run test suite: `php tests/test_redirect_cleanup_middleware.php`

## Future Considerations

If additional paths need redirect parameter cleanup, simply add them to the `$cleanPaths` array in `RedirectParameterCleanupMiddleware.php`.

Example:
```php
private array $cleanPaths = [
    '/',
    '/logout',
    '/dashboard',
    '/reports',  // Add new path here
];
```

## References

- Original issue: Redirect loops on `/?redirect=%2F`
- Apache logs showing 302/301/302 pattern
- Slim Framework middleware documentation
- PHP-FIG PSR-15 middleware specification
