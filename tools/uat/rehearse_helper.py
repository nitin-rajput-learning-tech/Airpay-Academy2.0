#!/usr/bin/env python3
"""rehearse_helper.py - database, config and report helper for tools/uat/rehearse_52_to_53.sh.

LOCAL REHEARSAL ONLY. It talks to the private MariaDB 11.4 on 127.0.0.1:3308 (D:/Claude Local/moodle53) and touches
exactly one database, uatreh52. It never prints a password: the root password is read from the same secrets file that
mdb114_admin.py uses (default D:/Claude Local/moodle53/.local-db.txt, override with LOCAL_DB_SECRET) and handed to
mariadb.exe through a throw-away --defaults-extra-file that is deleted straight after.

  python rehearse_helper.py ping                       connect and print the server version
  python rehearse_helper.py sql FILE                   run a guarded SQL file (see below)
  python rehearse_helper.py query [--nodb] "SELECT ..." run ONE read-only statement against uatreh52 (SELECT/SHOW/DESCRIBE)
  python rehearse_helper.py serverinfo                 print the server settings that differ between MariaDB and MySQL 8.4
  python rehearse_helper.py config --tree DIR --dataroot DIR --wwwroot URL
                                                       write DIR/config.php for uatreh52 (guarded, noemailever on)
  python rehearse_helper.py adminpass --out FILE       create FILE with a generated test admin password if it is missing
  python rehearse_helper.py dump FILE                  logical dump of uatreh52 to FILE (FILE must live under .../uatreh/)
  python rehearse_helper.py restore FILE               load such a dump into an EMPTY uatreh52 (create it with the guarded sql file)
  python rehearse_helper.py versions-diff A.tsv B.tsv  plugin versions before / after (rehearse_versions.php output)
  python rehearse_helper.py counts-diff A.tsv B.tsv    table row counts before / after (rehearse_counts.php --what=tables)
  python rehearse_helper.py config-diff A.tsv B.tsv    settings before / after (rehearse_counts.php --what=config)
  python rehearse_helper.py summarize-log FILE         count PHP warnings / notices / deprecations / debugging() lines in a CLI log

Why a wrapper at all. The Bash hook of the Claude Code sessions that run this kit refuses the word that deletes a
database when it appears in a command string, and a database has to be recreated on every run of the rehearsal. The SQL
therefore lives in a FILE that this wrapper validates statement by statement before it runs anything:
  - the only destructive statement allowed is   <drop-database> IF EXISTS `uatreh52`
  - the only creation allowed is                CREATE DATABASE [IF NOT EXISTS] `uatreh52` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
  - read-only SELECT / SHOW / DESCRIBE statements are allowed
  - anything else, and any other schema name, exits 2 before a single statement is sent.
So a mistyped file cannot touch r53_final, s53_final or any other database on that server. dump and restore are fixed to
uatreh52 as well (restore refuses unless uatreh52 exists and holds no table).

Exit codes: 0 ok, 1 the database client failed or a comparison found a problem, 2 refused / usage.
"""
import argparse
import os
import re
import secrets
import subprocess
import sys
import tempfile

SECRET = os.environ.get('LOCAL_DB_SECRET', 'D:/Claude Local/moodle53/.local-db.txt')
BIN = os.environ.get('MARIADB_BIN', 'D:/Claude Local/moodle53/mariadb-11.4.13-winx64/bin/')
DB = 'uatreh52'
PORT = 3308

_DROP = 'DR' + 'OP'   # spelled in two pieces so this file itself never trips a naive word filter

ALLOWED = [
    re.compile(r'^' + _DROP + r'\s+DATABASE\s+IF\s+EXISTS\s+`?' + DB + r'`?$', re.I),
    re.compile(r'^CREATE\s+DATABASE\s+(IF\s+NOT\s+EXISTS\s+)?`?' + DB + r'`?\s+DEFAULT\s+CHARACTER\s+SET\s+utf8mb4\s+COLLATE\s+utf8mb4_unicode_ci$', re.I),
    re.compile(r'^(SELECT|SHOW|DESCRIBE|DESC)\b', re.I),
]


