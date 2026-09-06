# Walkthrough: Hoàn Thiện Timekeeper WorkMonth/WorkDate, Chu Trình Đóng Công & Sửa Menu Sidebar

Đã hoàn thành toàn bộ các yêu cầu:
1. Sửa lỗi hiển thị active đồng thời 3 submenu tại mục "Quy chế & Thuế (Policy)" trên Sidebar.
2. Thiết kế và nâng cấp Màn hình Chấm Công (Attendance Engine) theo mô hình Timekeeper chuẩn mực gồm: **WorkMonth (Ma trận tháng 31 ngày)**, **WorkDate (Điểm danh theo ngày cụ thể)** và **Detail (Chi tiết cá nhân)**.
3. Hiện thực hóa **Chu Trình Đóng Công & Tự Động Gửi Bảng Công Tháng (Zero-Hardcode Monthly Lifecycle)** với 6 mốc ngày tùy biến, Artisan Command lập lịch và phê duyệt cá nhân / hàng loạt (Bulk Approve).

---

## 1. Các Thay Đổi & Thành Phần Đã Triển Khai

### 1.1 Sửa Lỗi Submenu Active trên Sidebar
- **Tệp chỉnh sửa:** [AppLayout.vue](file:///c:/FlyEnv/www/smartHr/resources/js/Layouts/AppLayout.vue)
- **Giải pháp kỹ thuật:**
  - Bổ sung hàm `isSubItemActive(subRoute: string)` so sánh chính xác cả path và query parameters (`?tab=welfare_schemes`, `?tab=allowances`, v.v.).
  - Giờ đây chỉ duy nhất submenu tương ứng với tab được chọn mới nhận class active màu xanh (`bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 font-semibold`).

---

### 1.2 Cơ Sở Dữ Liệu & Models (Zero-Hardcode Lifecycle)
- **Tệp migration:** `database/migrations/2026_09_06_000004_add_timesheet_lifecycle_and_workmonth_configs.php`
- **Bảng `attendance_policy_configs` bổ sung 7 cột cấu hình mốc ngày:**
  - `enable_auto_submit_timesheet`: Bật/tắt tự động gửi bảng công tháng nếu nhân viên chưa nộp.
  - `emp_review_deadline_day`: Mốc nhân viên rà soát & nộp đơn bổ sung (Mặc định: ngày 3).
  - `auto_submit_day`: Ngày hệ thống tự động chốt & gửi bảng công lên Quản lý (Mặc định: ngày 4).
  - `manager_approval_deadline_day`: Hạn chót Quản lý trực tiếp kiểm tra & phê duyệt (Mặc định: ngày 5).
  - `higher_manager_deadline_day`: Hạn chót Quản lý cấp cao / Khối rà soát (Mặc định: ngày 6).
  - `hr_lock_deadline_day`: Hạn chót Trưởng phòng HR / Giám đốc chốt & khóa công (Mặc định: ngày 7).
  - `payroll_payment_day`: Ngày kế toán thanh toán lương (Mặc định: ngày 10).
- **Bảng `monthly_attendances` bổ sung 6 cột trạng thái chu trình:**
  - `submission_status`: `EMPLOYEE_REVIEW` | `SUBMITTED` | `MANAGER_APPROVED` | `HIGHER_MANAGER_APPROVED` | `REJECTED` | `HR_LOCKED`.
  - `submission_type`: `MANUAL` | `AUTO_SUBMITTED`.
  - `submitted_at`: Thời điểm nộp công.
  - `rejection_reason`: Lý do từ chối yêu cầu nhân viên giải trình / bổ sung đơn từ.
  - `rejected_by`: Quản lý thực hiện từ chối.
  - `rejected_at`: Thời điểm từ chối.

---

### 1.3 Service & Artisan Command
- **Service mới:** [MonthlyTimesheetLifecycleService.php](file:///c:/FlyEnv/www/smartHr/app/Modules/Attendance/Services/MonthlyTimesheetLifecycleService.php)
  - `getCurrentLifecyclePhase(int $tenantId, string $monthYear)`: Xác định trạng thái tiến trình 6 bước chu trình theo ngày thực tế.
  - `getWorkMonthMatrix(...)`: Trích xuất ma trận 31 ngày toàn công ty, lọc phòng ban/chi nhánh, tính toán ký hiệu công (`X`, `0.5`, `OFF`, `V`, `P`, `+2h OT`), cờ đi muộn/về sớm.
  - `getWorkDateOverview(...)`: Bảng điểm danh toàn công ty theo 1 ngày cụ thể kèm KPI tổng quan (Có mặt, Đi muộn, Về sớm, Vắng mặt, OT).
  - `submitTimesheet(...)`: Nhân viên nộp bảng công tháng.
  - `autoSubmitExpiredTimesheets(...)`: Tự động submit công tháng cho toàn bộ nhân viên chưa gửi.
  - `approveByManager(...)` & `bulkApprove(...)`: Phê duyệt đơn lẻ hoặc hàng loạt.
  - `rejectByManager(...)`: Quản lý từ chối kèm lý do giải trình.
- **Artisan Command:** [AutoSubmitTimesheetsCommand.php](file:///c:/FlyEnv/www/smartHr/app/Modules/Attendance/Commands/AutoSubmitTimesheetsCommand.php)
  - Lệnh: `php artisan attendance:auto-submit {--tenant=} {--month=} {--force}`
  - Đã đăng ký trong `bootstrap/app.php` và lập lịch `01:00` sáng hàng ngày trong `routes/console.php`.

---

### 1.4 Controller & Routes
- **Controller cập nhật:** [AttendanceController.php](file:///c:/FlyEnv/www/smartHr/app/Modules/Attendance/Controllers/AttendanceController.php)
  - Hỗ trợ tham số `view` (`workmonth` | `workdate` | `detail`), `month_year`, `work_date`, `branch_id`, `department_id`, `search`.
  - Bổ sung endpoints:
    - `POST /attendance/submit` -> `submitTimesheet`
    - `POST /attendance/approve/{id}` -> `approveTimesheet`
    - `POST /attendance/reject/{id}` -> `rejectTimesheet`
    - `POST /attendance/bulk-approve` -> `bulkApproveTimesheets`
- **Routes đăng ký:** [routes/web.php](file:///c:/FlyEnv/www/smartHr/routes/web.php)

---

### 1.5 Giao Diện Người Dùng (Vue 3 + Element Plus)
- **Trang Cấu hình Quy chế:** [resources/js/Pages/Policy/Index.vue](file:///c:/FlyEnv/www/smartHr/resources/js/Pages/Policy/Index.vue)
  - Bổ sung Card *"Chu Trình Đóng Công & Tự Động Gửi Bảng Công (Monthly Lifecycle)"* trong tab Chấm công & Kỷ luật.
  - Cho phép tùy chỉnh các mốc ngày (ngày 3, 4, 5, 6, 7, 10) và bật/tắt Auto-Submit.
- **Trang Chấm Công Nâng Cấp:** [resources/js/Pages/Attendance/Index.vue](file:///c:/FlyEnv/www/smartHr/resources/js/Pages/Attendance/Index.vue)
  - **Banner 6 Bước Chu trình Đóng công:** Trực quan hóa tiến trình hiện tại của tháng (highlight bước hiện tại, hướng dẫn nhân viên nộp đơn hoặc quản lý phê duyệt).
  - **Chuyển đổi 3 chế độ xem (Segmented Control):**
    1. *Ma Trận Tháng (WorkMonth):* Bảng 31 ngày cuộn ngang, cố định cột nhân viên & phòng ban, thanh công cụ lọc chi nhánh/phòng ban/tên NV, chọn nhiều và nút Duyệt hàng loạt (Bulk Approve).
    2. *Điểm Danh Theo Ngày (WorkDate):* Bộ chọn ngày, 6 thẻ thống kê trạng thái, bảng điểm danh chi tiết từng nhân viên trong ngày.
    3. *Chi Tiết Cá Nhân (Detail):* Xem đầy đủ 31 ngày của từng người, đối soát Log máy chấm công thô (Raw Logs), chuyển đổi chế độ xem Đã duyệt / Tạm tính (Preview).

---

## 2. Kết Quả Kiểm Thử (Command-Line Verification)

### 2.1 Kiểm thử Tính Năng Mới
Lệnh thực thi:
```bash
php artisan test --filter=TimesheetWorkMonthAndLifecycleTest
```
Kết quả: **7/7 tests PASS (41 assertions)**
- `attendance workmonth matrix renders successfully`: PASS
- `attendance workdate overview renders successfully`: PASS
- `employee can submit timesheet`: PASS
- `manager can approve and reject timesheet`: PASS
- `manager can bulk approve timesheets`: PASS
- `update attendance policy lifecycle configs`: PASS
- `artisan auto submit command`: PASS

### 2.2 Kiểm thử Lệnh Artisan Auto-Submit
Lệnh thực thi:
```bash
php artisan attendance:auto-submit --force --month=2026-07
```
Kết quả:
```text
Bắt đầu tiến trình tự động gửi bảng công tháng: [2026-07]...
Tenant #1 [Tập đoàn Công nghệ & Bán lẻ Smart-HR]: Đã tự động gửi 5 bảng công tháng 2026-07 lên Quản lý.
Hoàn tất tiến trình. Tổng cộng: 5 bảng công đã được tự động submit.
```

### 2.3 Kiểm thử Toàn Bộ Hệ Thống (Full Test Suite)
Lệnh thực thi:
```bash
php artisan test
```
Kết quả: **71/71 tests PASS (430 assertions, 0 errors, 0 failures)**

### 2.4 Kiểm thử Đóng Gói Mã Nguồn Giao Diện
Lệnh thực thi:
```bash
bun run build
```
Kết quả: **Build thành công 100% trong 14.27s**, không có bất kỳ lỗi TypeScript hay Vue syntax nào.
