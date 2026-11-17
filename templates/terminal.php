<div class="terminal-overlay" id="guest-terminal"<?= empty($terminalStandalone) ? ' hidden' : '' ?> data-standalone="<?= empty($terminalStandalone) ? 'no' : 'yes' ?>" data-csrf="<?= e($_SESSION['csrf']) ?>">
<div class="terminal-window" role="dialog" aria-label="Breadnet 게스트 터미널" tabindex="-1">
<div class="terminal-title"><b>guest@breadnet — 80 × 24</b><button type="button" class="terminal-close" aria-label="터미널 닫기">×</button></div>
<div class="terminal-scroll"><div class="terminal-display">
<pre class="terminal-output" aria-label="터미널 출력" tabindex="0"></pre>
<form class="terminal-input" autocomplete="off"><label for="terminal-command" class="terminal-prompt">guest@breadnet:~$</label><input id="terminal-command" name="command" maxlength="4096" autocomplete="off" autocapitalize="off" spellcheck="false" aria-label="터미널 명령어"><button type="submit" class="terminal-send">↵</button></form>
</div></div>
<div class="terminal-status" role="status" aria-live="polite">연결 준비 중 · help: 도움말 · bread: TUI 데모</div>
<div class="terminal-keys" aria-label="TUI 조작"><button type="button" data-key="ArrowUp">↑</button><button type="button" data-key="ArrowDown">↓</button><button type="button" data-key="Enter">Enter</button><button type="button" data-key="q">q: 나가기</button></div>
</div></div>