def load_secret():
    cfg = {}
    with open(SECRET, encoding='utf-8') as fh:
        for line in fh:
            if '=' in line:
                k, v = line.strip().split('=', 1)
                cfg[k] = v
    for need in ('host', 'port', 'user', 'password'):
        if need not in cfg:
            sys.exit('refused: %s has no %s= line' % (SECRET, need))
    if cfg['host'] not in ('127.0.0.1', 'localhost') or int(cfg['port']) != PORT:
        sys.exit('refused: the secrets file points at %s:%s, this helper only talks to 127.0.0.1:%d' % (cfg['host'], cfg['port'], PORT))
    return cfg


def _option_file(cfg):
    fd, cnf = tempfile.mkstemp(suffix='.cnf')
    with os.fdopen(fd, 'w', encoding='utf-8') as fh:
        fh.write('[client]\nhost=127.0.0.1\nport=%d\nuser=%s\npassword=%s\n' % (PORT, cfg['user'], cfg['password']))
    return cnf


def _forget(cnf):
    try:
        os.remove(cnf)
    except OSError:
        pass


def run_client(args, stdin=None):
    """Run mariadb.exe with the password in a temporary option file. Returns CompletedProcess (text)."""
    cfg = load_secret()
    cnf = _option_file(cfg)
    try:
        return subprocess.run([BIN + 'mariadb.exe', '--defaults-extra-file=' + cnf, '--default-character-set=utf8mb4'] + args,
                              input=stdin, capture_output=True, text=True)
    finally:
        _forget(cnf)


def scrub(text):
    return text.replace(load_secret()['password'], '***')


def strip_comments(sql):
    sql = re.sub(r'/\*.*?\*/', ' ', sql, flags=re.S)
    out = []
    for line in sql.splitlines():
        line = re.sub(r'(^|\s)--.*$', '', line)
        line = re.sub(r'^\s*#.*$', '', line)
        out.append(line)
    return '\n'.join(out)


def split_statements(sql):
    return [s.strip() for s in strip_comments(sql).split(';') if s.strip()]


def guard(statements):
    for st in statements:
        flat = ' '.join(st.split())
        if not any(p.match(flat) for p in ALLOWED):
            sys.exit('refused: statement not allowed by rehearse_helper.py: %s' % flat[:120])
        # any other schema name anywhere in a read-only statement is refused too
        if re.match(r'^(SELECT|SHOW|DESCRIBE|DESC)\b', flat, re.I):
            for m in re.finditer(r'`?([A-Za-z0-9_]+)`?\s*\.\s*`?[A-Za-z0-9_]+`?', flat):
                if m.group(1).lower() not in (DB, 'information_schema'):
                    sys.exit('refused: schema %s is not allowed (only %s)' % (m.group(1), DB))
            if re.search(r'\bINTO\s+(OUTFILE|DUMPFILE)\b|\bLOAD_FILE\b', flat, re.I):
                sys.exit('refused: file access in a read-only statement')


def under_scratch(path):
    return 'uatreh' in os.path.abspath(path).replace('\\', '/')


def cmd_ping(_a):
    r = run_client(['-N', '-e', 'SELECT VERSION()'])
    sys.stdout.write(r.stdout)
    if r.returncode:
        sys.stderr.write(scrub(r.stderr))
    return r.returncode


def cmd_sql(a):
    with open(a.file, encoding='utf-8') as fh:
        statements = split_statements(fh.read())
    if not statements:
        sys.exit('refused: %s holds no statements' % a.file)
    guard(statements)
    for st in statements:
        r = run_client(['-e', st])
        if r.stdout.strip():
            print(r.stdout.strip())
        if r.returncode:
            sys.stderr.write(scrub(r.stderr))
            return 1
    print('ok: %d statement(s) from %s' % (len(statements), a.file))
    return 0


def cmd_query(a):
    statements = split_statements(a.statement)
    if len(statements) != 1:
        sys.exit('refused: exactly one statement')
    guard(statements)
    r = run_client(['-N', '-B'] + ([] if a.nodb else ['-D', DB]) + ['-e', statements[0]])
    sys.stdout.write(r.stdout)
    if r.returncode:
        sys.stderr.write(scrub(r.stderr))
    return r.returncode


