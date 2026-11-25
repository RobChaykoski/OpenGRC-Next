# AI model artifacts

Status: **implemented register, CLI registration, an approval screen, and the
serving-runtime baseline check**. This page covers the model artifact inventory
described in [ADR 0059](../decisions/0059-ai-model-artifact-register.md). It
records what a model file claims to be, the runtime that serves it, and which
bytes were approved; it does not store weights, run inference, or act as a model
registry.

## Why a register is not an asset copy

A model is not one object. A safetensors directory contains weights, `config.json`,
tokenizer files and a chat template; changing the template changes behaviour
without changing a weight. A GGUF file is one binary with a self-declared
metadata header. A PyTorch `.pt` or `.bin` file is pickle data that can execute
code when it is loaded.

The register therefore records:

| Field | Why it matters |
|---|---|
| Name, source, revision | Reproducibility; a name alone is not provenance |
| Format | Decides the handling class and whether approval is allowed |
| Per-file SHA-256 | Detects substitution and template drift |
| Content hash | SHA-256 over the sorted path/hash list, identifying the artifact |
| Tokenizer and chat-template hash | Behaviour can change without a weight change |
| License | Redistribution and policy |
| Architecture, quantization, context length, tensor count | Capacity and compatibility |
| Conversion tool and version | A derived artifact has a supply chain of its own |
| Scan tool and timestamp | A named scan is evidence; registration is not |
| Serving runtime and version | Deployment context; compared with the supported baseline |
| Owner, serving asset, application | Accountability and where it runs |
| Approval | A dated decision by a named person |

The row never holds tensor data, tokenizer vocabulary, a prompt-template body,
or a credential.

## Register an artifact

Registration runs on the server because it walks a path and hashes every file:

```bash
php artisan opengrc:model-artifact:register \
  --path=/srv/models/Meta-Llama-3.1-8B-Instruct-GGUF \
  --source=https://huggingface.co/.../commit/<sha> \
  --revision=<sha> \
  --license=llama3.1 \
  --owner=12 \
  --asset=4 \
  --runtime=vllm \
  --runtime-version=0.7.1 \
  --conversion-tool=llama.cpp \
  --conversion-version=b3600 \
  --scan-tool=grype \
  --scan-tool-version=0.1.0
```

Use `--dry-run` to inspect and see the findings without writing, and `--json`
for scripted registration. The command is idempotent by content hash: the same
bytes update the same row.

`--skip-hash` reads metadata only and leaves the content hash empty. That is
useful for a quick look, but such an artifact cannot be approved: an approval
that does not identify the bytes means nothing.

### Formats and handling

| Format | Handling class | Approval |
|---|---|---|
| GGUF | Metadata-only read; the file's claims are recorded, not trusted | Allowed with a content hash |
| Safetensors | Preferred format; every file in the directory is hashed | Allowed with a content hash |
| ONNX | The graph is executable logic; record the opset and runtime | Allowed with a content hash |
| PyTorch pickle (`.pt`, `.pth`, `.bin`, `.ckpt`) | Untrusted executable content | **Refused** |

A pickle-format artifact can be registered so that it is visible, but it cannot
be approved from the command or the screen. Convert it to safetensors, or record
an isolated scan and the conversion first.

## Serving runtime

The runtime is deployment context, not a property of the bytes: the same artifact
can be served by a different runtime after an upgrade. Record it at registration
with `--runtime` and `--runtime-version` (both are required together), or later
without re-reading the model:

```bash
php artisan opengrc:model-artifact:runtime 12 \
  --runtime=vllm \
  --runtime-version=0.7.1
```

The supported baseline is deployment configuration in `config/ai_models.php`:

```php
'runtime_baseline' => [
    'vllm' => ['min' => '0.6.0'],
    'llama.cpp' => ['min' => '3600'],
    'onnxruntime' => ['min' => '1.18.0', 'max' => '1.22.0'],
],
```

- A runtime not listed in the baseline, a version below `min`, or a version above
  `max` raises **Medium: unsupported inference runtime**. The finding has a stable
  dedupe key, so re-recording the runtime updates it, and a supported value
  resolves it.
