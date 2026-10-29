#!/usr/bin/env bash
# One-time GitHub setup of the SETI repository (CONTRIBUTING.md). Run it from the repository root, as the repository
# owner, after the first push to main:
#
#   gh auth refresh -s project        # only needed for the Project board (step 8)
#   scripts/github/bootstrap.sh AleQuesada2012/SETI-Management-System
#   DRY_RUN=1 scripts/github/bootstrap.sh AleQuesada2012/SETI-Management-System   # print what it would do
#
# Safe to re-run: existing labels, milestones and issues (matched by title) are kept, and every requirement issue is
# synced to docs/requirements/requirements.json (milestone and assignee). Re-run it after editing the JSON (planning
# changes) and once every teammate has accepted the invitation (assignees can only be set after that).
set -euo pipefail

REPO=${1:?"usage: $0 owner/repo"}
OWNER=${REPO%%/*}
REQUIREMENTS=docs/requirements/requirements.json
DRY_RUN=${DRY_RUN:-0}

run() { if [[ $DRY_RUN == 1 ]]; then printf 'DRY-RUN:'; printf ' %q' "$@"; echo; else "$@"; fi; }
step() { printf '\n\033[1m== %s\033[0m\n' "$1"; }

command -v gh >/dev/null && command -v jq >/dev/null || { echo "Needs the GitHub CLI (gh) and jq."; exit 1; }
[[ -f $REQUIREMENTS ]] || { echo "Run it from the repository root ($REQUIREMENTS not found)."; exit 1; }

step "1. Repository settings: squash merge only, PR title as commit title, delete merged branches"
run gh api -X PATCH "repos/$REPO" --silent \
  -F allow_squash_merge=true -F allow_merge_commit=false -F allow_rebase_merge=false \
  -F delete_branch_on_merge=true -f squash_merge_commit_title=PR_TITLE -f squash_merge_commit_message=PR_BODY

step "2. Collaborators (they receive an invitation by email)"
# Personal-account repositories do not support the organization-only maintain role; all teammates get write access.
for user in Rubiocai DavidZamoraB Dizabu JoseHerrera21; do
  run gh api -X PUT "repos/$REPO/collaborators/$user" -f permission=push --silent
done

step "3. Labels"
label() { run gh label create "$1" --repo "$REPO" --color "$2" --description "$3" --force; }
label "module:security"     "5319e7" "Sign-in, audit trail (RF_00_x)"
label "module:assets"       "1d76db" "Assets module (RF_01–RF_31)"
label "module:spaces"       "0e8a16" "Spaces module (RF_32–RF_45)"
label "module:reservations" "fbca04" "Reservations module (RF_46–RF_59)"
label "module:reports"      "d93f0b" "Reports and dashboards (RF_60–RF_84)"
label "module:users"        "c5def5" "Users and roles (RF_85–RF_101)"
label "module:platform"     "444444" "Shared platform: bootstrap, Docker, layout, CI"
label "module:db"           "6f42c1" "Migrations, seeders, schema"
label "module:docs"         "bfd4f2" "Documentation"
label "type:requirement"    "0052cc" "A functional requirement (RF)"
label "type:bug"            "b60205" "Something does not work"
label "type:task"           "cccccc" "Platform, database, docs or CI work"
label "duplicate"           "cfd3d7" "Closed together with another issue"
for default in bug documentation enhancement "good first issue" "help wanted" invalid question wontfix; do
  run gh label delete "$default" --repo "$REPO" --yes 2>/dev/null || true
done

step "4. Milestones (deliveries are Wednesdays 18:30 Costa Rica = 00:30 UTC Thursday)"
existing_milestones=$(gh api "repos/$REPO/milestones?state=all&per_page=100" --jq '.[].title' 2>/dev/null || true)
milestone() {
  grep -qxF "$1" <<<"$existing_milestones" && { echo "exists: $1"; return; }
  run gh api "repos/$REPO/milestones" --silent -f title="$1" -f due_on="$2" -f description="$3"
}
milestone "Sprint 1" "2026-10-01T00:30:00Z" "Delivery Wed 2026-09-30 18:30. Working base and first screen of every module."
milestone "Sprint 2" "2026-10-15T00:30:00Z" "Delivery Wed 2026-10-14 18:30."
milestone "Sprint 3" "2026-10-29T00:30:00Z" "Delivery Wed 2026-10-28 18:30. Deployment to AWS."

step "5. One issue per requirement (${REQUIREMENTS})"
# code → issue number, one "RF_03 12" pair per line (portable to macOS's bash 3.2: no associative arrays).
numbers=$(mktemp); trap 'rm -f "$numbers"' EXIT
gh issue list --repo "$REPO" --state all --limit 500 --json number,title \
  --jq '.[] | (.title | split(" ")[0]) + " " + (.number | tostring)' > "$numbers" 2>/dev/null || true
number_of() { awk -v c="$1" '$1 == c { print $2; exit }' "$numbers"; }

blob="https://github.com/$REPO/blob/main"
created=0
while IFS= read -r req; do
  code=$(jq -r .code <<<"$req")
  title="$code · $(jq -r .title <<<"$req")"
  owner=$(jq -r .owner <<<"$req")
  module=$(jq -r .module <<<"$req")

  sprint=$(jq -r .sprint <<<"$req")
  milestone_title="Sprint $sprint"
  existing=$(number_of "$code")
  if [[ -n $existing ]]; then
    run gh issue edit "$existing" --repo "$REPO" --milestone "$milestone_title" --add-assignee "$owner" >/dev/null 2>&1 \
      || run gh issue edit "$existing" --repo "$REPO" --milestone "$milestone_title" >/dev/null 2>&1 || true
    continue
  fi

  duplicate_of=$(jq -r '.closes_with // empty' <<<"$req")
  body=$(jq -r --arg blob "$blob" '
    "> " + .description + "\n\n" +
    "### Acceptance criteria\n\n" + ([.criteria[] | "- [ ] " + .] | join("\n")) + "\n\n" +
    "| | |\n|---|---|\n" +
    "| Owner | @" + .owner + " (" + .owner_name + ")" + (if .original_owner then " · moved from @" + .original_owner + " in planning round 3" else "" end) + " |\n" +
    "| Reviewer | @" + .reviewer + " |\n" +
    "| Sprint | " + (if .delivered_in_bootstrap then "Delivered in the bootstrap" else "Sprint " + (.sprint | tostring) end) + " |\n" +
    "| Details | [docs/requirements/" + .module + ".md](" + $blob + "/docs/requirements/" + .module + ".md#" + (.code | ascii_downcase) + ") |\n" +
    "| Module rules | [.ai/rules/" + .module + ".md](" + $blob + "/.ai/rules/" + .module + ".md) |\n" +
    (if .diagram then "| Diagram | [Lucid](" + .diagram + ") |\n" else "" end) +
    (if (.notes | length) > 0 then "\n### Notes\n\n" + ([.notes[] | "- " + .] | join("\n")) + "\n" else "" end) +
    "\nWork it with [.ai/workflow.md](" + $blob + "/.ai/workflow.md). Done when it meets the Definition of Done in [CONTRIBUTING.md](" + $blob + "/CONTRIBUTING.md#definition-of-done)."
  ' <<<"$req")

  args=(--repo "$REPO" --title "$title" --body "$body" --label "module:$module" --label "type:requirement")
  args+=(--milestone "$milestone_title")
  [[ -n $duplicate_of ]] && args+=(--label duplicate)

  if [[ $DRY_RUN == 1 ]]; then
    echo "DRY-RUN: create \"$title\" [module:$module] @$owner"
  else
    url=$(gh issue create "${args[@]}" --assignee "$owner" 2>/dev/null || gh issue create "${args[@]}")
    echo "$code ${url##*/}" >> "$numbers"
    echo "created #${url##*/} $title"
    sleep 1   # stay under GitHub's content-creation rate limit
  fi
  created=$((created + 1))
