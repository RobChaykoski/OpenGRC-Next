# 0059 - AI model artifacts are registered by format and provenance, not by weights

- Status: proposed
- Date: 2026-11-23

## Context

OpenGRC can register assets, evidence, SBOM components and findings. It has no
model for the artifact that many teams now deploy: a model file downloaded from
a hub, a vendor, or an internal training job. "We use Llama" is not an
inventory record. The file has a format, a hash, a license, a revision, a
tokenizer, and a conversion history, and those are the facts a control tester
needs.

The common formats are not interchangeable in risk terms (Hugging Face,
"Common AI Model Formats", 27 February 2025):

| Format | Structure | Security-relevant property |
|---|---|---|
| **GGUF** | One binary: key-value metadata, tensor descriptors, tensor data | Self-describing; metadata is readable without loading weights, but it is self-declared by the converter |
| **PyTorch `.pt` / `.pth`** | Pickle serialization of Python objects | Pickle can execute arbitrary code on load; the format is also slow and memory-hungry |
| **Safetensors** | JSON header plus tensor data, with restricted deserialization | No code execution on load; the model is usually a directory of files, not one file |
| **ONNX** | One file containing the computation graph, tensors and metadata | The graph is executable logic; portable, but operator coverage and quantization support vary |

Two properties drive this decision:

1. **A file's format decides whether opening it is safe.** A `.pt` or `.pth`
   from an untrusted source is executable content. Treating it as inert data is
   the same mistake as opening a macro document.
2. **A model is rarely one file.** Safetensors weights are accompanied by
   `config.json`, tokenizer files, and a chat template. Changing the chat
   template changes behaviour without changing a single weight. Hashing only
   `model.safetensors` under-records the artifact.

## Decision (proposed)

### 1. The model artifact is a component, not an asset copy

Register a model as a `SoftwareComponent`-style record linked to the asset or
service that serves it. Store **identity and metadata, not weights**. No tensor
data, tokenizer vocabulary, or prompt template body is copied into the OpenGRC
database. The artifact stays in the registry, object store, or build system.

Minimum fields:

| Field | Example | Why |
|---|---|---|
| Model name | `Meta-Llama-3.1-8B-Instruct-GGUF` | Human identity |
| Source and revision | Hub URL plus commit SHA or vendor release id | Reproducibility |
| Format | `gguf`, `safetensors`, `pytorch`, `onnx` | Decides the load-safety rule |
| Per-file SHA-256 | One entry per file in the artifact | Detects substitution and template drift |
| License | SPDX id | Policy and redistribution |
| Architecture | `llama`, `qwen2`, `gemma` (from GGUF `general.architecture`) | Compatibility and expected control set |
| Quantization | `Q4_K_M`, `IQ4_XS`, `Q8_0`, `fp16` | Quality and hardware claim |
| Tokenizer and chat-template hash | SHA-256 of `tokenizer_config.json` / GGUF `tokenizer.chat_template` | Behaviour drift |
| Size and tensor count | Bytes, tensor count | Capacity and completeness check |
| Owner and intended use | Team, purpose, data classification | Accountability |
| Approval | Dated decision and approver | Governance |

### 2. Format determines the handling class

