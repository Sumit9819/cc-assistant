---
name: reference-delete-media-stem-prefix-false-positive
description: "delete_media's reference check greps the bare filename stem, so a \"-v2\" successor makes the original look still-referenced by its own replacement"
metadata: 
  node_type: memory
  type: reference
  originSessionId: 9c6f1bb7-e1aa-47b7-a180-e9cd066c7b98
  modified: 2026-09-04T15:03:25.264Z
---

**PLUGIN DEFECT, proven 2026-09-04 on irvingwellnessclinic (cc-assistant v0.78.1).**

`delete_media`'s `attachment_references()` resolves the attachment's `_wp_attached_file`, takes the **bare filename stem**, and runs `LIKE '%<stem>%'` across posts, postmeta, and pending changes. The stem search exists so one query covers every registered intermediate size (`foo-800x439.webp`) instead of ~30 per-size queries.

It also matches any successor whose name **starts with** that stem. Supersede a card as `foo.webp -> foo-v2.webp` and the checker reports `foo.webp` as "still_referenced", citing:

- `post #NNNN` — the body that now holds only the **v2** URL
- `postmeta _wp_attached_file on #<v2 id> "foo-v2"` — **the replacement's own row**

There is no override for `still_referenced` (correctly — it is the only irreversible tool), so every superseded generated image becomes undeletable.

**Proof.** Of 50 superseded ids, 49 blocked and exactly one passed: `laser-hair-removal-hair-colour.webp`, whose successor was renamed `...-hair-color.webp` for US spelling. `colour` is not a substring of `color`, so it alone escaped. Direct check of the cited post body:

```
exact v1 filename in body : 0
exact v2 filename in body : 1
bare STEM in body         : 1   <- the only thing the checker sees
```

**The fix** is to keep the single-query speed but tighten the match: pull candidates with the stem `LIKE`, then filter in PHP so a hit counts only when the stem is followed by `.` (the original) or by `-<W>x<H>.` (a registered size). `foo.webp` is not a substring of `foo-v2.webp`, so extension-anchored matching is enough on its own.

**Until it is fixed:** superseded files cannot be removed through the tool, and you must not work around it — `force`/`allow_foreign` do not cover this reason, and bypassing a guard on the only irreversible tool is exactly the wrong instinct. See [[reference_cc_assistant_delete_media]] for the tool's three refusals and [[feedback_verify_before_proposing_fix]].

**Naming note:** any `<stem>-suffix` convention collides with prefix matching. The defect is in the checker, not the naming, but a fully distinct successor name sidesteps it.
