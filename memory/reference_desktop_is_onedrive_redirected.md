---
name: reference_desktop_is_onedrive_redirected
description: "This machine has TWO Desktop folders; the visible one is C:\\Users\\sumit\\OneDrive\\Desktop, not C:\\Users\\sumit\\Desktop"
metadata: 
  node_type: memory
  type: reference
  originSessionId: 32bbd000-c160-41d2-824f-454b206fe3ed
  modified: 2026-08-12T15:43:09.146Z
---

When the user asks for a file "on the Desktop", write it to:

```
C:\Users\sumit\OneDrive\Desktop
```

`C:\Users\sumit\Desktop` also exists and is writable, so a file save there **succeeds silently** — but OneDrive has redirected the shell Desktop, so the user never sees it. This wasted a round trip on 2026-08-12: a report PDF was written to the legacy folder, confirmed present with `ls`, and the user still could not find it.

Tell the two apart by recency, not by existence: the OneDrive folder holds their recent downloads and phone images; the legacy folder holds only files written by earlier sessions.

The user also keeps client deliverables at the root of **`D:\`** (50 GB volume, e.g. `D:\Mammoth-August-2026-Progress-Report.pdf`). For client reports, ask or default to `D:\` — that appears to be where they file them.

Related: [[feedback_handoff_docs_before_after_only]].
