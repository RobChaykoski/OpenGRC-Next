# Wazuh SCA evidence

This is the operator runbook for the Wazuh connector, written for the test
platform at `172.16.16.5`. It covers the read-only Wazuh user, TLS, the OpenGRC
source, validation, deployment, and the screenshots worth taking.

The connector reads Wazuh's **Security Configuration Assessment (SCA)** results
from the manager API on port `55000`. It does not read the indexer, alerts, File
Integrity Monitoring, rootcheck, or vulnerability detection.

## What OpenGRC stores

One evidence record per agent, not one per policy. That is deliberate: the
validator reduces to the latest record per asset, so per-policy records would
silently discard every policy but the newest. The record contains every policy's
detail in the payload.

| Field | Example | Meaning |
|---|---|---|
| `external_id` | `wazuh:sca:001` | Stable per agent |
| `evidence_type` | `sca-compliance` | Used by validation rules |
| `asset` | `web-01` | Wazuh agent name; matched to `Asset.name`, `hostname`, or `asset_tag` |
| `collected_at` | newest `end_scan` | The scan that produced the measurement |
| `status` | `effective`, `failed`, `unknown` | See the rules below |

Status rules:

| Wazuh state | OpenGRC status |
|---|---|
| Agent not `active` | `unknown` — the scan is stale, not a pass |
| Any policy has `fail > 0` | `failed` |
| Any policy has `invalid > 0`, missing counts, or `pass != total_checks` | `unknown` — the scan is incomplete |
| Every policy is complete with `fail = 0`, `invalid = 0`, `pass = total_checks` | `effective` |
| No SCA policy on the agent | `unknown`, reason `no_sca_policy` |
| SCA endpoint fails for one agent | `unknown`, reason `sca_unavailable`; the rest of the fleet continues |

Payload shape:

```json
{
  "source": "wazuh",
  "agent": {
    "id": "001",
    "name": "web-01",
    "status": "active",
    "version": "Wazuh v4.14.0",
    "ip": "10.0.0.10",
    "os": {"name": "Ubuntu", "version": "24.04", "platform": "ubuntu", "arch": "x86_64"},
    "groups": ["default"],
    "lastKeepAlive": "2026-11-20T08:00:00Z"
  },
  "score": 98,
  "pass": 230,
  "fail": 2,
  "invalid": 0,
  "totalChecks": 232,
  "policyCount": 2,
  "policies": [
    {"id": "cis_ubuntu24", "name": "CIS Ubuntu Linux 24.04 Benchmark", "score": 98, "pass": 180, "fail": 2, "invalid": 0, "totalChecks": 182, "scanEndedAt": "2026-11-17T09:05:00Z"},
    {"id": "pci_dss", "name": "PCI DSS", "score": 100, "pass": 50, "fail": 0, "invalid": 0, "totalChecks": 50, "scanEndedAt": "2026-11-18T09:05:00Z"}
  ],
  "agentActive": true,
  "scanStartedAt": "2026-11-17T09:00:00Z",
  "scanEndedAt": "2026-11-18T09:05:00Z",
  "scanAgeDays": 1,
  "capturedAt": "2026-11-20T08:15:00Z"
}
```

## 1. Create a read-only Wazuh API user

Do not use an administrator account in OpenGRC. The connector needs exactly two
actions on all agents:

- `agent:read` — list agents and their status
- `sca:read` — read SCA summaries

### Dashboard

**Server management → Security → Policies → Create policy**

```json
{
  "name": "opengrc_read",
  "policy": {
    "actions": ["agent:read", "sca:read"],
    "resources": ["agent:id:*"],
    "effect": "allow"
  }
}
```

Then:

1. **Roles → Create role** named `opengrc_read`.
2. Link the `opengrc_read` policy to the role.
3. **Users → Create user** named `opengrc` with a long unique password.
4. Link the `opengrc_read` role to the user.

### API

