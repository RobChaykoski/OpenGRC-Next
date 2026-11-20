# Forensic collectors

Date: 2026-11-20 · Status: design

A collector is a client-side agent that reads artifacts from an endpoint and
sends them to OpenGRC or to an analysis tool. This document defines the default
open-source design: **native operating-system engines first**, **integrated
open-source tools only where performance or capability justifies them**, and **no
proprietary tools in the default stack**.

The collector runs in the estate, not inside OpenGRC. Raw content stays in the
collector's case store; OpenGRC remains the system of record for the
authorisation, the hashes, the manifest reference, and the chain-of-custody
events (ADR 0019). The evidence pipeline stores the measurement and the ledger
entry, never the artifact (ADR 0009, ADR 0008). Nothing here changes OpenGRC's
read-only, append-only rules.

Two rules follow from `docs/OSS-POLICY.md`:

1. A collector that links or embeds GPL/AGPL code must be GPL/AGPL-compatible and
   is a distribution decision, not an integration detail. The default stack
   therefore **invokes** GPL tools as separate processes and **links** only
   permissive libraries.
2. A tool that is free to download but not open source is not part of the
   default. It is optional, must be named as such, and may not be the only source
   of an artifact class.

## 1. Matrix by artifact class

| Artifact | Windows native source / API | Linux native source / API | Integrated open-source option | Performance note | Legal / integrity note |
|---|---|---|---|---|---|
| **Processes** | Toolhelp32 (`CreateToolhelp32Snapshot`), `Win32_Process` via WMI/CIM, ETW `Microsoft-Windows-Kernel-Process`; command line from `ProcessCommandLine` or 4688 | `/proc/<pid>/{stat,status,cmdline,environ,exe,fd,maps}`, `ps`, `pidfd`; eBPF `sched_process_exec` / `sched_process_exit` | osquery `processes`, `process_open_sockets`; Velociraptor `pslist` | Snapshot is cheap (one syscall per process); full string extraction from every process is not. Read `/proc` once, not per field. | `environ` and command lines contain secrets and personal data. Redact by default; hash the full artifact and store the redacted view. |
| **Network connections** | `GetExtendedTcpTable` / `GetExtendedUdpTable` (iphlpapi), `Get-NetTCPConnection`; ETW `Kernel-Network`, WFP audit 5156 | `/proc/net/{tcp,tcp6,udp,udp6,unix}`, `ss -taupne`; conntrack; eBPF `tcp_connect`, `tcp_close` | osquery `process_open_sockets`, `socket_events`; Zeek for flow records; Suricata for IDS events | One snapshot is O(open sockets). Socket-to-process attribution needs a second lookup and is racy — record the race. | Ephemeral ports and remote IPs are personal data in some jurisdictions. Keep the retention window short. |
| **Users and logons** | Security log 4624/4625/4634/4647/4672; LSA `LsaEnumerateLogonSessions`; `NetUserEnum`; `Win32_LoggedOnUser`; `quser` | `/etc/passwd`, `/etc/group`, `getent`; `loginctl`; `last`/`lastlog`/`wtmp`/`btmp`; auditd `USER_LOGIN`, `USER_AUTH`, `USER_ACCT` | osquery `logged_in_users`, `users`; Wazuh log decoders; Velociraptor logon and session artifacts | Enumerating event logs is bounded by the time window; enumerating every account's last logon is slow on domain-joined hosts. | `/etc/shadow` and credential material are **not** collected. Record the query, not the hash. |
| **Persistence** | SCM `EnumServicesStatusEx` + `HKLM\SYSTEM\CurrentControlSet\Services`, 7045; Task Scheduler COM / `schtasks /query /xml`, `System32\Tasks`; Run keys; WMI `root\subscription`; Startup folders | systemd units and timers (`systemctl list-unit-files`, `/etc/systemd/system`, user units), cron (`/etc/crontab`, `/etc/cron.*`, per-user crontabs, `atq`), init scripts, `/etc/ld.so.preload`, shell rc files | osquery `services`, `crontab`, `startup_items`; Velociraptor persistence artifacts; Wazuh FIM on the unit/cron paths | Targeted key and directory reads only. Do not walk the whole filesystem to find a unit file. | These are the artifacts most likely to change while you read them. Two passes with timestamps beat one pass. |
| **Logs and events** | Event Log `EvtQuery`/`EvtNext` (Security, System, Application); PowerShell 4103/4104; Defender 1116/1117; ETW providers; Windows Event Forwarding | journald (`journalctl --output=json`, `--since`, cursor), `/var/log/*`, rsyslog/syslog-ng, auditd/ausearch | Wazuh agent (GPLv2) for collection and normalisation; Velociraptor `Windows.EventLogs`; Plaso for parsing | Read by cursor and time window. Pulling an entire channel is the most common cause of collector-induced outages. | Logs are the highest-volume and highest-privacy class. Filter at the source; never ship the whole Security channel by default. |
| **Filesystem metadata** | USN journal (`FSCTL_READ_USN_JOURNAL`, `fsutil usn readjournal`); MFT enumeration (`FSCTL_ENUM_USN_DATA`); `GetFileInformationByHandleEx`; VSS for a stable view | `statx` (birth time), `find -printf`, `debugfs`/`xfs_db` for deleted entries, fanotify/inotify for live changes, `getfattr` | Sleuth Kit (`fls`, `istat`, `tsk_recover`) on images; Plaso for timeline; osquery `file` for targeted paths | USN is a delta read. MFT enumeration is a full metadata pass but no data read — still minutes on a large volume; run it once per volume, not per directory. | Metadata proves existence and timing, not content. Hash the harvested record set and keep the USN journal ID and `$MFT` record numbers for reproducibility. |
| **Registry (Windows)** | `RegOpenKeyEx`/`RegQueryInfoKey`, `RegSaveKeyEx` for a consistent hive copy; hive files in `System32\config`, `Amcache.hve`, `NTUSER.DAT`, `UsrClass.dat` | n/a — no registry. Linux equivalents are `/etc`, dconf/gsettings, and (if in scope) Wine/Proton prefixes | RegRipper (verify licence) or `python-registry` (Apache-2.0) against a copied hive; Plaso registry parsers | Reading a live hive is racy and can block on a locked key; copy the hive first, parse offline. | Offline hive copies are the defensible artifact. Never edit a hive in place, and never load a hive on a production host. |
| **Memory** | Acquire with a signed kernel driver — options are few and each licence must be checked; prefer a crash dump where policy allows | LiME (GPL-2.0) or AVML (MIT); `/proc/kcore` read-only where available | Volatility 3 for analysis — **verify the licence** (Volatility Software License is not OSI-approved as of this writing) | Acquisition is slow, size is the full RAM, and page cache behaviour changes during the capture. Budget minutes and gigabytes. | Memory is the most sensitive class: credentials, keys, and third-party personal data. Require explicit authorisation, encrypt the spool, and set a destruction date at capture time. |
| **Disk imaging** | Raw read of `\\.\PhysicalDriveN` (admin), or VSS snapshot plus file-level copy; hash inline with `Get-FileHash`/CNG | `dcfldd`/`dc3dd` (GPL-2.0) with `conv=noerror,sync`, `sha256sum` inline; LVM or filesystem snapshot; mount images `-o ro,noload` | Sleuth Kit (CPL) for image analysis; `libewf` for compressed images | Full imaging is hours per terabyte and usually unnecessary. Prefer targeted files plus metadata, or a snapshot for locked files. | Avoid full-disk imaging by default: it is slow, expensive to store, and broadens the privacy footprint. If an image is required, hash before and after, record the blocker size, and never mount it read-write. |
| **Installed packages** | Registry `Uninstall` keys, `Get-Package`/PackageManagement, Appx (`Get-AppxPackage`); avoid `Win32_Product` | `dpkg-query -W -f`, `rpm -qa --qf`, `apk info -v`, `pacman -Q`, `snap list`, `flatpak list` | osquery `deb_packages`, `rpm_packages`, `python_packages`, `npm_packages`; PatchMon via its API | Native queries are fast and local. `Win32_Product` triggers an MSI consistency check and can repair software — do not use it. | Package names and versions are low-sensitivity; still needed for SBOM and vulnerability scope. |
| **Containers** | Docker/Podman CLI read-only (`ps --no-trunc`, `inspect`, `diff`), HCS (`hcsdiag`, `Get-ComputeProcess`), containerd `ctr` | Docker/Podman, `crictl ps`, `ctr`, `/proc/<pid>/cgroup`, overlay2 upper dirs; Kubernetes API read-only (`kubectl get --raw`) | osquery `docker_*` tables; Velociraptor container artifacts; Trivy for image scanning (Apache-2.0) | `docker export` copies a whole rootfs; `docker diff` and `inspect` are cheap. Image scans are CPU- and network-heavy — schedule them. | Container filesystems are not evidence until exported with a digest. Record image digest and container ID, not just the name. |
| **Cloud and identity** | Entra ID / Microsoft Graph `auditLogs/signIns`, `directoryAudits`; Azure Activity Log | CloudTrail, GCP Audit Logs, Kubernetes audit log; IMDS read-only; `sssd`/`realmd` and PAM config | Wazuh cloud modules; Velociraptor cloud artifacts; Plaso for exported logs | API-bound: paginate and respect rate limits. Per-user enumeration is N+1; batch with filters. | Cloud logs are usually the only record of an action. Preserve the API response, the query, and the request ID; do not re-type values. |

