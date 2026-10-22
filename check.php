<?php
/**
 * Checks the three example repositories and their agent instruction files.
 *
 * Usage: php check.php --allow-composer-install [wordpress-plugin|woocommerce-extension|magento-module ...]
 *        php check.php --static-only [wordpress-plugin|woocommerce-extension|magento-module ...]
 *
 * It refuses to run without one of the two flags. --static-only reads the example folders
 * and changes nothing. --allow-composer-install also copies each example to a temporary
 * folder, runs `composer install` there (downloads packages from Packagist and runs their
 * Composer plugins) and runs the commands listed in the example's AGENTS.md. The examples
 * themselves are never changed.
 *
 * For each example it:
 *   1. checks AGENTS.md, CLAUDE.md and .claude/settings.json against the rules the
 *      accompanying article describes (static checks);
 *   2. copies the example to a temporary directory and runs each command from the
 *      first Bash block in its AGENTS.md "Commands" section, one line per command,
 *      expecting exit 0;
 *   3. breaks the code on purpose and confirms the lint and test commands then fail;
 *   4. feeds deliberately broken instruction files to the static checks and confirms
 *      each one is caught.
 *
 * Needs PHP 8.3 or later. The full run also needs Composer on PATH as `composer` and
 * network access to Packagist. Exit codes: 0 all checks passed, 1 a check failed,
 * 2 cannot run or not confirmed.
 */

declare(strict_types=1);

const CODEX_DEFAULT_MAX_BYTES = 32768; // Codex project_doc_max_bytes default, 32 KiB.

const REQUIRED_SECTIONS = ['Commands', 'Before you propose a change', 'Coding standards', 'No-go zones', 'Other agent files'];

/**
 * Secret-bearing files that, when named in the No-go section, must have a Read deny rule.
 * "anywhere" means the file normally sits above a checked-out plugin or module, so the
 * rule must be anchored at the filesystem root (//**) to reach parent directories.
 */
const SECRET_FILES = [
    'wp-config.php' => 'anywhere',
    'app/etc/env.php' => 'anywhere',
    'auth.json' => 'anywhere',
    '.env' => 'here',
    '.env.*' => 'here',
];

const EXAMPLES = [
    'wordpress-plugin' => [
        'lint_break' => ['acme-reading-time.php', "return '<p class=\"acme-reading-time\">' . esc_html( \$label ) . '</p>' . \$content;", "echo '<p>' . \$label . '</p>';\n\treturn \$content;"],
        'test_break' => ['includes/class-acme-reading-time.php', '(int) ceil(', '(int) floor('],
    ],
    'woocommerce-extension' => [
        'lint_break' => ['acme-free-shipping-nudge.php', "\twc_print_notice(", "\techo \$threshold;\n\twc_print_notice("],
        'test_break' => ['includes/class-acme-free-shipping-nudge.php', 'return max( 0, $threshold - $subtotal );', 'return $threshold - $subtotal;'],
    ],
    'magento-module' => [
        'lint_break' => ['Model/DispatchDate.php', '$this->cutoffHour = $cutoffHour;', "\$this->cutoffHour = \$cutoffHour;\n        var_dump(\$_GET);"],
        'test_break' => ['Model/DispatchDate.php', "format('N') >= 6", "format('N') >= 7"],
    ],
];

$passed = 0;
$failed = 0;

function report(bool $ok, string $name, string $detail = ''): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . ($detail !== '' && !$ok ? "\n     " . str_replace("\n", "\n     ", $detail) : '') . "\n";
}

/**
 * Tags each Markdown line as [text, inside a fenced block, is a fence delimiter].
 * A fence opens with three or more backticks or tildes (up to three spaces of indent)
 * and closes with the same character at least as long. An unclosed fence runs to the
 * end of the file. This is a deliberately small scanner, not a full Markdown parser.
 */
function fenced_lines(string $markdown): array
{
    $out = [];
    $fence = null;
    foreach (explode("\n", $markdown) as $line) {
        if ($fence !== null) {
            $close = '/^ {0,3}' . preg_quote($fence[0], '/') . '{' . $fence[1] . ',}[ \t]*$/';
            $isClose = preg_match($close, $line) === 1;
            $out[] = [$line, true, $isClose];
            if ($isClose) {
                $fence = null;
            }
            continue;
        }
        if (preg_match('/^ {0,3}(`{3,}|~{3,})(.*)$/', $line, $m) === 1 && ($m[1][0] !== '`' || !str_contains($m[2], '`'))) {
            $fence = [$m[1][0], strlen($m[1])];
            $out[] = [$line, true, true];
            continue;
        }
        $out[] = [$line, false, false];
    }
    return $out;
}

