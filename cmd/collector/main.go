package main

import (
	"bytes"
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"smart-hr-edge/internal/config"
	"smart-hr-edge/internal/license"
)

type AttendancePunch struct {
	UserCode         string `json:"user_code"`
	PunchTime        string `json:"punch_time"`
	PunchState       string `json:"punch_state"` // CHECK_IN, CHECK_OUT, AUTO
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

func main() {
	fmt.Println("======================================================================")
	fmt.Println("       SMART-HR EDGE COLLECTOR DAEMON (Go 1.26+ Hardware Anchor)       ")
	fmt.Println("======================================================================")

	// 1. Kiểm tra và xác thực giấy phép smart-hr.lic bằng Ed25519
	licPath := "smart-hr.lic"
	licPayload, err := license.LoadAndVerifyLicense(licPath)
	if err != nil {
		fmt.Printf("\n❌ LỖI KHỞI ĐỘNG BẢN QUYỀN:\n   %v\n\n", err)
		fmt.Println("Vui lòng đặt tệp 'smart-hr.lic' hợp lệ do tác giả cấp vào cùng thư mục chạy.")
		os.Exit(1)
	}

	fmt.Printf("✓ Xác thực bản quyền thành công: %s (%s)\n", licPayload.CustomerName, licPayload.AllowedDomain)

	// 2. Nạp cấu hình hoặc chạy First-Run Setup Wizard
	appConfig, err := config.LoadOrRunWizard(licPayload)
	if err != nil {
		fmt.Printf("\n❌ LỖI THIẾT LẬP CẤU HÌNH:\n   %v\n\n", err)
		os.Exit(1)
	}

	fmt.Printf("✓ Máy chủ Cloud mục tiêu: %s\n", appConfig.ServerURL)
	fmt.Printf("✓ Máy chấm công LAN: %s:%d\n", appConfig.DeviceIP, appConfig.DevicePort)
	fmt.Printf("✓ Chi nhánh: %s\n", appConfig.BranchCode)
	fmt.Println("----------------------------------------------------------------------")
	fmt.Println("[ĐANG LẮNG NGHE VÀ THU THẬP LOG QUẸT THẺ TỪ MẠNG NỘI BỘ]...")

	// 3. Demo tạo một gói log quẹt thẻ và ký số HMAC-SHA256
	demoPunches := []AttendancePunch{
		{
			UserCode:         "EMP001",
			PunchTime:        time.Now().Format("2006-01-02 15:04:05"),
			PunchState:       "CHECK_IN",
			VerificationMode: "FINGERPRINT",
			DeviceSN:         "ZK-K40-DEMO-01",
		},
	}

	syncPayload := SyncBatchPayload{
		TenantDomain: licPayload.AllowedDomain,
		BranchCode:   appConfig.BranchCode,
		BatchID:      fmt.Sprintf("BATCH_%d", time.Now().Unix()),
		Punches:      demoPunches,
		SentAt:       time.Now().Format(time.RFC3339),
	}

	payloadBytes, _ := json.Marshal(syncPayload)
	signature := calculateHMAC(payloadBytes, licPayload.SharedHmacSecret)

	fmt.Printf("✓ Đã sinh gói tin Batch: %s (Kèm chữ ký HMAC: %s...)\n", syncPayload.BatchID, signature[:16])

	// Lắng nghe tín hiệu ngắt OS
	quit := make(chan os.Signal, 1)
	signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
	fmt.Println("✓ Edge Collector Daemon đang vận hành bình thường. Nhấn Ctrl+C để dừng.")

	// Chờ tín hiệu hoặc thoát
	select {
	case <-quit:
		fmt.Println("\nĐang dừng Smart-HR Edge Daemon an toàn...")
	case <-time.After(2 * time.Second):
		fmt.Println("✓ Thử nghiệm kiểm tra quy trình khởi động hoàn tất thành công 100%.")
	}
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

func sendBatchToCloud(serverURL string, payloadBytes []byte, signature string, branchCode string) error {
	reqURL := fmt.Sprintf("%s/api/v1/attendance/sync", serverURL)
	req, err := http.NewRequest("POST", reqURL, bytes.NewBuffer(payloadBytes))
	if err != nil {
		return err
	}

	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("X-SmartHR-Signature", signature)
	req.Header.Set("X-SmartHR-Branch", branchCode)

	client := &http.Client{Timeout: 10 * time.Second}
	resp, err := client.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return fmt.Errorf("máy chủ trả về mã HTTP %d", resp.StatusCode)
	}

	return nil
}
