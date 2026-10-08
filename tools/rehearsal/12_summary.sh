#!/usr/bin/env bash
# 12 summary -- one report for Nitin: what ran, how long it took, what was proven, what is still a person's job.
#
# Runbook: MIGRATION-REHEARSAL-RUNBOOK.md step 7 ("Report to Nitin: parity output + smoke results + any deviations");
# migration plan 9 (the rollout gate: a rehearsal unlocks the real window only if it produces ALL seven things) and 10
# (I-4: the hard-down window is restore + both hops + repairs + import, measured here).
#
# Reads only what the other steps wrote (state/, logs/timings.tsv, state/kv/); it changes nothing else and runs even when an
# earlier step failed (run_all.sh calls it last), so there is always a report. Writes reports/summary.md.
# The seven gates of the plan are listed with who proves each: the kit proves some; the others are people's work
# (per-user fingerprint, a real known-password login, the SCORM and certificate walk, the mail sender test).

# shellcheck source=lib/common.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib/common.sh"
step_init 12 summary "$@"

if [ "$EXECUTE" != 1 ]; then
    dry "would read state/*.status, state/kv/* and logs/timings.tsv and write reports/summary.md: the steps and their seconds, the I-4 window estimate, the parity checkpoints, the evidence hashes (baseline, package, decisions), what was accepted as unproven, and the seven rollout-gate items with who proves each"
    exit 0
fi

OUT="$REPORT_DIR/summary.md"
steps="00 01 02 03 04 05 06 07 08 09 10 11"
allok=1
step_label() {
    case "$1" in
        00) printf "preflight" ;; 01) printf "restore check" ;; 02) printf "source baseline" ;; 03) printf "hop 1 to 4.5" ;;
        04) printf "hop 2 to 5.x" ;; 05) printf "repairs" ;; 06) printf "ADR-031 roles" ;; 07) printf "theme switch" ;;
        08) printf "import guard" ;; 09) printf "import" ;; 10) printf "parity compare" ;; 11) printf "cron cycle" ;;
    esac
}

status_of() {
    local f="$STATE_DIR/$1.status"
    if [ -f "$f" ]; then
        sed -n 's/^status=//p' "$f"
    else
        printf 'not run'
    fi
}
field_of() {
    local f="$STATE_DIR/$1.status"
    if [ -f "$f" ]; then
        sed -n "s/^$2=//p" "$f"
    fi
}
secs_for() {
    # secs_for LABEL-REGEX -> the sum of seconds of the timed operations whose label matches.
    if [ -f "$TIMINGS_FILE" ]; then
        awk -F '\t' -v re="$1" '$3 ~ re { s += $4 } END { print s + 0 }' "$TIMINGS_FILE"
    else
        printf 0
    fi
}
mins() { awk -v s="$1" 'BEGIN { if (s + 0 == 0) { printf "-" } else { printf "%d min %02d s", s / 60, s % 60 } }'; }
kv() { local v; v="$(kv_get "$1")"; printf '%s' "${v:-(none)}"; }
verdict() {
    # verdict EXITCODE -> text
    case "$1" in
        0) printf 'exit 0, 100%% parity' ;;
        2) printf 'exit 2, NOT PROVEN' ;;
        '') printf 'not run' ;;
        *) printf 'exit %s' "$1" ;;
    esac
}

