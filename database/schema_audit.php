<?php declare(strict_types=1);

/**
 * Schema Audit — cross-check every SQL statement in the codebase against
 * the live database schema.
 *
 * This is the guard that catches the class of bug that keeps production
 * pages crashing with "Unknown column 'x' in 'field list'":
 *   • every column written by db()->insert()/db()->update() must exist,
 *   • every column read in a SELECT list must exist in one of the tables
 *     the statement actually reads from (JOIN-aware),
 *   • every table referenced must exist.
 *
 * Usage:  php database/schema_audit.php
 * Exit code 0 = clean, 1 = problems found.
 */

require_once __DIR__ . '/../config/db.php';

const SKIP_DIRS = ['vendor', 'node_modules', 'cache', 'uploads', '.git', 'scratch'];

/** Words that may follow a table name but are never an alias. */
const SQL_RESERVED = [
    'on', 'where', 'set', 'values', 'join', 'left', 'right', 'inner', 'outer',
    'cross', 'group', 'order', 'limit', 'offset', 'having', 'union', 'and',
    'or', 'as', 'select', 'from', 'into', 'using', 'straight_join', 'natural',
    'for', 'in', 'force', 'ignore', 'replace', 'duplicate', 'key', 'default',
    'values', 'update', 'delete', 'insert', 'create', 'table', 'index',
];

/**
 * SQL keywords that must never be mistaken for a column name.
 */
const SQL_KEYWORDS = [
    'distinct', 'all', 'sql_no_cache', 'sql_calc_found_rows', 'null', 'true',
    'false', 'default', 'case', 'when', 'then', 'else', 'end', 'binary',
    'interval', 'collate', 'escape', 'row', 'rows', 'range', 'groups',
];

/**
 * @return array{0:string[],1:string[]} [absolute file paths, relative paths]
 */
function php_files(string $root): array
{
    $out = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $file) {
        $path = $file->getPathname();
        if (!is_file($path) || strtolower($file->getExtension()) !== 'php') {
            continue;
        }
        foreach (SKIP_DIRS as $skip) {
            if (stripos($path, DIRECTORY_SEPARATOR . $skip . DIRECTORY_SEPARATOR) !== false) {
                continue 2;
            }
        }
        $out[] = $path;
    }
    sort($out);
    return [$out, array_map(fn($p) => str_replace(DIRECTORY_SEPARATOR, '/', substr($p, strlen($root) + 1)), $out)];
}

/**
 * Read a value from a result row regardless of the key casing the driver used
 * (information_schema comes back UPPERCASE, user tables lower).
 */
function row_value(array $row, array $keys): string
{
    $lower = array_map('strtolower', $keys);
    foreach ($row as $k => $v) {
        if (is_string($k) && in_array(strtolower($k), $lower, true)) {
            return (string)$v;
        }
    }
    return '';
}

/**
 * Extract quoted array keys that sit at the top level of an array literal.
 *
 * The body passed in is the *inside* of the array, so the nesting depth
 * starts at 1.
 */
function top_level_keys(string $arrayBody): array
{
    $keys = [];
    $depth = 1;
    $len = strlen($arrayBody);
    for ($i = 0; $i < $len; $i++) {
        $ch = $arrayBody[$i];
        if ($ch === '[' || $ch === '(') { $depth++; continue; }
        if ($ch === ']' || $ch === ')') { $depth--; continue; }
        if ($depth !== 1) { continue; }
        if ($ch === "'" || $ch === '"') {
            $quote = $ch;
            $j = $i + 1;
            $key = '';
            while ($j < $len && $arrayBody[$j] !== $quote) {
                $key .= $arrayBody[$j];
                $j++;
            }
            $rest = substr($arrayBody, $j + 1);
            if (preg_match('/^\s*=>/', $rest)) {
                // Keys may be backticked (db()->update() interpolates them
                // raw into SQL, so `2fa_attempts` is a valid PHP array key).
                $keys[] = strtolower(trim($key, "` \t\n\r"));
            }
            $i = $j;
        }
    }
    return array_values(array_unique($keys));
}

/**
 * Split a comma-separated list on top-level commas only.
 */
