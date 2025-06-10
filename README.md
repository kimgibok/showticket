# 🎭 ShowTicket - 공연 예매 시스템

> **웹프로그래밍 과제 - 공연 예매 웹사이트**  
> **개발 기간**: 2025년 6월
> **개발자**: [20226932] [김경언]

---

## 📋 프로젝트 개요

ShowTicket은 뮤지컬, 연극, 콘서트, 오페라, 발레 등 다양한 공연의 예매를 온라인으로 처리할 수 있는 웹 시스템입니다. 사용자는 회원가입 후 원하는 공연을 검색하고, 좌석을 선택하여 예매할 수 있으며, 관리자는 공연 등록 및 관리 기능을 사용할 수 있습니다.

## 🛠️ 기술 스택

- **Frontend**: HTML5, CSS3, JavaScript
- **Backend**: PHP 8.0+
- **Database**: MySQL 8.0+
- **Server**: Apache (XAMPP 권장)
- **Development Tool**: DBeaver, VS Code, draw.io

## 📁 프로젝트 구조

```
ShowTicket/
├── 📄 README.md                    # 프로젝트 설명서
├── 🗄️ database/
│   └── showticket.sql              # 데이터베이스 덤프 파일
├── 💻 src/
│   ├── dbconn.php                  # 데이터베이스 연결
│   ├── home.php                    # 메인 페이지
│   ├── login.php                   # 로그인 페이지
│   ├── register.php                # 회원가입 페이지
│   ├── logout.php                  # 로그아웃 처리
│   ├── mypage.php                  # 마이페이지
│   ├── performances.php            # 공연 목록 페이지
│   ├── performance_detail.php      # 공연 상세 페이지
│   ├── seat_selection.php          # 좌석 선택 페이지
│   ├── booking_process.php         # 예매 처리 페이지
│   ├── my_bookings.php             # 내 예매 내역
│   ├── admin_performances.php      # 관리자 공연 관리
│   ├── admin_add_performance.php   # 공연 등록
│   └── admin_edit_performance.php  # 공연 수정
└── 🖼️ assets/
    ├── 홈페이지.png
    ├── 공연목록.png
    ├── 예매페이지.png
    └── 관리자페이지.png
```

## ⭐ 주요 기능

### 👤 사용자 기능
- **회원 관리**: 회원가입, 로그인, 개인정보 수정, 회원탈퇴
- **공연 조회**: 장르별 공연 검색, 공연 상세 정보 조회
- **예매 시스템**: 날짜/회차 선택, 좌석 선택, 결제 방법 선택
- **예매 관리**: 예매 내역 조회, 예매 상세 정보 확인
- **마이페이지**: 개인정보 관리, 예매 통계, 비밀번호 변경

### 🔧 관리자 기능
- **공연 관리**: 공연 등록, 수정, 삭제, 상태 변경
- **회차 관리**: 공연 일정 자동/수동 생성
- **예매 통계**: 공연별 예매 현황 및 매출 통계
- **좌석 관리**: VIP, R석, S석 구성 및 가격 설정

## 🗄️ 데이터베이스 설계

### 주요 테이블
- **users**: 사용자 정보 (회원정보, 권한)
- **venues**: 공연장 정보
- **performances**: 공연 정보 (제목, 장르, 가격, 좌석 구성)
- **performance_schedules**: 공연 회차 정보
- **booking_groups**: 예매 그룹 (결제 단위)
- **bookings**: 예매 상세 (좌석별 정보)

### ERD 특징
- 정규화된 테이블 구조
- 외래키 제약조건으로 데이터 무결성 보장
- 예매 취소 시 데이터 추적 가능한 구조

## 🚀 설치 및 실행 방법

### 1. 환경 요구사항
- XAMPP (Apache + MySQL + PHP)
- 웹 브라우저 (Chrome, Firefox 등)

### 2. 설치 순서

#### 2-1. 데이터베이스 설정
```sql
-- MySQL에서 데이터베이스 생성
CREATE DATABASE showticket;

-- 덤프 파일 복원
USE showticket;
SOURCE [프로젝트경로]/database/showticket.sql;
```

#### 2-2. 웹 서버 설정
1. XAMPP 설치 및 Apache, MySQL 시작
2. `src/` 폴더를 `htdocs/`에 복사
3. `src/dbconn.php`에서 데이터베이스 연결 정보 확인/수정

#### 2-3. 실행
- 브라우저에서 `http://localhost/home.php` 접속

## 👥 테스트 계정

### 일반 사용자
- **아이디**: `test1234`
- **비밀번호**: `qwer1234`
- **권한**: 공연 조회, 예매, 개인정보 관리

### 관리자
- **아이디**: `admin1234`
- **비밀번호**: `qwer1234`
- **권한**: 공연 관리, 사용자 관리, 통계 조회, 공연 조회, 예매, 개인정보 관리

## 🎯 주요 특징

### 🔒 보안 기능
- 사용자 입력 데이터 검증 및 SQL Injection 방지
- 세션 관리를 통한 인증/인가
- 관리자 권한 분리

### 📱 사용자 경험
- 반응형 웹 디자인 (모바일 호환)
- 직관적인 네비게이션
- 실시간 폼 검증
- 단계별 예매 프로세스

### 🎨 디자인
- 모던한 그라디언트 디자인
- 일관된 색상 체계 (#667eea 메인 컬러)
- 카드 기반 레이아웃
- 부드러운 애니메이션 효과

## 📊 기능별 페이지 설명

| 페이지명 | 파일명 | 주요 기능 |
|---------|--------|----------|
| 메인 페이지 | home.php | 공연 소개, 장르별 공연 미리보기 |
| 공연 목록 | performances.php | 전체/장르별 공연 목록, 필터링 |
| 공연 상세 | performance_detail.php | 공연 정보, 날짜/회차 선택 |
| 좌석 선택 | seat_selection.php | 좌석 배치도, 좌석 선택 |
| 예매 확인 | booking_process.php | 예매 정보 확인, 결제 |
| 내 예매 | my_bookings.php | 예매 내역, 상세 정보 |
| 마이페이지 | mypage.php | 개인정보 관리, 통계 |
| 로그인/회원가입 | login.php, logout.php, register.php | 계정 관리 |
| 관리자 페이지 | admin_*.php | 공연 관리, 통계 |

## 🐛 제한사항

1. **결제 시스템**: 실제 결제는 구현되지 않음 (UI만 제공)
2. **이메일 알림**: 예매 확인 이메일 발송 기능 없음
3. **파일 업로드**: 공연 포스터 이미지는 URL 입력 방식
4. **실시간 알림**: 예매 마감 등의 실시간 알림 없음


---

## 📄 라이선스

이 프로젝트는 교육 목적으로 개발되었으며, 상업적 사용을 금지합니다.

**© 2025 ShowTicket Project. All rights reserved.**
