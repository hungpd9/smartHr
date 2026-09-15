package main

import (
	"bytes"
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"flag"
	"fmt"
	"io"
	"math/rand"
	"net/http"
	"os"
	"os/signal"
	"sort"
	"strings"
	"syscall"
	"time"

	"smart-hr-edge/internal/license"
)

type AttendancePunch struct {
	UserCode         string `json:"user_code"`
	PunchTime        string `json:"punch_time"`
	PunchState       string `json:"punch_state"`       // CHECK_IN, CHECK_OUT, AUTO
	VerificationMode string `json:"verification_mode"` // FINGERPRINT, FACE, CARD
	DeviceSN         string `json:"device_sn"`
}

type SyncBatchPayload struct {
	TenantDomain string            `json:"tenant_domain"`
	BranchCode   string            `json:"branch_code"`
	BatchID      string            `json:"batch_id"`
	Punches      []AttendancePunch `json:"punches"`
	SentAt       string            `json:"sent_at"`
}

type EmployeeItem struct {
	ID             int    `json:"id"`
	UserCode       string `json:"user_code"`
	AttendanceCode string `json:"attendance_code"`
	FullName       string `json:"full_name"`
}

type EmployeesResponse struct {
	Status     string         `json:"status"`
	BranchCode string         `json:"branch_code"`
	BranchName string         `json:"branch_name"`
	Total      int            `json:"total"`
	Employees  []EmployeeItem `json:"employees"`
}

type SyncResponse struct {
	Status           string `json:"status"`
	Message          string `json:"message"`
	BatchID          string `json:"batch_id"`
	ProcessedRecords int    `json:"processed_records"`
	CalculatedDays   int    `json:"calculated_days"`
	TotalReceived    int    `json:"total_received"`
	Timestamp        string `json:"timestamp"`
}

func main() {
	serverURL := flag.String("server", "http://smart-hr.test", "URL máy chủ Smart-HR Cloud Backend")
	branchCode := flag.String("branch", "HN_HQ", "Mã chi nhánh (ví dụ: HN_HQ, DN_HUB, PQ_STORE, HCM_BRANCH)")
	dateStr := flag.String("date", time.Now().Format("2006-01-02"), "Ngày cần mô phỏng chấm công (định dạng YYYY-MM-DD)")
	mode := flag.String("mode", "fast", "Chế độ chạy: 'fast' (bơm tức thì cả ngày), 'daemon' (thời gian thực), 'checkin', 'checkout'")
	licPath := flag.String("lic", "smart-hr.lic", "Đường dẫn tệp bản quyền smart-hr.lic")
	secretKey := flag.String("secret", "", "Khóa HMAC Secret Key (nếu muốn ghi đè khóa trong license)")
	deviceSN := flag.String("device", "ZK-K40-SIMULATOR-01", "Số sê-ri máy chấm công giả lập")
	flag.Parse()

	fmt.Println("======================================================================")
	fmt.Println("       SMART-HR HARDWARE SIMULATOR DAEMON (Golang Edge Simulator)     ")
	fmt.Println("======================================================================")
	fmt.Printf("✓ Máy chủ mục tiêu: %s\n", *serverURL)
	fmt.Printf("✓ Chi nhánh:       %s\n", *branchCode)
	fmt.Printf("✓ Ngày mô phỏng:   %s\n", *dateStr)
	fmt.Printf("✓ Chế độ vận hành: %s\n", *mode)
	fmt.Printf("✓ Mã thiết bị:     %s\n", *deviceSN)
	fmt.Println("----------------------------------------------------------------------")

	// 1. Xác định Khóa bí mật HMAC-SHA256
	activeSecret := *secretKey
	allowedDomain := "smart-hr.test"

	if activeSecret == "" {
		licPayload, err := license.LoadAndVerifyLicense(*licPath)
		if err == nil && licPayload != nil {
			activeSecret = licPayload.SharedHmacSecret
			allowedDomain = licPayload.AllowedDomain
			fmt.Printf("✓ Bản quyền Ed25519 hợp lệ: %s (%s)\n", licPayload.CustomerName, licPayload.AllowedDomain)
		} else {
			// Fallback dùng khóa chi nhánh HN_HQ mặc định trong CSDL
			activeSecret = "d250fe6be5bdc1f9e14bdb55cb93ff4d"
			fmt.Printf("! Không đọc được %s (%v). Sử dụng khóa bí mật mặc định của chi nhánh.\n", *licPath, err)
		}
	} else {
		fmt.Println("✓ Sử dụng khóa HMAC Secret tùy chỉnh từ dòng lệnh.")
	}

	// 2. Thu thập danh sách Nhân sự
	employees := fetchEmployees(*serverURL, *branchCode)
	if len(employees) == 0 {
		fmt.Println("! Không thể lấy danh sách qua API. Sử dụng danh sách nhân sự mẫu dự phòng (EMP001 -> EMP019).")
		employees = getFallbackEmployees()
	}
	fmt.Printf("✓ Danh sách mô phỏng: %d nhân viên hoạt động tại chi nhánh [%s]\n\n", len(employees), *branchCode)

	// 3. Thực thi theo chế độ
	switch *mode {
	case "fast":
		runFastSimulation(*serverURL, *branchCode, allowedDomain, activeSecret, *deviceSN, *dateStr, employees)
	case "checkin":
		runCheckInOnly(*serverURL, *branchCode, allowedDomain, activeSecret, *deviceSN, *dateStr, employees)
	case "checkout":
		runCheckOutOnly(*serverURL, *branchCode, allowedDomain, activeSecret, *deviceSN, *dateStr, employees)
	case "daemon":
		runRealtimeDaemon(*serverURL, *branchCode, allowedDomain, activeSecret, *deviceSN, employees)
	default:
		fmt.Printf("❌ Chế độ '%s' không hợp lệ. Hãy chọn: fast, daemon, checkin, checkout.\n", *mode)
		os.Exit(1)
	}
}

