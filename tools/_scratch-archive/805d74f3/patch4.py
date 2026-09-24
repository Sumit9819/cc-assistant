from pathlib import Path
p = Path("D:/faceless-studio/tools/study_video.py"); s = p.read_text(encoding="utf-8")
i = s.index('             "-vf", f"scale=320:-2,drawbox')
j = s.index('\n', s.index('drawtext=text=', i))
s = s[:i] + '''             # No drawtext: this ffmpeg build segfaults without a fontconfig
             # file. Frames are in cut order; times are in the JSON.
             "-vf", "scale=320:-2",''' + s[j:]
old = '''    # vtt: cue starts only
    starts = []
    for m in re.finditer(r"(\\d\\d):(\\d\\d):(\\d\\d)\\.(\\d\\d\\d) -->", subs.read_text(encoding="utf-8")):'''
assert old in s, "vtt block"
s = s.replace(old, '''    # vtt / srt: cue starts only
    starts = []
    for m in re.finditer(r"(\\d\\d):(\\d\\d):(\\d\\d)[.,](\\d\\d\\d) -->", subs.read_text(encoding="utf-8")):''')
s = s.replace('''    sheet(video, [0.0] + picks, out_dir / f"{stem}.cuts.png")''',
              '''    sheet(video, [0.0] + picks, out_dir / f"{stem}.cuts.png")
    (out_dir / f"{stem}.cuts.json").write_text(json.dumps([round(c, 2) for c in cuts]), encoding="utf-8")''')
# threshold from the environment so it can be calibrated
s = s.replace('SCDET_THRESHOLD = 10.0', 'import os\nSCDET_THRESHOLD = float(os.environ.get("SCDET", "10.0"))')
p.write_text(s, encoding="utf-8")
print("patched study_video.py")
