---
name: reference-setting-revert-restores-whole-option
description: "Reverting one plugin_setting_update pending restores the ENTIRE option snapshot, silently undoing every sibling change applied to that option since it was queued"
metadata: 
  node_type: memory
  type: reference
  originSessionId: 32bbd000-c160-41d2-824f-454b206fe3ed
  modified: 2026-09-07T16:18:12.157Z
---

`CC_Assistant_Setting_Writer` is asymmetric, and the asymmetry is a trap:

- **`apply_plan`** calls `get_option()` on the LIVE option and writes only its own
  `path`. Several pendings against the same option therefore accumulate correctly,
  in any order. This is why queueing seven days of `opening_hours.{0-6}.time` as
  seven separate pendings is safe.
- **`revert_plan`** does `update_option($option, $payload['option_snapshot'])` —
  it restores the WHOLE option as it stood when THAT pending was queued.

So reverting one member of a group wipes every sibling applied since. On
sids-ponds 2026-09-07, reverting #401 (Sunday) would have restored the snapshot
taken before #394-#400 and put all seven days back to `09:00-17:00` plus the old
postal code. The revert would have looked like it succeeded.

**To undo one change in a group, queue a fresh forward change.** Never
`propose_revert`. Only revert a setting pending when it is the sole change to
that option since it was queued.

Related: [[reference_pending_supersede_is_silent]],
[[feedback_read_settings_not_frontend_for_plugin_use]].