// runFastSimulation sinh toàn bộ dữ liệu quẹt thẻ của cả ngày và gửi ngay lập tức
func runFastSimulation(serverURL, branchCode, domain, secret, deviceSN, dateStr string, employees []EmployeeItem) {
	fmt.Printf("[1/3] Đang lập lịch và sinh dữ liệu quẹt thẻ thực tế cho ngày %s...\n", dateStr)

	// Xáo trộn danh sách để phân nhóm ngẫu nhiên chân thực
	r := rand.New(rand.NewSource(time.Now().UnixNano()))
	shuffled := make([]EmployeeItem, len(employees))
	copy(shuffled, employees)
	r.Shuffle(len(shuffled), func(i, j int) { shuffled[i], shuffled[j] = shuffled[j], shuffled[i] })

	total := len(shuffled)
	// Tỷ lệ: 80% - 90% đúng giờ, 10% - 15% đi muộn, 1-2 người vắng
	onTimeCount := int(float64(total) * 0.85)
	if onTimeCount >= total {
		onTimeCount = total - 2
	}
	lateCount := total - onTimeCount - 1 // 1 người vắng mặt
	if lateCount < 1 && total > 2 {
		lateCount = 1
	}

	onTimeGroup := shuffled[:onTimeCount]
	lateGroup := shuffled[onTimeCount : onTimeCount+lateCount]
	absentGroup := shuffled[onTimeCount+lateCount:]

	fmt.Printf("   -> Nhóm 1: %d nhân sự ĐÚNG GIỜ (Check-in 07:40 - 08:05)\n", len(onTimeGroup))
	fmt.Printf("   -> Nhóm 2: %d nhân sự ĐI MUỘN (Check-in 08:20 - 08:45)\n", len(lateGroup))
	fmt.Printf("   -> Nhóm 3: %d nhân sự VẮNG MẶT (Không quẹt thẻ)\n", len(absentGroup))

	var allPunches []AttendancePunch

	// 1. Sinh Check-in cho nhóm Đúng giờ: 07:40:00 - 08:04:59
	for _, emp := range onTimeGroup {
		// 07:40 đến 08:05 (ngẫu nhiên giây từ 7*3600+40*60 đến 8*3600+4*60+59)
		inSec := randomBetween(r, 7*3600+40*60, 8*3600+4*60+59)
		inTime := formatTimeOfDay(dateStr, inSec)

		allPunches = append(allPunches, AttendancePunch{
			UserCode:         emp.UserCode,
			PunchTime:        inTime,
			PunchState:       "CHECK_IN",
			VerificationMode: randomVerifyMode(r),
			DeviceSN:         deviceSN,
		})
	}

	// 2. Sinh Check-in cho nhóm Đi muộn: 08:20:00 - 08:45:00
	for _, emp := range lateGroup {
		inSec := randomBetween(r, 8*3600+20*60, 8*3600+45*60)
		inTime := formatTimeOfDay(dateStr, inSec)

		allPunches = append(allPunches, AttendancePunch{
			UserCode:         emp.UserCode,
			PunchTime:        inTime,
			PunchState:       "CHECK_IN",
			VerificationMode: randomVerifyMode(r),
			DeviceSN:         deviceSN,
		})
	}

	// 3. Sinh Check-out cho tất cả nhân sự đã vào ca: 17:00:00 - 18:30:00
	attendedEmployees := append(onTimeGroup, lateGroup...)
	for idx, emp := range attendedEmployees {
		var outSec int
		if idx%3 == 0 {
			// Làm thêm giờ OT: 17:35 đến 18:45
			outSec = randomBetween(r, 17*3600+35*60, 18*3600+45*60)
		} else {
			// Về đúng giờ: 17:01 đến 17:15
			outSec = randomBetween(r, 17*3600+1*60, 17*3600+15*60)
		}

		outTime := formatTimeOfDay(dateStr, outSec)
		allPunches = append(allPunches, AttendancePunch{
			UserCode:         emp.UserCode,
			PunchTime:        outTime,
			PunchState:       "CHECK_OUT",
			VerificationMode: randomVerifyMode(r),
			DeviceSN:         deviceSN,
		})
	}

	// Sắp xếp các bản ghi theo thời gian quẹt thẻ tăng dần
	sort.Slice(allPunches, func(i, j int) bool {
		return allPunches[i].PunchTime < allPunches[j].PunchTime
	})

	fmt.Printf("\n[2/3] Đã sinh tổng cộng %d lượt quẹt thẻ (Check-in + Check-out). Đang đóng gói & ký số HMAC-SHA256...\n", len(allPunches))

	// In bảng preview tóm tắt
	printPunchesPreview(allPunches)

	// Gửi lên máy chủ Laravel Core
	batchID := fmt.Sprintf("SIM_%s_%d", strings.ReplaceAll(dateStr, "-", ""), time.Now().Unix()%100000)
	resp, err := sendBatch(serverURL, branchCode, domain, secret, batchID, allPunches)
	if err != nil {
		fmt.Printf("\n❌ GỬI THẤT BẠI: %v\n\n", err)
		os.Exit(1)
	}

	fmt.Printf("\n[3/3] ✅ KẾT QUẢ PHẢN HỒI TỪ MÁY CHỦ CLOUD CORE:\n")
	fmt.Printf("   * Trạng thái:            %s\n", resp.Status)
	fmt.Printf("   * Thông điệp:            %s\n", resp.Message)
	fmt.Printf("   * Batch ID:              %s\n", resp.BatchID)
	fmt.Printf("   * Bản ghi thô tiếp nhận: %d\n", resp.ProcessedRecords)
	fmt.Printf("   * Ngày công đã tính lại: %d (Đã qua 7-Pipe Pipeline)\n\n", resp.CalculatedDays)
	fmt.Println("👉 Dữ liệu đã đồng bộ tức thì lên Dashboard và Bảng Chấm công (/attendance)!")
}

