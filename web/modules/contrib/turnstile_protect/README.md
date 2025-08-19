## INTRODUCTION

The Turnstile Protect module is a way to put site routes behind a Cloudflare Turnstile.

The primary use case for this module is:

- You have a route (or routes) on your Drupal site that do not need indexed by search engines you want to protect from excessive crawling

## How it works

```mermaid
flowchart TD
    Client(Client accesses path on website) --> Cookie{Has client passed turnstile before?}
    Cookie -- Yes --> Continue(Go to original destination)
    Cookie -- No --> Authenticated{Is client authenticated?}
    Authenticated -- Yes --> Continue(Go to original destination)
    Authenticated -- No --> IP_BYPASS{Is client IP whitelisted by captcha module?}
    IP_BYPASS -- Yes --> Continue(Go to original destination)
    IP_BYPASS -- No --> GOOD_BOT{Is client IP hostname in allowed bot list?}
    GOOD_BOT -- No --> PROTECTED_ROUTE{Is this route protected by Turnstile?}
    GOOD_BOT -- Yes --> CANONICAL_URL_BOT{Are there URL parameters?}
    CANONICAL_URL_BOT -- Yes --> PROTECTED_ROUTE{Is this route protected by Turnstile?}
    CANONICAL_URL_BOT -- No --> Continue(Go to original destination)
    PROTECTED_ROUTE -- Yes --> RATE_LIMIT{Is this IP in a range seeing increased traffic?}
    PROTECTED_ROUTE -- No --> Continue(Go to original destination)
    RATE_LIMIT -- Yes --> REDIRECT(Redirect to /challenge)
    RATE_LIMIT -- No --> Continue(Go to original destination)
    REDIRECT --> CHALLENGE{Cloudflare turnstile challenge}
    CHALLENGE -- Pass --> Continue(Go to original destination)
    CHALLENGE -- Fail --> Stuck
```

## REQUIREMENTS

- [Turnstile](https://www.drupal.org/project/turnstile)
- [Captcha](https://www.drupal.org/project/captcha)

## INSTALLATION

Install as you would normally install a contributed Drupal module.
See: https://www.drupal.org/node/895232 for further information.

## CONFIGURATION

- Follow [the turnstile module install instructions](https://www.drupal.org/project/turnstile)
- Configure which route(s) to protect - (TODO - implement config settings)
- If you want to exclude IPs from being protected, configure them in the captcha IP settings on your site at `/admin/config/people/captcha`

## MAINTAINERS

Current maintainers for Drupal 10:

- Joe Corall (joecorall) - https://www.drupal.org/u/joecorall
