#!/usr/bin/env bash
# At a sprint delivery: move every open issue of a milestone to the next one (docs/PLANNING.md § Carry-over), and
# print the list for the sprint report.
#
#   scripts/github/carry-over.sh AleQuesada2012/SETI-Management-System "Sprint 1" "Sprint 2"
set -euo pipefail

REPO=${1:?"usage: $0 owner/repo <from milestone> <to milestone>"}
FROM=${2:?"from milestone"}
TO=${3:?"to milestone"}

issues=$(gh issue list --repo "$REPO" --milestone "$FROM" --state open --limit 500 --json number,title --jq '.[] | "\(.number)\t\(.title)"')
[[ -z $issues ]] && { echo "No open issues in $FROM."; exit 0; }

echo "Carried over from $FROM to $TO:"
while IFS=$'\t' read -r number title; do
  gh issue edit "$number" --repo "$REPO" --milestone "$TO" >/dev/null
  gh issue comment "$number" --repo "$REPO" --body "Carried over from $FROM to $TO: first priority of $TO (docs/PLANNING.md)." >/dev/null
  echo "- #$number $title"
done <<<"$issues"