// runCheckInOnly chỉ sinh lượt Check-in buổi sáng
func runCheckInOnly(serverURL, branchCode, domain, secret, deviceSN, dateStr string, employees []EmployeeItem) {
	r := rand.New(rand.NewSource(time.Now().UnixNano()))
	var punches []AttendancePunch

	for idx, emp := range employees {
		// 85% đúng giờ, 15% muộn
		var sec int
		if idx%6 == 0 {
			sec = randomBetween(r, 8*3600+20*60, 8*3600+40*60) // Sau 8:20
		} else {
			sec = randomBetween(r, 7*3600+40*60, 8*3600+4*60) // 7:40 - 8:04
		}

		punches = append(punches, AttendancePunch{
			UserCode:         emp.UserCode,
			PunchTime:        formatTimeOfDay(dateStr, sec),
			PunchState:       "CHECK_IN",
			VerificationMode: randomVerifyMode(r),
			DeviceSN:         deviceSN,
		})
	}

	batchID := fmt.Sprintf("SIM_IN_%d", time.Now().Unix()%100000)
	resp, err := sendBatch(serverURL, branchCode, domain, secret, batchID, punches)
	if err != nil {
		fmt.Printf("❌ Lỗi gửi Check-in: %v\n", err)
		return
	}
	fmt.Printf("✓ Đã gửi thành công %d lượt Check-in: %s\n", len(punches), resp.Message)
}