function split_top_level(string $list): array
{
    $parts = [];
    $depth = 0;
    $buf = '';
    $len = strlen($list);
    $quote = null;
    for ($i = 0; $i < $len; $i++) {
        $ch = $list[$i];
        if ($quote !== null) {
            $buf .= $ch;
            if ($ch === $quote) { $quote = null; }
            continue;
        }
        if ($ch === "'" || $ch === '"' || $ch === '`') {
            $quote = $ch;
            $buf .= $ch;
            continue;
        }
        if ($ch === '(') { $depth++; }
        if ($ch === ')') { $depth--; }
        if ($ch === ',' && $depth === 0) {
            $parts[] = trim($buf);
            $buf = '';
            continue;
        }
        $buf .= $ch;
    }
    if (trim($buf) !== '') {
        $parts[] = trim($buf);
    }
    return $parts;
}

/** Find the first occurrence of $needle outside of parentheses/quotes. */
function find_top_level(string $haystack, string $needle, int $offset = 0): int
{
    $depth = 0;
    $len = strlen($haystack);
    $needleLen = strlen($needle);
    $quote = null;
    for ($i = $offset; $i < $len; $i++) {
        $ch = $haystack[$i];
        if ($quote !== null) {
            if ($ch === $quote) { $quote = null; }
            continue;
        }
        if ($ch === "'" || $ch === '"' || $ch === '`') { $quote = $ch; continue; }
        if ($ch === '(') { $depth++; continue; }
        if ($ch === ')') { $depth--; continue; }
        if ($depth === 0 && strcasecmp(substr($haystack, $i, $needleLen), $needle) === 0) {
            $before = $i > 0 ? $haystack[$i - 1] : ' ';
            $after  = $haystack[$i + $needleLen] ?? ' ';
            if (!ctype_alnum($before) && $before !== '_' && !ctype_alnum($after) && $after !== '_') {
                return $i;
            }
        }
    }
    return -1;
}

/**
 * Collect every table referenced by a statement together with its alias.
 *
 * @return array<int, array{table:string, alias:string}> 
 */
