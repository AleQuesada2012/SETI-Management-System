#!/usr/bin/env bash
# Prints everything a chat assistant without repository access (ChatGPT, Claude.ai, Gemini web) needs to help with one
# requirement: the agent instructions, the workflow, the rules of its module, the requirement itself and the glossary.
#
#   scripts/ai/context-pack.sh RF_03 > rf03-context.md          # then upload or paste the file
#   scripts/ai/context-pack.sh RF_03 --issue 12 > context.md    # also include the live GitHub issue (needs gh)
set -euo pipefail

code=${1:?"usage: $0 RF_xx [--issue <number>]"}
issue=""
[[ ${2:-} == "--issue" ]] && issue=${3:?"--issue needs a number"}

root=$(cd "$(dirname "$0")/../.." && pwd)
cd "$root"

command -v jq >/dev/null || { echo "Needs jq." >&2; exit 1; }
module=$(jq -r --arg c "$code" '.[] | select(.code == $c) | .module' docs/requirements/requirements.json)
[[ -n $module ]] || { echo "Unknown requirement $code (see docs/requirements/README.md)." >&2; exit 1; }

section() { printf '\n\n---\n\n<!-- %s -->\n\n' "$1"; cat "$1"; }
strip_frontmatter() { awk 'NR==1 && /^---$/ {fm=1; next} fm && /^---$/ {fm=0; next} !fm' "$1"; }

cat <<EOF
# Context pack: $code (module: $module)

You are helping a student of the SETI project work on requirement $code. The repository's rules follow; apply them
exactly. The person you talk to runs the commands and is the author of the result. Answer in English for code and
commit messages, in Spanish for user-facing text.
EOF

section AGENTS.md
section .ai/workflow.md
for rule in all "$module" frontend testing database; do
  printf '\n\n---\n\n<!-- .ai/rules/%s.md -->\n\n' "$rule"
  strip_frontmatter ".ai/rules/$rule.md"
done

printf '\n\n---\n\n<!-- docs/requirements/%s.md#%s -->\n\n' "$module" "$(tr '[:upper:]' '[:lower:]' <<<"$code")"
awk -v h="## $code" '$0 == h {p=1; print; next} p && /^## / {exit} p' "docs/requirements/$module.md"

if [[ -n $issue ]]; then
  printf '\n\n---\n\n<!-- GitHub issue #%s -->\n\n' "$issue"
  gh issue view "$issue" --json title,body,labels,assignees,milestone \
    --jq '"# " + .title + "\n\nMilestone: " + (.milestone.title // "none") + " · Assignees: " + ([.assignees[].login] | join(", ")) + "\n\n" + .body'
fi

section CONTEXT.md
printf '\n'