// runCheckOutOnly chỉ sinh lượt Check-out buổi chiều
func runCheckOutOnly(serverURL, branchCode, domain, secret, deviceSN, dateStr string, employees []EmployeeItem) {
	r := rand.New(rand.NewSource(time.Now().UnixNano()))
	var punches []AttendancePunch

	for idx, emp := range employees {
		var sec int
		if idx%3 == 0 {
			sec = randomBetween(r, 17*3600+35*60, 18*3600+30*60) // OT
		} else {
			sec = randomBetween(r, 17*3600+1*60, 17*3600+15*60) // Đúng giờ
		}

		punches = append(punches, AttendancePunch{
			UserCode:         emp.UserCode,
			PunchTime:        formatTimeOfDay(dateStr, sec),
			PunchState:       "CHECK_OUT",
			VerificationMode: randomVerifyMode(r),
			DeviceSN:         deviceSN,
		})
	}

	batchID := fmt.Sprintf("SIM_OUT_%d", time.Now().Unix()%100000)
	resp, err := sendBatch(serverURL, branchCode, domain, secret, batchID, punches)
	if err != nil {
		fmt.Printf("❌ Lỗi gửi Check-out: %v\n", err)
		return
	}
	fmt.Printf("✓ Đã gửi thành công %d lượt Check-out: %s\n", len(punches), resp.Message)
}

// runRealtimeDaemon chạy thường trực theo nhịp đồng hồ thời gian thực
func runRealtimeDaemon(serverURL, branchCode, domain, secret, deviceSN string, employees []EmployeeItem) {
	fmt.Println("🚀 BẮT ĐẦU CHẾ ĐỘ THỜI GIAN THỰC (REALTIME DAEMON)...")
	fmt.Println("   * 07:40 - 08:05: Tự động gửi Check-in (80% - 90% nhân sự)")
	fmt.Println("   * 08:20 - 08:45: Tự động gửi Check-in số nhân sự đi muộn còn lại")
	fmt.Println("   * 17:00 - 18:00: Tự động gửi Check-out")
	fmt.Println("----------------------------------------------------------------------")

	quit := make(chan os.Signal, 1)
	signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)

	ticker := time.NewTicker(30 * time.Second)
	defer ticker.Stop()

	sentTodayOnTime := false
	sentTodayLate := false
	sentTodayCheckOut := false
	lastDay := time.Now().Day()

	for {
		select {
		case <-quit:
			fmt.Println("\nĐang dừng Realtime Daemon an toàn...")
			return
		case now := <-ticker.C:
			// Reset trạng thái khi qua ngày mới
			if now.Day() != lastDay {
				sentTodayOnTime = false
				sentTodayLate = false
				sentTodayCheckOut = false
				lastDay = now.Day()
				fmt.Printf("\n[NGÀY MỚI] %s - Đã reset lịch trình mô phỏng.\n", now.Format("02/01/2006"))
			}

			hour, minute := now.Hour(), now.Minute()
			dateToday := now.Format("2006-01-02")

			// Khung 1: 07:40 - 08:05
			if !sentTodayOnTime && ((hour == 7 && minute >= 40) || (hour == 8 && minute <= 5)) {
				fmt.Printf("\n⏰ [%s] Đến khung 07:40 - 08:05: Đang gửi lượt Check-in Đúng giờ...\n", now.Format("15:04:05"))
				runCheckInOnly(serverURL, branchCode, domain, secret, deviceSN, dateToday, employees)
				sentTodayOnTime = true
			}

			// Khung 2: 08:20 - 08:45
			if !sentTodayLate && (hour == 8 && minute >= 20 && minute <= 45) {
				fmt.Printf("\n⏰ [%s] Đến khung sau 08:20: Đang gửi lượt Check-in Đi muộn...\n", now.Format("15:04:05"))
				runCheckInOnly(serverURL, branchCode, domain, secret, deviceSN, dateToday, employees)
				sentTodayLate = true
			}

			// Khung 3: 17:00 - 18:00
			if !sentTodayCheckOut && (hour >= 17 && hour < 18) {
				fmt.Printf("\n⏰ [%s] Đến khung 17:00 - 18:00: Đang gửi lượt Check-out...\n", now.Format("15:04:05"))
				runCheckOutOnly(serverURL, branchCode, domain, secret, deviceSN, dateToday, employees)
				sentTodayCheckOut = true
			}
		}
	}
}