- A version that cannot be compared (a word such as `nightly`, or a missing
  version) is treated as unverified, not as supported. Record a numeric version
  or a llama.cpp build such as `b3600`.
- An empty baseline is fail-closed: no runtime is approved, and any recorded
  runtime becomes a finding until `config/ai_models.php` is set.
- The screen shows the runtime, its version, and a **Within baseline** or
  **Outside baseline** state. The finding remains the record of the decision.
- The register does not probe the deployment. The value is what the operator
  recorded, not what a running process reports.

A baseline change is a policy decision: review it like any other control
threshold, and record why a version was added or removed.

## Review and approve

**Entities → AI models** lists every registered artifact. The screen is
read-only for the fields that identify the artifact; hashes and provenance are
evidence, not editable metadata.

- **Approve** records the signed-in user and the current time. It is refused for
  pickle-format files and for artifacts with no content hash.
- **Withdraw approval** returns the artifact to Proposed. The previous approval
  stays in the activity log.
- **Retire** marks a decommissioned artifact. Its findings and history stay.

Approval does not close an open finding, does not mean the weights are trusted,
and does not prove the artifact is license-compatible with the intended use.
Those are separate decisions with their own records.

## Findings

Registration raises findings with a stable dedupe key. Re-registering the same
artifact updates the finding rather than creating a duplicate, and a condition
that is no longer present is resolved with a note.

| Condition | Severity |
|---|---|
| Pickle-format model | High: unsafe model serialization |
| Declared identity with different bytes | High: artifact substitution |
| Missing source, revision or content hash | Medium: unverifiable provenance |
| No license recorded | Medium: model license gap |
| Derived format with no conversion tool | Medium: unrecorded model conversion |
| Chat template changed | Medium: model behaviour drift |
| Runtime outside the supported baseline, or not comparable | Medium: unsupported inference runtime |

A changed artifact loses its approval until a person reviews the new bytes.

## Operating rules

1. **Hash every file.** A clean weight hash does not cover a changed tokenizer or
   chat template.
2. **Treat GGUF metadata as a claim.** `general.name` and
   `general.architecture` are written by the converter. Prove provenance with
   the source revision and the file hashes.
3. **Register the base model and the derived artifact.** A GGUF conversion has a
   base model and a conversion toolchain; both belong in the register.
4. **Scan in a disposable environment.** The metadata reader never loads weights
   or pickle data, but the scanner is still parsing untrusted bytes. Give it no
   credentials.
5. **Do not paste model contents into notes.** File names, source URLs and
   metadata can disclose customers, corpora or internal naming. Keep the register
   internal.
6. **Retire, do not delete.** The approval history and findings are the evidence.

## Limits, stated plainly

- **Registration is CLI-only.** There is no upload endpoint, by design: walking
  and hashing a path is a server-side operation, and a browser upload would make
  the web container a model store.
- **The runtime is recorded, not discovered.** The register stores one runtime
  and version per row and compares them with `config/ai_models.php`. It does not
  query the serving deployment, so a runtime upgrade must be recorded with
  `opengrc:model-artifact:runtime`. If several runtimes serve the same bytes,
  register the artifact against the primary deployment and record the others on
  the serving asset; the content hash still identifies the bytes.
- **The baseline is a config file, not a screen.** Changing which runtime versions
  are supported is an operator change to `config/ai_models.php`, reviewed and
  deployed like any other policy threshold.
- **One serving link.** A row links to one asset and one application. If several
  assets serve the same bytes, register the artifact against the asset that owns
  the deployment and record the others in the asset inventory; the content hash
  still identifies the bytes.
- **License compatibility is human judgement.** The register records the SPDX
  license and raises a gap when it is missing. It does not decide whether the
  license permits the intended use.
- **No evaluation scores.** Benchmarks, red-team results and prompt/response
  logging are assessment evidence with their own collection and privacy rules.

## Related

- [ADR 0059: AI model artifact register](../decisions/0059-ai-model-artifact-register.md)
- [SBOM and OSV supply-chain evidence](../integrations/sbom-osv.md)
- [Findings and remediation](findings-and-remediation.md)
- [Capability map and maturity](README.md)
- [NIS2 readiness](nis2-readiness.md)