/** Returns the text under a "## Heading" up to the next level-two heading outside code, or null. */
function section(string $markdown, string $heading): ?string
{
    $body = null;
    foreach (fenced_lines($markdown) as [$line, $inFence]) {
        if (!$inFence && preg_match('/^## (.+?)\s*$/', $line, $m) === 1) {
            if ($body !== null) {
                break;
            }
            if ($m[1] === $heading) {
                $body = [];
            }
            continue;
        }
        if ($body !== null) {
            $body[] = $line;
        }
    }
    return $body === null ? null : implode("\n", $body);
}

/**
 * Returns the commands in the first ```bash fence of the text, or null. Blank lines and
 * comment lines are dropped. Each command must occupy one line: a line ending in a
 * backslash is returned unchanged, and static_problems() rejects it, because joining
 * lines here cannot reproduce the shell's own continuation rules.
 */
function first_bash_block(string $text): ?array
{
    $lines = null;
    foreach (fenced_lines($text) as [$line, $inFence, $delimiter]) {
        if ($lines === null) {
            if ($delimiter && preg_match('/^ {0,3}(`{3,}|~{3,})\s*bash\s*$/', $line) === 1) {
                $lines = [];
            }
            continue;
        }
        if ($delimiter || !$inFence) {
            break;
        }
        $lines[] = $line;
    }
    if ($lines === null) {
        return null;
    }
    $commands = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $commands[] = $line;
    }
    return $commands;
}

/** Removes fenced code blocks and inline code spans, which Claude Code skips when parsing imports. */
function without_code(string $markdown): string
{
    $kept = [];
    foreach (fenced_lines($markdown) as [$line, $inFence]) {
        if (!$inFence) {
            $kept[] = $line;
        }
    }
    return preg_replace('/(`+).+?\1/s', '', implode("\n", $kept));
}

/** Returns @path imports found outside code, in the order they appear. */
function claude_imports(string $markdown): array
{
    preg_match_all('/(?:^|\s)@([^\s]+)/m', without_code($markdown), $m);
    return $m[1];
}

/** Lines worth comparing for duplication: non-empty, not headings, not the import itself. */
function content_lines(string $markdown): array
{
    $lines = [];
    foreach (explode("\n", without_code($markdown)) as $line) {
        $norm = strtolower(trim(preg_replace('/\s+/', ' ', ltrim(trim($line), "-*0123456789. "))));
        if ($norm === '' || str_starts_with(trim($line), '#') || str_starts_with($norm, '@')) {
            continue;
        }
        $lines[] = $norm;
    }
    return $lines;
}

/**
 * Static checks on one example's instruction files. $files maps relative path to contents
 * (null when absent). Returns a list of problems; an empty list means the files pass.
 */
