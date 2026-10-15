#!/usr/bin/env python3
"""mysql84_reserved_scan.py - find MySQL 8.4 reserved words (and a few sibling risks) in the schema names that a
set of commits ADDS to Moodle plugins.

Why this exists. The UAT database is MySQL 8.4; every local rehearsal runs on MariaDB 11.4, which accepts words
that MySQL 8 refuses. On 2026-09-03 `stored` (a MySQL 8 reserved word) as a column in local_sentientia_xapi's
install.xml passed every local run and broke the first UAT install. Moodle's DDL generator only quotes the words in
its own, ancient, MySQL-6.0-era list (lib/ddl/mysql_sql_generator.php), so a name that is reserved in 8.4 but
absent from that list reaches MySQL unquoted and fails.

What it reads. Every db/install.xml, db/upgrade.php and db/install.php that was ADDED or MODIFIED between --base and
--head (git diff, whole repository: both the top-level and the moodle-enhancement/ plugin trees). From each it takes
the identifiers the file declares: table, field, index and key names, plus the field names listed inside index and
key definitions (install.xml attributes; in PHP, new xmldb_table/xmldb_field/xmldb_index/xmldb_key, add_field/
add_index/add_key, rename_field/rename_table). By default only identifiers that are NEW in head (not present in the
same file at --base) are judged, because anything already at --base is already installed on UAT and proved fine
there; --all judges every identifier in head.

Which identifiers matter. Moodle prefixes every table (mdl_), generates every index name itself from the table and
field names (lib/ddl/sql_generator.php getNameForObject) and issues no foreign-key constraints, so the names written
in the XML for tables, indexes and keys never reach MySQL as bare identifiers. COLUMN names do, unquoted unless they
are in Moodle's own list. So: a reserved COLUMN name fails the scan; a reserved TABLE name is only reported (it
would matter for an empty $CFG->prefix); index and key names are not judged (a key called `primary` is normal).

Tiers
  R84   reserved in MySQL 8.4 (list below, written from the MySQL 8.4 manual, "Keywords and Reserved Words", from
        memory - there was no network access when this was written). A hit FAILS the scan (exit 1).
  WATCH not reserved in 8.4 as far as the author knows, but reserved in MariaDB 11.4, or added to MySQL around 8.4/9.x
        with reserved status the author could not confirm. Reported, does not fail.
  MDL   present in Moodle's own MySQL reserved list read from --moodle-root (Moodle quotes these itself, so they
        install fine; reported only as information on how a R84 hit is covered).
  Also: identifiers longer than 64 characters (MySQL limit) and install.xml files that are not well-formed XML
  (an unescaped '<' in a COMMENT attribute once silently broke a whole file) both fail the scan.
  And: raw DDL strings (ALTER/CREATE/DROP of TABLE or INDEX) on lines ADDED to upgrade.php/install.php are listed for
  a human, because they bypass Moodle's quoting.

Usage
  python mysql84_reserved_scan.py --base 481252996 --head origin/claude/gap-integration [--moodle-root DIR] [--all]
  Exit 0 = no R84 hit, no oversize identifier, no XML error. Exit 1 = at least one. Exit 2 = usage / git problem.
Test fixtures (tests/fixtures/...) are scanned and reported separately: they are loaded by PHPUnit only and never
reach a production database.
"""
import argparse
import re
import subprocess
import sys

try:  # the files scanned are this repository's own install.xml, but prefer the hardened parser when it is installed
    import defusedxml.ElementTree as ET
except ImportError:  # pragma: no cover
    import xml.etree.ElementTree as ET