## 2. Windows native engines

Use these first. They are signed by the OS vendor, already installed, and need no
new agent surface.

- **USN journal** — `FSCTL_READ_USN_JOURNAL` (or `fsutil usn readjournal`) gives
  create/delete/rename/change records with reasons and timestamps. It is a delta
  read, so daily collection is cheap and gaps are visible.
- **MFT enumeration** — `FSCTL_ENUM_USN_DATA` walks file records without reading
  file data. Use it for a size/sorting/timestamp baseline, not for content.
- **ETW** — `Microsoft-Windows-Kernel-Process`, `Kernel-Network`,
  `Kernel-File`, `DNS-Client`. A real-time session is the cheapest live telemetry
  on Windows; buffer it locally instead of streaming every event.
- **Windows Event Log** — `EvtQuery`/`EvtNext`. Security, System, Application,
  plus PowerShell 4103/4104 (script block and module logging) and Defender
  1116/1117. Use Windows Event Forwarding where a collector should not read
  Security directly.
- **Registry APIs** — `RegSaveKeyEx` for a consistent hive copy, then parse
  offline. `Amcache.hve`, `SRUM` (`SRUDB.dat`), and `Prefetch` (`*.pf`) are
  execution evidence that survives process exit.
- **WMI/CIM** — inventory, services, logged-on users, hotfixes, and
  `root\subscription` for WMI persistence. `Win32_Product` is excluded: it
  triggers MSI reconfiguration.
