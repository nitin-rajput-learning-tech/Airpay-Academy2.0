<?php
/**
 * Parse mdl_course INSERT from SQL dump and extract course data.
 * Read-only analysis - no modifications to any files.
 */

$sql_file = 'D:\\Claude Local\\airpay-ld-os\\backups\\Production Database backup 6th April\\airpayprod-6-4.sql';
$output_file = 'D:\\Claude Local\\airpay-ld-os\\course_extract.txt';

// Column positions (0-indexed) from CREATE TABLE:
// 0=id, 1=category, 2=sortorder, 3=fullname, 4=shortname, 5=idnumber
// 6=summary, 7=summaryformat, 8=format, ...
// 37=open_path, 38=open_categoryid

$target_line = 3765;
$insert_line = '';

$fh = fopen($sql_file, 'r');
if (!$fh) {
    die("Cannot open SQL file\n");
}

$line_num = 0;
while (($line = fgets($fh)) !== false) {
    $line_num++;
    if ($line_num == $target_line) {
        $insert_line = trim($line);
        break;
    }
}
fclose($fh);

if (empty($insert_line)) {
    die("Could not find line $target_line\n");
}

// Remove the INSERT INTO prefix
$values_str = preg_replace('/^INSERT INTO `mdl_course` VALUES\s*/', '', $insert_line);
// Remove trailing semicolon
$values_str = rtrim($values_str, ';');

// Parse each row tuple - handling nested parens and quoted strings
$courses = [];
$pos = 0;
$len = strlen($values_str);

while ($pos < $len) {
    // Find opening paren
    while ($pos < $len && $values_str[$pos] !== '(') {
        $pos++;
    }
    if ($pos >= $len) break;
    $pos++; // skip (

    // Parse fields within this tuple
    $fields = [];
    $field = '';
    $in_quote = false;
    $depth = 0;

    while ($pos < $len) {
        $ch = $values_str[$pos];

        if ($in_quote) {
            if ($ch === '\\' && $pos + 1 < $len) {
                $field .= $ch . $values_str[$pos + 1];
                $pos += 2;
                continue;
            }
            if ($ch === "'") {
                // Check for '' escape
                if ($pos + 1 < $len && $values_str[$pos + 1] === "'") {
                    $field .= "''";
                    $pos += 2;
                    continue;
                }
                $in_quote = false;
                $field .= $ch;
                $pos++;
                continue;
            }
            $field .= $ch;
            $pos++;
            continue;
        }

        // Not in quote
        if ($ch === "'") {
            $in_quote = true;
            $field .= $ch;
            $pos++;
            continue;
        }

        if ($ch === ',' && $depth === 0) {
            $fields[] = trim($field);
            $field = '';
            $pos++;
            continue;
        }

        if ($ch === ')') {
            if ($depth === 0) {
                $fields[] = trim($field);
                $pos++; // skip )
                break;
            }
            $depth--;
        }

        if ($ch === '(') {
            $depth++;
        }

        $field .= $ch;
        $pos++;
    }

    // Extract needed fields: id(0), fullname(3), shortname(4), open_path(37)
    if (count($fields) > 37) {
        $id = trim($fields[0]);
        $fullname = trim($fields[3], "'");
        $fullname = stripslashes($fullname);
        $shortname = trim($fields[4], "'");
        $shortname = stripslashes($shortname);
        $open_path = trim($fields[37], "'");

        if ($open_path === 'NULL') {
            $open_path = '(null)';
        }

        $courses[] = [
            'id' => $id,
            'fullname' => $fullname,
            'shortname' => $shortname,
            'open_path' => $open_path,
        ];
    }
}

// Write output
$out = fopen($output_file, 'w');

// Part 1: All courses
fwrite($out, "=== ALL COURSES FROM mdl_course (" . count($courses) . " total) ===\n\n");
fwrite($out, sprintf("%-6s | %-60s | %-15s | %s\n", 'ID', 'FULLNAME', 'SHORTNAME', 'OPEN_PATH'));
fwrite($out, str_repeat('-', 110) . "\n");

foreach ($courses as $c) {
    fwrite($out, sprintf("%-6s | %-60s | %-15s | %s\n",
        $c['id'],
        mb_substr($c['fullname'], 0, 60),
        mb_substr($c['shortname'], 0, 15),
        $c['open_path']
    ));
}

// Part 2: Group by tenant
$tenant1 = []; // /1 = Airpay
$tenant77 = []; // /77 = Public
$other = [];

foreach ($courses as $c) {
    if ($c['open_path'] === '/1') {
        $tenant1[$c['id']] = $c;
    } elseif ($c['open_path'] === '/77') {
        $tenant77[$c['id']] = $c;
    } else {
        $other[$c['id']] = $c;
    }
}

fwrite($out, "\n\n=== TENANT SUMMARY ===\n");
fwrite($out, "Airpay (/1):  " . count($tenant1) . " courses\n");
fwrite($out, "Public (/77): " . count($tenant77) . " courses\n");
fwrite($out, "Other/null:   " . count($other) . " courses\n");