function static_problems(array $files): array
{
    $problems = [];
    $agents = $files['AGENTS.md'] ?? null;
    if ($agents === null) {
        return ['AGENTS.md is missing'];
    }

    if (strlen($agents) > CODEX_DEFAULT_MAX_BYTES) {
        $problems[] = sprintf('AGENTS.md is %d bytes; Codex stops adding instructions at %d bytes by default', strlen($agents), CODEX_DEFAULT_MAX_BYTES);
    }

    foreach (REQUIRED_SECTIONS as $heading) {
        if (section($agents, $heading) === null) {
            $problems[] = "AGENTS.md has no \"## {$heading}\" section";
        }
    }

    $commands = first_bash_block(section($agents, 'Commands') ?? '');
    if ($commands === null || $commands === []) {
        $problems[] = 'the Commands section has no ```bash block of commands';
    } else {
        foreach (preg_grep('/\\\\$/', $commands) as $continued) {
            $problems[] = "a Commands line ends in a backslash (`{$continued}`); write each command on one line, with no trailing backslash";
        }
        foreach (['lint', 'test'] as $word) {
            if (!preg_grep('/\b' . $word . '\b/', $commands)) {
                $problems[] = "the Commands block has no {$word} command";
            }
        }
    }

    $claude = $files['CLAUDE.md'] ?? null;
    if ($claude !== null) {
        $imports = claude_imports($claude);
        if (($imports[0] ?? null) !== 'AGENTS.md') {
            $problems[] = 'CLAUDE.md exists but does not import @AGENTS.md first, outside code; Claude Code then reads CLAUDE.md instead of AGENTS.md';
        }
        $duplicates = array_intersect(content_lines($claude), content_lines($agents));
        if ($duplicates !== []) {
            $problems[] = 'CLAUDE.md repeats AGENTS.md: "' . reset($duplicates) . '"';
        }
    }

    $nogo = section($agents, 'No-go zones') ?? '';
    $settings = $files['.claude/settings.json'] ?? null;
    $deny = [];
    if ($settings !== null) {
        $decoded = json_decode($settings, true);
        if (!is_array($decoded) || !is_array($decoded['permissions']['deny'] ?? null)) {
            $problems[] = '.claude/settings.json is not valid JSON with a permissions.deny list';
        } else {
            $deny = $decoded['permissions']['deny'];
        }
    }
    foreach (SECRET_FILES as $name => $where) {
        if (!str_contains($nogo, '`' . $name . '`')) {
            continue;
        }
        $expected = $where === 'anywhere' ? "Read(//**/{$name})" : "Read({$name})";
        if (!in_array($expected, $deny, true)) {
            $problems[] = "No-go zones names {$name} but .claude/settings.json has no {$expected} deny rule";
        }
    }
    if (str_contains($nogo, '`vendor/`') && !in_array('Edit(vendor/**)', $deny, true)) {
        $problems[] = 'No-go zones names vendor/ but .claude/settings.json has no Edit(vendor/**) deny rule';
    }

    return $problems;
}

/**
 * Production autoload rules must not reach test code. In the Magento example this keeps
 * the ComponentRegistrar stand-in out of any Magento project that installs the module.
 *
 * This is a restricted guard for these examples, not proof that an arbitrary Composer
 * configuration loads no test code: it rejects paths naming a test directory, and any
 * classmap entry that is a wildcard or the package root, because those can reach one.
 */
function autoload_problems(string $composerJson): array
{
    $autoload = json_decode($composerJson, true)['autoload'] ?? [];
    $paths = [];
    array_walk_recursive($autoload, static function ($path) use (&$paths) {
        $paths[] = (string) $path;
    });
    $bad = array_values(preg_grep('#(^|/)(tests?|Test)(/|$)#', $paths));
    foreach ((array) ($autoload['classmap'] ?? []) as $path) {
        $path = (string) $path;
        if (strpbrk($path, '*?[') !== false || in_array(trim($path, '/'), ['', '.', '..'], true)) {
            $bad[] = $path;
        }
    }
    return $bad === [] ? [] : ['composer.json "autoload" can reach test code: ' . implode(', ', array_unique($bad))];
}

function read_instruction_files(string $dir): array
{
    $files = [];
    foreach (['AGENTS.md', 'CLAUDE.md', '.claude/settings.json'] as $rel) {
        $files[$rel] = is_file("{$dir}/{$rel}") ? file_get_contents("{$dir}/{$rel}") : null;
    }
    return $files;
}

function copy_tree(string $from, string $to): void
{
    mkdir($to, 0777, true);
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($items as $item) {
        $rel = substr($item->getPathname(), strlen($from) + 1);
        if ($rel === 'vendor' || str_starts_with($rel, 'vendor/') || $rel === 'composer.lock') {
            continue;
        }
        is_dir($item->getPathname()) ? mkdir("{$to}/{$rel}", 0777, true) : copy($item->getPathname(), "{$to}/{$rel}");
    }
}