- **VSS** — `IVssBackupComponents` or `diskshadow` gives a stable, read-only view
  of locked and in-use files (event logs, hives, databases) without stopping the
  service.
- **Scheduled tasks and services** — Task Scheduler COM plus `System32\Tasks`,
  SCM enumeration plus the `Services` key. Read both: the API view and the
  on-disk definition can disagree.
- **TCP tables and process list** — `GetExtendedTcpTable` and Toolhelp32 give a
  consistent-enough snapshot for triage, with the usual attribution race noted.

### Sysmon

Sysmon is **free but proprietary**: no source, a Microsoft EULA, and no
redistribution right. It is therefore **optional** and **not part of the
open-source-only default**. Where a deployment wants Sysmon-class depth without
it, use:

- Windows audit policy — 4688 with command line, 4656/4663 object access, 5156
  WFP connections;
- the ETW providers above, consumed by the collector;
- PowerShell script block logging;
- the Wazuh agent (GPLv2) for rule matching on those channels.

## 3. Linux native engines

- **auditd / ausearch** — the kernel audit subsystem is the closest Linux
  equivalent to a security event log. Rules for `execve`, file access, and
  identity changes. `ausearch --start` and `aureport` for extraction.
- **eBPF (BCC, bpftrace, libbpf)** — process exec/exit, socket connect, file
  open, and DNS, with low overhead when the program is targeted. Requires
  `CAP_BPF` + `CAP_PERFMON` (or root) and a kernel new enough to support the
  program type. Record the kernel version and the program hash.
