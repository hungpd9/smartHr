package license

import (
	"crypto/ed25519"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"os"
	"time"
)

// AuthorPublicKeyHex là khóa công khai của tác giả được nhúng cứng vào file nhị phân Go
const AuthorPublicKeyHex = "d26114cc88643c69ae8534b63442f35808b6d59cc02da54d8dbbe49ebd63ee80"

type LicenseFile struct {
	Format    string `json:"format"`
	Payload   string `json:"payload"`
	Signature string `json:"signature"`
}

type LicensePayload struct {
	AllowedDomain    string `json:"allowed_domain"`
	CustomerName     string `json:"customer_name"`
	ExpiresAt        string `json:"expires_at"`
	SharedHmacSecret string `json:"shared_hmac_secret"`
	IssuedAt         string `json:"issued_at"`
}

// LoadAndVerifyLicense đọc và kiểm tra tính toàn vẹn của smart-hr.lic bằng Ed25519
func LoadAndVerifyLicense(path string) (*LicensePayload, error) {
	data, err := os.ReadFile(path)
	if err != nil {
		return nil, fmt.Errorf("không thể đọc tệp giấy phép '%s': %w", path, err)
	}

	var licFile LicenseFile
	if err := json.Unmarshal(data, &licFile); err != nil {
		return nil, fmt.Errorf("tệp giấy phép không đúng định dạng JSON: %w", err)
	}

	if licFile.Format != "SMARTHR-LIC-V1" {
		return nil, errors.New("định dạng giấy phép không được hỗ trợ (cần SMARTHR-LIC-V1)")
	}

	pubKeyBytes, err := hex.DecodeString(AuthorPublicKeyHex)
	if err != nil || len(pubKeyBytes) != ed25519.PublicKeySize {
		return nil, errors.New("khóa công khai tác giả nhúng trong binary bị lỗi")
	}

	sigBytes, err := hex.DecodeString(licFile.Signature)
	if err != nil || len(sigBytes) != ed25519.SignatureSize {
		return nil, errors.New("chữ ký số trong giấy phép không hợp lệ (sai kích thước hoặc ký tự)")
	}

	payloadBytes, err := base64.StdEncoding.DecodeString(licFile.Payload)
	if err != nil {
		return nil, fmt.Errorf("không thể giải mã payload base64: %w", err)
	}

	// Xác thực chữ ký số Ed25519
	if !ed25519.Verify(pubKeyBytes, payloadBytes, sigBytes) {
		return nil, errors.New("CHỮ KÝ SỐ KHÔNG HỢP LỆ! Tệp giấy phép đã bị chỉnh sửa hoặc giả mạo")
	}

	var payload LicensePayload
	if err := json.Unmarshal(payloadBytes, &payload); err != nil {
		return nil, fmt.Errorf("không thể trích xuất thông tin bản quyền: %w", err)
	}

	// Kiểm tra thời hạn giấy phép
	expiryTime, err := time.Parse("2006-01-02", payload.ExpiresAt)
	if err == nil && time.Now().After(expiryTime.Add(24*time.Hour)) {
		return &payload, fmt.Errorf("GIẤY PHÉP ĐÃ HẾT HẠN vào ngày %s", payload.ExpiresAt)
	}

	return &payload, nil
}
