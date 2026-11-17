# 게스트 터미널 확장

## 현재 구조

`templates/terminal.php`의 80×24 창 → `public/terminal.js` → CSRF 보호 POST `/terminal-api.php` → `app/terminal.php`의 명령 처리 순서로 동작합니다. 외부 라이브러리와 명령 실행 함수는 사용하지 않습니다. 프로그램 이름은 명시적 레지스트리에 있고 PHP 핸들러로만 처리합니다.

가상 파일과 세션 메모는 `terminal_files()`, 프로그램 목록은 `terminal_programs()`, 실행 분기는 `terminal_handle()`에 있습니다. 새 가상 데모를 추가할 때 목록과 실행 분기를 함께 추가하세요. 이름이나 경로를 사용자 입력에서 받아 `exec`, `system`, `shell_exec`, `proc_open`, 동적 함수 호출에 넘기지 마세요.

## 요청과 응답

요청은 같은 출처의 form-urlencoded POST입니다.

```text
csrf=<session token>&command=ls&key=
```

`command`는 최대 4,096자이며 TUI는 `key`로 `ArrowUp`, `ArrowDown`, `j`, `k`, `Enter`, `q`, `Escape`, `ControlC`를 받습니다. 세션당 분당 240요청을 허용합니다. 입력은 PHP 세션 잠금으로 직렬화됩니다.

```json
{
  "output": "readme.txt  about.txt",
  "prompt": "guest@breadnet:~$",
  "mode": "shell",
  "cols": 80,
  "rows": 24
}
```

`clear: true`는 스크롤백 초기화, `screen: true`는 전체 TUI 화면 교체, `exit: true`는 창 닫기입니다. 출력은 HTML 대신 `textContent`로 표시하며 제어 문자는 제거합니다. 현재 화면은 23행 출력 + 1행 입력으로 구성됩니다. ANSI escape 시퀀스나 실제 프로세스의 바이트 스트림을 해석하는 터미널 에뮬레이터는 아닙니다.

## 실제 CLI/TUI 프로그램 연결 시

실행 파일이 정해지면 PHP 웹 프로세스와 별도로 **격리된 실행 서비스**를 구성합니다. PHP의 세션 ID나 관리자 쿠키를 실행 환경에 전달하지 말고, 검증된 세션에 연결된 짧은 수명의 별도 티켓으로 접근시킵니다.

실제 TUI는 PTY에서 TERM, 80×24 창 크기, 입력 바이트, 종료 신호, 화면 크기 변경을 처리해야 합니다. 스트리밍 전송과 ANSI/VT 터미널 에뮬레이터도 필요하므로 현재 데모 응답을 그대로 실제 TUI에 연결할 수 없습니다.

실행 서비스는 프로그램 ID를 서버의 고정 실행 파일 목록에 매핑하고, 셸 문자열을 조립하지 않아야 합니다. 방문자별 비특권 컨테이너/격리 환경에 임시 홈을 주고 호스트 파일·DB·비밀키를 마운트하지 않습니다. 네트워크는 기본 차단하며 CPU·메모리·프로세스 수·디스크·출력량·최대 실행 시간·유휴 시간을 제한합니다. 연결 종료와 만료 시 프로세스와 작업 공간도 정리합니다.

프로그램별 요구 런타임, 파일 쓰기 필요 여부, 네트워크 필요 여부를 정한 뒤 runner를 선택하세요. 현재 작업은 이 실행 서비스를 공개하거나 임의 프로세스를 실행하지 않습니다.
