# ADV-2026-011 — The remote IP is taken from headers the client writes

**Severity:** high where a copy makes an access decision on the address (an IP allow-list,
a rate limit, an admin-only network). Low where it is only logged, though the log can no
longer be trusted.
**Fixed in HCPHP:** `application/core/Application.php`, `application/config/default.json.sample`
**Fixed in HCPHP by:** `3089781` (2026-11-09)
**Affects:** any copy whose `Application::getRemoteIp()` / `getRemoteIP()` reads
`HTTP_CLIENT_IP` or `HTTP_X_FORWARDED_FOR` before `REMOTE_ADDR`

---

## What was wrong

```php
// application/core/Application.php
static function getRemoteIp(): string
{
    $sources = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR',
    ];

    foreach ($sources as $source) {
        if (getenv($source)) {
            return getenv($source);
        }
    }

    return '127.0.0.1';
}
```

Every source but `REMOTE_ADDR` is a request header, and the client writes request headers. The
first one set wins, so a client sending `X-Forwarded-For: 10.0.0.1` comes from `10.0.0.1` as
far as the application can tell.

HCPHP itself does not call `getRemoteIp()`. Copies do: for IP allow-lists, for a user's
last-login address, for an event log.

## Impact

- **An IP allow-list built on it lets everyone in.** Send the header with an allowed address.
- **A rate limit or lockout keyed on it** is reset by changing the header on each request.
- **Every logged address is the client's choice**, including what lands in an audit trail.
- The value is not validated, so `Client-IP: <script>` reaches whatever prints or stores it.

## How to tell if you are affected

```bash
grep -nE "HTTP_CLIENT_IP|HTTP_X_FORWARDED_FOR" application/core/Application.php
grep -rnE "getRemoteIp\(|getRemoteIP\(" application | grep -v vendor
```

The first grep hitting a list that ends with `REMOTE_ADDR` is the defect. The second shows how
much it matters: each caller that decides something on the address is a way in.

Runtime check, on a local instance only, for a page restricted by address:

```bash
curl -s -o /dev/null -w '%{http_code}\n' -H 'X-Forwarded-For: <an allowed address>' \
  http://localhost:<port>/<restricted page>
```

An affected copy lets the request in. A fixed one refuses it, unless the request really comes
from a trusted proxy.

**Markers.** The hcphp plugin's `drift.sh` reads these. Paths are relative to the application
root. A copy whose code matches neither shape is reported as "read by hand", never as fixed:

- defective: `application/core/Application.php` matches `'HTTP_CLIENT_IP'`
- fixed: `application/core/Application.php` matches `\$_SERVER\['REMOTE_ADDR'\]`
- fixed: `application/core/Application.php` matches `trustedProxies`

## The fix

The connection's own address is the answer. A forwarding header counts only when the
connection comes from a proxy the deployment trusts, and then it is read **right to left**,
because each proxy appends the address it saw and everything to its left is what the client
sent:

```php
static function getRemoteIp(?array $trustedProxies = null): string
{
    $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');

    if ($remote === '') {
        return '127.0.0.1'; // a command-line run has no connection
    }

    if ($trustedProxies === null) {
        $config = new Config('default', ['trustedProxies' => []]);
        $trustedProxies = (array)$config->get('trustedProxies');
    }

    if (!in_array($remote, $trustedProxies, true)) {
        return $remote;
    }

    $chain = array_map('trim', explode(',', (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')));

    for ($i = count($chain) - 1; $i >= 0; $i--) {
        $address = $chain[$i];

        $isClient = filter_var($address, FILTER_VALIDATE_IP)
            && !in_array($address, $trustedProxies, true);

        if ($isClient) {
            return $address;
        }
    }

    return $remote;
}
```

`default.json` gets `"trustedProxies": []`. Empty is the safe default: no header is believed.

## Porting checklist

- [ ] **Before deploying, find out how traffic reaches PHP.** Behind a reverse proxy or a load
      balancer, `REMOTE_ADDR` is the proxy's address for every request. List each proxy's
      address in `"trustedProxies"`, or every visitor will look like the proxy. That breaks an
      allow-list the other way: it opens it to everyone if the proxy's address is allowed, and
      closes it to everyone if it is not.
- [ ] If the proxy sends `Forwarded` (RFC 7239) rather than `X-Forwarded-For`, configure it to
      send `X-Forwarded-For`, or port the parsing for that header too. Never read both.
- [ ] Audit every caller: each decision taken on the address (allow-lists, rate limits,
      lockouts) and each place it is stored. Stored addresses from before the fix may be
      forged. Do not use them as evidence of where someone was.
- [ ] Look for code that reads the headers directly, without `getRemoteIp()`.
      `grep -rnE "HTTP_X_FORWARDED|HTTP_CLIENT_IP|X-Forwarded-For" application`.
- [ ] Copy `tests/Unit/Core/RemoteIpTest.php`. It sets each header in both `$_SERVER` and the
      environment, so it runs against the old `getenv()` code: 9 of its 11 tests fail there.
- [ ] Probe a restricted page locally with a forged header, as above.

## Notes

- The old code read `getenv()`. Under PHP-FPM that returns the FastCGI parameters, headers
  included. The fixed code reads `$_SERVER`, which holds the same values and is easier to set
  in a test.
- `trustedProxies` takes exact addresses, not CIDR ranges. A deployment behind a pool of
  proxies with changing addresses needs range matching, a separate change.

## Status

Fixed in HCPHP in `3089781` (2026-11-09). Each application built on HCPHP tracks its own status in its
own repository: run `/hcphp:port ADV-2026-011` there, which checks for the defect by the markers above
and records the result in that repository.
