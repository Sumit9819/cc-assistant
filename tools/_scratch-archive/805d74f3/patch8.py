"""Owner's five notes on the fourth cut: a flow variant for the comparison,
documents shown full width, a spoken outro on the end card."""
import json
from pathlib import Path

ROOT = Path("D:/faceless-studio")

# ---------------------------------------------------------------- 1. compare card: "flow" variant
p = ROOT / "motion/compare_card.html"; s = p.read_text(encoding="utf-8")
old = '''  window.seek = (t) => {
    const p = clamp(t / Math.min(S.duration * 0.8, 4.5));
    ctx.clearRect(0, 0, W, H);
    ctx.fillStyle = "#14120f";
    ctx.fillRect(0, 0, W, H);
    if (S.variant === "ratio") drawRatio(p);
    else if (S.variant === "split") drawSplit(p);
    else drawBars(p);
  };'''
new = '''  function coin(x, y, r, fill, stroke) {
    ctx.beginPath(); ctx.arc(x, y, r, 0, Math.PI * 2);
    ctx.fillStyle = fill; ctx.fill();
    ctx.lineWidth = 4; ctx.strokeStyle = stroke; ctx.stroke();
    ctx.beginPath(); ctx.arc(x, y, r * 0.72, 0, Math.PI * 2);
    ctx.lineWidth = 2; ctx.strokeStyle = stroke; ctx.globalAlpha *= 0.5; ctx.stroke(); ctx.globalAlpha /= 0.5;
  }

  function drawFlow(p) {
    // "Picture four pennies going in, and one cent coming out." Literally:
    // a.value / b.value coins travel into the block, one leaves it. The
    // count is the ratio rounded; the caption keeps the exact figures.
    const times = Math.max(1, Math.round(S.a.value / S.b.value));
    const y = 520, r = 62, gap = 40;
    const blockX = W / 2 - 150, blockW = 300, blockH = 300;
    // The press: a dark block with a slot on each side.
    ctx.fillStyle = "#231f19"; ctx.fillRect(blockX, y - blockH / 2, blockW, blockH);
    ctx.fillStyle = GOLD; ctx.fillRect(blockX, y - blockH / 2, blockW, 6); ctx.fillRect(blockX, y + blockH / 2 - 6, blockW, 6);
    ctx.fillStyle = "#0e0c0a"; ctx.fillRect(blockX - 8, y - r - 14, 16, 2 * r + 28); ctx.fillRect(blockX + blockW - 8, y - r - 14, 16, 2 * r + 28);
    // Coins in: each travels from the left edge to the slot, staggered.
    const inSpan = 0.55;                       // share of the animation for the intake
    const startX = -r, endX = blockX - 10;
    for (let i = 0; i < times; i++) {
      const q = clamp((p - i * (inSpan / (times + 1))) / (inSpan * 0.7));
      if (q <= 0) continue;
      const x = startX + (endX - startX) * easeOut(q);
      const fade = clamp((endX - x) / 40);     // slips into the slot
      ctx.globalAlpha = fade;
      coin(x - i * 0, y - (times - 1) * 0 , r, "#b87333", "#e0a970");
      ctx.globalAlpha = 1;
    }
    // Trail of the coins still waiting, so four are always visible early.
    // Coin out: leaves the right slot after the intake is done.
    const q2 = clamp((p - inSpan - 0.08) / 0.3);
    if (q2 > 0) {
      const x = blockX + blockW + 10 + (W * 0.3) * easeOut(q2);
      ctx.globalAlpha = clamp(q2 * 3);
      coin(x, y, r, "#b87333", "#e0a970");
      ctx.globalAlpha = 1;
    }
    // Captions: exact figures under each side.
    ctx.textAlign = "center";
    ctx.fillStyle = CREAM; ctx.font = '700 64px "Sora"';
    const inA = easeOut((p - 0.05) / 0.3), outA = easeOut((p - inSpan - 0.1) / 0.3);
    ctx.globalAlpha = inA;
    ctx.fillText(`${fmt(S.a.value, places(S.a.value))} ${S.a.unit}`, blockX / 2 + 40, y + r + 130);
    ctx.font = '500 30px "Manrope"'; ctx.fillStyle = "#b7ad9d";
    ctx.fillText(S.a.label, blockX / 2 + 40, y + r + 176);
    ctx.globalAlpha = outA;
    ctx.fillStyle = CREAM; ctx.font = '700 64px "Sora"';
    ctx.fillText(`${fmt(S.b.value, places(S.b.value))} ${S.b.unit}`, blockX + blockW + (W - blockX - blockW) / 2 - 40, y + r + 130);
    ctx.font = '500 30px "Manrope"'; ctx.fillStyle = "#b7ad9d";
    ctx.fillText(S.b.label, blockX + blockW + (W - blockX - blockW) / 2 - 40, y + r + 176);
    ctx.globalAlpha = 1;
  }

  window.seek = (t) => {
    const p = clamp(t / Math.min(S.duration * 0.8, 4.5));
    ctx.clearRect(0, 0, W, H);
    ctx.fillStyle = "#14120f";
    ctx.fillRect(0, 0, W, H);
    if (S.variant === "ratio") drawRatio(p);
    else if (S.variant === "split") drawSplit(p);
    else if (S.variant === "flow") drawFlow(p);
    else drawBars(p);
  };'''
