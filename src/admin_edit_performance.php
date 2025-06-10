<?php
include './dbconn.php';
session_start();

// 관리자 권한 체크
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_staff']) || !$_SESSION['is_staff']) {
    header("Location: home.php");
    exit();
}

$performance_id = $_GET['id'] ?? 0;
if (!$performance_id) {
    header("Location: admin_performances.php");
    exit();
}

$error_message = "";
$success_message = "";

// 공연장 목록 조회
$venues_query = "SELECT venue_id, venue_name, location FROM venues ORDER BY venue_name";
$venues_result = mysqli_query($connect, $venues_query);

// 기존 공연 정보 조회
$perf_query = "
    SELECT p.*, v.venue_name
    FROM performances p
    JOIN venues v ON p.venue_id = v.venue_id
    WHERE p.performance_id = ?
";
$perf_stmt = mysqli_prepare($connect, $perf_query);
mysqli_stmt_bind_param($perf_stmt, "i", $performance_id);
mysqli_stmt_execute($perf_stmt);
$perf_result = mysqli_stmt_get_result($perf_stmt);
$performance = mysqli_fetch_assoc($perf_result);

if (!$performance) {
    header("Location: admin_performances.php");
    exit();
}

// 기존 공연 회차 조회
$schedules_query = "
    SELECT * FROM performance_schedules 
    WHERE performance_id = ? 
    ORDER BY performance_date ASC, show_time ASC
";
$schedules_stmt = mysqli_prepare($connect, $schedules_query);
mysqli_stmt_bind_param($schedules_stmt, "i", $performance_id);
mysqli_stmt_execute($schedules_stmt);
$schedules_result = mysqli_stmt_get_result($schedules_stmt);
$existing_schedules = [];
while ($schedule = mysqli_fetch_assoc($schedules_result)) {
    $existing_schedules[] = $schedule;
}

// 예매가 있는지 확인 (전체)
$booking_check_query = "
    SELECT COUNT(*) as booking_count 
    FROM booking_groups bg
    JOIN performance_schedules ps ON bg.schedule_id = ps.schedule_id
    WHERE ps.performance_id = ? AND bg.status = 'confirmed'
";
$booking_check_stmt = mysqli_prepare($connect, $booking_check_query);
mysqli_stmt_bind_param($booking_check_stmt, "i", $performance_id);
mysqli_stmt_execute($booking_check_stmt);
$booking_check_result = mysqli_stmt_get_result($booking_check_stmt);
$has_bookings = mysqli_fetch_assoc($booking_check_result)['booking_count'] > 0;

// 예매된 회차별 상세 정보 조회
$booked_schedules_query = "
    SELECT DISTINCT ps.schedule_id, ps.performance_date, ps.show_time, ps.round_name,
           GROUP_CONCAT(DISTINCT b.seat_type) as booked_seat_types
    FROM performance_schedules ps
    JOIN booking_groups bg ON ps.schedule_id = bg.schedule_id
    JOIN bookings b ON bg.group_id = b.booking_group_id
    WHERE ps.performance_id = ? AND bg.status = 'confirmed'
    GROUP BY ps.schedule_id
";
$booked_schedules_stmt = mysqli_prepare($connect, $booked_schedules_query);
mysqli_stmt_bind_param($booked_schedules_stmt, "i", $performance_id);
mysqli_stmt_execute($booked_schedules_stmt);
$booked_schedules_result = mysqli_stmt_get_result($booked_schedules_stmt);
$booked_schedules = [];
while ($row = mysqli_fetch_assoc($booked_schedules_result)) {
    $booked_schedules[$row['schedule_id']] = explode(',', $row['booked_seat_types']);
}

// 예매된 좌석 타입들 확인
$booked_seat_types = [];
if ($has_bookings) {
    $booked_seats_query = "
        SELECT DISTINCT b.seat_type
        FROM bookings b
        JOIN booking_groups bg ON b.booking_group_id = bg.group_id
        JOIN performance_schedules ps ON bg.schedule_id = ps.schedule_id
        WHERE ps.performance_id = ? AND bg.status = 'confirmed'
    ";
    $booked_seats_stmt = mysqli_prepare($connect, $booked_seats_query);
    mysqli_stmt_bind_param($booked_seats_stmt, "i", $performance_id);
    mysqli_stmt_execute($booked_seats_stmt);
    $booked_seats_result = mysqli_stmt_get_result($booked_seats_stmt);
    while ($row = mysqli_fetch_assoc($booked_seats_result)) {
        $booked_seat_types[] = $row['seat_type'];
    }
    mysqli_stmt_close($booked_seats_stmt);
}