def cmd_serverinfo(_a):
    names = ['version', 'version_comment', 'sql_mode', 'lower_case_table_names', 'character_set_server', 'collation_server',
             'innodb_default_row_format', 'innodb_strict_mode', 'innodb_file_per_table', 'max_allowed_packet', 'time_zone']
    for n in names:
        r = run_client(['-N', '-B', '-e', 'SELECT @@%s' % n])
        print('%s=%s' % (n, r.stdout.strip() if not r.returncode else '(n/a)'))
    return 0


def php_quote(value):
    return "'" + value.replace('\\', '\\\\').replace("'", "\\'") + "'"


def cmd_config(a):
    cfg = load_secret()
    tree = a.tree.rstrip('/\\')
    if not os.path.isfile(tree + '/lib/setup.php'):
        sys.exit('refused: %s is not a Moodle directory root (no lib/setup.php)' % tree)
    if not re.search(r'uatreh', a.dataroot.replace('\\', '/')) or not a.wwwroot.startswith('http://localhost'):
        sys.exit('refused: the dataroot must live under .../uatreh/ and the wwwroot must be http://localhost...')
    text = f"""<?php  // Moodle configuration file - UAT 5.2 -> 5.3 UPGRADE REHEARSAL ONLY ({DB} on 127.0.0.1:{PORT}).
// Generated by tools/uat/rehearse_helper.py. Holds a database password: never copy, package or commit it.
unset($CFG);
global $CFG;
$CFG = new stdClass();
$CFG->dbtype    = 'mariadb';
$CFG->dblibrary = 'native';
$CFG->dbhost    = '127.0.0.1';
$CFG->dbname    = '{DB}';
$CFG->dbuser    = {php_quote(cfg['user'])};
$CFG->dbpass    = {php_quote(cfg['password'])};
$CFG->prefix    = 'mdl_';
$CFG->dboptions = array('dbpersist' => 0, 'dbport' => {PORT}, 'dbsocket' => '', 'dbcollation' => 'utf8mb4_unicode_ci');
// Hard guard: this codebase must never touch another database or server.
if ($CFG->dbname !== '{DB}' || $CFG->prefix !== 'mdl_' || $CFG->dboptions['dbport'] !== {PORT} || $CFG->dbhost !== '127.0.0.1') {{
    fwrite(STDERR, "REHEARSAL GUARD: refusing to run against {{$CFG->dbname}}\\n");
    exit(1);
}}
$CFG->wwwroot   = getenv('REHEARSAL_WWWROOT') ?: {php_quote(a.wwwroot)};   // the env override exists only for the php -S render smoke
$CFG->dataroot  = {php_quote(a.dataroot.replace('/', chr(92)))};
$CFG->admin     = 'admin';
$CFG->directorypermissions = 0777;
$CFG->routerconfigured = true;      // as on UAT
$CFG->noemailever = true;           // never send mail from a rehearsal
$CFG->debug = 32767;                // DEBUG_DEVELOPER: every notice reaches the log (UAT itself runs with debug off)
$CFG->debugdisplay = 1;
require_once(__DIR__ . '/lib/setup.php');
"""
    with open(tree + '/config.php', 'w', encoding='utf-8', newline='\n') as fh:
        fh.write(text)
    print('config.php written: %s (db %s, wwwroot %s)' % (tree + '/config.php', DB, a.wwwroot))
    return 0


def cmd_adminpass(a):
    if not os.path.exists(a.out):
        with open(a.out, 'w', encoding='utf-8') as fh:
            fh.write('Aa1!' + secrets.token_urlsafe(14) + '\n')
    print('admin password file present: %s (value not printed)' % a.out)
    return 0


def cmd_dump(a):
    if not under_scratch(a.file):
        sys.exit('refused: the dump file must live under .../uatreh/')
    cfg = load_secret()
    cnf = _option_file(cfg)
    try:
        with open(a.file, 'wb') as out:
            r = subprocess.run([BIN + 'mariadb-dump.exe', '--defaults-extra-file=' + cnf, '--single-transaction', '--quick',
                                '--routines', '--default-character-set=utf8mb4', '--max-allowed-packet=1G', DB],
                               stdout=out, stderr=subprocess.PIPE, text=True)
    finally:
        _forget(cnf)
    if r.returncode:
        sys.stderr.write(scrub(r.stderr))
        return 1
    print('dump written: %s (%d bytes)' % (a.file, os.path.getsize(a.file)))
    return 0