done < <(jq -c '.[]' "$REQUIREMENTS")
echo "$created new issue(s)."

step "6. Close the requirements delivered by the bootstrap commit"
bootstrap_commit=$(git rev-parse --short origin/main 2>/dev/null || git rev-parse --short HEAD 2>/dev/null || echo "the first commit")
for code in $(jq -r '.[] | select(.delivered_in_bootstrap) | .code' "$REQUIREMENTS"); do
  num=$(number_of "$code")
  if [[ -z $num ]]; then [[ $DRY_RUN == 1 ]] && echo "DRY-RUN: close $code (created above)"; continue; fi
  state=$(gh issue view "$num" --repo "$REPO" --json state --jq .state 2>/dev/null || echo OPEN)
  [[ $state == CLOSED ]] && continue
  run gh issue close "$num" --repo "$REPO" --reason completed \
    --comment "Delivered in the bootstrap ($bootstrap_commit): see docs/PLANNING.md. Tests: tests/Feature/{Auth,Audit,Authorization,Reports}."
done

step "6b. Platform task issues"
task() { # title, milestone, labels, assignees, body
  if grep -q -F -- "$1" <(gh issue list --repo "$REPO" --state all --limit 500 --json title --jq '.[].title' 2>/dev/null); then echo "exists: $1"; return; fi
  run gh issue create --repo "$REPO" --title "$1" --milestone "$2" --label "$3" --label "type:task" --assignee "$4" --body "$5" >/dev/null \
    || run gh issue create --repo "$REPO" --title "$1" --milestone "$2" --label "$3" --label "type:task" --body "$5" >/dev/null
  echo "task: $1"
}
task "AWS deployment of compose.prod.yaml" "Sprint 3" "module:platform" "AleQuesada2012,Rubiocai" \
"Deploy the application to the single AWS VM with \`compose.prod.yaml\` (Caddy + automatic HTTPS, PostgreSQL container, queue, scheduler, Reverb), following its header and \`.env.production.example\`. Set up daily backups of the \`pgsql-data\` and \`storage\` volumes and document restore steps. Done when the Sprint 3 demo runs on the public HTTPS URL. See docs/PLANNING.md."
task "Power BI connectivity test (RF_73)" "Sprint 3" "module:reports" "AleQuesada2012" \
"Half-day test at the start of Sprint 3, after the AWS deployment: can Power BI Service refresh from the VM's PostgreSQL through the read-only \`powerbi_reader\` role? Try a direct cloud connection first (TLS, firewall rules); if Power BI requires it, install an on-premises data gateway on a SETI PC; the safety net is a manually refreshed report. Record the outcome in docs/decisions.md. See docs/PLANNING.md § Power BI."