# ---------------------------------------------------------------------------------------------------------------
# MySQL 8.4 reserved words (the (R) entries of the keyword table). MASTER_BIND and MASTER_SSL_VERIFY_SERVER_CERT were
# dropped from the table in 8.4 as far as the author remembers; they stay in the list because over-reporting is the
# safe direction.
# ---------------------------------------------------------------------------------------------------------------
R84 = set("""
ACCESSIBLE ADD ALL ALTER ANALYZE AND AS ASC ASENSITIVE
BEFORE BETWEEN BIGINT BINARY BLOB BOTH BY
CALL CASCADE CASE CHANGE CHAR CHARACTER CHECK COLLATE COLUMN CONDITION CONSTRAINT CONTINUE CONVERT CREATE CROSS CUBE
CUME_DIST CURRENT_DATE CURRENT_TIME CURRENT_TIMESTAMP CURRENT_USER CURSOR
DATABASE DATABASES DAY_HOUR DAY_MICROSECOND DAY_MINUTE DAY_SECOND DEC DECIMAL DECLARE DEFAULT DELAYED DELETE
DENSE_RANK DESC DESCRIBE DETERMINISTIC DISTINCT DISTINCTROW DIV DOUBLE DROP DUAL
EACH ELSE ELSEIF EMPTY ENCLOSED ESCAPED EXCEPT EXISTS EXIT EXPLAIN
FALSE FETCH FIRST_VALUE FLOAT FLOAT4 FLOAT8 FOR FORCE FOREIGN FROM FULLTEXT FUNCTION
GENERATED GET GRANT GROUP GROUPING GROUPS
HAVING HIGH_PRIORITY HOUR_MICROSECOND HOUR_MINUTE HOUR_SECOND
IF IGNORE IN INDEX INFILE INNER INOUT INSENSITIVE INSERT INT INT1 INT2 INT3 INT4 INT8 INTEGER INTERSECT INTERVAL INTO
IO_AFTER_GTIDS IO_BEFORE_GTIDS IS ITERATE
JOIN JSON_TABLE
KEY KEYS KILL
LAG LAST_VALUE LATERAL LEAD LEADING LEAVE LEFT LIKE LIMIT LINEAR LINES LOAD LOCALTIME LOCALTIMESTAMP LOCK LONG
LONGBLOB LONGTEXT LOOP LOW_PRIORITY
MASTER_BIND MASTER_SSL_VERIFY_SERVER_CERT MATCH MAXVALUE MEDIUMBLOB MEDIUMINT MEDIUMTEXT MIDDLEINT MINUTE_MICROSECOND
MINUTE_SECOND MOD MODIFIES
NATURAL NOT NO_WRITE_TO_BINLOG NTH_VALUE NTILE NULL NUMERIC
OF ON OPTIMIZE OPTIMIZER_COSTS OPTION OPTIONALLY OR ORDER OUT OUTER OUTFILE OVER
PARTITION PERCENT_RANK PRECISION PRIMARY PROCEDURE PURGE
RANGE RANK READ READS READ_WRITE REAL RECURSIVE REFERENCES REGEXP RELEASE RENAME REPEAT REPLACE REQUIRE RESIGNAL
RESTRICT RETURN REVOKE RIGHT RLIKE ROW ROWS ROW_NUMBER
SCHEMA SCHEMAS SECOND_MICROSECOND SELECT SENSITIVE SEPARATOR SET SHOW SIGNAL SMALLINT SPATIAL SPECIFIC SQL
SQLEXCEPTION SQLSTATE SQLWARNING SQL_BIG_RESULT SQL_CALC_FOUND_ROWS SQL_SMALL_RESULT SSL STARTING STORED
STRAIGHT_JOIN SYSTEM
TABLE TERMINATED THEN TINYBLOB TINYINT TINYTEXT TO TRAILING TRIGGER TRUE
UNDO UNION UNIQUE UNLOCK UNSIGNED UPDATE USAGE USE USING UTC_DATE UTC_TIME UTC_TIMESTAMP
VALUES VARBINARY VARCHAR VARCHARACTER VARYING VIRTUAL
WHEN WHERE WHILE WINDOW WITH WRITE
XOR
YEAR_MONTH
ZEROFILL
""".split())

# Not reserved in 8.4 as far as known, but worth a look: MariaDB 11.4 reserved extras, plus words MySQL added around
# 8.4/9.x whose reserved status the author could not confirm without the manual.
WATCH = set("""
CURRENT_ROLE DELETE_DOMAIN_ID DO_DOMAIN_IDS GENERAL IGNORE_DOMAIN_IDS IGNORE_SERVER_IDS MASTER_HEARTBEAT_PERIOD OFFSET
PAGE_CHECKSUM PARSE_VCOL_EXPR POSITION REF_SYSTEM_ID RETURNING SLOW STATS_AUTO_RECALC STATS_PERSISTENT
STATS_SAMPLE_PAGES
QUALIFY TABLESAMPLE PARALLEL MANUAL BERNOULLI AUTO GTIDS PARSE_TREE S3 QUERY LOG VECTOR
""".split())

# Identifier families to judge, by where they appear.
KIND_TABLE, KIND_FIELD, KIND_INDEX, KIND_KEY = 'table', 'field', 'index', 'key'


def git(*args):
    r = subprocess.run(['git'] + list(args), capture_output=True, text=True, encoding='utf-8', errors='replace')
    if r.returncode != 0:
        return None
    return r.stdout


def show(rev, path):
    out = git('show', '%s:%s' % (rev, path))
    return out if out is not None else ''


def changed_files(base, head):
    out = git('diff', '--name-status', '--diff-filter=AM', base, head, '--', '*/db/install.xml',
              '*/db/upgrade.php', '*/db/install.php', 'db/install.xml', 'db/upgrade.php', 'db/install.php')
    if out is None:
        sys.exit('git diff failed: are %s and %s valid revisions of this repository?' % (base, head))
    files = []
    for line in out.splitlines():
        parts = line.split('\t')
        if len(parts) == 2:
            files.append(parts[1])
    return sorted(set(files))