// 폼 제출 처리
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 기본 정보
    $title = trim($_POST['title']);
    $genre = $_POST['genre'];
    $venue_id = $_POST['venue_id'];
    $description = trim($_POST['description']);
    $poster_image = trim($_POST['poster_image']);
    
    // 공연 기간
    $performance_start_date = $_POST['performance_start_date'];
    $performance_end_date = $_POST['performance_end_date'];
    
    // 예매 기간
    $booking_start_date = $_POST['booking_start_date'] . ' ' . $_POST['booking_start_time'];
    $booking_end_date = $_POST['booking_end_date'] . ' ' . $_POST['booking_end_time'];
    
    // 좌석 구성 (예매된 좌석 타입의 가격은 수정 불가)
    $vip_floor = $_POST['vip_floor'] ?? 1;
    $vip_seats = $_POST['vip_seats'] ?? 0;
    if (in_array('VIP', $booked_seat_types)) {
        $vip_price = $performance['vip_price']; // 기존 가격 유지
    } else {
        $vip_price = $_POST['vip_price'] ?? 0;
    }
    
    $r_floor = $_POST['r_floor'] ?? 1;
    $r_seats = $_POST['r_seats'] ?? 0;
    if (in_array('R', $booked_seat_types)) {
        $r_price = $performance['r_price']; // 기존 가격 유지
    } else {
        $r_price = $_POST['r_price'] ?? 0;
    }
    
    $s_floor = $_POST['s_floor'] ?? 2;
    $s_seats = $_POST['s_seats'] ?? 0;
    if (in_array('S', $booked_seat_types)) {
        $s_price = $performance['s_price']; // 기존 가격 유지
    } else {
        $s_price = $_POST['s_price'] ?? 0;
    }
    
    $status = $_POST['status'];
    
    // 회차 정보 처리
    $schedules = [];
    if (isset($_POST['schedule_date'])) {
        for ($i = 0; $i < count($_POST['schedule_date']); $i++) {
            if (!empty($_POST['schedule_date'][$i]) && !empty($_POST['schedule_time'][$i])) {
                $schedules[] = [
                    'date' => $_POST['schedule_date'][$i],
                    'time' => $_POST['schedule_time'][$i],
                    'round_name' => trim($_POST['schedule_round'][$i])
                ];
            }
        }
    }
    
    // 유효성 검사
    if (empty($title)) {
        $error_message = "공연명을 입력해주세요.";
    } elseif (empty($genre)) {
        $error_message = "장르를 선택해주세요.";
    } elseif (empty($venue_id)) {
        $error_message = "공연장을 선택해주세요.";
    } elseif (empty($performance_start_date) || empty($performance_end_date)) {
        $error_message = "공연 기간을 입력해주세요.";
    } elseif (empty($booking_start_date) || empty($booking_end_date)) {
        $error_message = "예매 기간을 입력해주세요.";
    } elseif (strtotime($performance_start_date) > strtotime($performance_end_date)) {
        $error_message = "공연 종료일은 시작일보다 늦어야 합니다.";
    } elseif (strtotime($booking_start_date) > strtotime($booking_end_date)) {
        $error_message = "예매 종료일은 시작일보다 늦어야 합니다.";
    } elseif (strtotime($booking_end_date) > strtotime($performance_end_date)) {
        $error_message = "예매 종료일은 공연 종료일일 이전이어야 합니다.";
    } elseif ($vip_seats == 0 && $r_seats == 0 && $s_seats == 0) {
        $error_message = "최소 하나 이상의 좌석을 설정해주세요.";
    } elseif (empty($schedules)) {
        $error_message = "최소 하나 이상의 공연 회차를 추가해주세요.";
    } else {
        // 트랜잭션 시작
        mysqli_begin_transaction($connect);
        
        try {
            // 공연 정보 업데이트
            $update_performance = "
                UPDATE performances SET 
                    title = ?, genre = ?, venue_id = ?, description = ?, poster_image = ?,
                    performance_start_date = ?, performance_end_date = ?,
                    vip_floor = ?, vip_seats = ?, vip_price = ?,
                    r_floor = ?, r_seats = ?, r_price = ?,
                    s_floor = ?, s_seats = ?, s_price = ?,
                    booking_start_date = ?, booking_end_date = ?, status = ?
                WHERE performance_id = ?
            ";
            
            $update_stmt = mysqli_prepare($connect, $update_performance);
            mysqli_stmt_bind_param($update_stmt, "ssissssiiiiiiiiisssi", 
                $title, $genre, $venue_id, $description, $poster_image,
                $performance_start_date, $performance_end_date,
                $vip_floor, $vip_seats, $vip_price,
                $r_floor, $r_seats, $r_price,
                $s_floor, $s_seats, $s_price,
                $booking_start_date, $booking_end_date, $status,
                $performance_id
            );
            
            if (!mysqli_stmt_execute($update_stmt)) {
                throw new Exception("공연 정보 수정 중 오류가 발생했습니다: " . mysqli_error($connect));
            }
            mysqli_stmt_close($update_stmt);
            
            // 회차 정보 업데이트 (예매된 회차는 삭제하지 않고 새 회차만 추가)
            if (!empty($schedules)) {
                // 예매되지 않은 기존 회차만 삭제
                if (!empty($booked_schedules)) {
                    $booked_schedule_ids = array_keys($booked_schedules);
                    $placeholders = str_repeat('?,', count($booked_schedule_ids) - 1) . '?';
                    $delete_schedules = "
                        DELETE FROM performance_schedules 
                        WHERE performance_id = ? AND schedule_id NOT IN ($placeholders)
                    ";
                    $delete_stmt = mysqli_prepare($connect, $delete_schedules);
                    $params = array_merge([$performance_id], $booked_schedule_ids);
                    $types = str_repeat('i', count($params));
                    mysqli_stmt_bind_param($delete_stmt, $types, ...$params);
                } else {
                    // 예매된 회차가 없으면 모든 기존 회차 삭제
                    $delete_schedules = "DELETE FROM performance_schedules WHERE performance_id = ?";
                    $delete_stmt = mysqli_prepare($connect, $delete_schedules);
                    mysqli_stmt_bind_param($delete_stmt, "i", $performance_id);
                }
                mysqli_stmt_execute($delete_stmt);
                mysqli_stmt_close($delete_stmt);
                
                // 새 회차들 추가 (예매된 회차와 중복되지 않는 것만)
                $insert_schedule = "
                    INSERT INTO performance_schedules (performance_id, performance_date, show_time, round_name)
                    VALUES (?, ?, ?, ?)
                ";
                $schedule_stmt = mysqli_prepare($connect, $insert_schedule);
                
                foreach ($schedules as $schedule) {
                    // 기존 예매된 회차와 중복 체크
                    $is_duplicate = false;
                    foreach ($booked_schedules as $booked_schedule_id => $booked_seat_types) {
                        $existing_schedule = array_filter($existing_schedules, function($s) use ($booked_schedule_id) {
                            return $s['schedule_id'] == $booked_schedule_id;
                        });
                        if (!empty($existing_schedule)) {
                            $existing = reset($existing_schedule);
                            if ($existing['performance_date'] == $schedule['date'] && 
                                $existing['show_time'] == $schedule['time']) {
                                $is_duplicate = true;
                                break;
                            }
                        }
                    }
                    
                    if (!$is_duplicate) {
                        mysqli_stmt_bind_param($schedule_stmt, "isss", 
                            $performance_id, 
                            $schedule['date'], 
                            $schedule['time'], 
                            $schedule['round_name']
                        );
                        
                        if (!mysqli_stmt_execute($schedule_stmt)) {
                            throw new Exception("공연 회차 수정 중 오류가 발생했습니다: " . mysqli_error($connect));
                        }
                    }
                }
                mysqli_stmt_close($schedule_stmt);
            }
            
            mysqli_commit($connect);
            $success_message = "공연 정보가 성공적으로 수정되었습니다!";
            
            // 성공 시 다시 정보 조회하여 화면 업데이트
            mysqli_stmt_execute($perf_stmt);
            $perf_result = mysqli_stmt_get_result($perf_stmt);
            $performance = mysqli_fetch_assoc($perf_result);
            
            mysqli_stmt_execute($schedules_stmt);
            $schedules_result = mysqli_stmt_get_result($schedules_stmt);
            $existing_schedules = [];
            while ($schedule = mysqli_fetch_assoc($schedules_result)) {
                $existing_schedules[] = $schedule;
            }
            
        } catch (Exception $e) {
            mysqli_rollback($connect);
            $error_message = $e->getMessage();
        }
    }
}