function collect_tables(string $sql): array
{
    $tables = [];
    $re = '/\b(?:FROM|JOIN|INTO|UPDATE)\s+`?([A-Za-z0-9_]+)`?(?:\s*\.\s*`?([A-Za-z0-9_]+)`?)?/i';
    if (!preg_match_all($re, $sql, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
        return $tables;
    }
    foreach ($m as $hit) {
        // "ON DUPLICATE KEY UPDATE col = ..." — the UPDATE here is a clause,
        // not a table reference.
        $prefix = substr($sql, max(0, $hit[0][1] - 30), min(30, $hit[0][1]));
        if (preg_match('/ON\s+DUPLICATE\s+KEY\s*$/i', $prefix)) {
            continue;
        }
        // "FOR UPDATE SKIP LOCKED" — locking clause, not a table.
        if (preg_match('/\bFOR\s*$/i', $prefix)) {
            continue;
        }
        $name = $hit[1][0];
        // system schemas are not application tables
        $schemaName = strtolower($hit[1][0]);
        if (!empty($hit[2])) {
            $schemaName = strtolower($hit[1][0]);
            $name = $hit[2][0];
        }
        if (in_array($schemaName, ['information_schema', 'mysql', 'performance_schema', 'sys'], true)) {
            continue;
        }
        if (strcasecmp($name, 'DUAL') === 0) {
            continue;
        }
        // schema-qualified: use the second part
        if (!empty($hit[2])) {
            $name = $hit[2][0];
        }
        $name = strtolower($name);

        $alias = '';
        $rest  = substr($sql, $hit[0][1] + strlen($hit[0][0]));
        if (preg_match('/^\s+(?:AS\s+)?`?([A-Za-z0-9_]+)`?/i', $rest, $am)) {
            $candidate = strtolower($am[1]);
            if (!in_array($candidate, SQL_RESERVED, true)) {
                $alias = $candidate;
            }
        }

        $tables[] = ['table' => $name, 'alias' => $alias];
    }
    return $tables;
}

/** Resolve a qualifier (alias or table name) to a table key. */
function resolve_qualifier(array $tables, string $qualifier): ?string
{
    $qualifier = strtolower(trim($qualifier, '` '));
    foreach ($tables as $t) {
        if ($t['alias'] !== '' && $t['alias'] === $qualifier) {
            return $t['table'];
        }
    }
    foreach ($tables as $t) {
        if ($t['table'] === $qualifier) {
            return $t['table'];
        }
    }
    return null;
}

/**
 * Reduce a SELECT expression to [qualifier|null, column|null].
 * Returns [null, null] for anything that is not a plain column reference.
 */
function parse_column_expr(string $expr): array
{
    $expr = trim($expr);
    if ($expr === '' || $expr === '*' || str_contains($expr, '*')) {
        return [null, null];
    }
    // strip leading modifiers: DISTINCT / ALL / SQL_CALC_FOUND_ROWS ...
    $expr = preg_replace('/^(DISTINCT|ALL|SQL_NO_CACHE|SQL_CALC_FOUND_ROWS)\s+/i', '', (string)$expr);
    $expr = trim((string)$expr);
    // strip trailing alias: "col AS alias" / "col alias"
    if (preg_match('/^(.*?)\s+AS\s+`?[A-Za-z0-9_]+`?$/is', $expr, $m)) {
        $expr = trim($m[1]);
    } elseif (preg_match('/^(`?[A-Za-z0-9_]+`?)\s+`?[A-Za-z0-9_]+`?$/i', $expr, $m)
        && !preg_match('/^(COUNT|SUM|AVG|MIN|MAX|DATE|SUM|YEAR|MONTH|COALESCE|IF|NOW|CONCAT|LEFT|RIGHT|ROUND|FLOOR|CEIL|UNIX_TIMESTAMP|DATE_FORMAT|TIMESTAMPDIFF|TRIM|UPPER|LOWER|SUBSTRING|LENGTH)\b/i', $expr)
        && !in_array(strtolower(trim($m[1], '` ')), SQL_KEYWORDS, true)) {
        $expr = trim($m[1], '` ');
    }
    if (preg_match('/^`?([A-Za-z0-9_]+)`?\s*\.\s*`?([A-Za-z0-9_]+)`?$/', $expr, $m)) {
        return [strtolower($m[1]), strtolower($m[2])];
    }
    if (preg_match('/^`?([A-Za-z0-9_]+)`?$/', $expr, $m)) {
        $col = strtolower($m[1]);
        // numeric literal (e.g. "SELECT 1 AS ok") is not a column
        if (in_array($col, SQL_KEYWORDS, true) || is_numeric($col)) {
            return [null, null];
        }
        return [null, $col];
    }
    return [null, null];
}

/** Does this string look like an SQL statement? */
function looks_like_sql(string $s): bool
{
    $t = ltrim((string)$s);
    if (strlen($t) < 12) {
        return false;
    }
    // Must *begin* with a SQL statement keyword — this filters out prose
    // strings ("Select the language from the list") and migration comments.
    if (!preg_match('/^(SELECT|INSERT|UPDATE|DELETE|REPLACE|WITH)\b/i', $t)) {
        return false;
    }
    return (bool)preg_match('/\b(FROM|INTO|SET|VALUES)\b/i', $t);
}

/**
 * Strip PHP comments from source code (comments are replaced by spaces so
 * offsets stay stable). Without this, the audit happily "finds" bugs in
 * documentation comments such as `db()->insert('table', [...])`.
 */
function php_code_only(string $code): string
{
    $out = '';
    foreach (token_get_all($code) as $token) {
        if (is_array($token)) {
            $text = $token[1];
            if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                $text = (string)preg_replace('/[^\n]/', ' ', $text);
            }
            $out .= $text;
        } else {
            $out .= $token;
        }
    }
    return $out;
}

/**
 * Remove the surrounding quotes of a PHP string literal and unescape it.
 */
function strip_php_string_quotes(string $literal): string
{
    $quote = $literal[0] ?? '';
    if (($quote === "'" || $quote === '"') && substr($literal, -1) === $quote && strlen($literal) >= 2) {
        $literal = substr($literal, 1, -1);
    }
    if ($quote === "'") {
        return str_replace(["\\'", '\\\\'], ["'", '\\'], $literal);
    }
    return (string)preg_replace('/\\\\(["\\\\$nrt])/', '$1', $literal);
}

/**
 * Pull every SQL string literal out of a PHP source file using the PHP
 * tokenizer — this sees real string literals only (not comments, not
 * strings nested inside other strings).
 */
