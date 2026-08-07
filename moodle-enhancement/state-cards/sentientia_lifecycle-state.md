# State Card — `local_sentientia_lifecycle`

**Component:** `local_sentientia_lifecycle`
**Version:** `2026080700` / `1.1.0-beta`
**Maturity:** `MATURITY_BETA`
**Status:** Live observer + compliance-check cron. Joiner auto-enrol
flag-gated (default OFF) as of 2026-08-07.
**Last refreshed:** 2026-08-07 (KeKa JML hardening — ADR-029)

> **Card history:** the 2026-05-24 initial card described this plugin as a
> skeleton; the 2026-05-28 F-092 back-port then prepended the "live
> observer" description WITHOUT deleting the skeleton text, leaving the
> card self-contradictory for ten weeks. This 2026-08-07 rewrite is the
> single coherent source: the plugin is **live**, not a skeleton.

---

## Mission

Employee/course lifecycle automation. Observes user + enrolment events,
auto-enrols joiners into mandatory courses (flag-gated), and runs a daily
compliance-check cron that notifies employees + managers of approaching
mandatory-training deadlines (messages API + optional Teams alert).

## DB tables

None — uses core event + scheduled-task + tag infrastructure.

## Feature flags (`db/feature_flags.php`, added 2026-08-07)

| Flag | Default | Gates |
|---|---|---|
| `sentientia.lifecycle.autoenrol.enabled` | OFF | Joiner auto-enrolment in `observer::user_created` |

## Mandatory-course definition (ADR-029)

Visible course carrying the tag configured in
`local_sentientia_lifecycle/mandatory_tag` (default `mandatory`),
tenant-scoped: a course whose `open_path` roots in a different tenant than
the joiner is never touched; pathless tagged courses are platform-wide.
**The pre-2026-08-07 heuristic (enrol every joiner into every visible
course with a future enddate, platform-wide) is retired unconditionally.**

## Wired surfaces

- `db/events.php` — `\core\event\user_created` → joiner auto-enrol
  (flag-gated); `\core\event\user_updated` → placeholder (department-change
  re-evaluation still unimplemented).
- `db/tasks.php` — `task\compliance_check`, weekdays 07:00.
- `db/messages.php` — `compliance_deadline` message provider.
- `settings.php` (new 2026-08-07) — `mandatory_tag` setting.
- `classes/observer.php` — joiner auto-enrol (rewritten 2026-08-07).
- `classes/task/compliance_check.php` — daily deadline scan
  (still uses `enddate` to define a *deadline*, which is correct for
  notification purposes; enrolment no longer keys off it).
- `classes/privacy/provider.php` — null provider.

## Tests

`tests/observer_test.php` (new 2026-08-07, 5 methods, uses
`bizlms_fixture`): flag-off no-op, tagged-only enrolment, tenant scoping
via `open_path`, custom tag setting, suspended-user skip.

## Open follow-ups

- [ ] `user_updated` department-change re-evaluation (observer placeholder).
- [ ] Compliance-check task: consider aligning its course universe with the
      mandatory-tag definition (today it scans all dated courses).
- [ ] v2 mandatory definition: course custom field (checkbox) once the
      customfield provisioning story is settled — see ADR-029 table.
- [ ] PHPUnit for `task\compliance_check` (still none).
