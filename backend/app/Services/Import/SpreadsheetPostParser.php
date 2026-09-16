<?php

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Log;

class SpreadsheetPostParser
{
    /**
     * Map of standard field names to possible Vietnamese column headers
     */
    protected $headerMappings = [
        'stt' => ['stt', 'no', 'số thứ tự', 'so thu tu'],
        'title' => ['title', 'tiêu đề', 'tieu de', 'heading'],
        'content' => ['content', 'nội dung', 'noi dung', 'body', 'text'],
        'cta' => ['cta', 'call to action', 'kêu gọi hành động', 'keu goi hanh dong'],
        'hashtags' => ['hashtags', 'hashtag', 'tags', 'thẻ'],
        'image' => ['image', 'ảnh', 'anh', 'hình ảnh', 'hinh anh', 'picture'],
        'image_key' => ['image_key', 'mã ảnh', 'ma anh'],
        'publish_date' => ['publish_date', 'ngày đăng', 'ngay dang', 'date'],
        'publish_time' => ['publish_time', 'giờ đăng', 'gio dang', 'time'],
        'timezone' => ['timezone', 'múi giờ', 'mui gio'],
        'facebook_page_id' => ['facebook_page_id', 'facebook page', 'page id'],
        'brand_id' => ['brand_id', 'thương hiệu', 'thuong hieu', 'brand'],
    ];

    public function parse(string $filePath): array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            if (empty($rows)) {
                return [];
            }

            $headers = array_shift($rows);
            $columnMap = $this->mapHeaders($headers);
            
            $parsedData = [];
            
            foreach ($rows as $index => $row) {
                // Skip completely empty rows
                if (empty(array_filter($row))) {
                    continue;
                }
                
                $rowData = $this->extractRowData($row, $columnMap);
                
                // Add meta info
                $rowData['_row_number'] = $index + 2; // +1 for 0-index, +1 for header row
                
                $parsedData[] = $rowData;
            }

            return $parsedData;

        } catch (\Exception $e) {
            Log::error('Spreadsheet parsing failed: ' . $e->getMessage());
            throw new \Exception('Không thể đọc file Excel/CSV. Vui lòng kiểm tra lại định dạng.');
        }
    }

    protected function mapHeaders(array $headers): array
    {
        $map = [];
        
        foreach ($headers as $colIndex => $headerValue) {
            if (empty($headerValue)) continue;
            
            $normalizedHeader = mb_strtolower(trim($headerValue), 'UTF-8');
            
            foreach ($this->headerMappings as $standardField => $possibleNames) {
                if (in_array($normalizedHeader, $possibleNames)) {
                    $map[$standardField] = $colIndex;
                    break;
                }
            }
        }
        
        return $map;
    }

    protected function extractRowData(array $row, array $columnMap): array
    {
        $data = [
            'stt' => $this->getCellValue($row, $columnMap, 'stt'),
            'title' => $this->getCellValue($row, $columnMap, 'title'),
            'content' => $this->getCellValue($row, $columnMap, 'content'),
            'cta' => $this->getCellValue($row, $columnMap, 'cta'),
            'hashtags' => $this->getCellValue($row, $columnMap, 'hashtags'),
            'image' => $this->getCellValue($row, $columnMap, 'image'),
            'image_key' => $this->getCellValue($row, $columnMap, 'image_key'),
            'publish_date' => $this->getCellValue($row, $columnMap, 'publish_date'),
            'publish_time' => $this->getCellValue($row, $columnMap, 'publish_time'),
            'timezone' => $this->getCellValue($row, $columnMap, 'timezone'),
            'facebook_page_id' => $this->getCellValue($row, $columnMap, 'facebook_page_id'),
            'brand_id' => $this->getCellValue($row, $columnMap, 'brand_id'),
        ];
        
        // Clean hashtags
        if (!empty($data['hashtags'])) {
            // Split by comma or space and ensure # prefix
            $tags = preg_split('/[\s,]+/', $data['hashtags']);
            $tags = array_filter(array_map('trim', $tags));
            $data['hashtags'] = array_values($tags);
        } else {
            $data['hashtags'] = [];
        }

        // Clean CTA
        if (!empty($data['cta'])) {
            $data['cta'] = trim($data['cta']);
        }
        
        return $data;
    }

    protected function getCellValue(array $row, array $columnMap, string $field)
    {
        if (isset($columnMap[$field]) && isset($row[$columnMap[$field]])) {
            return trim((string)$row[$columnMap[$field]]);
        }
        return null;
    }
}