function extract_sql_statements(string $code): array
{
    $out = [];
    $tokens = token_get_all($code);
    $n = count($tokens);

    for ($i = 0; $i < $n; $i++) {
        $token = $tokens[$i];

        if (is_array($token)) {
            if ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
                $sql = strip_php_string_quotes($token[1]);
                if (looks_like_sql($sql)) {
                    $out[] = $sql;
                }
                continue;
            }
            if ($token[0] === T_START_HEREDOC) {
                $buf = '';
                for ($j = $i + 1; $j < $n; $j++) {
                    if (is_array($tokens[$j]) && $tokens[$j][0] === T_END_HEREDOC) {
                        break;
                    }
                    $buf .= is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
                }
                if (looks_like_sql($buf)) {
                    $out[] = $buf;
                }
            }
            continue;
        }

        // double-quoted string with interpolation
        if ($token === '"') {
            $buf = '';
            for ($j = $i + 1; $j < $n; $j++) {
                if ($tokens[$j] === '"') {
                    break;
                }
                $buf .= is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
            }
            $sql = (string)preg_replace('/\{\$[^}]*\}/', '?', $buf);
            $sql = (string)preg_replace('/\$\{[^}]*\}/', '?', $sql);
            $sql = (string)preg_replace('/\$[A-Za-z_][A-Za-z0-9_]*/', '?', $sql);
            if (looks_like_sql($sql)) {
                $out[] = $sql;
            }
        }
    }
    return $out;
}

$root = dirname(__DIR__);
[$files, $relFiles] = php_files($root);

echo "Schema audit — scanning " . count($files) . " PHP files against the live database\n";
echo str_repeat('-', 74) . "\n";

// Load live schema (base tables AND views — `tickets` is a view over
// `support_tickets`, and views expose real, queryable columns).
$schema = [];
$tables = db()->fetchAll(
    "SELECT table_name, table_type FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type IN ('BASE TABLE','VIEW')"
);
foreach ($tables as $t) {
    $tableName = row_value($t, ['table_name', 'TABLE_NAME']);
    if ($tableName === '') {
        continue;
    }
    $name = strtolower($tableName);
    $schema[$name] = [];
    foreach (db()->fetchAll(
        "SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?",
        [$tableName]
    ) as $c) {
        $col = row_value($c, ['column_name', 'COLUMN_NAME']);
        if ($col !== '') {
            $schema[$name][] = strtolower($col);
        }
    }
}
echo "Live schema: " . count($schema) . " tables/views\n\n";

$problems = [];
$checked  = 0;

/**
 * Report a problem once.
 */
function report(array &$problems, string $table, string $issue, string $detail, string $file): void
{
    $problems[] = ['table' => $table, 'issue' => $issue, 'detail' => $detail, 'file' => $file];
}

