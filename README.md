# Breadnet

웹 2.0 이전의 제작 방식으로 만드는 개인 블로그. PHP가 HTML을 만들고 브라우저는 링크와 일반 폼으로 이동합니다. React, TypeScript, JavaScript, 외부 라이브러리와 빌드 과정이 없습니다. 런타임과 보안은 현대 기준을 따릅니다.

## 기능

- 일기 목록과 페이지 이동
- 관리자 로그인, 일기 작성·수정·삭제, 로그아웃
- 익명 방명록 작성, 관리자 승인 후 공개, 삭제와 페이지 이동
- SQLite 저장, SQL 매개변수 바인딩, HTML 출력 이스케이프
- CSRF, 로그인 시 세션 ID 재발급, 2시간 만료
- 비밀번호 해시, CLI 재설정과 기존 세션 즉시 무효화
- IP 기반 로그인 제한(15분에 5회), 방명록 제한(10분에 3회)

이름과 방명록 글은 공개됩니다. 제한용 IP는 DB의 임의 키를 이용한 HMAC으로만 저장하고 만료된 제한 기록은 다음 시도 때 정리합니다. 웹서버 로그에는 IP가 남을 수 있습니다. 방문 카운터와 음악 재생은 없습니다.

## Windows에서 시작

이번 작업에서 `.runtime/php/`에 공식 Windows PHP 8.5.12-1 NTS 빌드를 받아 SHA-256을 확인했습니다. 이 폴더는 Git에 포함되지 않습니다. 다른 PC에서는 공식 PHP 안정 버전과 PDO SQLite, mbstring 확장을 설치하세요. 시스템 PHP 사용 시 프로젝트 ini를 불러와도 두 확장이 활성화되는지 확인하세요.

프로젝트 폴더의 PowerShell에서:

```powershell
.\php.ps1 bin/setup.php
.\dev.ps1
```

첫 명령이 DB와 관리자 `breadkeeper`를 만들고 임의 비밀번호를 한 번 출력합니다. 비밀번호 관리자에 보관하세요. 기존 DB는 덮어쓰지 않습니다. 실제 사용 DB와 관리자 계정은 아직 생성하지 않았습니다.

브라우저에서 http://127.0.0.1:8000/ 을 열고 `/admin.php`에서 로그인하세요. 개발 서버는 루프백에만 바인딩됩니다. 종료는 Ctrl+C입니다.

비밀번호를 잊으면 아래 명령으로 새 비밀번호를 발급합니다. 기존 관리자 세션도 즉시 무효화됩니다.

```powershell
.\php.ps1 bin/reset-password.php
```

## Linux 실행과 배포

PHP 8.4.27 이상인 8.4 계열 또는 PHP 8.5.12 이상인 8.5 계열의 최신 보안 패키지와 `pdo_sqlite`, `mbstring`이 필요합니다. 8.6 이상도 정식 안정 버전만 사용하세요. 앱의 버전 검사는 알려진 최소 수정 버전만 확인합니다. 배포판 백포트로 숫자가 더 낮은 PHP는 현재 검사에서 거부되므로 요구 버전을 만족하는 지원 패키지를 사용하세요.

로컬 개발:

```sh
php -c config/php.ini bin/setup.php
php -c config/php.ini -S 127.0.0.1:8000 -t public
```

운영은 Apache + PHP-FPM + HTTPS를 기준으로 합니다. `config/apache.conf.example`에서 호스트 이름, 인증서 경로, FPM 소켓을 실제 값으로 바꾸세요.

1. 저장소를 `/srv/breadnet`에 배치하고 공개 루트는 반드시 `/srv/breadnet/public`으로 설정합니다. 저장소 전체를 공개하지 마세요.
2. `config/php.ini` 설정을 FPM 시작 시 읽는 ini 조각으로 설치합니다. 예: `/etc/php/8.5/fpm/conf.d/99-breadnet.ini`. 배포판에 따라 경로가 다릅니다. 기존 확장 설정을 유지하세요.
3. `var/`만 FPM 사용자에게 쓰기를 허용하고 코드와 설정은 읽기 전용으로 둡니다. 같은 FPM 사용자로 `php bin/setup.php`를 실행합니다. DB 경로는 `BREADNET_DB` 환경변수로 변경할 수 있습니다.
4. 도메인과 인증서를 설정한 뒤 Apache와 PHP-FPM의 모든 워커를 재시작합니다. FPM에서 패치 버전과 `session.upload_progress.enabled=0`이 실제 적용됐는지 확인하세요. CLI 설정만 확인해서는 충분하지 않습니다.
5. 배포 환경에서도 HTTPS, 관리자 로그인·로그아웃과 승인 전 방명록 비공개를 확인합니다. PHP 내장 개발 서버는 운영에 사용하지 않습니다.

직접 HTTPS를 처리하는 Apache/FPM 기준이며 `X-Forwarded-*` 헤더는 신뢰하지 않습니다. 프록시 뒤에서는 신뢰할 프록시 범위와 HTTPS 전달 설정을 별도로 구성해야 합니다. 운영 HTTP 요청은 앱에서도 거부합니다.

PHP2Shell의 알려진 수정 버전을 요구하고 후속 세션 업로드 문제 대응으로 진행률 기능을 시작 설정에서 비활성화했습니다. 파일 업로드도 사용하지 않습니다. 설정만으로 미패치 PHP를 안전하게 만들 수는 없습니다. PHP와 OS 보안 업데이트를 계속 적용하세요.

## 구성과 운영

```text
public/        공개 PHP 페이지와 CSS
app/           DB·인증·CSRF·요청 제한
templates/     HTML 머리글과 바닥글
bin/           CLI 초기화·비밀번호 재설정
config/        PHP 설정과 Apache 예시
var/           SQLite DB (Git 제외, 공개 폴더 밖)
tests/         임시 DB를 사용하는 HTTP 통합 테스트
```

운영 DB는 서버 밖에도 정기 백업하세요. SQLite 백업 API나 `sqlite3 var/breadnet.sqlite ".backup '/안전한/경로/breadnet.sqlite'"`로 일관된 복사본을 만들고 복원도 확인하세요. 초기화 스크립트는 신규 설치용이며 앞으로 스키마 변경 시에는 마이그레이션이 필요합니다.

관리 화면은 일기 최근 50개, 방명록 승인 대기 우선 50개를 표시합니다. 대규모 관리용 검색과 페이지 이동은 아직 없습니다. 방명록 검증 오류 시 원래 입력은 보존하지 않습니다.

## 검증

Python 표준 라이브러리만 사용합니다. 별도 임시 DB를 만들어 실제 데이터는 변경하지 않습니다.

```powershell
python tests/smoke.py
```

다른 PHP는 `python tests/smoke.py /path/to/php`로 지정합니다. 초기화·중복 방지, 보안 헤더·설정, 비공개 경로, CSRF·인증 차단, 세션 갱신·무효화, 일기 CRUD, XSS 이스케이프, 방명록 승인, 페이지 이동과 요청 제한을 실제 HTTP 요청으로 확인합니다.

공식 참고: [PHP 보안 공지](https://www.php.net/archive/2026.php#2026-10-21-5), [후속 공지](https://www.php.net/archive/2026.php#2026-10-23-1), [Windows 빌드](https://www.php.net/downloads.php?os=windows).