step "7. Protect main: PR + 1 approval (code owners on platform paths) + green checks + resolved conversations"
protection=$(jq -n '{
  required_status_checks: {strict: false, contexts: ["ci", "secrets", "pr-title"]},
  enforce_admins: true,
  required_pull_request_reviews: {required_approving_review_count: 1, dismiss_stale_reviews: true, require_code_owner_reviews: true},
  required_conversation_resolution: true,
  restrictions: null,
  allow_force_pushes: false,
  allow_deletions: false
}')
if [[ $DRY_RUN == 1 ]]; then echo "DRY-RUN: PUT repos/$REPO/branches/main/protection $protection"; else
  gh api -X PUT "repos/$REPO/branches/main/protection" --input - --silent <<<"$protection"; fi

step "8. Project board"
if gh auth status 2>&1 | grep -q "'project'\|project"; then
  project=$(gh project list --owner "$OWNER" --format json --jq '.projects[] | select(.title == "SETI") | .number' 2>/dev/null || true)
  if [[ -z $project ]]; then
    project=$(run gh project create --owner "$OWNER" --title "SETI" --format json --jq .number)
  fi
  run gh project link "$project" --owner "$OWNER" --repo "$REPO" || true
  for num in $(gh issue list --repo "$REPO" --state open --limit 500 --json number --jq '.[].number' 2>/dev/null); do
    run gh project item-add "$project" --owner "$OWNER" --url "https://github.com/$REPO/issues/$num" >/dev/null
  done
  echo "Board: https://github.com/users/$OWNER/projects/$project (add the Board view with Status columns in the UI)."
else
  echo "Skipped: run 'gh auth refresh -s project' and re-run this script to create the board."
fi

step "Done"
echo "Next: every teammate accepts the invitation, then re-run this script to assign the remaining issues."
