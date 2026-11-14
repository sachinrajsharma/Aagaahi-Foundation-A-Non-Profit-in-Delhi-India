<?php
/**
 * database/migrate.php — applies the SQL files in database/migrations/ in order, once each.
 *
 * Use from the browser installer (install/), or from a terminal:   php database/migrate.php
 * Each file is recorded in the schema_migrations table, so running it again does nothing new.
 */
require_once __DIR__ . '/../includes/db.php';

/** Splits SQL text into single statements, ignoring ; inside quotes and comments. */
function sql_statements(string $sql): array
{
    $out = []; $cur = ''; $len = strlen($sql); $q = null;
    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i]; $nx = $i + 1 < $len ? $sql[$i + 1] : '';
        if ($q !== null) {                                    // inside '...' "..." or `...`
            $cur .= $ch;
            if ($ch === '\\' && $q !== '`') { $cur .= $nx; $i++; continue; }
            if ($ch === $q) { if ($nx === $q) { $cur .= $nx; $i++; } else $q = null; }
            continue;
        }
        if ($ch === '-' && $nx === '-' && ($i + 2 >= $len || ctype_space($sql[$i + 2]))) { while ($i < $len && $sql[$i] !== "\n") $i++; $cur .= "\n"; continue; }
        if ($ch === '#') { while ($i < $len && $sql[$i] !== "\n") $i++; $cur .= "\n"; continue; }
        if ($ch === '/' && $nx === '*') { $i += 2; while ($i + 1 < $len && !($sql[$i] === '*' && $sql[$i + 1] === '/')) $i++; $i++; continue; }
        if ($ch === "'" || $ch === '"' || $ch === '`') { $q = $ch; $cur .= $ch; continue; }
        if ($ch === ';') { if (trim($cur) !== '') $out[] = trim($cur); $cur = ''; continue; }
        $cur .= $ch;
    }
    if (trim($cur) !== '') $out[] = trim($cur);
    return $out;
}

/** @return array{applied: string[], skipped: string[]} */
function run_migrations(PDO $pdo, ?string $dir = null): array
{
    $dir = $dir ?? __DIR__ . '/migrations';
    $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (name VARCHAR(190) NOT NULL PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $done = $pdo->query('SELECT name FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    $files = glob($dir . '/*.sql') ?: [];
    sort($files);
    $result = ['applied' => [], 'skipped' => []];
    foreach ($files as $f) {
        $name = basename($f);
        if (in_array($name, $done, true)) { $result['skipped'][] = $name; continue; }
        foreach (sql_statements((string)file_get_contents($f)) as $stmt) $pdo->exec($stmt);   // DDL commits on its own in MySQL
        $pdo->prepare('INSERT INTO schema_migrations (name) VALUES (?)')->execute([$name]);
        $result['applied'][] = $name;
    }
    return $result;
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        $r = run_migrations(db_connect(false));
        echo 'Applied: ' . ($r['applied'] ? implode(', ', $r['applied']) : 'nothing new') . "\n";
        if ($r['skipped']) echo 'Already applied: ' . implode(', ', $r['skipped']) . "\n";
    } catch (Throwable $e) { fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n"); exit(1); }
}
