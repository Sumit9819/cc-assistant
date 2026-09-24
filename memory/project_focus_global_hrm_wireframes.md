---
name: project-focus-global-hrm-wireframes
description: Focus Global HRM wireframe deck — revision 2 scope decisions and where the generator lives
metadata: 
  node_type: memory
  type: project
  originSessionId: 61087461-db9c-4b36-a057-31d797a39def
  modified: 2026-08-17T08:04:07.761Z
---

Focus Global HRM is an internal multi-company HRM product being specified through a wireframe deck (A4 landscape HTML, one screen per page). Revision 2 (2026-08-17) went from 57 to 71 screens.

**Scope decisions the user made:** Turnover report and the whole Invoices module (contractor self-billing) are OUT. Attendance/time tracking is IN and is the centre of gravity — revision 1 explicitly stated "no clock-in exists in this system".

**Added in rev 2:** Attendance (clock in/out, my timesheet, team, corrections, schedule), Settings › Holidays, Settings › Attendance policy, My Account (password/2FA/sessions), Notifications, Profile › Personal & emergency, Profile › Attendance, Performance (goals + review cycle), Training & certifications, Hiring › Interview scheduling, Reports › Attendance & overtime.

**How to revise it:** the deck is generated, never hand-edited. Python generator at `OneDrive\Desktop\Focus-HRM-Wireframe-Generator\` — `wf_core.py` (layout engine + shell), `wf_a/b/c.py` (screens), `build.py` (assembly + out-of-frame validator), `qa.py` (panel-overlap check), `qa2.py` (label-bleed check). Run all three before shipping; rev 2 ships at 0/0/0.

**Why a generator:** revision 1 was hand-built and had overlapping callout dots, chips narrower than their labels, and double-encoded UTF-8. See [[feedback_verify_rendered_visuals_after_build]].
