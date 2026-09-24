set -e
for n in "$@"; do
  if [ -s "icons/$n.svg" ]; then echo "have $n"; continue; fi
  curl -sfL "https://cdn.jsdelivr.net/npm/lucide-static@latest/icons/$n.svg" -o "icons/$n.svg" \
    && grep -q "<svg" "icons/$n.svg" && echo "got  $n" || { rm -f "icons/$n.svg"; echo "MISS $n"; }
done