assert old in s; s = s.replace(old, new); p.write_text(s, encoding="utf-8")

c = ROOT / "projects/penny-cost/cards.json"; d = json.loads(c.read_text(encoding="utf-8"))
d["paragraphs"]["10"]["variant"] = "flow"
d["paragraphs"]["10"]["a"]["label"] = "goes in, to make one"
d["paragraphs"]["10"]["b"]["label"] = "comes out"
c.write_text(json.dumps(d, indent=1, ensure_ascii=False), encoding="utf-8")

# ---------------------------------------------------------------- 2. documents: full width, crop above/below only
p = ROOT / "motion/capture_doc.mjs"; s = p.read_text(encoding="utf-8")
old = '''const anchor = rects ? { x: rects[0][0], y: rects[0][1], width: rects[0][2], height: rects[0][3] } : box;
let cx = Math.max(0, Math.min(anchor.x + anchor.width / 2 - CROP_W / 2, vw - CROP_W));
let cy = Math.max(0, Math.min(anchor.y - CROP_H * 0.42, vh - CROP_H));
const clip = { x: cx, y: cy, width: Math.min(CROP_W, vw - cx), height: Math.min(CROP_H, vh - cy) };'''
new = '''const anchor = rects ? { x: rects[0][0], y: rects[0][1], width: rects[0][2], height: rects[0][3] } : box;
// The crop is the CONTENT COLUMN, edge to edge, so the scene never has to
// cut a line of text off at the side; only above and below is trimmed.
// The column is the quoted element's box plus a small gutter.
const gutter = 36;
const colX = Math.max(0, box.x - gutter), colW = Math.min(vw - colX, box.width + 2 * gutter);
let cy = Math.max(0, Math.min(anchor.y - CROP_H * 0.42, vh - CROP_H));
const clip = { x: colX, y: cy, width: colW, height: Math.min(CROP_H, vh - cy) };'''
assert old in s; s = s.replace(old, new); p.write_text(s, encoding="utf-8")

p = ROOT / "motion/document_scene.html"; s = p.read_text(encoding="utf-8")
old = '''    // Zoom past "fit": the reader should see the quoted lines large, with
    // the page's own surroundings around them, not the whole capture.
    base = SHEET_W / (S.width * 0.72);'''
new = '''    // Fit the capture's full width: nothing is cut off at either side
    // (owner, 2026-09-04). The capture is already the content column, so
    // this IS the zoom; only above and below is trimmed.
    base = SHEET_W / S.width;'''
assert old in s; s = s.replace(old, new)
old = '''    // Push: 1.00 -> 1.06 over the whole shot, about the quoted line.
    const push = 1 + 0.06 * (t / Math.max(S.duration, 0.1));
    const s = base * push;
    // Keep the quote's screen position fixed while scaling.
    const tx = ox * push + (cx * base) * (1 - push);
    const ty = oy * push + (cy * base) * (1 - push);
    $("page").style.transform = `translate(${tx}px, ${ty}px) scale(${s})`;'''
new = '''    // Motion is a slow vertical drift (28px over the shot), never a zoom:
    // a zoom would crop the sides, and the width must stay whole.
    const drift = -28 * (t / Math.max(S.duration, 0.1));
    const maxUp = Math.min(0, SHEET_H - S.height * base);
    const ty = Math.max(maxUp, oy + drift);
    $("page").style.transform = `translate(0px, ${ty}px) scale(${base})`;'''
assert old in s; s = s.replace(old, new)
s = s.replace('''    ox = Math.min(0, Math.max(SHEET_W / 2 - cx * base, SHEET_W - S.width * base));
''', '''    ox = 0;
''')
p.write_text(s, encoding="utf-8")

# ---------------------------------------------------------------- 3. spoken outro on the end card
sc = ROOT / "projects/penny-cost/script.md"; t = sc.read_text(encoding="utf-8")
if "# Outro" not in t:
    t = t.rstrip() + "\n\n# Outro\n\nThanks for watching. If you want more of what a rule actually does, subscribe, and the next one will find you.\n"
    sc.write_text(t, encoding="utf-8")
b = ROOT / "fvs/bookends.py"; u = b.read_text(encoding="utf-8")
u = u.replace("OUTRO_SECONDS = 12.0", "OUTRO_SECONDS = 8.0   # the outro is now SPOKEN over the end card; the silent tail can be shorter")
b.write_text(u, encoding="utf-8")
sp = ROOT / "projects/penny-cost/sceneplan.json"; plan = json.loads(sp.read_text(encoding="utf-8"))
plan["paragraphs"]["21"] = {"template": "end_card", "note": "Thanks for watching."}
sp.write_text(json.dumps(plan, indent=1, ensure_ascii=False), encoding="utf-8")
print("patched: compare flow, document full-width, outro")