function remove_tree(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) {
        is_dir($item->getPathname()) && !is_link($item->getPathname()) ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

/** Runs one shell command in $cwd. Returns [exit code, combined output]. */
function run(string $command, string $cwd): array
{
    $process = proc_open(['/bin/sh', '-c', $command . ' 2>&1'], [1 => ['pipe', 'w']], $pipes, $cwd);
    if (!is_resource($process)) {
        return [127, "could not start: {$command}"];
    }
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    return [proc_close($process), (string) $output];
}

function tail(string $output, int $lines = 15): string
{
    return implode("\n", array_slice(explode("\n", rtrim($output)), -$lines));
}

/** Applies a one-off replacement to a file in the scratch copy, runs a command, restores the file. */
function run_broken(string $work, array $break, string $command): array
{
    [$rel, $search, $replace] = $break;
    $path = "{$work}/{$rel}";
    $original = file_get_contents($path);
    if (substr_count($original, $search) !== 1) {
        return [null, "break target not found exactly once in {$rel}"];
    }
    file_put_contents($path, str_replace($search, $replace, $original));
    try {
        return run($command, $work);
    } finally {
        file_put_contents($path, $original);
    }
}

/** Deliberately broken variants of an example's instruction files, each of which must be caught. */
function broken_variants(array $files): array
{
    $agents = $files['AGENTS.md'];
    $claude = $files['CLAUDE.md'];
    $settings = json_decode($files['.claude/settings.json'], true);
    preg_match('/^- .+$/m', section($agents, 'No-go zones') ?? '', $firstRule);
    $variants = [
        'CLAUDE.md without the import' => ['CLAUDE.md' => preg_replace('/^@AGENTS\.md\s*$/m', 'Read AGENTS.md before starting.', $claude)],
        'CLAUDE.md import inside backticks' => ['CLAUDE.md' => preg_replace('/^@AGENTS\.md\s*$/m', '`@AGENTS.md`', $claude)],
        'CLAUDE.md repeating an AGENTS.md rule' => ['CLAUDE.md' => $claude . "\n" . ($firstRule[0] ?? '- (no No-go rule found to copy)') . "\n"],
        'AGENTS.md over the Codex size limit' => ['AGENTS.md' => $agents . str_repeat("<!-- padding -->\n", 2100)],
        'CLAUDE.md import after an unclosed fence' => ['CLAUDE.md' => "~~~markdown\n" . $claude],
        // Callbacks keep the replacement literal; preg_replace would turn two backslashes into one.
        'a Commands line ending in an escaped backslash' => ['AGENTS.md' => preg_replace_callback('/^composer test$/m', static fn () => "composer test\nprintf %s marker\\\\\nfalse", $agents, 1)],
        'a command split by a line continuation' => ['AGENTS.md' => preg_replace_callback('/^composer lint$/m', static fn () => "compo\\\nser lint", $agents, 1)],
        'AGENTS.md without a Commands section' => ['AGENTS.md' => str_replace('## Commands', '## Build', $agents)],
        'AGENTS.md wrapped in a code fence' => ['AGENTS.md' => "````markdown\n" . $agents . "````\n"],
    ];
    foreach ($settings['permissions']['deny'] as $rule) {
        $without = array_values(array_diff($settings['permissions']['deny'], [$rule]));
        $variants["settings.json without {$rule}"] = ['.claude/settings.json' => json_encode(['permissions' => ['deny' => $without]])];
    }
    foreach ($settings['permissions']['deny'] as $rule) {
        if (preg_match('#^Read\(//\*\*/(.+)\)$#', $rule, $m) === 1) {
            $relative = array_map(static fn ($r) => $r === $rule ? "Read({$m[1]})" : $r, $settings['permissions']['deny']);
            $variants["settings.json with a relative {$m[1]} rule"] = ['.claude/settings.json' => json_encode(['permissions' => ['deny' => $relative]])];
        }
    }
    return $variants;
}

const FLAG_FULL = '--allow-composer-install';
const FLAG_STATIC = '--static-only';

if (PHP_VERSION_ID < 80300) {
    fwrite(STDERR, "check.php needs PHP 8.3 or later; this is PHP " . PHP_VERSION . ".\n");
    exit(2);
}

$args = array_slice($argv, 1);
$flags = array_values(array_filter($args, static fn ($arg) => str_starts_with($arg, '--')));
$names = array_values(array_diff($args, $flags));
foreach (array_diff($flags, [FLAG_FULL, FLAG_STATIC]) as $unknown) {
    fwrite(STDERR, "Unknown option: {$unknown}\n");
    exit(2);
}
$full = in_array(FLAG_FULL, $flags, true);
$staticOnly = in_array(FLAG_STATIC, $flags, true);
if ($full && $staticOnly) {
    fwrite(STDERR, "Pass either " . FLAG_FULL . " or " . FLAG_STATIC . ", not both.\n");
    exit(2);
}
if (!$full && !$staticOnly) {
    fwrite(STDERR, "Warning: the full run copies the examples to a temporary folder, runs `composer install` there (it downloads packages from Packagist and runs their Composer plugins) and runs the commands listed in each AGENTS.md. Use a development machine. Not run: pass " . FLAG_FULL . " to confirm, or " . FLAG_STATIC . " for the read-only checks.\n");
    exit(2);
}
if ($full) {
    fwrite(STDERR, "Warning: running composer install and the AGENTS.md commands in a temporary copy of each example, at your own risk.\n");
    if (run('command -v composer', __DIR__)[0] !== 0) {
        fwrite(STDERR, "Composer must be on PATH as `composer`.\n");
        exit(2);
    }
}

$selected = $names ?: array_keys(EXAMPLES);
foreach ($selected as $name) {
    if (!isset(EXAMPLES[$name])) {
        fwrite(STDERR, "Unknown example: {$name}\n");
        exit(2);
    }
}

$scratch = sys_get_temp_dir() . '/agents-md-check-' . bin2hex(random_bytes(4));
register_shutdown_function(static fn () => remove_tree($scratch));

foreach ($selected as $name) {
    echo "\n== {$name} (PHP " . PHP_VERSION . ")\n";
    $dir = __DIR__ . '/' . $name;
    $files = read_instruction_files($dir);

    $problems = static_problems($files);
    report($problems === [], "{$name}: instruction files pass the static checks", implode("\n", $problems));

    $stray = array_filter(['vendor', 'composer.lock', 'wp-config.php', 'app/etc/env.php', 'auth.json', '.env', 'CLAUDE.local.md'], static fn ($p) => file_exists("{$dir}/{$p}"));
    report($stray === [], "{$name}: no dependencies, secrets or local instruction files in the example", implode(', ', $stray));

    $composerJson = file_get_contents("{$dir}/composer.json");
    $autoload = autoload_problems($composerJson);
    report($autoload === [], "{$name}: production autoload passes the restricted path checks", implode("\n", $autoload));
    $testDir = $name === 'magento-module' ? 'Test/Unit/_files/' : 'tests/';
    foreach ([$testDir, 'T*/', '.'] as $leak) {
        $leaky = json_decode($composerJson, true);
        $leaky['autoload']['classmap'][] = $leak;
        report(autoload_problems(json_encode($leaky)) !== [], "{$name}: autoload check catches classmap entry \"{$leak}\"");
    }

    foreach (broken_variants($files) as $label => $override) {
        $caught = static_problems(array_merge($files, $override)) !== [];
        report($caught, "{$name}: static checks catch {$label}");
    }

    if ($staticOnly) {
        continue;
    }

    $work = "{$scratch}/{$name}";
    copy_tree($dir, $work);
    $commands = first_bash_block(section($files['AGENTS.md'], 'Commands') ?? '') ?? [];
    $allRan = true;
    foreach ($commands as $command) {
        [$code, $output] = run($command, $work);
        report($code === 0, "{$name}: `{$command}` exits 0", "exit {$code}\n" . tail($output));
        $allRan = $allRan && $code === 0;
    }
    if (!$allRan) {
        report(false, "{$name}: skipped the break checks because a command failed");
        continue;
    }

    $lintCommand = preg_grep('/\blint\b/', $commands);
    $testCommand = preg_grep('/\btest\b/', $commands);
    foreach (['lint' => [reset($lintCommand), 'lint_break'], 'test' => [reset($testCommand), 'test_break']] as $kind => [$command, $key]) {
        [$code, $output] = run_broken($work, EXAMPLES[$name][$key], $command);
        // Name the sniff or assertion, so a crash cannot pass for a real finding.
        $reason = $kind === 'lint'
            ? (preg_match('/\(((?:[A-Za-z0-9]+\.){2,3}[A-Za-z0-9]+)\)/', $output, $m) === 1 ? $m[1] : '')
            : (preg_match('/Failed asserting [^\n]+/', $output, $m) === 1 ? $m[0] : '');
        report($code !== null && $code !== 0 && $reason !== '', "{$name}: `{$command}` fails when the code is broken on purpose [{$reason}]", $code === null ? $output : "exit {$code}\n" . tail($output));
    }

    [$code] = run(reset($lintCommand), $work);
    report($code === 0, "{$name}: lint passes again after the broken file is restored");
}

echo "\n{$passed} passed, {$failed} failed\n";
if ($staticOnly) {
    echo "Read-only checks only: the commands and the break-on-purpose checks were not run. Use " . FLAG_FULL . " for those.\n";
}
exit($failed === 0 ? 0 : 1);
