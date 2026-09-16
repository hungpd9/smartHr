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

type BranchItem struct {
	ID               int    `json:"id"`
	Code             string `json:"code"`
	Name             string `json:"name"`
	Address          string `json:"address"`
	IsRemoteOrIsland bool   `json:"is_remote_or_island"`
}

type BranchesResponse struct {
	Status   string       `json:"status"`
	Total    int          `json:"total"`
	Branches []BranchItem `json:"branches"`
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

type BranchContext struct {
	Branch    BranchItem
	DeviceSN  string
	Employees []EmployeeItem
}

func main() {
	serverURL := flag.String("server", "http://smart-hr.test", "URL máy chủ Smart-HR Cloud Backend")
	branchCode := flag.String("branch", "ALL", "Mã chi nhánh (ví dụ: HN_HQ, DN_HUB, PQ_STORE, HCM_BRANCH) hoặc 'ALL' để mô phỏng toàn bộ công ty")
	dateStr := flag.String("date", time.Now().Format("2006-01-02"), "Ngày cần mô phỏng chấm công (định dạng YYYY-MM-DD)")
	mode := flag.String("mode", "fast", "Chế độ chạy: 'fast' (bơm tức thì cả ngày), 'daemon' (thời gian thực), 'checkin', 'checkout'")
	licPath := flag.String("lic", "smart-hr.lic", "Đường dẫn tệp bản quyền smart-hr.lic")
	secretKey := flag.String("secret", "", "Khóa HMAC Secret Key (nếu muốn ghi đè khóa trong license)")
	deviceSN := flag.String("device", "", "Số sê-ri máy chấm công giả lập (mặc định tự sinh theo chi nhánh: ZK-<BRANCH>-SIM-01)")
	flag.Parse()

	fmt.Println("======================================================================")
	fmt.Println("       SMART-HR HARDWARE SIMULATOR DAEMON (Golang Edge Simulator)     ")
	fmt.Println("======================================================================")
	fmt.Printf("✓ Máy chủ mục tiêu: %s\n", *serverURL)
	fmt.Printf("✓ Chi nhánh:       %s\n", *branchCode)
	fmt.Printf("✓ Ngày mô phỏng:   %s\n", *dateStr)
	fmt.Printf("✓ Chế độ vận hành: %s\n", *mode)
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
			activeSecret = "d250fe6be5bdc1f9e14bdb55cb93ff4d"
			fmt.Printf("! Không đọc được %s (%v). Sử dụng khóa bí mật mặc định của chi nhánh.\n", *licPath, err)
		}
	} else {
		fmt.Println("✓ Sử dụng khóa HMAC Secret tùy chỉnh từ dòng lệnh.")
	}

	// 2. Xác định danh sách chi nhánh mục tiêu
	isAllBranches := strings.ToUpper(strings.TrimSpace(*branchCode)) == "ALL" || *branchCode == "*"
	var targetBranches []BranchItem

	if isAllBranches {
		targetBranches = fetchBranches(*serverURL)
		fmt.Printf("✓ Chế độ TOÀN CÔNG TY (ALL BRANCHES): Phát hiện %d chi nhánh đang hoạt động.\n", len(targetBranches))
	} else {
		targetBranches = []BranchItem{
			{Code: *branchCode, Name: fmt.Sprintf("Chi nhánh %s", *branchCode)},
		}
		fmt.Printf("✓ Chế độ ĐƠN CHI NHÁNH: Mục tiêu [%s]\n", *branchCode)
	}

	// 3. Chuẩn bị bối cảnh (Context) cho từng chi nhánh
	var branchContexts []BranchContext
	totalCompanyEmployees := 0

	for _, b := range targetBranches {
		sn := *deviceSN
		if sn == "" {
			sn = fmt.Sprintf("ZK-%s-SIM-01", b.Code)
		}

		emps := fetchEmployees(*serverURL, b.Code)
		if len(emps) == 0 {
			if !isAllBranches {
				fmt.Printf("! Không thể lấy danh sách qua API cho [%s]. Sử dụng nhân sự mẫu dự phòng.\n", b.Code)
				emps = getFallbackEmployees()
			} else {
				fmt.Printf("! Chi nhánh [%s - %s]: Không có nhân viên đang hoạt động, bỏ qua.\n", b.Code, b.Name)
				continue
			}
		}

		totalCompanyEmployees += len(emps)
		branchContexts = append(branchContexts, BranchContext{
			Branch:    b,
			DeviceSN:  sn,
			Employees: emps,
		})

		fmt.Printf("   • Chi nhánh [%-10s]: %-35s (%2d nhân sự | Device: %s)\n", b.Code, b.Name, len(emps), sn)
	}

	if len(branchContexts) == 0 {
		fmt.Println("❌ Không có chi nhánh hợp lệ nào để mô phỏng. Dừng tiến trình.")
		os.Exit(1)
	}

	fmt.Printf("\n✓ Tổng cộng: %d nhân viên trên toàn hệ thống sẵn sàng mô phỏng.\n", totalCompanyEmployees)
	fmt.Println("----------------------------------------------------------------------")

	// 4. Thực thi theo chế độ
	switch *mode {
	case "fast":
		totalProcessed := 0
		totalCalculated := 0
		for idx, bc := range branchContexts {
			fmt.Printf("\n🏢 [%d/%d] BẮT ĐẦU MÔ PHỎNG CHI NHÁNH: %s (%s) - %d nhân sự\n", idx+1, len(branchContexts), bc.Branch.Name, bc.Branch.Code, len(bc.Employees))
			rec, calc := runFastSimulation(*serverURL, bc.Branch.Code, allowedDomain, activeSecret, bc.DeviceSN, *dateStr, bc.Employees)
			totalProcessed += rec
			totalCalculated += calc
		}

		if len(branchContexts) > 1 {
			fmt.Println("\n======================================================================")
			fmt.Println("🎉 HOÀN TẤT MÔ PHỎNG CHẤM CÔNG TOÀN CÔNG TY (ALL BRANCHES)")
			fmt.Println("======================================================================")
			fmt.Printf("✓ Tổng số chi nhánh:          %d chi nhánh\n", len(branchContexts))
			fmt.Printf("✓ Tổng số nhân sự tham gia:    %d nhân viên\n", totalCompanyEmployees)
			fmt.Printf("✓ Tổng bản ghi quẹt thẻ (log): %d lượt Check-in/Check-out\n", totalProcessed)
			fmt.Printf("✓ Tổng ngày công đã tính:      %d ngày công (qua 7-Pipe Pipeline)\n", totalCalculated)
			fmt.Println("👉 Toàn bộ Bảng Chấm Công (/attendance) của công ty đã sẵn sàng để kiểm thử!")
		}

	case "checkin":
		for idx, bc := range branchContexts {
			fmt.Printf("[%d/%d] Gửi Check-in chi nhánh [%s]...\n", idx+1, len(branchContexts), bc.Branch.Code)
			runCheckInOnly(*serverURL, bc.Branch.Code, allowedDomain, activeSecret, bc.DeviceSN, *dateStr, bc.Employees)
		}

	case "checkout":
		for idx, bc := range branchContexts {
			fmt.Printf("[%d/%d] Gửi Check-out chi nhánh [%s]...\n", idx+1, len(branchContexts), bc.Branch.Code)
			runCheckOutOnly(*serverURL, bc.Branch.Code, allowedDomain, activeSecret, bc.DeviceSN, *dateStr, bc.Employees)
		}

	case "daemon":
		runRealtimeDaemonMultiBranch(*serverURL, allowedDomain, activeSecret, branchContexts)

	default:
		fmt.Printf("❌ Chế độ '%s' không hợp lệ. Hãy chọn: fast, daemon, checkin, checkout.\n", *mode)
		os.Exit(1)
	}
}

