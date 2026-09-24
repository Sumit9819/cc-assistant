---
name: reference-onnx-int8-slower-without-vnni
description: On Zen 2 CPUs (no AVX512-VNNI) int8 ONNX models run ~9x SLOWER than fp32 — benchmark before trusting "quantized is lightweight" advice
metadata:
  type: reference
---

Measured on this machine (AMD Ryzen 5 5500U, Zen 2, 6c/12t) with Kokoro-82M TTS via onnxruntime 1.25 CPUExecutionProvider:

| model | threads | real-time factor |
|---|---|---|
| kokoro-v1.0.int8.onnx | 12 | **0.31x** (slower than realtime) |
| kokoro-v1.0.onnx (fp32) | 6 | **2.83x** |

fp32 is ~9x faster than int8. Zen 2 has AVX2 but **no AVX512-VNNI**, so int8 matmul kernels fall back to a slow path while fp32 uses well-optimised AVX2 kernels. The universal "use the quantized model, it's lightweight" guidance inverts on this hardware — int8 only wins where VNNI (Intel Cascade Lake+, Zen 4+) or ARM dot-product instructions exist.

Also measured: **6 threads beat 12** (2.83x vs 2.31x). Compute-bound ONNX gains nothing from SMT and loses to contention — pin `intra_op_num_threads` to physical cores.

RAM was never the constraint: fp32 peaked at 768 MB, int8 at ~500 MB. The int8 download saved disk, not time.

**How to apply:** never pick a quantization level from a blog claim. Benchmark int8 vs fp32 vs fp16 on the target CPU with a warm-up run first, and check for VNNI before assuming int8 helps. Applies to any onnxruntime workload here, not just TTS. See [[project-faceless-video-studio]].