def added_lines(base, head, path):
    """{line number in head: text} for the lines the diff adds to one file."""
    out = git('diff', '-U0', base, head, '--', path) or ''
    res, cur = {}, 0
    for line in out.splitlines():
        m = re.match(r'^@@ -\d+(?:,\d+)? \+(\d+)(?:,(\d+))? @@', line)
        if m:
            cur = int(m.group(1))
            continue
        if line.startswith('+') and not line.startswith('+++'):
            res[cur] = line[1:]
            cur += 1
    return res


def xml_names(text):
    """(set of (kind, name), error string) from an install.xml body."""
    names = set()
    try:
        root = ET.fromstring(text.encode('utf-8'))
    except ET.ParseError as e:
        return names, str(e)
    for t in root.iter('TABLE'):
        names.add((KIND_TABLE, t.get('NAME', '')))
        for f in t.iter('FIELD'):
            names.add((KIND_FIELD, f.get('NAME', '')))
        for k in t.iter('KEY'):
            names.add((KIND_KEY, k.get('NAME', '')))
            for fn in (k.get('FIELDS') or '').split(','):
                if fn.strip():
                    names.add((KIND_FIELD, fn.strip()))
        for i in t.iter('INDEX'):
            names.add((KIND_INDEX, i.get('NAME', '')))
            for fn in (i.get('FIELDS') or '').split(','):
                if fn.strip():
                    names.add((KIND_FIELD, fn.strip()))
    names.discard((KIND_TABLE, ''))
    return {n for n in names if n[1]}, None


PHP_PATTERNS = [
    (KIND_TABLE, re.compile(r"new\s+xmldb_table\(\s*['\"]([^'\"]+)['\"]")),
    (KIND_FIELD, re.compile(r"new\s+xmldb_field\(\s*['\"]([^'\"]+)['\"]")),
    (KIND_FIELD, re.compile(r"add_field\(\s*['\"]([^'\"]+)['\"]")),
    (KIND_INDEX, re.compile(r"new\s+xmldb_index\(\s*['\"]([^'\"]+)['\"]")),
    (KIND_INDEX, re.compile(r"add_index\(\s*['\"]([^'\"]+)['\"]")),
    (KIND_KEY, re.compile(r"new\s+xmldb_key\(\s*['\"]([^'\"]+)['\"]")),
    (KIND_KEY, re.compile(r"add_key\(\s*['\"]([^'\"]+)['\"]")),
    (KIND_FIELD, re.compile(r"rename_field\(\s*\$[A-Za-z_]+\s*,\s*\$[A-Za-z_]+\s*,\s*['\"]([^'\"]+)['\"]")),
    (KIND_TABLE, re.compile(r"rename_table\(\s*\$[A-Za-z_]+\s*,\s*['\"]([^'\"]+)['\"]")),
]
# Field lists inside index / key definitions: ... xmldb_index('n', XMLDB_INDEX_NOTUNIQUE, ['a', 'b'] ...
LIST_PATTERN = re.compile(r"(?:xmldb_index|add_index|xmldb_key|add_key)\(\s*['\"][^'\"]+['\"]\s*,\s*[A-Z_]+\s*,\s*\[([^\]]*)\]")
QUOTED = re.compile(r"['\"]([^'\"]+)['\"]")


def php_names(text):
    names = set()
    for kind, pat in PHP_PATTERNS:
        for m in pat.finditer(text):
            names.add((kind, m.group(1)))
    for m in LIST_PATTERN.finditer(text):
        for q in QUOTED.finditer(m.group(1)):
            names.add((KIND_FIELD, q.group(1)))
    return names


def moodle_reserved(root):
    """Lowercase words in Moodle's own MySQL reserved list, or None when --moodle-root is not usable."""
    if not root:
        return None
    try:
        text = open(root.rstrip('/\\') + '/public/lib/ddl/mysql_sql_generator.php', encoding='utf-8').read()
    except OSError:
        try:
            text = open(root.rstrip('/\\') + '/lib/ddl/mysql_sql_generator.php', encoding='utf-8').read()
        except OSError:
            return None
    i = text.find('function getReservedWords')
    if i < 0:
        return None
    return {w.lower() for w in re.findall(r"'([a-zA-Z_0-9]+)'", text[i:])}