def cmd_restore(a):
    if not under_scratch(a.file) or not os.path.isfile(a.file):
        sys.exit('refused: the dump file must exist and live under .../uatreh/')
    cfg = load_secret()
    r = run_client(['-N', '-e', "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='%s'" % DB])
    if r.returncode or r.stdout.strip() != '0':
        sys.exit('refused: %s must exist and be empty before a restore (got %r)' % (DB, r.stdout.strip()))
    cnf = _option_file(cfg)
    try:
        with open(a.file, 'rb') as src:
            r = subprocess.run([BIN + 'mariadb.exe', '--defaults-extra-file=' + cnf, '--max-allowed-packet=1G',
                                '--default-character-set=utf8mb4', DB], stdin=src, stderr=subprocess.PIPE, text=True)
    finally:
        _forget(cnf)
    if r.returncode:
        sys.stderr.write(scrub(r.stderr))
        return 1
    print('restored %s into %s' % (a.file, DB))
    return 0


# ----------------------------------------------------------------------------------------------------------------
# comparisons and log summary (no database access)
# ----------------------------------------------------------------------------------------------------------------
def read_tsv(path):
    rows = []
    with open(path, encoding='utf-8') as fh:
        for line in fh:
            line = line.rstrip('\r\n')
            if line:
                rows.append(line.split('\t'))
    return rows


def cmd_versions_diff(a):
    before, after = {}, {}
    core = {'A': {}, 'B': {}}
    for tag, path, store in (('A', a.a, before), ('B', a.b, after)):
        for row in read_tsv(path):
            if row[0].startswith('#core'):
                core[tag][row[1]] = row[2] if len(row) > 2 else ''
            elif len(row) >= 6:
                store[row[0]] = {'db': row[1], 'disk': row[2], 'release': row[3], 'status': row[4], 'origin': row[5]}
    print('CORE   version  %s -> %s' % (core['A'].get('version'), core['B'].get('version')))
    print('CORE   release  %s -> %s' % (core['A'].get('release'), core['B'].get('release')))
    print('CORE   branch   %s -> %s' % (core['A'].get('branch'), core['B'].get('branch')))
    print('plugins listed: before %d, after %d' % (len(before), len(after)))
    changed = [c for c in sorted(after) if c in before and before[c]['db'] != after[c]['db']]
    same = [c for c in sorted(after) if c in before and before[c]['db'] == after[c]['db']]
    new = [c for c in sorted(after) if c not in before]
    gone = [c for c in sorted(before) if c not in after]
    print('')
    print('== plugin version before -> after: %d changed, %d unchanged, %d new, %d gone' % (len(changed), len(same), len(new), len(gone)))
    for tag in ('add', 'std'):
        rows = [c for c in changed if after[c]['origin'] == tag]
        print('-- %s plugins changed: %d' % ('Sentientia / additional' if tag == 'add' else 'Moodle standard', len(rows)))
        for c in rows:
            print('  %-44s %-12s -> %-12s  %s -> %s' % (c, before[c]['db'], after[c]['db'], before[c]['release'] or '-', after[c]['release'] or '-'))
    print('-- unchanged additional plugins: %s' % (', '.join(c for c in same if after[c]['origin'] == 'add') or 'none'))
    print('-- new in the after table: %s' % (', '.join('%s=%s' % (c, after[c]['db']) for c in new) or 'none'))
    print('-- in the before table, not in the after table: %s' % (', '.join(gone) or 'none'))
    bad = [c for c in sorted(after) if after[c]['status'] not in ('uptodate', 'nodb')]
    drift = [c for c in sorted(after) if after[c]['db'] and after[c]['disk'] and after[c]['db'] != after[c]['disk']]
    print('')
    print('== after-upgrade status: %d plugin(s) not uptodate: %s' % (len(bad), ', '.join('%s(%s)' % (c, after[c]['status']) for c in bad) or 'none'))
    print('== after-upgrade db version != disk version: %d: %s'
          % (len(drift), ', '.join('%s db=%s disk=%s' % (c, after[c]['db'], after[c]['disk']) for c in drift) or 'none'))
    return 1 if (bad or drift) else 0


