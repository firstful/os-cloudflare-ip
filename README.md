# os-cloudflare-ip

OPNsense plugin: keep Cloudflare **named IP lists** current with the router's
WAN egress IP(s) — one list per configured WAN interface.

## Why this exists

- The ISP rotates the home WAN IP (observed 2026-08-12).
- Anything keyed to the home IP in Cloudflare (Access policy IP allowlists,
  WAF rules referencing lists, firewall rule list matches) goes stale.
- The router owns the WAN session (DHCP/PPPoE) — it is the authoritative,
  instant, credential-free source of the egress IP.
- A custom plugin was chosen over a Cloudflare Worker relay: no worker, no
  external dependency, credential lives on the router with exactly one
  permission scope (Account Rulesets Edit).

## Layout

```
Makefile                                  plugin build (opnsense.mk)
pkg-descr
src/opnsense/
  mvc/app/models/OPNsense/CloudflareIp/   model XML + PHP (settings schema)
  mvc/app/controllers/OPNsense/CloudflareIp/
    Api/SettingsController.php            JSON API (settings CRUD + update/status)
    SettingsController.php                page controller
  mvc/app/views/OPNsense/CloudflareIp/    settings.volt (UI)
  www/js/opnsense/scripts/cloudflareip/   settings.js (UI logic)
  service/conf/actions.d/                 configd action (cloudflareip update)
  scripts/opnsense/cloudflareip/update.php  backend rotation script
```

## Dev mode (iterating without packaging)

On an OPNsense box (dev VM recommended first):

```sh
# place the tree (rsync/scp from the repo)
rsync -a src/opnsense/ root@<vm>:/usr/local/opnsense/
# reload configd so the new action is registered
configctl configd restart
# model/UI changes: pick up on next page load; controllers auto-discovered
```

Then hit the UI: Services -> Cloudflare IP. Verify the form, save settings,
"Update now", and the status panel.

## Building the package

From inside the OPNsense plugins tree (or with the OPNsense build tools):

```sh
cd net/os-cloudflare-ip   # or link the repo dir in
make build
make package              # -> work/pkg/os-cloudflare-ip-0.1.pkg
pkg add os-cloudflare-ip-0.1.pkg
```

## Cloudflare API surface used

All under `/client/v4/accounts/{account_id}/rules/lists`:

- `GET  ...?name=<list>`  — find list id by name
- `POST ...`              — create list (`kind: ip`) if missing
- `PUT  .../{id}/items`   — replace all items with current egress IPs
- `GET  .../bulk_operations/{op_id}` — poll the async result

Token scope required: **Account → Account Rulesets → Edit** (only).

## Status / TODOs

- [x] Model + settings schema (general + per-WAN rows)
- [x] UI form + WAN grid + Update-now + status panel
- [x] configd action + backend rotation script
- [ ] Periodic scheduling (cron) — decide: model-driven cron vs System Cron entry
- [ ] IPv6 policy: push global v6 address only, or /64? (Access accepts /64?)
- [ ] Interface dropdown instead of free-text interface name
- [ ] Validation test against live CF account (needs scoped token)
- [ ] Package freeze + ops doc