```bash
TOKEN=$(curl -sk -u '<admin-user>:<admin-password>' \
  -X POST "https://172.16.16.5:55000/security/user/authenticate?raw=true")

# 1. Policy
curl -sk -X POST "https://172.16.16.5:55000/security/policies" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"name":"opengrc_read","policy":{"actions":["agent:read","sca:read"],"resources":["agent:id:*"],"effect":"allow"}}'

# 2. Role
curl -sk -X POST "https://172.16.16.5:55000/security/roles" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"name":"opengrc_read"}'

# 3. Link the policy to the role (use the IDs returned above)
curl -sk -X POST "https://172.16.16.5:55000/security/roles/<ROLE_ID>/policies?policy_ids=<POLICY_ID>" \
  -H "Authorization: Bearer $TOKEN"

# 4. Create the user
curl -sk -X POST "https://172.16.16.5:55000/security/users" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"username":"opengrc","password":"<long-unique-password>"}'

# 5. Link the role to the user
curl -sk -X POST "https://172.16.16.5:55000/security/users/<USER_ID>/roles?role_ids=<ROLE_ID>" \
  -H "Authorization: Bearer $TOKEN"
```

Verify the account is read-only:

```bash
USER_TOKEN=$(curl -sk -u 'opengrc:<password>' \
  -X POST "https://172.16.16.5:55000/security/user/authenticate?raw=true")

curl -sk -H "Authorization: Bearer $USER_TOKEN" \
  "https://172.16.16.5:55000/agents?limit=2" | jq '.data.total_affected_items'

# This must be rejected: the account has no write action.
curl -sk -o /dev/null -w '%{http_code}\n' -X DELETE \
  -H "Authorization: Bearer $USER_TOKEN" \
  "https://172.16.16.5:55000/agents?agents_list=999"
```

## 2. Fix the API certificate

The platform currently presents the Wazuh default certificate:

```
Subject: CN=wazuh.com, O=Wazuh, ...
SAN:     DNS:localhost
Issuer:  CN=wazuh.com (self-signed)
Expires: 2027-06-01
```

`https://172.16.16.5:55000` therefore fails hostname verification even if the
certificate is trusted. OpenGRC will not disable TLS verification to work around
it. Choose one of these:

### Option A — regenerate the Wazuh certificate with an IP SAN (recommended)

On the Wazuh manager:

```bash
sudo cp /var/ossec/api/configuration/ssl/server.crt /var/ossec/api/configuration/ssl/server.crt.bak
sudo cp /var/ossec/api/configuration/ssl/server.key /var/ossec/api/configuration/ssl/server.key.bak

sudo openssl req -x509 -newkey rsa:4096 -sha256 -days 825 -nodes \
  -keyout /var/ossec/api/configuration/ssl/server.key \
  -out /var/ossec/api/configuration/ssl/server.crt \
  -subj "/C=US/ST=California/L=San Francisco/O=Wazuh/CN=172.16.16.5" \
  -addext "subjectAltName=IP:172.16.16.5,DNS:wazuh.local"

sudo chown wazuh:wazuh /var/ossec/api/configuration/ssl/server.crt /var/ossec/api/configuration/ssl/server.key
sudo chmod 640 /var/ossec/api/configuration/ssl/server.crt /var/ossec/api/configuration/ssl/server.key
sudo systemctl restart wazuh-manager
```

For the Docker deployment, copy the two files into the manager container and
restart it, or mount them so they survive container recreation:

```bash
docker cp server.crt <manager-container>:/var/ossec/api/configuration/ssl/server.crt
docker cp server.key <manager-container>:/var/ossec/api/configuration/ssl/server.key
docker restart <manager-container>
```

Copy the new `server.crt` to the OpenGRC host and keep it private:

```powershell
# Windows example
mkdir C:\certs -Force
scp wazuh@172.16.16.5:/var/ossec/api/configuration/ssl/server.crt C:\certs\wazuh-172.16.16.5.pem
```

```bash
# Linux example
install -D -m 600 /tmp/server.crt /var/www/opengrc/storage/app/private/certs/wazuh-172.16.16.5.pem
```

Verify from the OpenGRC host. A `401` is success; a certificate error is not:

```powershell
curl.exe --cacert C:\certs\wazuh-172.16.16.5.pem -sS -o NUL -w "HTTP %{http_code}`n" https://172.16.16.5:55000/
```

### Option B — use an internal CA

Sign the API certificate with the CA your OpenGRC host already trusts and put the
CA chain in the CA bundle. This is the cleanest production option; the source
field then points at the CA file, not at the server certificate.

### Option C — test-only tunnel

If you do not want to touch the Wazuh certificate yet, forward the port and use
the existing `localhost` SAN:

```bash
ssh -L 55000:172.16.16.5:55000 <user>@<host-that-can-reach-wazuh>
```

Then set the source base URL to `https://localhost:55000` and the CA bundle to
the existing `server.crt`. This is for a laptop test, not for a container or a
production deployment.

## 3. Configure the source in OpenGRC

**Admin → System → Evidence sources → Create evidence source**

| Field | Value |
|---|---|
| Name | `Wazuh 172.16.16.5` |
| Type | `Wazuh` |
| Enabled | on |
| API base URL | `https://172.16.16.5:55000` |
| Username | `opengrc` |
| Password | the read-only user's password; it is write-only in the UI |
| CA bundle | `C:\certs\wazuh-172.16.16.5.pem` (or the container path) |
| Primary control | `CSC-4` in this installation; `CM-6` if NIST 800-53 is imported |
| Timeout | `30` seconds for a first run |

The password and configuration are encrypted at rest. The CA bundle is a path on
the OpenGRC host, never a URL.

If OpenGRC runs in Docker, mount the file and use the container path:

```yaml
services:
  app:
    volumes:
      - ./certs/wazuh-172.16.16.5.pem:/run/secrets/wazuh-ca.pem:ro
```

Then set the CA bundle to `/run/secrets/wazuh-ca.pem`. The app container must also
be able to route to `172.16.16.5:55000`; `host.docker.internal` is not a
substitute for the Wazuh host.

## 4. Run a collection

The **Run collection** action queues `CollectEvidenceJob`; it needs a worker:

```bash
php artisan queue:work --tries=1 --timeout=300
```

For a first test, skip the queue and run it synchronously:

```bash
php artisan opengrc:evidence:collect --source=1
```

A successful run prints counts and exits `0`. If it fails, the job error is the
first place to look:

```bash
php artisan tinker --execute="echo App\Models\EvidenceJob::latest('id')->first()?->error;"
```

Then evaluate the rules:

```bash
php artisan opengrc:evidence:validate
```

The evidence browser is **Admin → System → Evidence records**. Filter by the
Wazuh source.

## 4a. Live test on `172.16.16.5`

Observed on 2026-11-20:

- TCP `55000` is reachable and `GET /` returns `401`, so the manager API is up
  and authentication is required.
- The documented default accounts `wazuh:wazuh` and `wazuh-wui:wazuh-wui` were
  rejected with `401`. Do not rely on defaults; create the read-only user in
  section 1.
- The API certificate is self-signed with `CN=wazuh.com` and
  `SAN=DNS:localhost`, expiring 2027-06-01. `https://172.16.16.5:55000` without
  `-k` fails with `SEC_E_UNTRUSTED_ROOT`. Fix the certificate (section 2) or use
  the test-only tunnel; never disable verification.

Test sequence:

1. Create the read-only user with `agent:read` and `sca:read` (section 1).
2. Regenerate the manager certificate with `SAN=IP:172.16.16.5` (section 2), or
   put a properly signed certificate on it. Copy the certificate PEM to the
   OpenGRC host.
3. Create the source with:
   - base URL `https://172.16.16.5:55000`
   - the read-only username and password
   - the CA bundle path
   - **Agent group** `test` (or another small group)
   - **Max agents** `10`
   - timeout `30`
4. Run a dry run before writing anything:

   ```bash
   php artisan opengrc:evidence:collect --source=<id> --dry-run
   ```

   Expected: a summary table with `Read`, records seen, and Effective / Failed /
   Unknown counts. The dry run creates no evidence job and no evidence record.
5. If the dry run is clean, run for real:

   ```bash
   php artisan opengrc:evidence:collect --source=<id>
   php artisan opengrc:evidence:validate
   ```

6. Raise `Max agents` only after the first run is clean. Use the group filter
   for a pilot; do not scan the whole fleet on the first attempt.