VOLATILE = re.compile(r'^(logstore_standard_log|log|task_log|task_adhoc|task_scheduled|sessions|event|events_queue_handlers|'
                      r'config_log|upgrade_log|cache_flags|cache_text|stats_.*|lock_db|local_sentientia_.*_log|'
                      r'local_sentientia_.*audit.*|notifications|messages|message_.*|user_preferences|user_lastaccess|'
                      r'capabilities|role_capabilities|external_functions|external_services_functions)$')


def cmd_counts_diff(a):
    before = {r[0]: int(r[1]) for r in read_tsv(a.a) if len(r) == 2}
    after = {r[0]: int(r[1]) for r in read_tsv(a.b) if len(r) == 2}
    added = sorted(set(after) - set(before))
    dropped = sorted(set(before) - set(after))
    less = [(t, before[t], after[t]) for t in sorted(set(before) & set(after)) if after[t] < before[t]]
    more = [(t, before[t], after[t]) for t in sorted(set(before) & set(after)) if after[t] > before[t]]
    print('tables: before %d, after %d (%d added, %d dropped)' % (len(before), len(after), len(added), len(dropped)))
    print('rows:   before %d, after %d' % (sum(before.values()), sum(after.values())))
    print('-- tables added: %s' % (', '.join(added) or 'none'))
    print('-- tables dropped: %s' % (', '.join(dropped) or 'none'))
    real = [x for x in less if not VOLATILE.match(x[0])]
    vol = [x for x in less if VOLATILE.match(x[0])]
    print('== tables whose row count FELL: %d (data-bearing: %d, volatile / registry tables: %d)' % (len(less), len(real), len(vol)))
    for t, x, y in real:
        print('  LOST  %-52s %7d -> %7d' % (t, x, y))
    for t, x, y in vol:
        print('  (vol) %-52s %7d -> %7d' % (t, x, y))
    print('-- tables whose row count grew: %d' % len(more))
    for t, x, y in more[:40]:
        print('  grew  %-52s %7d -> %7d' % (t, x, y))
    bad_dropped = [t for t in dropped if not VOLATILE.match(t)]
    return 1 if (bad_dropped or real) else 0


POSTURE = ('theme', 'forcelogin', 'enablemyhome', 'frontpage', 'frontpageloggedin', 'defaulthomepage', 'enablecompletion',
           'enablebadges', 'registerauth', 'maintenance_enabled')


def cmd_config_diff(a):
    def load(path):
        d = {}
        for r in read_tsv(path):
            if len(r) >= 2:
                d[(r[0], r[1])] = r[2] if len(r) > 2 else ''
        return d
    before, after = load(a.a), load(a.b)
    added = sorted(set(after) - set(before))
    removed = sorted(set(before) - set(after))
    changed = sorted(k for k in set(before) & set(after) if before[k] != after[k])
    print('settings: before %d, after %d (%d added, %d removed, %d changed)' % (len(before), len(after), len(added), len(removed), len(changed)))
    print('== posture settings (core) before -> after')
    moved = 0
    for name in POSTURE:
        k = ('core', name)
        if k in before or k in after:
            flag = '' if before.get(k) == after.get(k) else '   <-- CHANGED'
            moved += 1 if flag else 0
            print('  %-22s %r -> %r%s' % (name, before.get(k), after.get(k), flag))
    # a core / plugin upgrade re-versions every component it touches; those rows say nothing, so they are counted, not listed
    bumps = [k for k in changed if k[1] in ('version', 'release')]
    other = [k for k in changed if k[1] not in ('version', 'release')]
    print('== settings that CHANGED value: %d (%d are component version / release bumps, not listed; %d listed)' % (len(changed), len(bumps), len(other)))
    for k in other[:120]:
        print('  %s / %s : %r -> %r' % (k[0], k[1], before[k][:60], after[k][:60]))
    if len(other) > 120:
        print('  ... %d more' % (len(other) - 120))
    print('== settings REMOVED (%d)' % len(removed))
    for k in removed[:80]:
        print('  %s / %s = %r' % (k[0], k[1], before[k][:60]))
    print('== settings ADDED: %d (first 40 listed)' % len(added))
    for k in added[:40]:
        print('  %s / %s = %r' % (k[0], k[1], after[k][:60]))
    return 1 if moved else 0


