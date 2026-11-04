<?php declare(strict_types=1);

/**
 * Schema Audit — cross-check every SQL statement in the codebase against
 * the live database schema.
 *
 * This is the guard that catches the class of bug that keeps production
 * pages crashing with "Unknown column 'x' in 'field list'":
 *   • every column written by db()->insert()/db()->update() must exist,
 *   • every column read in a SELECT list must exist,
 *   • every table referenced must exist.
 *
 * Usage:  php database/schema_audit.php
 * Exit code 0 = clean, 1 = problems found.
 */

require_once __DIR__ . '/../config/db.php';

const SKIP_DIRS = ['vendor', 'node_modules', 'cache', 'uploads', '.git', 'scratch'];

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
 * Extract quoted array keys that sit at the top level of an array literal.
 */
function top_level_keys(string $arrayBody): array
{
    $keys = [];
    $depth = 0;
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
            // A key is followed by => (ignoring whitespace)
            $rest = substr($arrayBody, $j + 1);
            if (preg_match('/^\s*=>/', $rest)) {
                $keys[] = strtolower($key);
            }
            $i = $j;
        }
    }
    return array_values(array_unique($keys));
}

/**
 * Split a SQL SELECT list on top-level commas.
 */
function split_select_list(string $list): array
{
    $parts = [];
    $depth = 0;
    $buf = '';
    $len = strlen($list);
    for ($i = 0; $i < $len; $i++) {
        $ch = $list[$i];
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

/** Reduce an expression to a bare column name when possible. */
function column_name(string $expr): ?string
{
    $expr = trim($expr);
    if ($expr === '' || $expr === '*') {
        return null;
    }
    // strip table alias prefix
    if (preg_match('/^`?\w+`?\.\s*`?([\w]+)`?$/', $expr, $m)) {
        $expr = $m[1];
    }
    $expr = trim($expr, '` ');
    if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $expr)) {
        return strtolower($expr);
    }
    return null; // expression / alias / function — not verifiable
}

/**
 * Read a column value from a result row regardless of the key casing the
 * driver used (information_schema comes back UPPERCASE, user tables lower).
 */
function row_value(array $row, array $keys): string
{
    foreach ($keys as $key) {
        if (isset($row[$key])) {
            return (string)$row[$key];
        }
    }
    foreach ($row as $k => $v) {
        if (is_string($k) && in_array(strtolower($k), array_map('strtolower', $keys), true)) {
            return (string)$v;
        }
    }
    return '';
}

$root = dirname(__DIR__);
[$files, $relFiles] = php_files($root);

echo "Schema audit — scanning " . count($files) . " PHP files against the live database\n";
echo str_repeat('-', 74) . "\n";

// Load live schema
$schema = [];
$tables = db()->fetchAll(
    "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'"
);
foreach ($tables as $t) {
    // information_schema is returned with UPPERCASE keys by the MySQL driver
    // (TABLE_NAME / COLUMN_NAME), so read them case-insensitively.
    $tableName = (string)row_value($t, ['table_name', 'TABLE_NAME']);
    if ($tableName === '') {
        continue;
    }
    $name = strtolower($tableName);
    $schema[$name] = [];
    foreach (db()->fetchAll(
        "SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?",
        [$tableName]
    ) as $c) {
        $col = (string)row_value($c, ['column_name', 'COLUMN_NAME']);
        if ($col !== '') {
            $schema[$name][] = strtolower($col);
        }
    }
}
echo "Live schema: " . count($schema) . " tables\n\n";

$problems = [];
$checked  = 0;

foreach ($files as $idx => $file) {
    $rel  = $relFiles[$idx];
    $code = (string)file_get_contents($file);

    // 1. db()->insert('table', [ 'col' => ... ]) / db()->update('table', [ ... ])
    if (preg_match_all(
        "/db\(\)->(?:insert|update)\(\s*['\"]([A-Za-z0-9_]+)['\"]\s*,\s*\[(.*?)\]\s*\)/s",
        $code,
        $m,
        PREG_SET_ORDER
    )) {
        foreach ($m as $hit) {
            $table = strtolower($hit[1]);
            if (!isset($schema[$table])) {
                $problems[] = ['table' => $table, 'issue' => 'table missing', 'detail' => $table, 'file' => $rel];
                continue;
            }
            $checked++;
            foreach (top_level_keys($hit[2]) as $col) {
                if (!in_array($col, $schema[$table], true)) {
                    $problems[] = [
                        'table'  => $table,
                        'issue'  => 'column missing',
                        'detail' => $table . '.' . $col,
                        'file'   => $rel,
                    ];
                }
            }
        }
    }

    // 2. SELECT column lists (quoted SQL strings)
    if (preg_match_all(
        "/SELECT\s+(.+?)\s+FROM\s+`?([A-Za-z0-9_]+)`?/is",
        $code,
        $m,
        PREG_SET_ORDER
    )) {
        foreach ($m as $hit) {
            $table = strtolower($hit[2]);
            if (!isset($schema[$table])) {
                // Only report when the table name is not a variable/expression.
                if (!preg_match('/^\$/', $hit[2])) {
                    $problems[] = ['table' => $table, 'issue' => 'table missing (SELECT)', 'detail' => $table, 'file' => $rel];
                }
                continue;
            }
            $checked++;
            foreach (split_select_list($hit[1]) as $expr) {
                $col = column_name($expr);
                if ($col !== null && !in_array($col, $schema[$table], true)) {
                    $problems[] = [
                        'table'  => $table,
                        'issue'  => 'column missing (SELECT)',
                        'detail' => $table . '.' . $col,
                        'file'   => $rel,
                    ];
                }
            }
        }
    }

    // 3. UPDATE/INSERT ... SET style statements
    if (preg_match_all(
        "/(?:UPDATE|INSERT\s+INTO)\s+`?([A-Za-z0-9_]+)`?\s+SET\s+(.+?)(?:\s+WHERE|$)/is",
        $code,
        $m,
        PREG_SET_ORDER
    )) {
        foreach ($m as $hit) {
            $table = strtolower($hit[1]);
            if (!isset($schema[$table])) {
                continue;
            }
            $checked++;
            foreach (split_select_list($hit[2]) as $pair) {
                if (!preg_match('/^`?([A-Za-z0-9_]+)`?\s*=/', trim($pair), $cm)) {
                    continue;
                }
                $col = strtolower($cm[1]);
                if (!in_array($col, $schema[$table], true)) {
                    $problems[] = [
                        'table'  => $table,
                        'issue'  => 'column missing (SET)',
                        'detail' => $table . '.' . $col,
                        'file'   => $rel,
                    ];
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