| Symptom | Check |
|---|---|
| `401` | Username/password, token endpoint, account enabled |
| TLS / certificate error | CA bundle path and the certificate SAN, not `verify=false` |
| `403` | The user lacks `agent:read` or `sca:read` |
| `0` agents | The group filter is wrong or the user cannot read the group |
| Everything `Unknown` | SCA is not configured, or the agents are disconnected |

## 5. Validation rule

Create **Admin → System → Validation rules** for the control you are testing
(`CSC-4` here) with this definition:

```json
{
  "match": { "evidence_type": "sca-compliance" },
  "assert": [
    { "path": "agentActive", "op": "eq", "value": true },
    { "path": "invalid", "op": "eq", "value": 0 },
    { "path": "fail", "op": "eq", "value": 0 },
    { "path": "scanAgeDays", "op": "lt", "value": 7 }
  ]
}
```

The same evidence type can be evaluated by several rules on different controls:
CIS CSC-4, NIST CM-6, ISO A.8.9, SOC 2 CC7.1, and so on. The mapping is data.

The aggregate `fail` covers every SCA policy on the agent. If you need a
CIS-only rule, the current rule format cannot select a policy inside the
payload; either run one Wazuh policy per agent group or add a policy filter as a
future enhancement.

## 6. Asset mapping

The connector sets `assetIdentifier` to the Wazuh agent name. The ingestor
matches it against `Asset.name`, `Asset.hostname`, then `Asset.asset_tag`.

- If the names match, the evidence is attached to the asset automatically.
- If they do not, the record is stored with `asset_id = null` and appears as an
  unmapped external id. Create the asset with the agent name or hostname, then
  re-run validation.
- Use the same hostname for PatchMon and Wazuh if you want to compare the two
  sources by asset later.

## 7. Test matrix

Run these against a lab agent before trusting the source in production.

| Case | How to produce it | Expected |
|---|---|---|
| Healthy agent | Default SCA scan passes | `effective`, `fail = 0`, `invalid = 0` |
| Failing check | Break one CIS check on a lab agent | `failed`, `fail > 0`, one finding per rule and asset |
| Incomplete scan | Stop the agent mid-scan or point at a partial policy | `unknown`, `invalid > 0` |
| Disconnected agent | Stop the Wazuh agent | `unknown`, `agentActive = false` |
| No SCA policy | Remove the SCA policy from a lab agent | `unknown`, reason `no_sca_policy` |
| SCA endpoint error | Revoke `sca:read` for one agent group | `unknown`, reason `sca_unavailable`, other agents unaffected |
| Token expiry | Lower `auth_token_exp_timeout` to 60s and collect a large fleet | Connector re-authenticates and completes |
| Pagination | More than 500 agents | Every agent is read; a truncated list fails loudly |
| Wrong password | Change the source password | Job fails with `401`, no evidence written |
| Wrong CA / hostname | Use the default certificate with the IP URL | Job fails with a TLS error; no `verify=false` escape |
| API down | Stop `wazuh-manager` | Job fails; old evidence remains and `opengrc:doctor` reports staleness |
| Unmapped asset | Rename an agent | Evidence stored with `asset_id = null`, not guessed |

`php artisan opengrc:doctor` checks that every enabled source has a registered
connector and a fresh run. It does not validate the vendor certificate; the test
above does.

## 8. Screenshot checklist

Capture these in order for a clean demo:

1. **Evidence sources list** — Wazuh source enabled, type badge, last run.
2. **Source edit page** — Type `Wazuh`, base URL, username, masked password, CA
   bundle path, primary control, timeout. Do not show the password.
3. **Terminal: collection** — `opengrc:evidence:collect --source=1` with counts.
4. **Evidence records list** — filter by the Wazuh source; show `effective`,
   `failed`, and `unknown` rows side by side.
5. **Evidence record detail** — agent, all policies, `score`, `pass/fail/invalid`,
   scan timestamps, payload SHA-256, validation history.
6. **Validation rule** — the `sca-compliance` JSON definition.
7. **Control view (CSC-4)** — evidence and validation tabs, latest status,
   confidence, linked outcome.
8. **Finding detail** — a failed SCA check with severity, owner, due date, and
   the source/rule it came from.