- **journald** — `journalctl --output=json --since` with cursor-based resumption.
  Cursors survive collector restarts; timestamps do not, if the clock moved.
- **`/proc` and `/sys`** — process, socket, mount, and device state. Read each
  file once; `/proc/<pid>/environ` changes under you.
- **systemd** — `systemctl list-unit-files`, `systemctl cat`, timers, and
  `systemd-analyze` for ordering. Also check drop-in directories, which override
  the unit file without changing it.
- **fanotify / inotify** — live file-change notification. fanotify is
  mount-wide and needs `CAP_SYS_ADMIN`; inotify is per-directory and has a watch
  limit. Neither is a substitute for a journal.
- **Package managers** — `dpkg-query`, `rpm -qa`, `apk`, `pacman`, plus `snap`
  and `flatpak`. Prefer the query interface over parsing `/var/lib/dpkg/status`.
- **cron and at** — `/etc/crontab`, `/etc/cron.*`, `/var/spool/cron/*`, `atq`.
  Check for files that are present but not executable by their owner, and for
  crontabs not owned by the user they run as.
- **sshd and session records** — `sshd` logs, `last`, `lastlog`, `wtmp`,
  `btmp`, `utmpdump`. `btmp` is the failed-login record and is frequently
  rotated away; capture it early.
- **LSM** — AppArmor (`aa-status`) and SELinux (`sestatus`, `ausearch -m AVC`)
  state and denials. A disabled LSM is itself a control finding.
- **Mount and block devices** — `mount`, `findmnt`, `df`, `blkid`, `lsblk`.
  Record read-only mounts and the options used (`ro,noload` for an image).

## 4. Integrated open-source options and licences

| Tool | Licence | Role in this design | Integration |
|---|---|---|---|
| Velociraptor | AGPL-3.0 | Fleet orchestration, artifact collection, triage at scale | Separate server; collectors push to it. AGPL network clause applies to its own deployment. |
| osquery / Fleet | osquery Apache-2.0 (also GPL-2.0 dual); Fleet core MIT, `ee/` commercial | Fast, SQL-shaped fleet queries | Separate deployment. Use only the free core; do not ship `ee/`. |
| Wazuh | GPLv2 | Host log collection, FIM, SCA, rule matching | Agent and manager run separately; OpenGRC reads its API (see `wazuh-evidence.md`). |
| YARA | BSD-3 | Content and pattern scanning of files and memory dumps | Linked library; safe for the default stack. |
| ClamAV | GPLv2 | Malware scanning of collected files | Invoked as a separate process, as in `OSS-POLICY.md`. |
| Plaso | Apache-2.0 | Super-timeline construction from many artifact types | Offline analysis; consumes exported artifacts. |
| Timesketch | Apache-2.0 | Collaborative timeline review and tagging | Separate server; receives Plaso output. |
| Sleuth Kit | CPL | Disk-image and filesystem analysis (`fls`, `istat`, `tsk_recover`) | Invoked on images, never on live filesystems. |
| Volatility 3 | Custom (Volatility Software License) | Memory analysis | **Verify before adopting**; not OSI-approved as of this writing, so not in the default stack. |
| AVML | MIT | Linux memory acquisition | Permissive; acceptable to ship or bundle. |
| LiME | GPL-2.0 | Linux memory acquisition (loadable module) | Separately built and shipped; not linked into OpenGRC. |
| Zeek | BSD-3 | Network flow and protocol logs | Separate sensor. |
| Suricata | GPLv2 | Network IDS/IPS and flow records | Separate sensor; called, not linked. |

GPL and AGPL tools are **separately invoked**: executed as processes or deployed
as their own services. No GPL code is linked into OpenGRC, consistent with
`docs/OSS-POLICY.md` and ADR 0018. A tool that must be linked (YARA, AVML) is
permissive. Anything else needs a licence review before it becomes a dependency.

Do not confuse "free" with "open source". Zimmerman tools, FTK Imager, and Sysmon
are free to use and **not** open source; they may appear in an opt-in profile but
must never be the only source of an artifact class, and the default profile must
work without them.

## 5. Native or integrated?

