<?php
declare(strict_types=1);

// A virtual filesystem and explicit program registry. No host filesystem or OS commands.
function terminal_files(): array
{
    return [
        '/home/guest/readme.txt' => "Welcome to Breadnet's guest home.\nTry: help, ls, cat about.txt, cd programs, bread\nThis home belongs to your browser session.\nUse note TEXT to replace notes.txt. No real server files are exposed.",
        '/home/guest/about.txt' => "BREADNET / a small handmade corner of the internet.\nHTML + CSS + PHP. Make yourself at home.",
        '/home/guest/notes.txt' => $_SESSION['terminal']['note'],
        '/home/guest/programs/readme.txt' => "Available programs:\n  hello [name]   A tiny CLI greeting.\n  bread          An interactive TUI bakery.\nFuture CLI/TUI executables need a separate isolated runner + PTY.\nThis demo never launches host processes.",
    ];
}
function terminal_programs(): array
{
    // Names map to our own handlers, never a user-supplied executable or function.
    return ['hello' => 'CLI greeting', 'bread' => 'TUI bakery demo'];
}
function terminal_path(string $path, string $cwd): ?string
{
    if ($path === '~') $path = '/home/guest';
    elseif (str_starts_with($path, '~/')) $path = '/home/guest/' . substr($path, 2);
    $parts = [];
    foreach (explode('/', str_starts_with($path, '/') ? $path : $cwd . '/' . $path) as $part) {
        if ($part === '' || $part === '.') continue;
        if ($part === '..') array_pop($parts); else $parts[] = $part;
    }
    $result = '/' . implode('/', $parts);
    return $result === '/home/guest' || str_starts_with($result, '/home/guest/') ? $result : null;
}
function terminal_prompt(): string
{
    return 'guest@breadnet:' . str_replace('/home/guest', '~', $_SESSION['terminal']['cwd']) . '$';
}
function terminal_reply(string $output = '', array $extra = []): array
{
    return array_merge(['output' => $output, 'prompt' => terminal_prompt(),
        'mode' => $_SESSION['terminal']['program'] ? 'tui' : 'shell', 'cols' => 80, 'rows' => 24], $extra);
}
function terminal_tui(): string
{
    $t = $_SESSION['terminal'];
    $lines = ['+------------------------------------------------------------------------------+',
        '| BREADNET BAKERY                                         guest / demo program |',
        '+------------------------------------------------------------------------------+', '', '   Choose a loaf:', ''];
    foreach (['Country bread', 'Butter toast', 'Cinnamon roll'] as $i => $name) {
        $lines[] = ($t['selection'] === $i ? ' > ' : '   ') . $name;
    }
    $lines[] = ''; $lines[] = '   ' . $t['message'];
    while (count($lines) < 21) $lines[] = '';
    $lines[] = '   Up/Down or j/k: choose    Enter: bake    q / Esc / Ctrl+C: return';
    $lines[] = '+------------------------------------------------------------------------------+';
    return implode("\n", $lines);
}
function terminal_handle(string $command, string $key = ''): array
{
    $_SESSION['terminal'] ??= ['cwd' => '/home/guest', 'note' => "Your session notebook.\n", 'program' => null,
        'selection' => 0, 'message' => 'The oven is ready.'];
    $t =& $_SESSION['terminal'];
    if ($t['program']) {
        if (in_array($key, ['q', 'Escape', 'ControlC'], true)) {
            $t['program'] = null; return terminal_reply('Back in your guest home.', ['clear' => true]);
        }
        if ($key === 'ArrowUp' || $key === 'k') $t['selection'] = ($t['selection'] + 2) % 3;
        if ($key === 'ArrowDown' || $key === 'j') $t['selection'] = ($t['selection'] + 1) % 3;
        if ($key === 'Enter') $t['message'] = ['Country bread', 'Butter toast', 'Cinnamon roll'][$t['selection']] . ' is freshly baked!';
        return terminal_reply(terminal_tui(), ['screen' => true]);
    }
    if ($key !== '') return terminal_reply();
    if ($command === '') return terminal_reply("Breadnet guest terminal / 80 x 24\nLogged in as guest. Type help to begin.\n");
    // Quotes group arguments; shell syntax is never evaluated.
    preg_match_all('/"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"|\'([^\']*)\'|(\S+)/u', $command, $matches, PREG_SET_ORDER);
    $args = array_map(static fn($m) => str_starts_with($m[0], '"') ? stripcslashes($m[1]) :
        (str_starts_with($m[0], "'") ? $m[2] : $m[0]), $matches);
    $name = array_shift($args) ?? '';
    if (str_contains($name, '/')) {
        $path = terminal_path($name, $t['cwd']);
        $name = $path !== null && dirname($path) === '/home/guest/programs' ? basename($path) : $name;
    }
    $dirs = ['/home/guest', '/home/guest/programs'];
    $files = terminal_files();
    switch ($name) {
        case 'help': return terminal_reply("help                 Show this guide\nls [path]            List your guest home\npwd / cd [path]      Print / change directory\ncat FILE             Read a virtual file\nwhoami / date        Guest identity / server time\necho [text]          Print text (no pipes or redirection)\nnote TEXT            Replace your session's notes.txt (max 2,000 chars)\nprograms             List available CLI/TUI demos\nhello [name]         Run the CLI demo\nbread                Run the interactive TUI demo\nclear / reset / exit Clear screen / reset guest home / close terminal\nUp/Down: history; Ctrl+L: clear; Ctrl+C: cancel. No system shell.");
        case 'pwd': return terminal_reply($t['cwd']);
        case 'whoami': return terminal_reply('guest');
        case 'date': return terminal_reply(date('Y-m-d H:i:s T'));
        case 'echo': return terminal_reply(implode(' ', $args));
        case 'clear': return terminal_reply('', ['clear' => true]);
        case 'exit': return terminal_reply('See you around, guest.', ['exit' => true]);
        case 'reset': unset($_SESSION['terminal']); return terminal_handle('') + ['clear' => true];
        case 'note':
            $note = implode(' ', $args);
            if (mb_strlen($note) > 2000) return terminal_reply('note: maximum 2,000 characters.');
            $t['note'] = $note . "\n"; return terminal_reply('Saved ~/notes.txt for this session.');
        case 'cd':
            $path = terminal_path($args[0] ?? '~', $t['cwd']);
            if (!in_array($path, $dirs, true)) return terminal_reply('cd: no such guest directory.');
            $t['cwd'] = $path; return terminal_reply();
        case 'cat':
            if (!$args) return terminal_reply('Usage: cat FILE');
            $path = terminal_path($args[0], $t['cwd']);
            return terminal_reply($files[$path ?? ''] ?? 'cat: no such guest file.');
        case 'ls':
            $path = terminal_path($args[0] ?? '.', $t['cwd']);
            if (!in_array($path, $dirs, true)) return terminal_reply('ls: no such guest directory.');
            $entries = [];
            foreach ($dirs as $dir) if (dirname($dir) === $path) $entries[] = basename($dir) . '/';
            foreach ($files as $file => $_) if (dirname($file) === $path) $entries[] = basename($file);
            if ($path === '/home/guest/programs') $entries = array_merge($entries, array_keys(terminal_programs()));
            return terminal_reply(implode('  ', $entries));
        case 'programs':
            $lines = []; foreach (terminal_programs() as $program => $description) $lines[] = $program . ' - ' . $description;
            return terminal_reply(implode("\n", $lines));
        case 'hello': return terminal_reply('Hello, ' . ($args ? implode(' ', $args) : 'guest') . '! Welcome to Breadnet.');
        case 'bread':
            $t['program'] = 'bread'; return terminal_reply(terminal_tui(), ['screen' => true]);
        default: return terminal_reply($name . ': command not found. Try help.');
    }
}
