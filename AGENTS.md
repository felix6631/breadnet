# Breadnet 작업 지침

## 프로젝트의 방향

PHP가 HTML을 렌더링하는 개인 블로그다. 링크와 일반 POST 폼을 사용한다.
React, TypeScript, 브라우저 JavaScript, 외부 라이브러리, 번들러를 추가하지 않는다.
한국어 UI와 기존 홈페이지 스타일을 유지한다. 런타임 요구 사항은 README.md와
app/bootstrap.php를 기준으로 한다. 버전 검사를 우회해 테스트하지 않는다.

## 읽기 순서와 변경 위치

1. README.md: 실행 환경, 제품 범위, 배포 제약.
2. app/bootstrap.php: DB 연결, 입력/출력, 세션, 인증, CSRF, 요청 제한.
3. public/index.php, admin.php, guestbook.php: 공개 목록, 관리, 방명록 처리.
4. templates/: 공통 HTML. public/style.css: 디자인.
5. bin/setup.php, reset-password.php: 설치와 자격 증명 갱신.
6. tests/smoke.py: 실제 HTTP 동작과 보안 회귀 검증.

## 작업과 검증

- `python3 bin/harness.py doctor`: PHP 버전·확장·프로젝트 ini 점검.
- `python3 bin/harness.py check`: 환경 점검 → PHP/Python 문법 검사 → HTTP 통합 테스트.
- `python3 bin/harness.py dev`: 임시 DB와 세션으로 로컬 서버 실행. 로그인 비밀번호는
  시작할 때 출력하며 종료하면 데이터는 삭제된다. 기존 DB를 사용하지 않는다.
- PHP 경로는 `--php /path/to/php` 또는 `BREADNET_PHP`로 지정한다.
- Windows에서는 `python3` 대신 `python`을 사용해도 된다.
- 변경에 맞는 동작을 기존 통합 테스트에 추가한다. 검증이 불가능하면 이유와
  실행하지 못한 검사를 보고하고, 통과했다고 표현하지 않는다.

## 지켜야 할 경계

- 웹 공개 루트는 public/뿐이다. DB, 설정, CLI, 테스트를 공개하지 않는다.
- 실제 var/ DB에 테스트 데이터를 넣거나 비밀번호를 재설정하지 않는다.
  `.runtime/`, DB, 비밀번호, 세션 파일은 커밋하지 않는다.
- SQL은 매개변수 바인딩, 사용자 출력은 e(), 변경 요청은 POST와 CSRF 검사를 유지한다.
- 방명록은 관리자 승인 뒤에 공개한다. 로그인 시 세션/CSRF 재발급과
  비밀번호 재설정 시 기존 세션 무효화를 유지한다.
- 요청 제한은 세션이 아닌 DB에서 처리하고 X-Forwarded-* 헤더를 신뢰하지 않는다.
- 스키마 변경은 기존 DB의 이전 방법을 함께 설계한다. setup.php는 신규 설치용이다.
- 제품 요구 없이 의존성, 프레임워크, 기능, 배포를 확장하지 않는다.
