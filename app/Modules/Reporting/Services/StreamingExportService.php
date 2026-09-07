<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Modules\Reporting\Enums\ReportType;
use App\Modules\Reporting\Models\ReportExportHistory;

class StreamingExportService
{
    public function __construct(
        protected ReportDataProviderService $dataProvider
    ) {}

    /**
     * Xuất dữ liệu báo cáo dạng Streaming Response với mức tiêu thụ RAM tối thiểu (RAM < 15MB)
     * Kèm UTF-8 BOM để Microsoft Excel tự động hiển thị font tiếng Việt có dấu chuẩn 100%
     */
    public function export(
        int $tenantId,
        int $userId,
        string $reportType,
        string $format = 'CSV',
        array $filters = [],
        ?string $ipAddress = null
    ): StreamedResponse {
        $startTime = microtime(true);
        $enum = ReportType::tryFrom($reportType) ?? ReportType::TIMESHEET_SUMMARY;
        $timestamp = date('Ymd_His');
        $format = strtoupper($format);
        $extension = ($format === 'EXCEL_TSV') ? 'tsv' : 'csv';
        $fileName = "SmartHR_{$enum->value}_{$timestamp}.{$extension}";

        $delimiter = ($format === 'EXCEL_TSV') ? "\t" : ",";

        $columns = $enum->columns();
        $headerLabels = array_column($columns, 'label');

        $cursor = $this->dataProvider->getReportCursor($tenantId, $enum->value, $filters);

        $headers = [
            'Content-Type' => ($format === 'EXCEL_TSV') ? 'text/tab-separated-values; charset=UTF-8' : 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
            'Pragma' => 'public',
        ];

        return new StreamedResponse(function () use (
            $cursor,
            $headerLabels,
            $delimiter,
            $tenantId,
            $userId,
            $enum,
            $format,
            $fileName,
            $filters,
            $ipAddress,
            $startTime
        ) {
            $handle = fopen('php://output', 'w');

            // 1. Ghi ký tự UTF-8 BOM (\xEF\xBB\xBF) để Excel không bị vỡ font tiếng Việt
            fwrite($handle, "\xEF\xBB\xBF");

            // 2. Ghi dòng tiêu đề (Header row)
            fputcsv($handle, $headerLabels, $delimiter);

            // 3. Streaming dữ liệu từng dòng từ LazyCollection
            $rowCount = 0;
            foreach ($cursor as $row) {
                fputcsv($handle, (array)$row, $delimiter);
                $rowCount++;
            }

            fclose($handle);

            // 4. Lưu vết lịch sử xuất báo cáo (Audit Trail)
            $executionTimeMs = (int)round((microtime(true) - $startTime) * 1000);
            ReportExportHistory::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'report_type' => $enum->value,
                'file_format' => $format,
                'file_name' => $fileName,
                'filter_params' => $filters,
                'row_count' => $rowCount,
                'execution_time_ms' => $executionTimeMs,
                'ip_address' => $ipAddress,
            ]);
        }, 200, $headers);
    }
}