// Part 3: Find matching courses across tenants
// Normalize fullname for comparison (lowercase, trim)
$t1_names = [];
foreach ($tenant1 as $c) {
    $key = strtolower(trim($c['fullname']));
    $t1_names[$key][] = $c;
}

$t77_names = [];
foreach ($tenant77 as $c) {
    $key = strtolower(trim($c['fullname']));
    $t77_names[$key][] = $c;
}

// Exact fullname matches
$matches = [];
foreach ($t1_names as $name => $t1_courses) {
    if (isset($t77_names[$name])) {
        foreach ($t1_courses as $t1c) {
            foreach ($t77_names[$name] as $t77c) {
                $matches[] = [
                    'fullname' => $t1c['fullname'],
                    't1_id' => $t1c['id'],
                    't1_shortname' => $t1c['shortname'],
                    't77_id' => $t77c['id'],
                    't77_shortname' => $t77c['shortname'],
                ];
            }
        }
    }
}

fwrite($out, "\n\n=== COURSES IN BOTH /1 (AIRPAY) AND /77 (PUBLIC) — EXACT FULLNAME MATCH ===\n");
fwrite($out, "Found: " . count($matches) . " matching course pairs\n\n");

if (count($matches) > 0) {
    fwrite($out, sprintf("%-60s | %-8s %-15s | %-8s %-15s\n",
        'FULLNAME', 'T1_ID', 'T1_SHORT', 'T77_ID', 'T77_SHORT'));
    fwrite($out, str_repeat('-', 120) . "\n");

    foreach ($matches as $m) {
        fwrite($out, sprintf("%-60s | %-8s %-15s | %-8s %-15s\n",
            mb_substr($m['fullname'], 0, 60),
            $m['t1_id'],
            mb_substr($m['t1_shortname'], 0, 15),
            $m['t77_id'],
            mb_substr($m['t77_shortname'], 0, 15)
        ));
    }
}

// Part 4: Fuzzy matches - courses with similar names (substring match)
fwrite($out, "\n\n=== SIMILAR NAMES ACROSS TENANTS (fuzzy - one name contains the other) ===\n\n");

$fuzzy_matches = [];
foreach ($t1_names as $name1 => $t1_courses) {
    foreach ($t77_names as $name77 => $t77_courses) {
        if ($name1 === $name77) continue; // already in exact matches
        // Check if one contains the other (at least 10 chars to avoid false positives)
        if (strlen($name1) >= 10 && strlen($name77) >= 10) {
            if (strpos($name1, $name77) !== false || strpos($name77, $name1) !== false) {
                foreach ($t1_courses as $t1c) {
                    foreach ($t77_courses as $t77c) {
                        $fuzzy_matches[] = [
                            't1_fullname' => $t1c['fullname'],
                            't1_id' => $t1c['id'],
                            't77_fullname' => $t77c['fullname'],
                            't77_id' => $t77c['id'],
                        ];
                    }
                }
            }
        }
    }
}

if (count($fuzzy_matches) > 0) {
    fwrite($out, "Found: " . count($fuzzy_matches) . " fuzzy matches\n\n");
    foreach ($fuzzy_matches as $fm) {
        fwrite($out, sprintf("  /1  ID %-6s: %s\n  /77 ID %-6s: %s\n\n",
            $fm['t1_id'], $fm['t1_fullname'],
            $fm['t77_id'], $fm['t77_fullname']
        ));
    }
} else {
    fwrite($out, "No fuzzy matches found.\n");
}

// Part 5: Full list per tenant
fwrite($out, "\n\n=== FULL LIST: /1 (AIRPAY) COURSES ===\n\n");
fwrite($out, sprintf("%-6s | %-70s | %s\n", 'ID', 'FULLNAME', 'SHORTNAME'));
fwrite($out, str_repeat('-', 100) . "\n");
foreach ($tenant1 as $c) {
    fwrite($out, sprintf("%-6s | %-70s | %s\n", $c['id'], mb_substr($c['fullname'], 0, 70), $c['shortname']));
}

fwrite($out, "\n\n=== FULL LIST: /77 (PUBLIC) COURSES ===\n\n");
fwrite($out, sprintf("%-6s | %-70s | %s\n", 'ID', 'FULLNAME', 'SHORTNAME'));
fwrite($out, str_repeat('-', 100) . "\n");
foreach ($tenant77 as $c) {
    fwrite($out, sprintf("%-6s | %-70s | %s\n", $c['id'], mb_substr($c['fullname'], 0, 70), $c['shortname']));
}

fclose($out);

echo "Done. Output written to: $output_file\n";
echo "Total courses: " . count($courses) . "\n";
echo "Airpay (/1): " . count($tenant1) . "\n";
echo "Public (/77): " . count($tenant77) . "\n";
echo "Exact matches: " . count($matches) . "\n";
echo "Fuzzy matches: " . count($fuzzy_matches) . "\n";
