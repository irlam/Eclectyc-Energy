# Visual Flow Diagram: Redirect Loop Fix

## Before the Fix (Problem Scenario)

```
User accesses: https://eclectyc.energy/?redirect=%2F
                            ↓
              ┌─────────────────────────┐
              │  Apache Web Server      │
              └─────────────────────────┘
                            ↓
              ┌─────────────────────────┐
              │  Slim Application       │
              │  - Routing Middleware   │
              │  - Route: GET /         │
              └─────────────────────────┘
                            ↓
              ┌─────────────────────────┐
              │  AuthMiddleware         │
              │  - User not logged in   │
              │  - Build redirect URL   │
              └─────────────────────────┘
                            ↓
              Path: /?redirect=%2F
              Query: redirect=%2F
              Remove redirect param from query
              Redirect target: /
                            ↓
              REDIRECT 302 → /login?redirect=%2F
                            ↓
              ┌─────────────────────────┐
              │  AuthController         │
              │  showLoginForm()        │
              │  - Check if logged in   │
              └─────────────────────────┘
                            ↓
         If user IS logged in (session still active):
              REDIRECT 302 → /
                            ↓
              But browser still has /?redirect=%2F
                            ↓
              🔄 INFINITE LOOP! 🔄
```

## After the Fix (Solution)

```
User accesses: https://eclectyc.energy/?redirect=%2F
                            ↓
              ┌─────────────────────────┐
              │  Apache Web Server      │
              └─────────────────────────┘
                            ↓
              ┌─────────────────────────┐
              │  Slim Application       │
              │  - Routing Middleware   │
              │  - Route: GET /         │
              └─────────────────────────┘
                            ↓
              ┌─────────────────────────┐
              │  🆕 CleanupMiddleware   │
              │  - Check: / in cleanPaths? ✓│
              │  - Has redirect param? ✓ │
              │  - Strip redirect param  │
              └─────────────────────────┘
                            ↓
              REDIRECT 301 → /
              (Permanent - updates bookmarks)
                            ↓
              ┌─────────────────────────┐
              │  Browser follows        │
              │  redirect to:           │
              │  https://eclectyc.      │
              │  energy/                │
              └─────────────────────────┘
                            ↓
              ┌─────────────────────────┐
              │  🆕 CleanupMiddleware   │
              │  - Check: / in cleanPaths? ✓│
              │  - Has redirect param? ✗ │
              │  - Pass through         │
              └─────────────────────────┘
                            ↓
              ┌─────────────────────────┐
              │  AuthMiddleware         │
              │  - User not logged in   │
              └─────────────────────────┘
                            ↓
              REDIRECT 302 → /login?redirect=%2F
                            ↓
              ┌─────────────────────────┐
              │  🆕 CleanupMiddleware   │
              │  - Check: /login in     │
              │    cleanPaths? ✗        │
              │  - Pass through         │
              │  (redirect param valid  │
              │   for login)            │
              └─────────────────────────┘
                            ↓
              ┌─────────────────────────┐
              │  AuthController         │
              │  showLoginForm()        │
              │  - Show login page      │
              │  - After login:         │
              │    redirect to /        │
              │    (NOT /?redirect=/)   │
              └─────────────────────────┘
                            ↓
              ✅ Clean URL flow - No loops!
```

## Key Differences

### Before
- URLs with redirect parameters could be bookmarked
- Redirect logic had to handle nested parameters
- Potential for ambiguity and loops
- Ugly URLs in address bar

### After
- Redirect parameters stripped from canonical URLs
- 301 redirects update bookmarks automatically
- Clean, simple URLs
- No ambiguity - redirect param only where needed
- No loops possible

## Middleware Execution Order

```
Request Flow (top to bottom):
┌────────────────────────────┐
│ 1. CORS Middleware         │ ← Added last, runs first
├────────────────────────────┤
│ 2. Error Middleware        │
├────────────────────────────┤
│ 3. Twig Middleware         │
├────────────────────────────┤
│ 4. Auth Globals Middleware │
├────────────────────────────┤
│ 5. 🆕 Cleanup Middleware   │ ← Runs before route middleware
├────────────────────────────┤
│ 6. Routing Middleware      │
├────────────────────────────┤
│ 7. Route Middleware        │ ← AuthMiddleware (route-specific)
├────────────────────────────┤
│ 8. Controller/Handler      │ ← AuthController, DashboardController, etc.
└────────────────────────────┘
```

## Example Scenarios

### Scenario 1: Bookmarked URL
```
Before: https://eclectyc.energy/?redirect=%2F (loop risk)
After:  https://eclectyc.energy/ (clean, works perfectly)
```

### Scenario 2: Search Engine Result
```
Before: /?redirect=%2Fadmin%2Fusers (confusing)
After:  / (redirects to login, then to /admin/users)
```

### Scenario 3: Shared Link
```
Before: /dashboard?redirect=%2F (unnecessary parameter)
After:  /dashboard (clean URL)
```

### Scenario 4: Valid Redirect (Preserved)
```
Before: /login?redirect=%2Fdashboard (works)
After:  /login?redirect=%2Fdashboard (still works - unchanged)
```

## Security Benefits

1. **Reduced Attack Surface**: Fewer places where redirect parameters are processed
2. **Canonical URLs**: Clear, predictable URL structure
3. **Cache Poisoning Prevention**: 301 redirects prevent malicious URLs from being cached
4. **Open Redirect Protection**: Existing sanitization still applies where redirects are valid
