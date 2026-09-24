"""Map each Font Awesome codepoint used on sids-ponds to its icon name, read
straight from the 6.4.2 stylesheet the site loads. The preview showed \\f429
rendering as the Stripe wordmark, not Apple Pay, so the mapping needs to come
from the CSS rather than from memory."""
import io
import re

SP = (r"C:/Users/sumit/AppData/Local/Temp/claude/"
      r"c--Users-sumit-Local-Sites-plugintesting-app-public/"
      r"32bbd000-c160-41d2-824f-454b206fe3ed/scratchpad")

css = io.open(SP + "/fa.css", encoding="utf-8", errors="ignore").read()

for code in ["f429", "f1f0", "f1f1", "f1f3", "e07b", "f48b", "f004"]:
    pattern = r'([^{}]+)\{content:"\\' + code + r'"\}'
    names = []
    for sel in re.findall(pattern, css):
        names += re.findall(r"\.fa-([a-z0-9-]+):before", sel)
    print("  \\{} -> {}".format(code, sorted(set(names)) or "NOT FOUND"))