9. **Framework matrix / assurance** — CSC-4 coverage and confidence.
10. **Doctor** — `opengrc:doctor` showing the evidence pipeline healthy.

For a deterministic screenshot set, keep one agent passing and one failing so the
list shows both `effective` and `failed`, plus one disconnected agent for
`unknown`.

## 9. Deployment

- Run a queue worker; the UI action queues a job. `queue:restart` after every
  deploy so workers do not keep old connector code in memory.
- Collection is not scheduled by the application yet. Add cron on the OpenGRC
  host:

  ```cron
  */30 * * * * cd /var/www/opengrc && php artisan opengrc:evidence:collect >> storage/logs/evidence.log 2>&1
  5 * * * *    cd /var/www/opengrc && php artisan opengrc:evidence:validate >> storage/logs/evidence.log 2>&1
  ```

  The first run of the day can be the one that matters; 30 minutes is a starting
  point, not a requirement.
- Restrict `172.16.16.5:55000` to the OpenGRC host at the firewall. Do not expose
  the manager API to the internet.
- Keep the Wazuh user read-only and rotate its password on a schedule. Update the
  source after rotation; the stored password is replaced only when you enter a
  new one.
- The JWT lasts 900 seconds. The connector refreshes once on `401`, but a very
  slow Wazuh API can still exceed the per-request timeout; raise the source
  timeout rather than lowering TLS or request safety.
- `opengrc:doctor --strict` is the deployment gate. The local development
  instance warns about the seeded `admin@example.com` without MFA; enrol 2FA or
  remove the demo account before production.

## 10. Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| `cURL error 60: SSL certificate problem` | CA bundle missing or wrong file | Point at the PEM that signed the API cert; do not disable verification |
| `Hostname mismatch` / `did not match expected` | Certificate SAN is `localhost`, URL is the IP | Regenerate with `IP:172.16.16.5` (Option A) or use the tunnel (Option C) |
| `401 Unauthorized` on collection | Wrong password, token expired and re-auth failed | Verify the read-only user with `curl`; re-enter the password |
| `403 Forbidden` on `/sca/...` | User lacks `sca:read` | Fix the Wazuh role/policy |
| `403` on `/agents` | User lacks `agent:read` | Fix the Wazuh role/policy |
| `0` agents | Wrong API, filtered user, or empty manager | Check `/agents` with the same user; the connector writes an `unknown` inventory record instead of a false pass |
| All records `unknown`, `no_sca_policy` | SCA is not configured on the agents | Enable SCA on the agent group |
| All records `unknown`, `sca_unavailable` | SCA endpoint error or agent not active | Check the agent status and `wazuh-manager` logs |
| Collection job times out | Large fleet or slow manager | Raise the source timeout and the queue worker timeout; check pagination |
| `doctor` says the source is stale | Collection has not run or is failing | Run collection, inspect `evidence_jobs.error` |
| Evidence is unmapped | Agent name does not match an asset | Create the asset with the agent name/hostname |

## 11. Security notes

- The connector only reads. The single `POST` is the token endpoint (ADR 0017).
- Credentials are encrypted at rest and never returned to the browser.
- The JWT is held in memory for the run and is not written to a payload or log.
- The CA bundle replaces the trust store; it cannot set `verify` to `false`.
- Evidence payloads contain hostnames, IPs, OS versions, and policy results. That
  is asset metadata, not end-user content; still treat the evidence browser as an
  internal system.
- The Wazuh API certificate is a secret-adjacent asset. Keep the private key on
  the manager and the CA bundle readable only by the OpenGRC process.

## 12. Limits and next steps

Not implemented in this slice:

- Vulnerability detector results (`/vulnerability/{agent_id}`) for RA-5.
- File Integrity Monitoring and rootcheck summaries.
- Alert and detection-coverage summaries from the indexer.
- Per-policy validation rules (the aggregate `fail` covers all policies).
- Scheduled collection inside Laravel (use cron for now).

The Wazuh connector is registered for `EvidenceSourceType::Wazuh`; `opengrc:doctor`
will treat a Wazuh source without this code as an operator error rather than
silently skipping it.
