package config

import (
	"bufio"
	"encoding/json"
	"fmt"
	"net/url"
	"os"
	"path/filepath"
	"strings"

	"smart-hr-edge/internal/license"
)

type AppConfig struct {
	ServerURL  string `json:"server_url"`
	DeviceIP   string `json:"device_ip"`
	DevicePort int    `json:"device_port"`
	BranchCode string `json:"branch_code"`
}

const ConfigFileName = "config.json"

// LoadOrRunWizard kiểm tra xem config.json đã tồn tại hay chưa, nếu chưa thì chạy First-run Wizard
func LoadOrRunWizard(lic *license.LicensePayload) (*AppConfig, error) {
	if _, err := os.Stat(ConfigFileName); err == nil {
		// Đã có tệp cấu hình, đọc và kiểm tra lại domain
		data, err := os.ReadFile(ConfigFileName)
		if err != nil {
			return nil, fmt.Errorf("không thể đọc tệp config.json: %w", err)
		}
		var cfg AppConfig
		if err := json.Unmarshal(data, &cfg); err != nil {
			return nil, fmt.Errorf("lỗi đọc nội dung config.json: %w", err)
		}

		// Bảo đảm domain trong config vẫn khớp với giấy phép
		parsedURL, err := url.Parse(cfg.ServerURL)
		if err == nil {
			host := strings.ToLower(parsedURL.Hostname())
			if host != "" && host != strings.ToLower(lic.AllowedDomain) {
				return nil, fmt.Errorf("tên miền trong config (%s) không khớp với giấy phép bản quyền (%s)", host, lic.AllowedDomain)
			}
		}

		return &cfg, nil
	}

	// Lần đầu chạy -> Setup Wizard
	return RunSetupWizard(lic)
}

func RunSetupWizard(lic *license.LicensePayload) (*AppConfig, error) {
	reader := bufio.NewReader(os.Stdin)

	fmt.Println("======================================================================")
	fmt.Println("               SMART-HR EDGE COLLECTOR - SETUP WIZARD                 ")
	fmt.Println("======================================================================")
	fmt.Println("[THÔNG TIN GIẤY PHÉP ĐÃ XÁC THỰC]")
	fmt.Printf("  ✓ Đơn vị bản quyền : %s\n", lic.CustomerName)
	fmt.Printf("  ✓ Tên miền đăng ký : %s\n", lic.AllowedDomain)
	fmt.Printf("  ✓ Thời hạn sử dụng : Đến hết ngày %s\n", lic.ExpiresAt)
	fmt.Println("----------------------------------------------------------------------")
	fmt.Println("[THIẾT LẬP KẾT NỐI MÁY CHỦ CLOUD]")

	var inputURL string
	for {
		fmt.Printf("> Nhập URL máy chủ Smart-HR của bạn (VD: https://%s): ", lic.AllowedDomain)
		line, _ := reader.ReadString('\n')
		inputURL = strings.TrimSpace(line)
		if inputURL == "" {
			inputURL = fmt.Sprintf("https://%s", lic.AllowedDomain)
		}

		// Chuẩn hóa URL
		if !strings.HasPrefix(inputURL, "http://") && !strings.HasPrefix(inputURL, "https://") {
			inputURL = "https://" + inputURL
		}

		parsed, err := url.Parse(inputURL)
		if err != nil || parsed.Hostname() == "" {
			fmt.Println("  ❌ URL không hợp lệ! Vui lòng nhập lại.")
			continue
		}

		inputHost := strings.ToLower(parsed.Hostname())
		expectedHost := strings.ToLower(lic.AllowedDomain)

		if inputHost != expectedHost {
			fmt.Printf("  ❌ TỪ CHỐI! Tên miền '%s' không khớp với giấy phép bản quyền '%s'!\n", inputHost, expectedHost)
			fmt.Println("  Vui lòng nhập đúng URL hệ thống của doanh nghiệp được cấp phép.")
			continue
		}

		fmt.Printf("  ✓ Tên miền khớp hoàn toàn với giấy phép bản quyền!\n")
		break
	}

	fmt.Println("----------------------------------------------------------------------")
	fmt.Println("[CẤU HÌNH MÁY CHẤM CÔNG TRONG MẠNG LAN]")

	fmt.Print("> Nhập IP máy chấm công (Mặc định: 192.168.1.201): ")
	deviceIP, _ := reader.ReadString('\n')
	deviceIP = strings.TrimSpace(deviceIP)
	if deviceIP == "" {
		deviceIP = "192.168.1.201"
	}

	fmt.Print("> Nhập mã chi nhánh (Mặc định: HN_HQ): ")
	branchCode, _ := reader.ReadString('\n')
	branchCode = strings.TrimSpace(branchCode)
	if branchCode == "" {
		branchCode = "HN_HQ"
	}

	cfg := &AppConfig{
		ServerURL:  inputURL,
		DeviceIP:   deviceIP,
		DevicePort: 4370, // Cổng chuẩn ZKTeco protocol
		BranchCode: branchCode,
	}

	// Lưu file config.json
	cfgData, err := json.MarshalIndent(cfg, "", "  ")
	if err != nil {
		return nil, err
	}

	if err := os.WriteFile(ConfigFileName, cfgData, 0644); err != nil {
		return nil, fmt.Errorf("không thể lưu tệp cấu hình %s: %w", ConfigFileName, err)
	}

	absPath, _ := filepath.Abs(ConfigFileName)
	fmt.Printf("\n✓ ĐÃ KHỞI TẠO CẤU HÌNH THÀNH CÔNG TẠI: %s\n", absPath)
	fmt.Println("======================================================================")

	return cfg, nil
}