foreach ($files as $idx => $file) {
    $rel  = $relFiles[$idx];
    $raw  = (string)file_get_contents($file);
    // Comments are stripped for the structural scans so documentation
    // examples never register as code.
    $code = php_code_only($raw);

    /* ── 1. db()->insert('table', [...]) / db()->update('table', [...]) ── */
    if (preg_match_all(
        "/db\(\)->(?:insert|update)\(\s*['\"]([A-Za-z0-9_]+)['\"]\s*,\s*\[(.*?)\]\s*\)/s",
        $code,
        $m,
        PREG_SET_ORDER
    )) {
        foreach ($m as $hit) {
            $table = strtolower($hit[1]);
            if (!isset($schema[$table])) {
                report($problems, $table, 'table missing', $table, $rel);
                continue;
            }
            $checked++;
            foreach (top_level_keys($hit[2]) as $col) {
                if (!in_array($col, $schema[$table], true)) {
                    report($problems, $table, 'column missing', $table . '.' . $col, $rel);
                }
            }
        }
    }

    /* ── 2. raw SQL statements ── */
    foreach (extract_sql_statements($code) as $sql) {
        $stmtTables = collect_tables($sql);
        if (!$stmtTables) {
            continue;
        }
        $known = [];
        foreach ($stmtTables as $t) {
            if (isset($schema[$t['table']])) {
                $known[] = $t;
            } else {
                if (getenv('SCHEMA_AUDIT_DEBUG')) {
                    fwrite(STDERR, "[DBG] {$rel} :: table='{$t['table']}' sql=" . substr(preg_replace('/\s+/', ' ', $sql), 0, 160) . "\n");
                }
                report($problems, $t['table'], 'table missing', $t['table'], $rel);
            }
        }
        if (!$known) {
            continue;
        }
        $primary = $known[0]['table'];

        /* SELECT ... */
        $fromPos = find_top_level($sql, 'FROM');
        $selPos  = find_top_level($sql, 'SELECT');
        if ($selPos !== -1 && $fromPos !== -1 && $fromPos > $selPos) {
            $checked++;
            $list = substr($sql, $selPos + 6, $fromPos - $selPos - 6);
            foreach (split_top_level($list) as $expr) {
                [$qualifier, $col] = parse_column_expr($expr);
                if ($col === null) {
                    continue;
                }
                if ($qualifier !== null) {
                    $resolved = resolve_qualifier($known, $qualifier);
                    if ($resolved === null) {
                        continue; // sub-query alias — not verifiable
                    }
                    if (!in_array($col, $schema[$resolved], true)) {
                        report($problems, $resolved, 'column missing (SELECT)', $resolved . '.' . $col, $rel);
                    }
                    continue;
                }
                // unqualified: must exist in at least one table of the statement
                $found = false;
                foreach ($known as $t) {
                    if (in_array($col, $schema[$t['table']], true)) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    report($problems, $primary, 'column missing (SELECT)', $primary . '.' . $col, $rel);
                }
            }
        }

        /* INSERT INTO table (cols) VALUES */
        if (preg_match('/\bINSERT\s+INTO\s+`?([A-Za-z0-9_]+)`?\s*\(([^)]*)\)/i', $sql, $im)) {
            $table = strtolower($im[1]);
            if (isset($schema[$table])) {
                $checked++;
                foreach (split_top_level($im[2]) as $c) {
                    $col = strtolower(trim($c, "` \t\n\r"));
                    if ($col === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $col)) {
                        continue;
                    }
                    if (!in_array($col, $schema[$table], true)) {
                        report($problems, $table, 'column missing (INSERT)', $table . '.' . $col, $rel);
                    }
                }
            }
        }

        /* UPDATE table SET col = ... */
        if (preg_match('/\bUPDATE\s+`?([A-Za-z0-9_]+)`?/i', $sql, $um)) {
            $table = strtolower($um[1]);
            $setPos = find_top_level($sql, 'SET');
            if (isset($schema[$table]) && $setPos !== -1) {
                $checked++;
                $setList = substr($sql, $setPos + 3);
                // stop at the next top-level WHERE
                $wherePos = find_top_level($setList, 'WHERE');
                if ($wherePos !== -1) {
                    $setList = substr($setList, 0, $wherePos);
                }
                foreach (split_top_level($setList) as $pair) {
                    if (!preg_match('/^`?([A-Za-z0-9_]+)`?\s*\.\s*`?([A-Za-z0-9_]+)`?\s*=/i', trim($pair), $pm)) {
                        if (!preg_match('/^`?([A-Za-z0-9_]+)`?\s*=/i', trim($pair), $pm)) {
                            continue;
                        }
                        $col = strtolower($pm[1]);
                    } else {
                        $col = strtolower($pm[2]);
                    }
                    if (!in_array($col, $schema[$table], true)) {
                        report($problems, $table, 'column missing (SET)', $table . '.' . $col, $rel);
                    }
                }
            }
        }
    }
}

// De-duplicate
$unique = [];
foreach ($problems as $p) {
    $unique[$p['file'] . '|' . $p['detail'] . '|' . $p['issue']] = $p;
}
$problems = array_values($unique);

if (empty($problems)) {
    echo "PASS — " . $checked . " statements checked, no schema mismatches found.\n";
    exit(0);
}

echo "FAIL — " . count($problems) . " schema mismatch(es) found:\n\n";
$byTable = [];
foreach ($problems as $p) {
    $byTable[$p['table']][] = $p;
}
foreach ($byTable as $table => $items) {
    echo "TABLE {$table}\n";
    foreach ($items as $p) {
        echo "  - [{$p['issue']}] {$p['detail']}  @ {$p['file']}\n";
    }
    echo "\n";
}
exit(1);
