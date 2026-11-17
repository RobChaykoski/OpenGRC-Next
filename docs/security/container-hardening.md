# Container hardening

The shipped `docker-compose.yml` is the hardened reference deployment. It is not
the only way to run OpenGRC, but it is the configuration that is tested and the
one to copy when you write your own.

## What the reference deployment does

| Control | Setting | Why |
|---|---|---|
| Read-only root filesystem | `read_only: true` | An exploit that writes to disk has nowhere to write |
| Explicit writable paths | `tmpfs` + one storage volume | Makes writable surface auditable |
| Dropped capabilities | `cap_drop: ALL` + named additions | Apache needs to bind 80, adjust ownership, and drop to `www-data`; nothing else |
| No privilege escalation | `no-new-privileges: true` | Blocks setuid-based escapes from the container |
| Init process | `init: true` | Reaps zombies; prevents PID 1 leaks |
| PID ceiling | `pids_limit` | Blunts fork bombs |
| Resource limits | `deploy.resources.limits` | A runaway import cannot starve the host |
| Separate queue worker | `worker` service | A fatal job cannot take the web tier down |
| Private cache headers | application | Evidence is not cacheable by shared proxies |

## Writable paths

Everything else in the image is read-only.

| Path | Type | Contents |
|---|---|---|
| `/var/www/html/storage` | volume | Application key, evidence, compiled views, logs |
| `/var/www/html/bootstrap/cache` | tmpfs | Laravel config/route cache, rebuilt at start |
| `/tmp` | tmpfs | PHP uploads in transit |
| `/run`, `/var/run/apache2`, `/var/lock` | tmpfs | PIDs, sockets, locks |
| `/var/log/apache2` | tmpfs | Access, error, and ModSecurity audit logs |

Logs in tmpfs disappear when the container restarts. Forward them to your log
platform (Docker log driver, journald, Fluent Bit) rather than relying on the
filesystem.

## Web application firewall

The image installs **ModSecurity with the OWASP Core Rule Set** and starts in
`DetectionOnly` mode:

```
SecRuleEngine DetectionOnly
SecAuditLog /var/log/apache2/modsec_audit.log
```

Detection-only is deliberate. CRS legitimately flags some Filament and Livewire
request patterns, and a fresh install that returns 403 on the login page is a
worse failure than a log entry nobody reads. The intended sequence is:

1. Deploy with `DetectionOnly` and normal traffic.
2. Review `modsec_audit.log` for a week or two.
3. Add exclusions for confirmed false positives.
4. Switch to `SecRuleEngine On` and keep monitoring.

```bash
docker exec -it opengrc-local \
  sed -i 's/^SecRuleEngine .*/SecRuleEngine On/' /etc/modsecurity/modsecurity.conf
docker restart opengrc-local
```

With a read-only root filesystem that `sed` will fail. Set it at build time or
mount an override file:

```yaml
    volumes:
      - ./modsecurity.conf:/etc/modsecurity/modsecurity.conf:ro
```

If you terminate TLS at a load balancer, the WAF still sees the request body, so
it remains useful. If your provider already runs a WAF, run OpenGRC with
ModSecurity in `DetectionOff` to avoid duplicated logging cost:

```
SecRuleEngine Off
```

## What is not hardened yet

- **The process runs as root inside the container.** Apache needs root to bind
  port 80 and switch to `www-data`. Moving to an unprivileged port (8080) with
  `USER www-data` is the next step; it requires changing the Apache vhost,
  healthcheck, and Compose port mapping together.
- **Base images are tag-pinned, not digest-pinned.** Add digests when you have a
  release process that updates them deliberately.
- **No image signing.** SBOM generation is in CI; add `cosign` signing before
  publishing images publicly.
- **Secrets are environment variables.** For anything beyond a lab, use Docker
  secrets or your orchestrator's secret store.
- **CSP still permits `unsafe-inline` and `unsafe-eval`.** Livewire and Alpine
  require them today. Moving to nonces and Alpine's CSP build is a planned
  project, not a config change; the trust center also loads two CDN scripts that
  should be self-hosted first.

## Verifying a running deployment

```bash
docker inspect --format='{{.State.Health.Status}}' opengrc-local
docker exec opengrc-local php artisan opengrc:doctor
docker exec opengrc-local sh -c 'apache2ctl -M | grep security2'
docker exec opengrc-local sh -c 'grep ^SecRuleEngine /etc/modsecurity/modsecurity.conf'
docker exec opengrc-local php artisan about
```

`opengrc:doctor` reports debug mode, URL, keys, storage, migrations, and queue
state in one page. In production, run it with `--strict` so warnings fail the
check.