// runFastSimulation sinh toàn bộ dữ liệu quẹt thẻ của cả ngày và gửi ngay lập tức
func runFastSimulation(serverURL, branchCode, domain, secret, deviceSN, dateStr string, employees []EmployeeItem) (int, int) {
	fmt.Printf("   [1/3] Lập lịch & sinh quẹt thẻ ngẫu nhiên cho ngày %s...\n", dateStr)

	r := rand.New(rand.NewSource(time.Now().UnixNano()))
	shuffled := make([]EmployeeItem, len(employees))
	copy(shuffled, employees)
	r.Shuffle(len(shuffled), func(i, j int) { shuffled[i], shuffled[j] = shuffled[j], shuffled[i] })

	total := len(shuffled)
	onTimeCount := int(float64(total) * 0.85)
	if onTimeCount >= total && total > 2 {
		onTimeCount = total - 2
	}
	lateCount := total - onTimeCount - 1
	if lateCount < 1 && total > 2 {
		lateCount = 1
	}

	onTimeGroup := shuffled[:onTimeCount]
	lateGroup := shuffled[onTimeCount : onTimeCount+lateCount]
	absentGroup := shuffled[onTimeCount+lateCount:]

	fmt.Printf("      -> Nhóm 1 (Đúng giờ 07:40 - 08:05): %d nhân sự\n", len(onTimeGroup))
	fmt.Printf("      -> Nhóm 2 (Đi muộn 08:20 - 08:45):  %d nhân sự\n", len(lateGroup))
	fmt.Printf("      -> Nhóm 3 (Vắng mặt):              %d nhân sự\n", len(absentGroup))

	var allPunches []AttendancePunch

	// 1. Sinh Check-in cho nhóm Đúng giờ: 07:40:00 - 08:04:59
	for _, emp := range onTimeGroup {
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

	// 3. Sinh Check-out: 17:00:00 - 18:30:00
	attendedEmployees := append(onTimeGroup, lateGroup...)
	for idx, emp := range attendedEmployees {
		var outSec int
		if idx%3 == 0 {
			outSec = randomBetween(r, 17*3600+35*60, 18*3600+45*60) // OT
		} else {
			outSec = randomBetween(r, 17*3600+1*60, 17*3600+15*60) // Đúng giờ
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

	// Sắp xếp thời gian quẹt thẻ tăng dần
	sort.Slice(allPunches, func(i, j int) bool {
		return allPunches[i].PunchTime < allPunches[j].PunchTime
	})

	fmt.Printf("   [2/3] Đã sinh %d lượt quẹt thẻ. Đang đóng gói & ký số HMAC-SHA256...\n", len(allPunches))
	printPunchesPreview(allPunches)

	batchID := fmt.Sprintf("SIM_%s_%s_%d", branchCode, strings.ReplaceAll(dateStr, "-", ""), time.Now().Unix()%10000)
	resp, err := sendBatch(serverURL, branchCode, domain, secret, batchID, allPunches)
	if err != nil {
		fmt.Printf("   ❌ LỖI GỬI LÔ [%s]: %v\n", branchCode, err)
		return 0, 0
	}

	fmt.Printf("   [3/3] ✅ CLOUD CORE: Tiếp nhận %d log thô | Đã tính công %d nhân sự/ngày\n", resp.ProcessedRecords, resp.CalculatedDays)
	return resp.ProcessedRecords, resp.CalculatedDays
}

// runCheckInOnly chỉ sinh lượt Check-in buổi sáng
func runCheckInOnly(serverURL, branchCode, domain, secret, deviceSN, dateStr string, employees []EmployeeItem) int {
	r := rand.New(rand.NewSource(time.Now().UnixNano()))
	var punches []AttendancePunch

	for idx, emp := range employees {
		var sec int
		if idx%6 == 0 {
			sec = randomBetween(r, 8*3600+20*60, 8*3600+40*60)
		} else {
			sec = randomBetween(r, 7*3600+40*60, 8*3600+4*60)
		}

		punches = append(punches, AttendancePunch{
			UserCode:         emp.UserCode,
			PunchTime:        formatTimeOfDay(dateStr, sec),
			PunchState:       "CHECK_IN",
			VerificationMode: randomVerifyMode(r),
			DeviceSN:         deviceSN,
		})
	}

	batchID := fmt.Sprintf("SIM_IN_%s_%d", branchCode, time.Now().Unix()%100000)
	resp, err := sendBatch(serverURL, branchCode, domain, secret, batchID, punches)
	if err != nil {
		fmt.Printf("   ❌ Lỗi gửi Check-in [%s]: %v\n", branchCode, err)
		return 0
	}
	fmt.Printf("   ✓ Chi nhánh [%s]: Gửi thành công %d lượt Check-in (%s)\n", branchCode, len(punches), resp.Message)
	return len(punches)
}

// runCheckOutOnly chỉ sinh lượt Check-out buổi chiều
func runCheckOutOnly(serverURL, branchCode, domain, secret, deviceSN, dateStr string, employees []EmployeeItem) int {
	r := rand.New(rand.NewSource(time.Now().UnixNano()))
	var punches []AttendancePunch

	for idx, emp := range employees {
		var sec int
		if idx%3 == 0 {
			sec = randomBetween(r, 17*3600+35*60, 18*3600+30*60)
		} else {
			sec = randomBetween(r, 17*3600+1*60, 17*3600+15*60)
		}

		punches = append(punches, AttendancePunch{
			UserCode:         emp.UserCode,
			PunchTime:        formatTimeOfDay(dateStr, sec),
			PunchState:       "CHECK_OUT",
			VerificationMode: randomVerifyMode(r),
			DeviceSN:         deviceSN,
		})
	}

	batchID := fmt.Sprintf("SIM_OUT_%s_%d", branchCode, time.Now().Unix()%100000)
	resp, err := sendBatch(serverURL, branchCode, domain, secret, batchID, punches)
	if err != nil {
		fmt.Printf("   ❌ Lỗi gửi Check-out [%s]: %v\n", branchCode, err)
		return 0
	}
	fmt.Printf("   ✓ Chi nhánh [%s]: Gửi thành công %d lượt Check-out (%s)\n", branchCode, len(punches), resp.Message)
	return len(punches)
}

// runRealtimeDaemonMultiBranch chạy thường trực theo nhịp đồng hồ thời gian thực cho toàn bộ các chi nhánh
func runRealtimeDaemonMultiBranch(serverURL, domain, secret string, branchContexts []BranchContext) {
	fmt.Println("🚀 BẮT ĐẦU CHẾ ĐỘ THỜI GIAN THỰC ĐA CHI NHÁNH (REALTIME DAEMON)...")
	fmt.Printf("   * Số chi nhánh theo dõi: %d chi nhánh\n", len(branchContexts))
	fmt.Println("   * 07:40 - 08:05: Tự động gửi Check-in Đúng giờ")
	fmt.Println("   * 08:20 - 08:45: Tự động gửi Check-in Đi muộn")
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
				fmt.Printf("\n⏰ [%s] Khung 07:40 - 08:05: Đang gửi Check-in Đúng giờ cho %d chi nhánh...\n", now.Format("15:04:05"), len(branchContexts))
				for _, bc := range branchContexts {
					runCheckInOnly(serverURL, bc.Branch.Code, domain, secret, bc.DeviceSN, dateToday, bc.Employees)
				}
				sentTodayOnTime = true
			}

			// Khung 2: 08:20 - 08:45
			if !sentTodayLate && (hour == 8 && minute >= 20 && minute <= 45) {
				fmt.Printf("\n⏰ [%s] Khung sau 08:20: Đang gửi Check-in Đi muộn cho %d chi nhánh...\n", now.Format("15:04:05"), len(branchContexts))
				for _, bc := range branchContexts {
					runCheckInOnly(serverURL, bc.Branch.Code, domain, secret, bc.DeviceSN, dateToday, bc.Employees)
				}
				sentTodayLate = true
			}

			// Khung 3: 17:00 - 18:00
			if !sentTodayCheckOut && (hour >= 17 && hour < 18) {
				fmt.Printf("\n⏰ [%s] Khung 17:00 - 18:00: Đang gửi Check-out cho %d chi nhánh...\n", now.Format("15:04:05"), len(branchContexts))
				for _, bc := range branchContexts {
					runCheckOutOnly(serverURL, bc.Branch.Code, domain, secret, bc.DeviceSN, dateToday, bc.Employees)
				}
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

	client := &http.Client{Timeout: 30 * time.Second}
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

func fetchBranches(serverURL string) []BranchItem {
	reqURL := fmt.Sprintf("%s/api/v1/attendance/branches", strings.TrimRight(serverURL, "/"))
	client := &http.Client{Timeout: 5 * time.Second}

	resp, err := client.Get(reqURL)
	if err != nil || resp.StatusCode != http.StatusOK {
		return getFallbackBranches()
	}
	defer resp.Body.Close()

	var data BranchesResponse
	if err := json.NewDecoder(resp.Body).Decode(&data); err != nil || len(data.Branches) == 0 {
		return getFallbackBranches()
	}

	return data.Branches
}

func getFallbackBranches() []BranchItem {
	return []BranchItem{
		{ID: 1, Code: "HN_HQ", Name: "Hội sở chính Hà Nội"},
		{ID: 2, Code: "DN_HUB", Name: "Trung tâm R&D Đà Nẵng"},
		{ID: 3, Code: "PQ_STORE", Name: "Trung tâm Phân phối Phú Quốc"},
		{ID: 4, Code: "HCM_BRANCH", Name: "Chi nhánh TP.HCM"},
	}
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
	fmt.Printf("      %-10s | %-19s | %-10s | %-12s\n", "MÃ NV", "THỜI GIAN QUẸT", "LOẠI", "PHƯƠNG THỨC")
	fmt.Println("      " + strings.Repeat("-", 60))

	previewCount := 4
	if len(punches) < previewCount {
		previewCount = len(punches)
	}

	for i := 0; i < previewCount; i++ {
		p := punches[i]
		fmt.Printf("      %-10s | %-19s | %-10s | %-12s\n", p.UserCode, p.PunchTime, p.PunchState, p.VerificationMode)
	}

	if len(punches) > previewCount {
		fmt.Printf("      ... và %d bản ghi khác tiếp theo ...\n", len(punches)-previewCount)
	}
}