// sendBatch gửi HTTP POST kèm chữ ký HMAC-SHA256
func sendBatch(serverURL, branchCode, domain, secretHex, batchID string, punches []AttendancePunch) (*SyncResponse, error) {
	payload := SyncBatchPayload{
		TenantDomain: domain,
		BranchCode:   branchCode,
		BatchID:      batchID,
		Punches:      punches,
		SentAt:       time.Now().Format(time.RFC3339),
	}

	payloadBytes, err := json.Marshal(payload)
	if err != nil {
		return nil, fmt.Errorf("lỗi JSON marshal: %w", err)
	}

	signature := calculateHMAC(payloadBytes, secretHex)
	reqURL := fmt.Sprintf("%s/api/v1/attendance/sync", strings.TrimRight(serverURL, "/"))

	req, err := http.NewRequest("POST", reqURL, bytes.NewBuffer(payloadBytes))
	if err != nil {
		return nil, fmt.Errorf("lỗi tạo request: %w", err)
	}

	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("X-SmartHR-Signature", signature)
	req.Header.Set("X-SmartHR-Branch", branchCode)

	client := &http.Client{Timeout: 15 * time.Second}
	resp, err := client.Do(req)
	if err != nil {
		return nil, fmt.Errorf("không thể kết nối tới máy chủ (%s): %w", reqURL, err)
	}
	defer resp.Body.Close()

	bodyBytes, _ := io.ReadAll(resp.Body)

	if resp.StatusCode != http.StatusOK {
		return nil, fmt.Errorf("máy chủ trả về mã HTTP %d: %s", resp.StatusCode, string(bodyBytes))
	}

	var syncResp SyncResponse
	if err := json.Unmarshal(bodyBytes, &syncResp); err != nil {
		return nil, fmt.Errorf("phản hồi không phải JSON: %s", string(bodyBytes))
	}

	return &syncResp, nil
}

func calculateHMAC(data []byte, secretHex string) string {
	secretBytes, err := hex.DecodeString(secretHex)
	if err != nil {
		secretBytes = []byte(secretHex)
	}
	h := hmac.New(sha256.New, secretBytes)
	h.Write(data)
	return hex.EncodeToString(h.Sum(nil))
}

func fetchEmployees(serverURL, branchCode string) []EmployeeItem {
	reqURL := fmt.Sprintf("%s/api/v1/attendance/employees?branch_code=%s", strings.TrimRight(serverURL, "/"), branchCode)
	client := &http.Client{Timeout: 5 * time.Second}

	resp, err := client.Get(reqURL)
	if err != nil || resp.StatusCode != http.StatusOK {
		return nil
	}
	defer resp.Body.Close()

	var data EmployeesResponse
	if err := json.NewDecoder(resp.Body).Decode(&data); err != nil {
		return nil
	}

	return data.Employees
}

func getFallbackEmployees() []EmployeeItem {
	codes := []string{
		"EMP001", "EMP002", "EMP003", "EMP004", "EMP005",
		"EMP007", "EMP008", "EMP009", "EMP010", "EMP011",
		"EMP012", "EMP013", "EMP014", "EMP015", "EMP016",
		"EMP017", "EMP018", "EMP019",
	}

	res := make([]EmployeeItem, len(codes))
	for i, c := range codes {
		res[i] = EmployeeItem{
			ID:       i + 1,
			UserCode: c,
			FullName: fmt.Sprintf("Nhân viên %s", c),
		}
	}
	return res
}

func randomBetween(r *rand.Rand, min, max int) int {
	if min >= max {
		return min
	}
	return min + r.Intn(max-min+1)
}

func formatTimeOfDay(dateStr string, totalSeconds int) string {
	h := totalSeconds / 3600
	m := (totalSeconds % 3600) / 60
	s := totalSeconds % 60
	return fmt.Sprintf("%s %02d:%02d:%02d", dateStr, h, m, s)
}

func randomVerifyMode(r *rand.Rand) string {
	n := r.Intn(100)
	if n < 70 {
		return "FINGERPRINT"
	} else if n < 95 {
		return "FACE"
	}
	return "CARD"
}

func printPunchesPreview(punches []AttendancePunch) {
	fmt.Printf("   %-10s | %-19s | %-10s | %-12s\n", "MÃ NV", "THỜI GIAN QUẸT", "LOẠI", "PHƯƠNG THỨC")
	fmt.Println("   " + strings.Repeat("-", 60))

	previewCount := 8
	if len(punches) < previewCount {
		previewCount = len(punches)
	}

	for i := 0; i < previewCount; i++ {
		p := punches[i]
		fmt.Printf("   %-10s | %-19s | %-10s | %-12s\n", p.UserCode, p.PunchTime, p.PunchState, p.VerificationMode)
	}

	if len(punches) > previewCount {
		fmt.Printf("   ... và %d bản ghi khác tiếp theo ...\n", len(punches)-previewCount)
	}
}