def main():
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--base', required=True, help='commit UAT runs (identifiers already there are not judged)')
    ap.add_argument('--head', required=True, help='target ref')
    ap.add_argument('--moodle-root', default='', help='a Moodle tree (for its own reserved list); optional')
    ap.add_argument('--all', action='store_true', help='judge every identifier in head, not only the new ones')
    ap.add_argument('--list-words', action='store_true', help='print the word lists used and exit')
    a = ap.parse_args()

    if a.list_words:
        print('R84 (%d words): %s' % (len(R84), ' '.join(sorted(R84))))
        print('WATCH (%d words): %s' % (len(WATCH), ' '.join(sorted(WATCH))))
        return 0

    mdl = moodle_reserved(a.moodle_root)
    files = changed_files(a.base, a.head)
    print('scan %s..%s: %d changed/added db files' % (a.base, a.head, len(files)))
    print('judging: %s identifiers; R84 list %d words, WATCH list %d words, Moodle own list: %s'
          % ('ALL' if a.all else 'NEW', len(R84), len(WATCH), 'not loaded' if mdl is None else '%d words' % len(mdl)))

    hits, tablehits, watch, xmlerrs, longs, rawddl = [], [], [], [], [], []
    judged_total, judged_files, names_by_file = 0, 0, {}
    seen = set()   # (kind, name) judged already, so a duplicated plugin tree reports once per file but counts once
    for path in files:
        head_text = show(a.head, path)
        base_text = show(a.base, path)
        is_xml = path.endswith('.xml')
        fixture = '/tests/fixtures/' in path
        if is_xml:
            head_names, err = xml_names(head_text)
            if err:
                xmlerrs.append((path, err))
            base_names, _ = xml_names(base_text) if base_text else (set(), None)
        else:
            head_names = php_names(head_text)
            base_names = php_names(base_text) if base_text else set()
            added = added_lines(a.base, a.head, path)
            for ln, txt in sorted(added.items()):
                if re.search(r"(?i)\b(alter|create|drop)\s+(table|index)\b", txt) and not fixture:
                    rawddl.append((path, ln, txt.strip()[:140]))
        todo = head_names if a.all else (head_names - base_names)
        if todo:
            judged_files += 1
        names_by_file[path] = len(todo)
        for kind, name in sorted(todo):
            if kind in (KIND_INDEX, KIND_KEY):
                continue                       # never a bare DB identifier (see the docstring)
            judged_total += 1
            up = name.upper()
            tag = 'fixture' if fixture else ''
            if up in R84 and kind == KIND_TABLE:
                tablehits.append((path, kind, name, tag))
            elif up in R84:
                hits.append((path, kind, name, tag, (mdl is not None and name.lower() in mdl)))
            elif up in WATCH:
                watch.append((path, kind, name, tag))
            if len(name) > 64:
                longs.append((path, kind, name))

    prod_hits = [h for h in hits if not h[3]]
    fixture_hits = [h for h in hits if h[3]]
    print('')
    print('files with judged identifiers: %d; table and column names judged: %d (the two plugin trees counted separately)'
          % (judged_files, judged_total))
    print('')
    print('== R84 hits in shipped schema (FAIL): %d' % len(prod_hits))
    for path, kind, name, tag, covered in prod_hits:
        print('  %-5s %-8s %-28s %s%s' % ('R84', kind, name, path,
              '   [Moodle quotes it itself]' if covered else '   [NOT in Moodle quote list: reaches MySQL bare]'))
    print('== R84 words as TABLE names (informational: tables are prefixed mdl_): %d' % len(tablehits))
    for path, kind, name, tag in tablehits:
        print('  R84   table    %-28s %s' % (name, path))
    print('== R84 hits in test fixtures only (informational): %d' % len(fixture_hits))
    for path, kind, name, tag, covered in fixture_hits:
        print('  %-5s %-8s %-28s %s' % ('R84', kind, name, path))
    print('== WATCH hits (informational): %d' % len(watch))
    for path, kind, name, tag in watch:
        print('  WATCH %-8s %-28s %s%s' % (kind, name, path, '  (fixture)' if tag else ''))
    print('== identifiers over 64 characters (FAIL): %d' % len(longs))
    for path, kind, name in longs:
        print('  LONG  %-8s %s (%d)  %s' % (kind, name, len(name), path))
    print('== install.xml not well-formed (FAIL): %d' % len(xmlerrs))
    for path, err in xmlerrs:
        print('  XML   %s: %s' % (path, err))
    print('== raw DDL strings on added lines (human review): %d' % len(rawddl))
    for path, ln, txt in rawddl:
        print('  RAW   %s:%d  %s' % (path, ln, txt))
    failed = bool(prod_hits or longs or xmlerrs)
    print('')
    print('RESULT: %s' % ('FAIL' if failed else 'PASS (no MySQL 8.4 reserved word among the %d judged table and column names)' % judged_total))
    return 1 if failed else 0


if __name__ == '__main__':
    sys.exit(main())