| Situation | Choose | Why |
|---|---|---|
| File and directory metadata, USN, MFT, registry hives, event logs on a Windows host | **Native** | No new agent, no licence question, and the OS already maintains the index. |
| Fleet-wide collection with central orchestration, quarantine, and hunts | **Integrated — Velociraptor** | Purpose-built for many endpoints; re-implementing its tasking and result store is not a good use of the collector. |
| Fast, repeatable fleet questions ("which hosts have this package?") | **Integrated — osquery/Fleet** | SQL over curated tables; the schema is the product. |
| Live Linux process, socket, and file events at low overhead | **Native — eBPF/auditd** | Kernel-side filtering; an agent polling `/proc` misses short-lived processes. |
| Host log collection, FIM, and rule matching with a manager | **Integrated — Wazuh** | Already integrated with OpenGRC evidence; do not duplicate its decoders. |
| Building a defensible timeline across many artifact types | **Integrated — Plaso + Timesketch** | Timeline merging and review UI are their core competency. |
| Analysing a disk image | **Integrated — Sleuth Kit** | Correct handling of images, deleted entries, and filesystem internals. |
| Scanning files or dumps for known patterns | **Integrated — YARA** (linked), ClamAV (invoked) | Mature rule engines; do not write signature matching yourself. |
| Full-disk imaging of every host by default | **Avoid** | Hours per terabyte, storage cost, and a broad privacy footprint for evidence the control rarely needs. |
| Making a proprietary agent the only source of an artifact | **Avoid** | Violates the open-source default and creates a licensing dependency for audit evidence. |
| Mounting or repairing evidence in place | **Avoid** | Changes timestamps and content; destroys integrity before hashing. Use a copy or an image. |
| `Win32_Product` for installed software | **Avoid** | Triggers MSI reconfiguration and can repair or restart software. |
| Live registry parsing of a busy hive | **Avoid** | Racy and can block; copy the hive and parse offline. |

## 6. Client hardening

- **Signed collector binary.** Build reproducibly, sign with a pinned key
  (Authenticode on Windows, a detached signature on Linux), and verify the
  signature before every upgrade. The collector refuses an unsigned policy
  bundle.
- **Least privilege.** Windows: `SYSTEM` only for classes that need it,
  otherwise `Event Log Readers`/`Backup Operators`; Linux: root only for memory
  and audit, otherwise `CAP_DAC_READ_SEARCH`, `CAP_SYSLOG`, `CAP_BPF`,
  `CAP_PERFMON`, `CAP_SYS_PTRACE`. Never install a permanent
  `CAP_SYS_ADMIN`-equivalent if a narrower capability works.
- **Read-only evidence access.** Open with read-only handles
  (`FILE_FLAG_BACKUP_SEMANTICS`, `GENERIC_READ`; `O_RDONLY|O_NOFOLLOW`, and
  `O_NOATIME` where permitted). Never mount read-write; never repair; never
  delete.
- **Scope allowlist.** Paths, registry keys, event channels, artifact classes,
  and container namespaces are enumerated. Anything not listed is denied, and
  the denial is logged as a decision, not as an error.
- **Time sync.** Check the clock offset before collection (`w32tm`, `chronyc
  tracking`, `timedatectl`) and record it in the manifest. Evidence with an
  unknown clock skew is not a timeline.
- **Encrypted local spool.** Queue data at rest is encrypted with a per-host key
  (BitLocker/LUKS volume or an age-style envelope). The server holds the
  decryption path; the spool is unreadable if the disk is stolen.
- **Bounded queue and backpressure.** A fixed-size spool with a documented
  drop policy: drop the oldest low-value class first, never the manifest, never
  the hash. If the queue is full, the collector stops collecting rather than
  overwriting evidence.
- **CPU and I/O priority.** Idle or below-normal priority (`nice`, `ionice`,
  `SetPriorityClass(IDLE_PRIORITY_CLASS)`, `PROCESS_MODE_BACKGROUND_BEGIN`),
  with a configurable concurrency cap. A forensic sweep must not page out the
  application it is investigating.
- **Resumable upload.** Chunked, idempotent uploads keyed by artifact id and
  hash. Resume after a network drop without re-reading the source.
