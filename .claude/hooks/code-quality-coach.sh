#!/bin/bash
# code-quality-coach.sh — Real-time Karpathy Principle Validation
# Runs on every file save (PostToolUse hook in settings.json)
# Non-blocking warnings only — helps you catch issues before commit.
#
# Validates:
#   1. Think Before Coding — function docblocks, clear intent
#   2. Simplicity First — magic numbers, unused variables, over-nesting
#   3. Surgical Changes — file category audit (for git commit, not save)
#   4. Goal-Driven — error handling, success criteria

set -euo pipefail

FILE="${1:-.}"
[ -f "$FILE" ] || exit 0  # Silent if not a file

RED='\033[0;31m'
YEL='\033[1;33m'
GRN='\033[0;32m'
BLU='\033[0;34m'
NC='\033[0m'

ISSUES=0

# Only check relevant file types
[[ "$FILE" =~ \.(php|py|scss|mustache|xml)$ ]] || exit 0

# ============================================================
# RULE 1: THINK BEFORE CODING (Function docblocks)
# ============================================================
if [[ "$FILE" =~ \.php$ ]]; then
    # Check for functions without docblocks (PHP)
    missing_docs=$(grep -nE "^\s*public\s+function|^\s*protected\s+function" "$FILE" 2>/dev/null | while IFS= read -r line; do
        line_num=$(echo "$line" | cut -d: -f1)
        # Simple check: grep for /** in the 5 lines above
        start=$((line_num - 5))
        [ $start -lt 1 ] && start=1
        docblock=$(sed -n "${start},$((line_num-1))p" "$FILE" | grep -c "/\*\*" || true)
        if [ $docblock -eq 0 ]; then
            func_name=$(echo "$line" | grep -oE "function\s+\w+" | cut -d' ' -f2)
            echo "$line_num:$func_name"
        fi
    done)

    if [ -n "$missing_docs" ]; then
        echo -e "${YEL}⚠  CODE QUALITY (Think Before):${NC} Missing docblocks"
        echo "$missing_docs" | head -3 | while IFS= read -r entry; do
            line_num=$(echo "$entry" | cut -d: -f1)
            func=$(echo "$entry" | cut -d: -f2)
            echo -e "   Line $line_num — function '$func' lacks /** @param @return @throws */"
        done
        ISSUES=$((ISSUES + 1))
    fi
fi

# ============================================================
# RULE 2: SIMPLICITY FIRST (Magic numbers & unused vars)
# ============================================================
if [[ "$FILE" =~ \.php$ ]]; then
    # Flag bare numeric timeouts/thresholds
    magic_nums=$(grep -nE "time|sleep|timeout|delay|retry|attempt|limit" "$FILE" 2>/dev/null | \
                 grep -E "=\s*[0-9]{2,}" | grep -vE "const|define|\s+//|\/\*" | head -2 || true)

    if [ -n "$magic_nums" ]; then
        echo -e "${YEL}⚠  CODE QUALITY (Simplicity):${NC} Magic numbers detected"
        echo "$magic_nums" | while IFS= read -r line; do
            line_num=$(echo "$line" | cut -d: -f1)
            echo "   Line $line_num — extract to const: const TIMEOUT = 30; at top of file"
        done
        ISSUES=$((ISSUES + 1))
    fi
fi

if [[ "$FILE" =~ \.py$ ]]; then
    # Flag unused imports (simple check)
    import_count=$(grep -c "^import\|^from" "$FILE" 2>/dev/null || true)
    if [ "$import_count" -gt 15 ]; then
        echo -e "${YEL}⚠  CODE QUALITY (Simplicity):${NC} High import count ($import_count)"
        echo "   Suggestion: Verify all imports are used; remove if unused"
        ISSUES=$((ISSUES + 1))
    fi

    # Flag long functions (>50 lines = likely needs splitting)
    func_count=$(grep -c "^def " "$FILE" 2>/dev/null || true)
    file_lines=$(wc -l < "$FILE")
    avg_lines=$((file_lines / (func_count + 1)))
    if [ "$avg_lines" -gt 50 ]; then
        echo -e "${YEL}⚠  CODE QUALITY (Simplicity):${NC} Long function detected"
        echo "   Suggestion: Functions average $avg_lines lines; consider extracting helpers"
        ISSUES=$((ISSUES + 1))
    fi
fi

# ============================================================
# RULE 3: SURGICAL CHANGES (File nesting/dependencies)
# ============================================================
if [[ "$FILE" =~ \.(php|py|scss)$ ]]; then
    # Check for deeply nested code (>3 levels in control flow)
    if [[ "$FILE" =~ \.php$ ]]; then
        deep_nesting=$(grep -nE "^\s{20,}" "$FILE" 2>/dev/null | head -1 || true)
        if [ -n "$deep_nesting" ]; then
            line_num=$(echo "$deep_nesting" | cut -d: -f1)
            echo -e "${YEL}⚠  CODE QUALITY (Surgical):${NC} Deep nesting (5+ levels)"
            echo "   Line $line_num — extract inner logic to helper function"
            ISSUES=$((ISSUES + 1))
        fi
    fi

    if [[ "$FILE" =~ \.scss$ ]]; then
        # Check for deeply nested selectors (>3 levels = hard to maintain)
        deep_selectors=$(grep -nE "^\s{16,}" "$FILE" 2>/dev/null | head -1 || true)
        if [ -n "$deep_selectors" ]; then
            line_num=$(echo "$deep_selectors" | cut -d: -f1)
            echo -e "${YEL}⚠  CODE QUALITY (Surgical):${NC} Deep selector nesting (4+ levels)"
            echo "   Line $line_num — BEM rule: keep nesting ≤2 levels"
            ISSUES=$((ISSUES + 1))
        fi
    fi
fi

# ============================================================
# RULE 4: GOAL-DRIVEN (Error handling in critical files)
# ============================================================
if [[ "$FILE" =~ /plugins/.*\.php$ ]] || [[ "$FILE" =~ /theme/.*\.php$ ]]; then
    # Check for API calls without error handling
    api_calls=$(grep -nE "curl|file_get_contents|fsockopen|Invoke-WebRequest|requests\." "$FILE" 2>/dev/null || true)

    if [ -n "$api_calls" ]; then
        # Check if try-catch or error check exists nearby
        has_error_handling=$(grep -c "try\|catch\|if.*error\|if.*false\|throw" "$FILE" 2>/dev/null || true)
        if [ "$has_error_handling" -eq 0 ]; then
            echo -e "${YEL}⚠  CODE QUALITY (Goal-Driven):${NC} API call without error handling"
            echo "   Suggestion: Wrap in try-catch, check return values, assert() results"
            ISSUES=$((ISSUES + 1))
        fi
    fi
fi

# ============================================================
# SUMMARY
# ============================================================
if [ "$ISSUES" -gt 0 ]; then
    echo ""
    echo -e "${BLU}═══════════════════════════════════════════════════════${NC}"
    echo -e "${YEL}  Code Quality Coach — $ISSUES suggestion(s)${NC}"
    echo -e "  File: $(basename "$FILE")"
    echo -e "  Reference: .claude/skills/code-quality-coach/SKILL.md"
    echo -e "${BLU}═══════════════════════════════════════════════════════${NC}"
else
    # Silent on success
    exit 0
fi

exit 0