{
    printf '# Stage B rehearsal summary\n\n'
    printf -- '- Generated: %s UTC\n' "$(ts)"
    printf -- '- Kit commit: %s\n' "$(kit_rev)"
    printf -- '- Database: %s on %s (allow-listed); wwwroot %s\n' "$DB_NAME" "$DB_HOST" "$REHEARSAL_WWWROOT"
    printf -- '- Releases: source %s, after hop 1 %s, after hop 2 %s\n' "$(kv release.source)" "$(kv release.hop1)" "$(kv release.hop2)"
    printf -- '- Engine: %s\n\n' "$(kv engine.version)"

    printf '## Steps\n\n| Step | Name | Status | Seconds | Warnings |\n|---|---|---|---|---|\n'
    for s in $steps; do
        st="$(status_of "$s")"
        [ "$st" = ok ] || allok=0
        printf '| %s | %s | %s | %s | %s |\n' "$s" "$(step_label "$s")" "$st" "$(field_of "$s" seconds)" "$(field_of "$s" warnings)"
    done

    printf '\n## Timings (the maintenance window is sized from these: migration plan I-4)\n\n'
    restore="$(secs_for '^(create database|restore database|restore moodledata)$')"
    hop1="$(secs_for '^HOP 1')"
    hop2="$(secs_for '^HOP 2')"
    baseline_take="$(secs_for '^take baseline$')"
    # Every timed operation of the steps that fall inside the hard-down window, not only the headline ones: the parity compares after
    # each hop, the repairs, the role scripts, the data-intact gate, the preflight, the dry run, the apply, the verify and the
    # parity after the import all run while the site is down.
    steps_secs() { awk -F '\t' -v re="$1" '$2 ~ re { s += $4 } END { print s + 0 }' "$TIMINGS_FILE" 2> /dev/null || printf 0; }
    window_steps="$(steps_secs '^(03|04|05|06|07|08|09|10)$')"
    repairs="$(steps_secs '^05$')"
    imp_all="$(steps_secs '^(09|10)$')"
    imp="$(secs_for '^import (apply|resume)$')"
    cron="$(secs_for '^CRON cycle')"
    tq="$(kv cron.transfer_question_categories_seconds)"
    printf '| What | Time |\n|---|---|\n'
    printf '| Restore (database and moodledata, if the kit did it) | %s |\n' "$(mins "$restore")"
    printf '| Baseline taken on the restored copy (step 02; on cutover day it is taken on live at the freeze) | %s |\n' "$(mins "$baseline_take")"
    printf '| Hop 1, 4.1.x to 4.5 (the upgrade only) | %s |\n' "$(mins "$hop1")"
    printf '| Hop 2, 4.5 to 5.x (the upgrade only) | %s |\n' "$(mins "$hop2")"
    printf '| Repairs (step 05, all commands) | %s |\n' "$(mins "$repairs")"
    printf '| Import, gate to parity (steps 09 and 10: gate, preflight, dry run, apply, verify, parity after) | %s |\n' "$(mins "$imp_all")"
    printf '|   of which the apply itself | %s |\n' "$(mins "$imp")"
    printf '| **Hard-down estimate (restore + baseline + every timed operation of steps 03 to 10)** | **%s** |\n' "$(mins $((restore + baseline_take + window_steps)))"
    printf '| One cron cycle over the restored backlog (step 11) | %s |\n' "$(mins "$cron")"
    printf '| transfer_question_categories task (seconds, from the task log) | %s |\n' "$tq"
    printf '\nThe estimate leaves out the freeze, the backup and the DNS steps, which are IT'"'"'s, and the untimed commands between the timed ones (a few seconds each). Compare it with the window agreed (I-5).\n'
    printf '\nEvery timed operation: `logs/timings.tsv`. It adds up every run of a step in this rehearsal (a step that was run twice counts twice; a new restore starts a new file). Restore the database from a dump and run from step 01 for one clean number.\n'

    printf '\n## Parity checkpoints (all against the one source baseline)\n\n| Checkpoint | Result |\n|---|---|\n'
    printf '| Restored copy vs the live baseline (restore loss) | %s |\n' "$(verdict "$(kv_get parity.restore_vs_live)")"
    printf '| Baseline vs the database it was taken from | %s |\n' "$(verdict "$(kv_get parity.source_selfcheck)")"
    printf '| After hop 1 (4.5, no Sentientia plugin) | %s |\n' "$(verdict "$(kv_get parity.after_hop1)")"
    hop2note="$(kv_get parity.after_hop2.note)"
    printf '| After hop 2 (5.x) | %s%s |\n' "$(verdict "$(kv_get parity.after_hop2)")" "${hop2note:+ ($hop2note)}"
    printf '| Before the import (data intact gate) | %s |\n' "$(verdict "$(kv_get parity.pre_import)")"
    printf '| After the import (`--after-import`) | %s |\n' "$(verdict "$(kv_get parity.after_import)")"
    printf '| After one cron cycle (informational; **not expected to be exit 0**, see below) | %s |\n' "$(verdict "$(kv_get parity.after_cron)")"
    printf '\nOutputs: `reports/parity-*.txt`.\n'
    printf '\n**How to read these rows.**\n\n'
    printf -- '- *After one cron cycle* differs from the baseline by design: the 5.0 upgrade queued `\\mod_qbank\\task\\transfer_question_categories`, which on its first run creates qbank activities (and, for a category or system bank, a container course), so `course_modules`, `course_sections` and perhaps `course` change. The data-intact gate is the row **After the import**, which runs BEFORE the first cron; on cutover day it must run before cron is enabled on the target.\n'
    printf -- '- *After hop 2* reads exit 0 even when the only failed line was the `message_provider_defaults` invariant (step 05 repairs it). In that case the compare stopped at the failure and printed no UNPROVEN (exit 2) items, so this row can look cleaner than its evidence; the gate **Before the import** (step 09) judges everything again, after the repairs, and is the row that counts.\n'

    printf '\n## Evidence to keep with the change ticket\n\n'
    printf -- '- Baseline: `%s`, SHA-256 `%s`, release `%s`\n' "$BASELINE_FILE" "$(kv baseline.sha256)" "$(kv baseline.release)"
    printf -- '- Baseline tool SHA-256: `%s` (carriage returns removed: `%s`). The baseline names the file that took it (`tool.sha256`): `%s`; the package'"'"'s copy, the same way: `%s`. All three must be the same file, and the tool refuses a comparison across two files\n' \
        "$(kv baseline.tool_sha256)" "$(kv baseline.tool_sha256_lf)" "$(kv baseline.tool_sha256_in_baseline)" "$(kv package.baseline_tool_sha256_lf)"
    printf -- '- Activities the Moodle 5.0 upgrade deletes unless the package carries a 5.x module (counted in the restored copy before any hop): mod_survey %s, mod_chat %s. Check before hop 2: %s\n' \
        "$(kv restore.activities_survey)" "$(kv restore.activities_chat)" "$(kv hop2.activities_lost)"
    printf -- '- 5.x package archive SHA-256: `%s` (release %s). The tree that really ran (manifest of every version.php): 4.5 `%s`, 5.x `%s`\n' \
        "$(kv package.5x.sha256)" "$(kv release.hop2_code)" "$(kv tree.45.manifest_sha)" "$(kv tree.5x.manifest_sha)"
    printf -- '- Restore: id `%s` (restored by hand: %s); the database and the moodledata carry it, and every writing step checked it\n' \
        "$(kv restore.id | cut -c1-8)" "$(kv restore.by_hand)"
    printf -- '- Capability allow-list SHA-256: `%s`; granted by the repair: %s\n' "$(kv caps.allowlist_sha256)" "$(kv caps.granted)"
    printf -- '- Import decisions file SHA-256: `%s`\n' "$(kv import.decisions_file_sha256)"
    printf -- '- **Decisions hash for cutover (`--expect-decisions-hash`): `%s`**\n' "$(kv import.decisions_hash)"
    printf -- '- Import apply run: %s; install fingerprint (`--confirm`): `%s`\n' "$(kv import.runid)" "$(kv import.fingerprint)"
    printf -- '- File store: %s distinct content hashes in the database, %s files on disk, %s missing, %s extra\n' \
        "$(kv filedir.db_hashes)" "$(kv filedir.disk_files)" "$(kv filedir.missing)" "$(kv filedir.extra)"
    printf -- '- Plugins missing from disk after hop 1: %s; Sentientia local plugins installed after hop 2: %s\n' \
        "$(kv hop1.missing_plugins)" "$(kv hop2.sentientia_plugins)"
    printf -- '- Cron cycle: %s; mails noemailever swallowed, as far as the cron output shows (best effort: a 0 can mean not captured; noemailever = 1 for the whole cycle is the proof): %s; scheduled tasks that phone home switched off for it: %s; failed tasks: %s; checks.php exit: %s\n' \
        "$(kv cron.cycle_line)" "$(kv cron.blocked_mails)" "$(kv cron.outbound_tasks_disabled)" "$(kv cron.failed_tasks)" "$(kv checks.exit)"
    printf -- '- Landing posture: %s; site theme: %s\n' "$(kv posture.landing)" "$(kv theme.site)"
    printf -- '- ADR-031 web-service smoke: %s\n' "$(kv adr031.ws_smoke)"

    printf '\n## Unproven items accepted for this run\n\n'
    found=0
    if [ -d "$STATE_DIR/kv" ]; then
        for f in "$STATE_DIR"/kv/accepted.*; do
            [ -f "$f" ] || continue
            printf -- '- %s: accepted by `%s`\n' "$(basename "$f" | sed 's/^accepted\.//')" "$(cat "$f")"
            found=1
        done
    fi
    [ "$found" = 1 ] || printf 'None: every exit 2 stopped the run.\n'
    # The tables the baseline does not name (legacy_other): a difference there is exit 2, not 1, so an acceptance can let a LOST ROW
    # through. Each one is listed by name so that the written acceptance is specific; payment tables first (a payment log row is
    # evidence nobody can recreate).
    others="$(cat "$REPORT_DIR"/parity-after-hop2.txt "$REPORT_DIR"/parity-before-import.txt "$REPORT_DIR"/parity-after-import.txt 2> /dev/null \
        | grep -o 'other_table_[a-z_]*:[A-Za-z0-9_]*' | LC_ALL=C sort -u || true)"
    if [ -n "$others" ]; then
        printf '\nTables that no BizLMS inventory names (`legacy_other`; a changed, missing or unchecked one is exit 2, so an acceptance of exit 2 lets it through). By name, payment tables first:\n\n'
        pay="$(printf '%s\n' "$others" | grep -E 'paygw_|payment' || true)"
        rest="$(printf '%s\n' "$others" | grep -Ev 'paygw_|payment' || true)"
        if [ -n "$pay" ]; then
            printf '%s\n' "$pay" | sed 's/^/- PAYMENT: `/; s/$/`/'
        fi
        if [ -n "$rest" ]; then
            printf '%s\n' "$rest" | sed 's/^/- `/; s/$/`/'
        fi
    fi

    printf '\n## The rollout gate (migration plan 9): all seven, or the rehearsal repeats\n\n| # | Gate | Who proves it | State |\n|---|---|---|---|\n'
    p_import="$(kv_get parity.after_import)"
    p_pre="$(kv_get parity.pre_import)"
    if [ "$p_import" = 0 ] && [ "$p_pre" = 0 ]; then
        if [ "$(kv_get parity.restore_vs_live)" = 0 ]; then
            g1="MET by the kit (restore loss isolated against live's own baseline)"
        else
            g1="PARTLY MET: the upgrades and the import are held to a baseline taken on the RESTORED copy (no LIVE_BASELINE_FILE), so loss in the restore itself is not isolated"
        fi
    else
        g1="NOT MET (see the parity table)"
    fi
    if [ "$(status_of 11)" = ok ] && [ -n "$(kv_get cron.blocked_mails)" ]; then g5="evidence recorded by the kit; Nitin judges it"; else g5="NOT MET (step 11 not ok)"; fi
    if [ "$(status_of 04)" = ok ] && [ "$(status_of 03)" = ok ]; then g7="measured by the kit; IT compares it with the window (I-5)"; else g7="NOT MET"; fi
    printf '| 1 | 100%% parity across counts and value checksums (every tenant bucket, users_tenant_other included, equal to the baseline) before and after the import | the kit | %s |\n' "$g1"
    printf '| 2 | A byte-identical per-user fingerprint diff (5 to 10 named users, one a re-completion user; plan 5.2) | a person, with the SQL of the plan | NOT IN THE KIT |\n'
    printf '| 3 | An interactive login with a real, known password, one account per tenant (plan 5.3, I-19) | a person | NOT IN THE KIT |\n'
    printf '| 4 | A clean SCORM and certificate walk landing on /my (runbook step 6; a file-backed SCORM activity must return 200) | a person | NOT IN THE KIT |\n'
    printf '| 5 | A drained-to-zero mail backlog and zero real-address sends across a full cron cycle under noemailever | the kit records it | %s |\n' "$g5"
    printf '| 6 | A proven mail sender (one test send; noemailever stays 1 in this kit) | a person (plan 8 step 1) | NOT IN THE KIT |\n'
    printf '| 7 | A measured core-upgrade duration that fits the maintenance window | the kit measures | %s |\n' "$g7"

    printf '\n## Left for people (the kit does not do these)\n\n'
    printf -- '- Per-user fingerprint, known-password logins, the SCORM and certificate walk (runbook step 6), the mail sender test.\n'
    printf -- '- Lift CLI maintenance for the walk if step 11 put it back on: `php admin/cli/maintenance.php --disable`.\n'
    printf -- '- Verify by hand that `paygw_airpay` is DISABLED (Site administration > Plugins > Payment gateways) and that Notifications shows no plugin requiring attention.\n'
    printf -- '- Runbook 4d (SW-1, `enable_oneclick_enrol.php`): a feature-flag decision of Nitin'"'"'s; the kit never flips a flag.\n'
    printf -- '- Who holds `local/sentientia_platform:crosstenant`: the platform role is created empty (step 06); Nitin names the holders.\n'
    printf -- '- Never uninstall a plugin that is missing from disk: it drops its tables, the archive the import reads.\n'
    printf -- '- This box has a restored copy of the live data: when the rehearsal is over, destroy it (database, moodledata, work directory).\n'

    printf '\n## Verdict of the kit\n\n'
    if [ "$allok" = 1 ] && [ "$p_import" = 0 ] && [ "$(kv_get parity.restore_vs_live)" != 0 ]; then
        printf 'Steps 00 to 11 finished ok and the parity after the import is exit 0, against a baseline taken on the restored copy: the upgrades and the import lost nothing, but loss in the restore itself is not isolated (gate 1 is partly met; run again with LIVE_BASELINE_FILE for the strong form). Gates 2, 3, 4 and 6 are still to be done by people.\n'
    elif [ "$allok" = 1 ] && [ "$p_import" = 0 ]; then
        printf 'Steps 00 to 11 finished ok and the parity after the import is exit 0. The kit'"'"'s part of the rollout gate is met; gates 2, 3, 4 and 6 are still to be done by people.\n'
    elif [ "$allok" = 1 ]; then
        printf 'Steps 00 to 11 finished ok, but the parity after the import is %s: read the parity table and the unproven list.\n' "$(verdict "$p_import")"
    else
        printf 'NOT COMPLETE: one or more steps did not finish ok (table above). Fix the first failed step and re-run from it: `bash tools/rehearsal/run_all.sh --execute --from NN`.\n'
    fi
} > "$OUT"

cat "$OUT"
log "summary written to ${OUT}"