// 예매 시작/종료 시간 분리
$booking_start_parts = explode(' ', $performance['booking_start_date']);
$booking_start_date = $booking_start_parts[0];
$booking_start_time = $booking_start_parts[1] ?? '09:00';

$booking_end_parts = explode(' ', $performance['booking_end_date']);
$booking_end_date = $booking_end_parts[0];
$booking_end_time = $booking_end_parts[1] ?? '23:59';

mysqli_stmt_close($perf_stmt);
mysqli_stmt_close($schedules_stmt);
mysqli_stmt_close($booking_check_stmt);
mysqli_stmt_close($booked_schedules_stmt);
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>공연 수정 - ShowTicket Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
        }
        
        .header {
            background-color: white;
            color: #333;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-bottom: 1px solid #eee;
        }
        
        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 1rem;
        }
        
        .logo {
            font-size: 2rem;
            font-weight: bold;
            text-decoration: none;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-left: -1rem;
        }
        
        .admin-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            margin-left: 0.5rem;
        }
        
        .container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 2rem;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .page-title {
            font-size: 2.5rem;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .page-subtitle {
            color: #666;
            font-size: 1.1rem;
        }
        
        .booking-notice {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border: 2px solid #2196f3;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .booking-notice-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .booking-notice-title {
            font-size: 1.2rem;
            font-weight: bold;
            color: #1565c0;
            margin-bottom: 0.5rem;
        }
        
        .booking-notice-text {
            color: #1565c0;
        }
        
        .price-restriction-notice {
            background: linear-gradient(135deg, #fff3e0 0%, #ffcc80 100%);
            border: 2px solid #ff9800;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: #e65100;
        }
        
        .form-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
            overflow: hidden;
        }
        
        .form-section {
            padding: 2rem;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .form-section:last-child {
            border-bottom: none;
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #667eea;
            display: inline-block;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .required {
            color: #e74c3c;
        }
        
        .form-control {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-control:disabled {
            background-color: #f8f9fa;
            color: #6c757d;
            cursor: not-allowed;
            border-color: #e9ecef;
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .input-group {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .seat-config {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            margin-top: 1rem;
        }
        
        .seat-type {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            border: 2px solid #e9ecef;
            transition: border-color 0.3s;
        }
        
        .seat-type:hover:not(.price-locked) {
            border-color: #667eea;
        }
        
        .seat-type.price-locked {
            opacity: 0.8;
            background: #fafafa;
        }
        
        .seat-type-title {
            font-weight: bold;
            margin-bottom: 1rem;
            text-align: center;
            padding: 0.5rem;
            border-radius: 5px;
            color: white;
        }
        
        .vip-type .seat-type-title {
            background: linear-gradient(135deg, #ffd700, #ffed4a);
            color: #333;
        }
        
        .r-type .seat-type-title {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
        }
        
        .s-type .seat-type-title {
            background: linear-gradient(135deg, #4ecdc4, #00d2d3);
        }
        
        .schedule-method-selector {
            margin-bottom: 2rem;
        }
        
        .method-tabs {
            display: flex;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 0.3rem;
            gap: 0.3rem;
        }
        
        .method-tab {
            flex: 1;
            padding: 0.8rem 1rem;
            background: transparent;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            color: #666;
        }
        
        .method-tab.active {
            background: #667eea;
            color: white;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        }
        
        .schedule-mode {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
        }
        
        .auto-schedule-container {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
        }
        
        .weekdays-container {
            margin: 1.5rem 0;
        }
        
        .weekdays-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.5rem;
        }
        
        .weekday-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
        }
        
        .weekday-checkbox {
            display: none;
        }
        
        .weekday-label {
            width: 40px;
            height: 40px;
            border: 2px solid #e9ecef;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            transition: all 0.3s;
            background: white;
            color: #666;
        }
        
        .weekday-label.sunday {
            color: #dc3545;
        }
        
        .weekday-label.saturday {
            color: #0066cc;
        }
        
        .weekday-checkbox:checked + .weekday-label {
            background: #667eea;
            border-color: #667eea;
            color: white;
            transform: scale(1.1);
        }
        
        .time-slots-container {
            margin: 1.5rem 0;
        }
        
        .time-slot-item {
            display: grid;
            grid-template-columns: 1fr 2fr auto;
            gap: 1rem;
            align-items: center;
            margin-bottom: 0.5rem;
            padding: 0.8rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .time-input, .round-input {
            margin: 0;
        }
        
        .schedule-preview {
            background: linear-gradient(135deg, #f0f4ff 0%, #e8f2ff 100%);
            border: 2px solid #667eea;
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 2rem;
        }
        
        .preview-content {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .preview-item {
            background: white;
            padding: 0.8rem;
            margin-bottom: 0.5rem;
            border-radius: 5px;
            border-left: 4px solid #667eea;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .preview-date {
            font-weight: bold;
            color: #333;
        }
        
        .preview-time {
            color: #667eea;
        }
        
        .preview-round {
            color: #666;
            font-size: 0.9rem;
        }
        
        .schedules-container {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1rem;
            background: #f8f9fa;
        }
        
        .schedule-item {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
            margin-bottom: 1rem;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        .schedule-item:last-child {
            margin-bottom: 0;
        }
        
        .schedule-item.booked {
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            border-color: #f44336;
        }
        
        .booked-badge {
            background: #f44336;
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: bold;
            margin-left: 0.5rem;
        }
        
        .btn {
            display: inline-block;
            padding: 0.8rem 1.5rem;
            background: transparent;
            color: #667eea;
            text-decoration: none;
            border-radius: 8px;
            border: 2px solid #667eea;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
            font-size: 1rem;
        }
        
        .btn:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateY(-2px);
        }
        
        .btn:disabled {
            background: #6c757d;
            border-color: #6c757d;
            color: white;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .btn:disabled:hover {
            transform: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            font-size: 1.1rem;
            padding: 1rem 2rem;
        }
        
        .btn-secondary {
            background: #6c757d;
            border-color: #6c757d;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            border-color: #28a745;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            border-color: #dc3545;
            color: white;
        }
        
        .btn-small {
            padding: 0.4rem 0.8rem;
            font-size: 0.9rem;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            padding: 2rem;
            background: #f8f9fa;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .help-text {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.3rem;
        }
        
        .price-lock-icon {
            color: #f44336;
            margin-left: 0.5rem;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .seat-config {
                grid-template-columns: 1fr;
            }
            
            .schedule-item {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- 헤더 -->
    <header class="header">
        <div class="nav-container">
            <a href="home.php" class="logo">🎭 ShowTicket<span class="admin-badge">ADMIN</span></a>
            
            <div style="display: flex; gap: 1rem; align-items: center;">
                <a href="admin_performances.php" style="color: #667eea; text-decoration: none;">← 공연 관리로 돌아가기</a>
                <span style="color: #333;">관리자 <?php echo htmlspecialchars($_SESSION['name']); ?>님</span>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- 페이지 헤더 -->
        <div class="page-header">
            <h1 class="page-title">✏️ 공연 수정</h1>
            <p class="page-subtitle"><?php echo htmlspecialchars($performance['title']); ?> 공연 정보를 수정하세요</p>
        </div>

        <!-- 예매 제한 안내 -->
        <?php if ($has_bookings): ?>
            <div class="booking-notice">
                <div class="booking-notice-icon">ℹ️</div>
                <div class="booking-notice-title">예매 내역이 있는 공연입니다</div>
                <div class="booking-notice-text">
                    • 예매된 좌석 타입의 가격은 수정할 수 없습니다<br>
                    • 예매된 회차는 삭제할 수 없지만 새 회차 추가는 가능합니다<br>
                    • 기본 정보, 공연 기간, 예매 기간, 상태는 수정 가능합니다
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form method="POST" action="" id="performanceForm">
            <div class="form-container">
                <!-- 기본 정보 -->
                <div class="form-section">
                    <h2 class="section-title">📋 기본 정보</h2>
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label class="form-label">공연명 <span class="required">*</span></label>
                            <input type="text" name="title" class="form-control" 
                                   placeholder="공연 제목을 입력하세요" required
                                   value="<?php echo htmlspecialchars($performance['title']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">장르 <span class="required">*</span></label>
                            <select name="genre" class="form-control" required>
                                <option value="">장르 선택</option>
                                <option value="뮤지컬" <?php echo $performance['genre'] == '뮤지컬' ? 'selected' : ''; ?>>뮤지컬</option>
                                <option value="연극" <?php echo $performance['genre'] == '연극' ? 'selected' : ''; ?>>연극</option>
                                <option value="콘서트" <?php echo $performance['genre'] == '콘서트' ? 'selected' : ''; ?>>콘서트</option>
                                <option value="오페라" <?php echo $performance['genre'] == '오페라' ? 'selected' : ''; ?>>오페라</option>
                                <option value="발레" <?php echo $performance['genre'] == '발레' ? 'selected' : ''; ?>>발레</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">공연장 <span class="required">*</span></label>
                            <select name="venue_id" class="form-control" required>
                                <option value="">공연장 선택</option>
                                <?php
                                mysqli_data_seek($venues_result, 0); // 결과 포인터 리셋
                                while ($venue = mysqli_fetch_assoc($venues_result)): 
                                ?>
                                    <option value="<?php echo $venue['venue_id']; ?>" 
                                            <?php echo $performance['venue_id'] == $venue['venue_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($venue['venue_name']); ?> 
                                        (<?php echo htmlspecialchars($venue['location']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group full-width">
                            <label class="form-label">공연 설명</label>
                            <textarea name="description" class="form-control" 
                                      placeholder="공연에 대한 상세한 설명을 입력하세요"><?php echo htmlspecialchars($performance['description']); ?></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <label class="form-label">포스터 이미지 URL</label>
                            <input type="url" name="poster_image" class="form-control" 
                                   placeholder="http://example.com/poster.jpg"
                                   value="<?php echo htmlspecialchars($performance['poster_image']); ?>">
                            <div class="help-text">포스터 이미지의 URL을 입력하세요 (선택사항)</div>
                        </div>
                    </div>
                </div>

                <!-- 공연 기간 -->
                <div class="form-section">
                    <h2 class="section-title">📅 공연 기간</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">공연 시작일 <span class="required">*</span></label>
                            <input type="date" name="performance_start_date" class="form-control" required
                                   value="<?php echo $performance['performance_start_date']; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">공연 종료일 <span class="required">*</span></label>
                            <input type="date" name="performance_end_date" class="form-control" required
                                   value="<?php echo $performance['performance_end_date']; ?>">
                        </div>
                    </div>
                </div>

                <!-- 예매 기간 -->
                <div class="form-section">
                    <h2 class="section-title">🎫 예매 기간</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">예매 시작 <span class="required">*</span></label>
                            <div class="input-group">
                                <input type="date" name="booking_start_date" class="form-control" required
                                       value="<?php echo $booking_start_date; ?>">
                                <input type="time" name="booking_start_time" class="form-control" required
                                       value="<?php echo $booking_start_time; ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">예매 종료 <span class="required">*</span></label>
                            <div class="input-group">
                                <input type="date" name="booking_end_date" class="form-control" required
                                       value="<?php echo $booking_end_date; ?>">
                                <input type="time" name="booking_end_time" class="form-control" required
                                       value="<?php echo $booking_end_time; ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 좌석 구성 -->
                <div class="form-section">
                    <h2 class="section-title">🪑 좌석 구성</h2>
                    
                    <?php if (!empty($booked_seat_types)): ?>
                        <div class="price-restriction-notice">
                            🔒 예매된 좌석 타입: <?php echo implode(', ', $booked_seat_types); ?>석의 가격은 수정할 수 없습니다
                        </div>
                    <?php endif; ?>
                    
                    <div class="seat-config">
                        <!-- VIP석 -->
                        <div class="seat-type vip-type <?php echo in_array('VIP', $booked_seat_types) ? 'price-locked' : ''; ?>">
                            <div class="seat-type-title">
                                VIP석
                                <?php if (in_array('VIP', $booked_seat_types)): ?>
                                    <span class="price-lock-icon">🔒</span>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label class="form-label">층수</label>
                                <select name="vip_floor" class="form-control">
                                    <option value="1" <?php echo $performance['vip_floor'] == 1 ? 'selected' : ''; ?>>1층</option>
                                    <option value="2" <?php echo $performance['vip_floor'] == 2 ? 'selected' : ''; ?>>2층</option>
                                    <option value="3" <?php echo $performance['vip_floor'] == 3 ? 'selected' : ''; ?>>3층</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">좌석 수</label>
                                <input type="number" name="vip_seats" class="form-control" min="0" 
                                       value="<?php echo $performance['vip_seats']; ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">가격 (원)</label>
                                <input type="number" name="vip_price" class="form-control" min="0" step="1000"
                                       value="<?php echo $performance['vip_price']; ?>" 
                                       <?php echo in_array('VIP', $booked_seat_types) ? 'disabled' : ''; ?>>
                                <?php if (in_array('VIP', $booked_seat_types)): ?>
                                    <div class="help-text">예매 내역으로 인해 가격 수정 불가</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- R석 -->
                        <div class="seat-type r-type <?php echo in_array('R', $booked_seat_types) ? 'price-locked' : ''; ?>">
                            <div class="seat-type-title">
                                R석
                                <?php if (in_array('R', $booked_seat_types)): ?>
                                    <span class="price-lock-icon">🔒</span>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label class="form-label">층수</label>
                                <select name="r_floor" class="form-control">
                                    <option value="1" <?php echo $performance['r_floor'] == 1 ? 'selected' : ''; ?>>1층</option>
                                    <option value="2" <?php echo $performance['r_floor'] == 2 ? 'selected' : ''; ?>>2층</option>
                                    <option value="3" <?php echo $performance['r_floor'] == 3 ? 'selected' : ''; ?>>3층</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">좌석 수</label>
                                <input type="number" name="r_seats" class="form-control" min="0"
                                       value="<?php echo $performance['r_seats']; ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">가격 (원)</label>
                                <input type="number" name="r_price" class="form-control" min="0" step="1000"
                                       value="<?php echo $performance['r_price']; ?>" 
                                       <?php echo in_array('R', $booked_seat_types) ? 'disabled' : ''; ?>>
                                <?php if (in_array('R', $booked_seat_types)): ?>
                                    <div class="help-text">예매 내역으로 인해 가격 수정 불가</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- S석 -->
                        <div class="seat-type s-type <?php echo in_array('S', $booked_seat_types) ? 'price-locked' : ''; ?>">
                            <div class="seat-type-title">
                                S석
                                <?php if (in_array('S', $booked_seat_types)): ?>
                                    <span class="price-lock-icon">🔒</span>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label class="form-label">층수</label>
                                <select name="s_floor" class="form-control">
                                    <option value="1" <?php echo $performance['s_floor'] == 1 ? 'selected' : ''; ?>>1층</option>
                                    <option value="2" <?php echo $performance['s_floor'] == 2 ? 'selected' : ''; ?>>2층</option>
                                    <option value="3" <?php echo $performance['s_floor'] == 3 ? 'selected' : ''; ?>>3층</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">좌석 수</label>
                                <input type="number" name="s_seats" class="form-control" min="0"
                                       value="<?php echo $performance['s_seats']; ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">가격 (원)</label>
                                <input type="number" name="s_price" class="form-control" min="0" step="1000"
                                       value="<?php echo $performance['s_price']; ?>" 
                                       <?php echo in_array('S', $booked_seat_types) ? 'disabled' : ''; ?>>
                                <?php if (in_array('S', $booked_seat_types)): ?>
                                    <div class="help-text">예매 내역으로 인해 가격 수정 불가</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="help-text" style="margin-top: 1rem; text-align: center;">
                        💡 최소 하나 이상의 좌석 타입은 설정해야 합니다
                    </div>
                </div>

                <!-- 공연 회차 -->
                <div class="form-section">
                    <h2 class="section-title">🎬 공연 회차</h2>
                    
                    <!-- 회차 생성 방식 선택 -->
                    <div class="schedule-method-selector">
                        <div class="method-tabs">
                            <button type="button" class="method-tab active" onclick="switchMethod('manual')">✏️ 수동 관리</button>
                            <button type="button" class="method-tab" onclick="switchMethod('auto')">📅 자동 추가</button>
                        </div>
                    </div>

                    <!-- 수동 관리 모드 -->
                    <div id="manualScheduleMode" class="schedule-mode">
                        <div class="schedules-container">
                            <h4 style="margin-bottom: 1rem; color: #667eea;">현재 등록된 회차</h4>
                            <div id="schedulesContainer">
                                <?php foreach ($existing_schedules as $index => $schedule): ?>
                                    <?php
                                    $is_booked = isset($booked_schedules[$schedule['schedule_id']]);
                                    $booked_types = $is_booked ? $booked_schedules[$schedule['schedule_id']] : [];
                                    ?>
                                    <div class="schedule-item <?php echo $is_booked ? 'booked' : ''; ?>">
                                        <div class="form-group">
                                            <label class="form-label">공연일</label>
                                            <input type="date" name="schedule_date[]" class="form-control" required
                                                   value="<?php echo $schedule['performance_date']; ?>" 
                                                   <?php echo $is_booked ? 'disabled' : ''; ?>>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">공연시간</label>
                                            <input type="time" name="schedule_time[]" class="form-control" required
                                                   value="<?php echo $schedule['show_time']; ?>" 
                                                   <?php echo $is_booked ? 'disabled' : ''; ?>>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">회차명 
                                                <?php if ($is_booked): ?>
                                                    <span class="booked-badge">예매됨</span>
                                                <?php endif; ?>
                                            </label>
                                            <input type="text" name="schedule_round[]" class="form-control" 
                                                   value="<?php echo htmlspecialchars($schedule['round_name']); ?>" 
                                                   <?php echo $is_booked ? 'disabled' : ''; ?>>
                                            <?php if ($is_booked): ?>
                                                <div class="help-text">예매 좌석: <?php echo implode(', ', $booked_types); ?>석</div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="form-group">
                                            <?php if ($is_booked): ?>
                                                <span class="btn btn-secondary btn-small" style="opacity: 0.6;">삭제불가</span>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-danger btn-small" onclick="removeSchedule(this)">삭제</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div style="text-align: center; margin-top: 1rem;">
                                <button type="button" class="btn btn-success" onclick="addSchedule()">+ 새 회차 추가</button>
                            </div>
                        </div>
                    </div>

                    <!-- 자동 추가 모드 -->
                    <div id="autoScheduleMode" class="schedule-mode" style="display: none;">
                        <div class="auto-schedule-container">
                            <h3 style="margin-bottom: 1rem; color: #667eea;">📅 새로운 회차를 자동 생성하여 추가</h3>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">회차 시작일</label>
                                    <input type="date" id="scheduleStartDate" class="form-control" value="<?php echo $performance['performance_start_date']; ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">회차 종료일</label>
                                    <input type="date" id="scheduleEndDate" class="form-control" value="<?php echo $performance['performance_end_date']; ?>">
                                </div>
                            </div>

                            <!-- 요일 선택 -->
                            <div class="weekdays-container">
                                <h4 style="margin-bottom: 1rem;">공연 요일 선택</h4>
                                <div class="weekdays-grid">
                                    <label class="weekday-item">
                                        <input type="checkbox" value="0" class="weekday-checkbox">
                                        <span class="weekday-label sunday">일</span>
                                    </label>
                                    <label class="weekday-item">
                                        <input type="checkbox" value="1" class="weekday-checkbox">
                                        <span class="weekday-label">월</span>
                                    </label>
                                    <label class="weekday-item">
                                        <input type="checkbox" value="2" class="weekday-checkbox">
                                        <span class="weekday-label">화</span>
                                    </label>
                                    <label class="weekday-item">
                                        <input type="checkbox" value="3" class="weekday-checkbox">
                                        <span class="weekday-label">수</span>
                                    </label>
                                    <label class="weekday-item">
                                        <input type="checkbox" value="4" class="weekday-checkbox">
                                        <span class="weekday-label">목</span>
                                    </label>
                                    <label class="weekday-item">
                                        <input type="checkbox" value="5" class="weekday-checkbox">
                                        <span class="weekday-label">금</span>
                                    </label>
                                    <label class="weekday-item">
                                        <input type="checkbox" value="6" class="weekday-checkbox">
                                        <span class="weekday-label saturday">토</span>
                                    </label>
                                </div>
                            </div>

                            <!-- 시간 설정 -->
                            <div class="time-slots-container">
                                <h4 style="margin-bottom: 1rem;">공연 시간 설정</h4>
                                <div id="timeSlotsList">
                                    <div class="time-slot-item">
                                        <input type="time" class="form-control time-input" value="19:30">
                                        <input type="text" class="form-control round-input" placeholder="회차명" value="저녁공연">
                                        <button type="button" class="btn btn-danger btn-small" onclick="removeTimeSlot(this)">삭제</button>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-success btn-small" onclick="addTimeSlot()">+ 시간 추가</button>
                            </div>

                            <div style="text-align: center; margin-top: 2rem;">
                                <button type="button" class="btn btn-primary" onclick="generateSchedules()">🎭 새 회차 자동 생성</button>
                            </div>
                        </div>
                    </div>

                    <!-- 생성된 회차 미리보기 -->
                    <div id="schedulePreview" class="schedule-preview" style="display: none;">
                        <h4 style="margin-bottom: 1rem; color: #667eea;">📋 생성될 새 회차 미리보기</h4>
                        <div class="preview-content">
                            <div id="previewList"></div>
                            <div style="text-align: center; margin-top: 1rem;">
                                <button type="button" class="btn btn-secondary" onclick="editSchedules()">수정</button>
                                <button type="button" class="btn btn-success" onclick="confirmSchedules()">기존 회차에 추가</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="help-text" style="margin-top: 1rem;">
                        💡 수동 관리: 기존 회차를 개별적으로 수정하거나 새 회차 추가<br>
                        💡 자동 추가: 기존 회차는 유지하고 새로운 회차를 일괄 추가<br>
                        🔒 예매된 회차는 삭제할 수 없습니다
                    </div>
                </div>

                <!-- 공연 상태 -->
                <div class="form-section">
                    <h2 class="section-title">⚙️ 공연 상태</h2>
                    <div class="form-group">
                        <label class="form-label">상태 <span class="required">*</span></label>
                        <select name="status" class="form-control" required>
                            <option value="upcoming" <?php echo $performance['status'] == 'upcoming' ? 'selected' : ''; ?>>예매 예정</option>
                            <option value="booking" <?php echo $performance['status'] == 'booking' ? 'selected' : ''; ?>>예매중</option>
                            <option value="closed" <?php echo $performance['status'] == 'closed' ? 'selected' : ''; ?>>예매 마감</option>
                            <option value="finished" <?php echo $performance['status'] == 'finished' ? 'selected' : ''; ?>>공연 종료</option>
                        </select>
                        <div class="help-text">
                            공연 상태에 따라 예매 가능 여부가 결정됩니다
                        </div>
                    </div>
                </div>
            </div>

            <!-- 폼 액션 -->
            <div class="form-actions">
                <a href="admin_performances.php" class="btn btn-secondary">취소</a>
                <button type="submit" class="btn btn-primary">💾 수정 완료</button>
            </div>
        </form>
    </div>

    <script>
        let scheduleCount = <?php echo count($existing_schedules); ?>;
        let generatedSchedules = [];
        const bookedSchedules = <?php echo json_encode($booked_schedules); ?>;

        // 페이지 로드 시 초기 설정
        window.addEventListener('load', function() {
            updateScheduleDateLimits();
        });

        // 회차 생성 방식 전환
        function switchMethod(method) {
            // 탭 활성화 상태 변경
            document.querySelectorAll('.method-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelector(`[onclick="switchMethod('${method}')"]`).classList.add('active');

            // 모드 전환
            if (method === 'auto') {
                document.getElementById('autoScheduleMode').style.display = 'block';
                document.getElementById('manualScheduleMode').style.display = 'none';
            } else {
                document.getElementById('autoScheduleMode').style.display = 'none';
                document.getElementById('manualScheduleMode').style.display = 'block';
            }
            
            // 미리보기 숨기기
            document.getElementById('schedulePreview').style.display = 'none';
        }

        // 시간 슬롯 추가
        function addTimeSlot() {
            const container = document.getElementById('timeSlotsList');
            const timeSlot = document.createElement('div');
            timeSlot.className = 'time-slot-item';
            timeSlot.innerHTML = `
                <input type="time" class="form-control time-input" value="14:00">
                <input type="text" class="form-control round-input" placeholder="회차명" value="오후공연">
                <button type="button" class="btn btn-danger btn-small" onclick="removeTimeSlot(this)">삭제</button>
            `;
            container.appendChild(timeSlot);
        }

        // 시간 슬롯 삭제
        function removeTimeSlot(button) {
            const timeSlots = document.querySelectorAll('.time-slot-item');
            if (timeSlots.length > 1) {
                button.closest('.time-slot-item').remove();
            } else {
                alert('최소 하나의 공연 시간은 있어야 합니다.');
            }
        }

        // 회차 자동 생성
        function generateSchedules() {
            const startDate = document.getElementById('scheduleStartDate').value;
            const endDate = document.getElementById('scheduleEndDate').value;
            
            if (!startDate || !endDate) {
                alert('회차 기간을 선택해주세요.');
                return;
            }

            // 선택된 요일 확인
            const selectedWeekdays = [];
            document.querySelectorAll('.weekday-checkbox:checked').forEach(checkbox => {
                selectedWeekdays.push(parseInt(checkbox.value));
            });

            if (selectedWeekdays.length === 0) {
                alert('공연 요일을 하나 이상 선택해주세요.');
                return;
            }

            // 시간 정보 수집
            const timeSlots = [];
            document.querySelectorAll('.time-slot-item').forEach(item => {
                const time = item.querySelector('.time-input').value;
                const round = item.querySelector('.round-input').value;
                if (time && round) {
                    timeSlots.push({ time, round });
                }
            });

            if (timeSlots.length === 0) {
                alert('공연 시간을 하나 이상 설정해주세요.');
                return;
            }

            // 기존 회차와 중복되지 않는 새 회차만 생성
            generatedSchedules = [];
            const start = new Date(startDate);
            const end = new Date(endDate);
            
            // 기존 회차 날짜/시간 목록 생성 (중복 체크용)
            const existingScheduleKeys = new Set();
            document.querySelectorAll('#schedulesContainer .schedule-item').forEach(item => {
                const dateInput = item.querySelector('input[name="schedule_date[]"]');
                const timeInput = item.querySelector('input[name="schedule_time[]"]');
                if (dateInput && timeInput && dateInput.value && timeInput.value) {
                    existingScheduleKeys.add(`${dateInput.value}_${timeInput.value}`);
                }
            });
            
            for (let date = new Date(start); date <= end; date.setDate(date.getDate() + 1)) {
                const weekday = date.getDay();
                if (selectedWeekdays.includes(weekday)) {
                    const dateStr = date.toISOString().split('T')[0];
                    timeSlots.forEach(slot => {
                        const scheduleKey = `${dateStr}_${slot.time}`;
                        // 기존 회차와 중복되지 않는 경우만 추가
                        if (!existingScheduleKeys.has(scheduleKey)) {
                            generatedSchedules.push({
                                date: dateStr,
                                time: slot.time,
                                round: slot.round
                            });
                        }
                    });
                }
            }

            if (generatedSchedules.length === 0) {
                alert('기존 회차와 중복되지 않는 새 회차가 없습니다.\n다른 조건을 선택해주세요.');
                return;
            }

            // 미리보기 표시
            displaySchedulePreview();
        }

        // 미리보기 표시
        function displaySchedulePreview() {
            const previewList = document.getElementById('previewList');
            previewList.innerHTML = '';

            generatedSchedules.forEach((schedule, index) => {
                const date = new Date(schedule.date);
                const weekdays = ['일', '월', '화', '수', '목', '금', '토'];
                const dateStr = `${date.getMonth() + 1}월 ${date.getDate()}일 (${weekdays[date.getDay()]})`;
                
                const previewItem = document.createElement('div');
                previewItem.className = 'preview-item';
                previewItem.innerHTML = `
                    <div>
                        <span class="preview-date">${dateStr}</span>
                        <span class="preview-time">${schedule.time}</span>
                        <span class="preview-round">${schedule.round}</span>
                    </div>
                    <button type="button" class="btn btn-danger btn-small" onclick="removeGeneratedSchedule(${index})">삭제</button>
                `;
                previewList.appendChild(previewItem);
            });

            document.getElementById('schedulePreview').style.display = 'block';
        }

        // 생성된 회차 삭제
        function removeGeneratedSchedule(index) {
            generatedSchedules.splice(index, 1);
            if (generatedSchedules.length === 0) {
                document.getElementById('schedulePreview').style.display = 'none';
            } else {
                displaySchedulePreview();
            }
        }

        // 회차 수정 (다시 설정으로 돌아가기)
        function editSchedules() {
            document.getElementById('schedulePreview').style.display = 'none';
        }

        // 회차 확정 (기존 회차에 추가)
        function confirmSchedules() {
            if (generatedSchedules.length === 0) {
                alert('생성된 회차가 없습니다.');
                return;
            }

            const confirmed = confirm(`${generatedSchedules.length}개의 새 회차를 기존 회차에 추가하시겠습니까?`);
            if (!confirmed) return;

            // 수동 모드로 전환
            switchMethod('manual');
            
            const container = document.getElementById('schedulesContainer');

            // 생성된 회차들을 기존 회차 목록에 추가
            generatedSchedules.forEach((schedule, index) => {
                scheduleCount++;
                const scheduleItem = document.createElement('div');
                scheduleItem.className = 'schedule-item';
                scheduleItem.innerHTML = `
                    <div class="form-group">
                        <label class="form-label">공연일</label>
                        <input type="date" name="schedule_date[]" class="form-control" value="${schedule.date}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">공연시간</label>
                        <input type="time" name="schedule_time[]" class="form-control" value="${schedule.time}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">회차명</label>
                        <input type="text" name="schedule_round[]" class="form-control" value="${schedule.round}">
                    </div>
                    <div class="form-group">
                        <button type="button" class="btn btn-danger btn-small" onclick="removeSchedule(this)">삭제</button>
                    </div>
                `;
                container.appendChild(scheduleItem);
            });

            alert(`${generatedSchedules.length}개의 새 회차가 추가되었습니다.`);
            document.getElementById('schedulePreview').style.display = 'none';
            
            // 생성된 회차 목록 초기화
            generatedSchedules = [];
            
            // 날짜 범위 업데이트
            updateScheduleDateLimits();
        }

        // 새 회차 추가 (수동)
        function addSchedule() {
            scheduleCount++;
            const container = document.getElementById('schedulesContainer');
            const scheduleItem = document.createElement('div');
            scheduleItem.className = 'schedule-item';
            scheduleItem.innerHTML = `
                <div class="form-group">
                    <label class="form-label">공연일</label>
                    <input type="date" name="schedule_date[]" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">공연시간</label>
                    <input type="time" name="schedule_time[]" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">회차명</label>
                    <input type="text" name="schedule_round[]" class="form-control" 
                           placeholder="예: ${scheduleCount}회차, 저녁공연" value="${scheduleCount}회차">
                </div>
                <div class="form-group">
                    <button type="button" class="btn btn-danger btn-small" onclick="removeSchedule(this)">삭제</button>
                </div>
            `;
            container.appendChild(scheduleItem);
            
            // 날짜 범위 설정
            updateScheduleDateLimits();
        }

        // 회차 삭제 (예매되지 않은 회차만)
        function removeSchedule(button) {
            const scheduleItem = button.closest('.schedule-item');
            
            // 예매된 회차인지 확인
            if (scheduleItem.classList.contains('booked')) {
                alert('예매된 회차는 삭제할 수 없습니다.');
                return;
            }
            
            const scheduleItems = document.querySelectorAll('#schedulesContainer .schedule-item');
            if (scheduleItems.length > 1) {
                scheduleItem.remove();
            } else {
                alert('최소 하나의 회차는 있어야 합니다.');
            }
        }

        // 회차 날짜 범위 업데이트
        function updateScheduleDateLimits() {
            const performanceStart = document.querySelector('input[name="performance_start_date"]').value;
            const performanceEnd = document.querySelector('input[name="performance_end_date"]').value;
            
            // 수동 입력 모드의 날짜 범위 설정
            document.querySelectorAll('input[name="schedule_date[]"]:not(:disabled)').forEach(input => {
                if (performanceStart) input.min = performanceStart;
                if (performanceEnd) input.max = performanceEnd;
            });

            // 자동 생성 모드의 날짜 범위 설정
            const autoStartInput = document.getElementById('scheduleStartDate');
            const autoEndInput = document.getElementById('scheduleEndDate');
            
            if (autoStartInput && performanceStart) {
                autoStartInput.min = performanceStart;
                if (!autoStartInput.value) autoStartInput.value = performanceStart;
            }
            if (autoEndInput && performanceEnd) {
                autoEndInput.max = performanceEnd;
                if (!autoEndInput.value) autoEndInput.value = performanceEnd;
            }
        }

        // 폼 유효성 검사
        document.getElementById('performanceForm').addEventListener('submit', function(e) {
            // 좌석 수 검사
            const vipSeats = parseInt(document.querySelector('input[name="vip_seats"]').value) || 0;
            const rSeats = parseInt(document.querySelector('input[name="r_seats"]').value) || 0;
            const sSeats = parseInt(document.querySelector('input[name="s_seats"]').value) || 0;
            
            if (vipSeats === 0 && rSeats === 0 && sSeats === 0) {
                e.preventDefault();
                alert('최소 하나 이상의 좌석 타입은 설정해야 합니다.');
                return;
            }

            // 좌석 수가 있으면 가격도 있어야 함 (예매되지 않은 좌석 타입만)
            const bookedSeatTypes = <?php echo json_encode($booked_seat_types); ?>;
            const vipPrice = parseInt(document.querySelector('input[name="vip_price"]').value) || 0;
            const rPrice = parseInt(document.querySelector('input[name="r_price"]').value) || 0;
            const sPrice = parseInt(document.querySelector('input[name="s_price"]').value) || 0;

            if ((vipSeats > 0 && vipPrice === 0 && !bookedSeatTypes.includes('VIP')) || 
                (rSeats > 0 && rPrice === 0 && !bookedSeatTypes.includes('R')) || 
                (sSeats > 0 && sPrice === 0 && !bookedSeatTypes.includes('S'))) {
                e.preventDefault();
                alert('좌석이 있는 타입은 가격을 설정해야 합니다.');
                return;
            }

            // 회차 확인
            const scheduleDates = document.querySelectorAll('input[name="schedule_date[]"]:not(:disabled)');
            if (scheduleDates.length === 0) {
                e.preventDefault();
                alert('최소 하나 이상의 공연 회차를 추가해주세요.');
                return;
            }

            // 날짜 유효성 검사
            const performanceStart = new Date(document.querySelector('input[name="performance_start_date"]').value);
            const performanceEnd = new Date(document.querySelector('input[name="performance_end_date"]').value);
            const bookingStart = new Date(document.querySelector('input[name="booking_start_date"]').value + 'T' + document.querySelector('input[name="booking_start_time"]').value);
            const bookingEnd = new Date(document.querySelector('input[name="booking_end_date"]').value + 'T' + document.querySelector('input[name="booking_end_time"]').value);

            if (performanceStart >= performanceEnd) {
                e.preventDefault();
                alert('공연 종료일은 시작일보다 늦어야 합니다.');
                return;
            }

            if (bookingStart >= bookingEnd) {
                e.preventDefault();
                alert('예매 종료일시는 시작일시보다 늦어야 합니다.');
                return;
            }

            if (bookingEnd >= performanceEnd) {
                e.preventDefault();
                alert('예매 종료일시는 공연 종료일 이전이어야 합니다.');
                return;
            }

            // 회차 날짜 검사
            let hasValidSchedule = false;
            document.querySelectorAll('input[name="schedule_date[]"]:not(:disabled)').forEach(input => {
                if (input.value) {
                    const scheduleDate = new Date(input.value);
                    if (scheduleDate >= performanceStart && scheduleDate <= performanceEnd) {
                        hasValidSchedule = true;
                    }
                }
            });

            if (!hasValidSchedule) {
                e.preventDefault();
                alert('공연 회차는 공연 기간 내의 날짜여야 합니다.');
                return;
            }

            // 최종 확인
            const confirmed = confirm('공연 정보를 수정하시겠습니까?');
            if (!confirmed) {
                e.preventDefault();
            }
        });

        // 날짜 입력 시 회차 날짜 범위 자동 설정
        document.querySelector('input[name="performance_start_date"]').addEventListener('change', updateScheduleDateLimits);
        document.querySelector('input[name="performance_end_date"]').addEventListener('change', updateScheduleDateLimits);
    </script>

    <?php mysqli_close($connect); ?>
</body>
</html>