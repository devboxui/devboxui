# forward_auth (Drupal 10)

Lightweight module providing a forward-auth endpoint for reverse proxies like Caddy and Traefik.

## Features
- Two modes: cookie/session (uses Drupal sessions) and token header (shared secret).
- Optionally restrict by roles.
- Returns 200 with `X-Forward-Auth-*` headers for allowed requests.
- Returns 401 (with optional Location redirect) for denied requests.

## Installation
1. Place the `forward_auth` folder into `modules/custom/`.
2. `drush en forward_auth -y` or enable from admin UI.
3. Configure at **Configuration → System → Forward Auth settings**.

## Example Caddy config (token mode)

```
route {
  @auth { path * }
  forward_auth {
    address http://drupal.internal/forward-auth/validate
    # set header with shared secret (example)
    header_up X-Forward-Auth-Token "super-secret-value"
  }
  reverse_proxy @auth http://php-upstream
}
```

## Example Traefik middleware (forward auth)

Traefik v2 forward auth expects an endpoint that returns 2xx for allow, 401/403 to deny.

```yaml
middlewares:
  my-auth:
    forwardAuth:
      address: "http://drupal.internal/forward-auth/validate"
      trustForwardHeader: true
      authResponseHeaders:
        - "X-Forward-Auth-User"
        - "X-Forward-Auth-Email"
```

## Testing
- Token mode: set header in `curl` and assert 200: `curl -I -H "X-Forward-Auth-Token: super-secret-value" https://your-drupal/forward-auth/validate`
- Cookie mode: visit your site to obtain a Drupal session cookie, then use `curl` with that cookie against the endpoint.
```