- **No content in logs.** Collector logs carry counts, paths, hashes, and
  decisions — never file content, command output, tokens, or personal data.
- **Kill switch.** A locally cached signed flag and a server-side revoke stop
  collection within one task cycle. The switch is honoured before new work is
  claimed, not after it finishes.
- **Offline mode.** Run from a signed policy bundle, collect within scope, and
  flush when connectivity returns. Offline mode may not widen scope.
- **Hash before upload.** SHA-256 per artifact, a manifest hash over the artifact
  list, and a signature over the manifest. The case store verifies both before
  it seals the package; OpenGRC receives hashes and references only (ADR 0019).
  A mismatch is an integrity incident, not a retry.

## 7. Performance

- **Do not walk the whole filesystem.** If the scope is a directory or an
  artifact set, enumerate that. A recursive scan of `/` or `C:\` is a denial of
  service on a busy host.
- **Prefer indexes and journals.** USN on Windows and journald cursors on Linux
  give change-since-last-run without a full pass. MFT enumeration is a full
  metadata pass, so run it once per volume and cache the result.
- **Use VSS for locked files** instead of retrying reads or stopping services.
  The snapshot is also a consistent point-in-time view for event logs and hives.
- **Target the query window.** Collect event channels by `--since`/`EvtQuery`
  time range, not by "everything available". The first run is the expensive one;
  make it explicit.
- **Batch and compress.** One artifact bundle per collection run, compressed, with
  a manifest — not one request per file.
- **Rate-limit at the source.** Token-bucket limits on API calls (cloud, Wazuh,
  Docker) and on upload. Back off on `429` rather than retrying immediately.
- **Measure the cost.** Record bytes read, wall time, CPU time, and queue depth
  per run. A collector that cannot state its own cost cannot be scheduled safely.
- **Schedule by class.** Metadata daily, logs hourly, package inventory daily,
  memory and images on request only.

## 8. Legal and integrity

- **Authorisation first.** Written scope, named system owner, lawful basis, and a
  retention/destruction date before the first byte leaves the host. Memory and
  personal data need explicit approval.
- **Hash at collection.** SHA-256 at the point of read, before compression, and
  again after transfer. Record both; a mismatch is an incident, not a retry.
- **Manifest per run.** Collector version and signature, host identity, tool
  versions and exact commands/APIs used, scope, clock offset, start/end time,
  artifact list with hashes, and the policy bundle hash.
- **Chain-of-custody event.** Every handoff — collection, spool, upload,
  analysis, export — is an append-only ledger entry with actor and timestamp
  (ADR 0008). Evidence is withdrawn or superseded, never deleted or repointed.
- **Read-only, no modification.** No writes to the source, no `chmod`, no mount
  repair, no timestamp restoration on the original. Preserve timestamps on the
  copy and record which timestamps are original.
- **Tool versions and commands.** "Collected with the latest version" is not
  evidence. Pin the version, ship the command line or API call, and keep the
  configuration. A reviewer must be able to re-run the collection.
- **Expert review.** A human reviews scope, hashes, and the timeline before the
  artifact supports a finding. The collector produces data; it does not conclude.
- **Separate the analysis environment.** Images, memory dumps, and malware are
  examined on an isolated host, never on the collector or the OpenGRC server.
- **GPL boundary.** Analysing evidence with a GPL tool is fine. Linking it into
  the product is not, without a licence review and an ADR.

## See also

- `docs/OSS-POLICY.md` — licence allowlist and the separately-invoked boundary
- `docs/grc/evidence-standard.md` — what counts as evidence, naming, freshness,
  chain of custody
- `docs/decisions/0008-evidence-ledger-and-migration-integrity.md` — append-only
  ledger and hashes
- `docs/decisions/0009-continuous-control-monitoring.md` — connector and evidence
  pipeline
- `docs/decisions/0019-forensic-evidence-handling.md` — authorisation, sealing,
  custody, retention, and why raw content stays out of OpenGRC
- `docs/security/forensic-compliance-mapping.md` — the evidence types and
  framework outcomes a collector feeds
- `docs/integrations/wazuh-evidence.md` — the shipped host-telemetry connector
- `docs/integrations/discovery-evidence.md` — hash-verified evidence ingestion
  from a separate engine