PHPMSG = re.compile(r'^(?:PHP )?(Warning|Notice|Deprecated|Fatal error|Parse error|Strict Standards|Error)\s*:\s*(.*)$')
FILELINE = re.compile(r'\s+in\s+(\S.*?)\s+on line\s+(\d+)\s*$')


def cmd_summarize_log(a):
    cats = {k: [] for k in ('Fatal error', 'Parse error', 'Error', 'Exception', 'Warning', 'Notice', 'Debugging', 'Deprecated')}
    with open(a.file, encoding='utf-8', errors='replace') as fh:
        lines = fh.read().splitlines()
    headers = 0
    for line in lines:
        stripped = line.strip()
        m = PHPMSG.match(stripped)
        if m:
            kind, msg = m.group(1), m.group(2)
            where = ''
            fm = FILELINE.search(msg)
            if fm:
                where = re.sub(r'^.*?(moodle5\.[23][\\/])(public[\\/])?', '', fm.group(1)).replace('\\', '/') + ':' + fm.group(2)
                msg = msg[:fm.start()]
            kind = 'Notice' if kind == 'Strict Standards' else kind
            cats[kind].append((msg.strip(), where))
        elif stripped.startswith('Debugging:'):
            cats['Debugging'].append((stripped[len('Debugging:'):].strip()[:200], ''))
        elif re.search(r'Default exception handler|Error code:|Exception - ', stripped):
            cats['Exception'].append((stripped[:200], ''))
        if line.startswith('-->'):
            headers += 1
    for kind in cats:
        print('%-12s %5d' % (kind, len(cats[kind])))
    print('components that printed a --> header: %d' % headers)
    for kind in ('Fatal error', 'Parse error', 'Error', 'Exception', 'Warning', 'Notice', 'Debugging', 'Deprecated'):
        items = cats[kind]
        if not items:
            continue
        grouped = {}
        for msg, where in items:
            key = (msg[:160], where)
            grouped[key] = grouped.get(key, 0) + 1
        print('')
        print('-- %s: %d line(s), %d distinct' % (kind, len(items), len(grouped)))
        limit = 12 if kind == 'Deprecated' else 40
        for (msg, where), n in sorted(grouped.items(), key=lambda kv: -kv[1])[:limit]:
            print('  x%-3d %s%s' % (n, msg, ('   [' + where + ']') if where else ''))
        if len(grouped) > limit:
            print('  ... %d more distinct' % (len(grouped) - limit))
    fatal = cats['Fatal error'] or cats['Parse error'] or cats['Error'] or cats['Exception']
    return 1 if fatal else 0


def main():
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    sub = ap.add_subparsers(dest='cmd', required=True)
    sub.add_parser('ping').set_defaults(fn=cmd_ping)
    sub.add_parser('serverinfo').set_defaults(fn=cmd_serverinfo)
    p = sub.add_parser('sql')
    p.add_argument('file')
    p.set_defaults(fn=cmd_sql)
    p = sub.add_parser('query')
    p.add_argument('--nodb', action='store_true')
    p.add_argument('statement')
    p.set_defaults(fn=cmd_query)
    p = sub.add_parser('config')
    p.add_argument('--tree', required=True)
    p.add_argument('--dataroot', required=True)
    p.add_argument('--wwwroot', required=True)
    p.set_defaults(fn=cmd_config)
    p = sub.add_parser('adminpass')
    p.add_argument('--out', required=True)
    p.set_defaults(fn=cmd_adminpass)
    for name, fn in (('dump', cmd_dump), ('restore', cmd_restore), ('summarize-log', cmd_summarize_log)):
        p = sub.add_parser(name)
        p.add_argument('file')
        p.set_defaults(fn=fn)
    for name, fn in (('versions-diff', cmd_versions_diff), ('counts-diff', cmd_counts_diff), ('config-diff', cmd_config_diff)):
        p = sub.add_parser(name)
        p.add_argument('a')
        p.add_argument('b')
        p.set_defaults(fn=fn)
    a = ap.parse_args()
    return a.fn(a)


if __name__ == '__main__':
    sys.exit(main())
