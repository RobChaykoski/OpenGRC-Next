# Agent coverage and missed assets

The **Missed assets** page (app panel → Discovery → Missed assets) compares the
OpenGRC asset register with the latest Wazuh, PatchMon and discovery evidence. It
answers two questions:

1. Which assets are observed but not documented, owned, or covered by the agents
   that should see them?
2. What is the next action, and how do I deploy the missing agent safely?

It is read-only. It does not create assets, change ownership, or install
anything.

## What it reads

| Source | Data | Used for |
|---|---|---|
| OpenGRC assets | name, hostname, asset tag, endpoint agent ID, IP, `assigned_to_user_id`, criticality | The documented estate and ownership |
| Wazuh evidence | latest `sca-compliance` record per external id: agent id, name, status, last collection | Agent presence, activity, and freshness |
| PatchMon evidence | latest `patch-compliance` record per external id: hostname, IP, reporting flag, report age | Patch-agent presence and freshness |
| Discovery mirror | latest `DiscoveryScan` per asset name and open `DiscoveryFinding` counts | Whether the data was scanned and where exposure concentrates |
| NetBox / Ralph / Fleet | Not integrated in this slice | Reported as not integrated, never counted as coverage |

The latest evidence is selected by the highest evidence id per external id. The
report reads only metadata: no file paths, match previews, or raw content.

## Gaps

| Gap | Meaning | Typical action |
|---|---|---|
| `undocumented` | Observed by Wazuh, PatchMon, or discovery but absent from the asset register | Create the asset, assign an owner, then deploy the missing agents |
| `unowned` | In the register without `assigned_to_user_id` | Assign an owner and criticality |
| `missing_wazuh` | Documented asset with no Wazuh evidence | Deploy the Wazuh agent |
| `missing_patchmon` | Documented asset with no PatchMon evidence | Deploy the PatchMon agent |
| `unscanned` | No discovery scan for the asset | Run a discovery scan or deploy the collector |
| `stale_wazuh` | Wazuh evidence older than 2 days or agent inactive | Check agent connectivity |
| `stale_patchmon` | PatchMon report older than 2 days or not reporting | Check the PatchMon agent and queue |
| `stale_discovery` | Discovery scan older than 7 days | Refresh the mirror or scan |

## Priority

- **Critical** — undocumented host observed by at least one source, or an
  unowned asset with critical/high discovery findings.
- **High** — missing Wazuh or PatchMon agent, or stale coverage on a sensitive
  asset.
- **Medium** — unowned, unscanned, or stale discovery.
- **Low** — covered and fresh.

The report does not guess criticality. It uses the asset criticality term when it
is set and discovery finding severity when it is available.

## Matching

The report matches by:

1. the resolved OpenGRC `asset_id` on the evidence record;
2. asset name, hostname, asset tag, and `endpoint_agent_id`;
3. hostname and IP from the Wazuh or PatchMon payload.

IP is a weak key because of DHCP. A host matched only by IP should be reviewed
before it is trusted.

## Deployment

Deployment commands are rendered only for **admin** and **editor** roles.
Viewers see the report and the recommendation, not the commands.

Configuration comes from `config/agent_deployment.php` and environment:

```dotenv
WAZUH_MANAGER=172.16.16.5
WAZUH_REGISTRATION_SERVER=172.16.16.5
WAZUH_AGENT_GROUP=default
WAZUH_APT_REPOSITORY=https://packages.wazuh.com/4.x/apt/
WAZUH_YUM_REPOSITORY=https://packages.wazuh.com/4.x/yum/
WAZUH_WINDOWS_MSI_URL=https://packages.wazuh.com/4.x/windows/wazuh-agent-4.14.0-1.msi

PATCHMON_SERVER_URL=https://patchmon.example
PATCHMON_INSTALL_COMMAND="curl -fsSL https://patchmon.example/install.sh | sh -s -- --server {{server_url}} --group {{agent_group}}"

FLEET_URL=https://fleet.example
```

Rules:

- The Wazuh manager is taken from `WAZUH_MANAGER`; if it is empty, the host from
  the configured Wazuh evidence source is used.
- Commands contain **no** registration password, API token, or enrollment
  secret. Those come from the operator's secret store.
- PatchMon and Fleet commands are only shown when their environment template or
  URL is configured. The page does not invent an installer.
- Install agents only on hosts in the approved scope. Do not enrol personal or
  out-of-scope devices.

## Running it

```bash
php artisan opengrc:evidence:collect --source=<wazuh-id> --dry-run
php artisan opengrc:evidence:collect --source=<wazuh-id>
php artisan opengrc:evidence:collect --source=<patchmon-id>
php artisan opengrc:discovery:sync
```

Then open **Discovery → Missed assets**. The report updates from the latest
evidence; there is no separate job.

## Limits

- NetBox, Ralph, and Fleet are not integrated yet. Their columns are reported as
  not integrated rather than as zero coverage.
- The report is a latest-record view, not a historical trend. Use the evidence
  ledger for history.
- A large estate is currently reduced in PHP. Move the latest-record reduction
  into SQL before the fleet reaches tens of thousands of assets.
- The page recommends; it does not create assets or write back to NetBox. The
  review-and-create step stays human until the asset link registry ships.