| Format | Handling |
|---|---|
| PyTorch pickle (`.pt`, `.pth`, and often `.bin` / `.ckpt`) | **Untrusted executable content.** No load in a shared environment. Require a scanned copy, a sandboxed conversion, or a safetensors equivalent before approval. |
| Safetensors | Preferred training and serving format. Hash every file in the directory, including tokenizer and config files. |
| GGUF | Preferred CPU serving format. Read metadata through a metadata-only parser (for example `@huggingface/gguf` or the hub's GGUF viewer); do not execute the file to inventory it. |
| ONNX | Treat the graph as code. Record the opset, input signature, and the runtime and version that will execute it. |

"Scanned" means the artifact was inspected with a format-aware tool in an
isolated environment; it does not mean the weights are trustworthy. It means the
container was not a live execution path during inspection.

### 3. GGUF metadata is evidence, not provenance

GGUF metadata is convenient and self-contained, which makes it easy to overrate.
The file can declare its own architecture, name, version, quantization and chat
template. A converter can write any value into those fields.

Use GGUF key-value metadata to answer "what does this file claim to be?" and
record those claims. Prove provenance separately with the source revision, the
registry signature or checksum, and the hash of the file itself. A GGUF file is
not evidence that Meta trained it.

### 4. Conversion is part of the supply chain

GGUF and ONNX artifacts are usually derived from a PyTorch or Safetensors base
model. Register both the base model and the derived artifact, and record the
conversion tool and version when they are known.

The GGUF community has asked for exactly this: the conversion command line, the
target `ggml` commit and date, and the training or conversion toolchain are not
standard metadata today. Because they are usually absent:

- record them as **unknown**, not as "not applicable";
- treat an unattributed conversion as a lower-confidence artifact;
- do not let an embedded `general.name` or `general.version` substitute for a
  conversion record.

### 5. An unsafe or unverifiable artifact becomes a finding

Findings have a stable dedupe key so a re-scan updates the same record:

| Condition | Finding |
|---|---|
| Pickle-format model from an untrusted source, or no scan record | High: unsafe model serialization |
| Hash mismatch against the registered artifact | High: artifact substitution |
| Missing license, or a license incompatible with the intended use | Medium: model license gap |
| Unknown base model or conversion tool for a derived format | Medium: unverifiable model provenance |
| Chat-template or tokenizer hash changed without a new approval | Medium: model behaviour drift |
| Model runtime version outside the supported baseline | Medium: unsupported inference runtime |

The finding does not automatically fail a control. Exposure, compensating
controls, and the approved use decide the response, as in ADR 0028.

### 6. Inspection is metadata-only and read-only

OpenGRC does not run inference, does not host weights, and does not become a
model registry. The scan path is:

```text
registry or vendor drop
  -> format-aware metadata read (no weight load, no pickle load)
  -> per-file SHA-256
  -> license and provenance lookup
  -> vulnerability and secret scan on the artifact and its runtime
  -> signed evidence record + findings
  -> OpenGRC register
```

The evidence record carries the scanner name and version, the scan time, the
artifact hashes, and the claim set. A later scan supersedes it; it does not
rewrite history.

### 7. A model file is confidential evidence by default

Model metadata can disclose the vendor, architecture, fine-tuning corpus, and
internal naming. Tokenizer and prompt-template files can carry customer content.
Treat the register and its evidence as internal, and publish only the fields the
institution has approved for a trust center or customer answer.

## Consequences

- An auditor can ask "which models are in production, in which format, from
  which source revision, and who approved them?" and get a dated answer.
- Pickle-format models stop arriving as invisible data. A team that needs one
  records the sandbox and the conversion rather than loading it on a laptop.
- The register is only as good as the artifact metadata. A model with no
  revision, no license and no conversion record is registered as
  low-confidence, not as compliant.
- Format parsers are an attack surface in the scanner, not only in the runtime.
  Run the metadata read in a disposable container with no credentials.
- The hardware-support differences in the source article (GGUF for CPU, ONNX
  for mobile and browser, safetensors for training) are recorded as deployment
  context, not as a control verdict.

## Out of scope

- Model evaluation, red-teaming and benchmark scores. Those are assessment
  evidence imported from the testing pipeline.
- Prompt and response logging. That is application telemetry with its own
  privacy decision.
- Acting as an OCI or Hugging Face proxy. OpenGRC reads metadata and hashes; the
  artifact stays where the engineering team put it.

## References

- Hugging Face, "Common AI Model Formats" (27 February 2025):
  https://huggingface.co/blog/ngxson/common-ai-model-formats
- ADR 0028: SBOM and OSV are supply-chain evidence, not a scanner
- ADR 0054: streaming evidence is a push contract
- `@huggingface/gguf` and the hub GGUF metadata viewer for metadata-only reads
