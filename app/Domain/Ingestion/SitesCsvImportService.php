<?php
/**
 * eclectyc-energy/app/Domain/Ingestion/SitesCsvImportService.php
 * Service for importing sites from CSV files
 * Last updated: 2025-11-15
 */

namespace App\Domain\Ingestion;

use PDO;
use PDOException;
use Exception;
use League\Csv\Reader;

class SitesCsvImportService
{
    private PDO $pdo;
    
    // Expected CSV headers (case-insensitive matching)
    private const HEADER_ALIASES = [
        'name' => ['name', 'site_name', 'sitename', 'site name'],
        'company_id' => ['company_id', 'companyid', 'company id', 'company'],
        'region_id' => ['region_id', 'regionid', 'region id', 'region'],
        'address' => ['address', 'site_address', 'street address'],
        'postcode' => ['postcode', 'post code', 'postal code', 'zip'],
        'site_type' => ['site_type', 'sitetype', 'type'],
        'floor_area' => ['floor_area', 'floorarea', 'area', 'floor area'],
        'latitude' => ['latitude', 'lat'],
        'longitude' => ['longitude', 'lon', 'long', 'lng'],
        'is_active' => ['is_active', 'active', 'status'],
        'created_at' => ['created_at', 'created', 'date created', 'creation_date'],
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Import sites from CSV file
     */
    public function importFromCsv(
        string $filePath,
        string $batchId,
        bool $dryRun = false,
        ?int $userId = null,
        ?callable $progressCallback = null
    ): IngestionResult {
        $result = new IngestionResult($batchId, 'sites');
        
        if (!file_exists($filePath)) {
            throw new Exception("CSV file not found: $filePath");
        }

        // Detect delimiter
        $delimiter = $this->detectDelimiter($filePath);
        
        try {
            $reader = Reader::createFromPath($filePath, 'r');
            $reader->setDelimiter($delimiter);
            $reader->setHeaderOffset(0);
            
            $headers = $reader->getHeader();
            $headerMap = $this->buildHeaderMap($headers);
            
            // Validate required headers
            $this->validateHeaders($headerMap);
            
            $records = $reader->getRecords();
            $rowNumber = 1; // Header is row 0
            
            foreach ($records as $record) {
                $rowNumber++;
                $result->incrementRecordsProcessed();
                
                if ($progressCallback) {
                    $progressCallback(
                        $result->getRecordsProcessed(),
                        $result->getRecordsImported(),
                        count($result->getErrors())
                    );
                }
                
                try {
                    $siteData = $this->extractSiteData($record, $headerMap, $rowNumber);
                    
                    if (!$dryRun) {
                        $this->insertSite($siteData);
                    }
                    
                    $result->incrementRecordsImported();
                } catch (Exception $e) {
                    $result->addError("Row $rowNumber: " . $e->getMessage());
                    $result->incrementRecordsFailed();
                }
            }
            
            $result->setMeta([
                'format' => 'sites',
                'dry_run' => $dryRun,
                'delimiter' => $delimiter === "\t" ? 'TAB' : $delimiter,
            ]);
            
        } catch (Exception $e) {
            throw new Exception("CSV parsing failed: " . $e->getMessage());
        }
        
        return $result;
    }

    /**
     * Detect CSV delimiter
     */
    private function detectDelimiter(string $filePath): string
    {
        $candidates = [',', "\t", ';', '|'];
        $bestDelimiter = ',';
        $highestCount = -1;

        $handle = @fopen($filePath, 'r');
        if ($handle === false) {
            return $bestDelimiter;
        }

        $line = fgets($handle, 65536) ?: '';
        fclose($handle);

        foreach ($candidates as $delimiter) {
            $count = substr_count($line, $delimiter);
            if ($count > $highestCount) {
                $highestCount = $count;
                $bestDelimiter = $delimiter;
            }
        }

        return $bestDelimiter;
    }

    /**
     * Build header map from CSV headers
     */
    private function buildHeaderMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $index => $header) {
            $normalizedHeader = strtolower(trim($header));
            $map[$index] = $normalizedHeader;
        }
        return $map;
    }

    /**
     * Check if header exists in map
     */
    private function hasAlias(array $headerMap, array $aliases): ?string
    {
        foreach ($headerMap as $index => $header) {
            if (in_array($header, $aliases, true)) {
                return $index;
            }
        }
        return null;
    }

    /**
     * Get value from record using header aliases
     */
    private function getValue(array $record, array $headerMap, array $aliases): ?string
    {
        $index = $this->hasAlias($headerMap, $aliases);
        if ($index !== null && isset($record[$index])) {
            $value = trim($record[$index]);
            return $value !== '' ? $value : null;
        }
        return null;
    }

    /**
     * Validate required headers
     */
    private function validateHeaders(array $headerMap): void
    {
        // Required field: name
        if (!$this->hasAlias($headerMap, self::HEADER_ALIASES['name'])) {
            throw new Exception('CSV must include a "name" column');
        }
    }

    /**
     * Extract site data from CSV record with validation
     */
    private function extractSiteData(array $record, array $headerMap, int $rowNumber): array
    {
        $data = [];
        
        // Get column positions for error messages
        $columns = array_keys($record);
        
        // Required: Name
        $name = $this->getValue($record, $headerMap, self::HEADER_ALIASES['name']);
        if (empty($name)) {
            throw new Exception("Missing required field 'name'");
        }
        $data['name'] = $name;
        
        // Optional: Company ID
        $companyId = $this->getValue($record, $headerMap, self::HEADER_ALIASES['company_id']);
        if ($companyId !== null) {
            if (!is_numeric($companyId)) {
                $columnIndex = $this->hasAlias($headerMap, self::HEADER_ALIASES['company_id']);
                $columnNumber = array_search($columnIndex, $columns) + 1;
                throw new Exception("Invalid company_id in row $rowNumber, column $columnNumber: must be numeric");
            }
            $data['company_id'] = (int) $companyId;
        } else {
            $data['company_id'] = null;
        }
        
        // Optional: Region ID
        $regionId = $this->getValue($record, $headerMap, self::HEADER_ALIASES['region_id']);
        if ($regionId !== null) {
            if (!is_numeric($regionId)) {
                $columnIndex = $this->hasAlias($headerMap, self::HEADER_ALIASES['region_id']);
                $columnNumber = array_search($columnIndex, $columns) + 1;
                throw new Exception("Invalid region_id in row $rowNumber, column $columnNumber: must be numeric");
            }
            $data['region_id'] = (int) $regionId;
        } else {
            $data['region_id'] = null;
        }
        
        // Optional: Address
        $data['address'] = $this->getValue($record, $headerMap, self::HEADER_ALIASES['address']);
        
        // Optional: Postcode
        $data['postcode'] = $this->getValue($record, $headerMap, self::HEADER_ALIASES['postcode']);
        
        // Optional: Site Type
        $siteType = $this->getValue($record, $headerMap, self::HEADER_ALIASES['site_type']);
        if ($siteType !== null) {
            $validTypes = ['office', 'warehouse', 'retail', 'industrial', 'residential', 'other'];
            $siteType = strtolower($siteType);
            if (!in_array($siteType, $validTypes, true)) {
                $columnIndex = $this->hasAlias($headerMap, self::HEADER_ALIASES['site_type']);
                $columnNumber = array_search($columnIndex, $columns) + 1;
                throw new Exception("Invalid site_type in row $rowNumber, column $columnNumber: must be one of " . implode(', ', $validTypes));
            }
            $data['site_type'] = $siteType;
        } else {
            $data['site_type'] = 'office';
        }
        
        // Optional: Floor Area
        $floorArea = $this->getValue($record, $headerMap, self::HEADER_ALIASES['floor_area']);
        if ($floorArea !== null) {
            if (!is_numeric($floorArea)) {
                $columnIndex = $this->hasAlias($headerMap, self::HEADER_ALIASES['floor_area']);
                $columnNumber = array_search($columnIndex, $columns) + 1;
                throw new Exception("Invalid floor_area in row $rowNumber, column $columnNumber: must be numeric");
            }
            $data['floor_area'] = (float) $floorArea;
        } else {
            $data['floor_area'] = null;
        }
        
        // Optional: Latitude
        $latitude = $this->getValue($record, $headerMap, self::HEADER_ALIASES['latitude']);
        if ($latitude !== null) {
            if (!is_numeric($latitude)) {
                $columnIndex = $this->hasAlias($headerMap, self::HEADER_ALIASES['latitude']);
                $columnNumber = array_search($columnIndex, $columns) + 1;
                throw new Exception("Invalid latitude in row $rowNumber, column $columnNumber: must be numeric");
            }
            $lat = (float) $latitude;
            if ($lat < -90 || $lat > 90) {
                throw new Exception("Invalid latitude: must be between -90 and 90");
            }
            $data['latitude'] = $lat;
        } else {
            $data['latitude'] = null;
        }
        
        // Optional: Longitude
        $longitude = $this->getValue($record, $headerMap, self::HEADER_ALIASES['longitude']);
        if ($longitude !== null) {
            if (!is_numeric($longitude)) {
                $columnIndex = $this->hasAlias($headerMap, self::HEADER_ALIASES['longitude']);
                $columnNumber = array_search($columnIndex, $columns) + 1;
                throw new Exception("Invalid longitude in row $rowNumber, column $columnNumber: must be numeric");
            }
            $lng = (float) $longitude;
            if ($lng < -180 || $lng > 180) {
                throw new Exception("Invalid longitude: must be between -180 and 180");
            }
            $data['longitude'] = $lng;
        } else {
            $data['longitude'] = null;
        }
        
        // Optional: Is Active
        $isActive = $this->getValue($record, $headerMap, self::HEADER_ALIASES['is_active']);
        if ($isActive !== null) {
            $isActive = strtolower($isActive);
            $data['is_active'] = in_array($isActive, ['1', 'true', 'yes', 'active'], true) ? 1 : 0;
        } else {
            $data['is_active'] = 1;
        }
        
        // Optional: Created At - with date format validation
        $createdAt = $this->getValue($record, $headerMap, self::HEADER_ALIASES['created_at']);
        if ($createdAt !== null) {
            // Validate date format
            $validFormats = [
                'Y-m-d H:i:s',
                'Y-m-d',
                'd/m/Y',
                'd-m-Y',
                'm/d/Y',
                'Y/m/d',
            ];
            
            $parsedDate = null;
            foreach ($validFormats as $format) {
                $date = \DateTime::createFromFormat($format, $createdAt);
                if ($date !== false) {
                    $parsedDate = $date->format('Y-m-d H:i:s');
                    break;
                }
            }
            
            if ($parsedDate === null) {
                $columnIndex = $this->hasAlias($headerMap, self::HEADER_ALIASES['created_at']);
                $columnNumber = array_search($columnIndex, $columns) + 1;
                throw new Exception("Invalid date format in row $rowNumber, column $columnNumber");
            }
            
            $data['created_at'] = $parsedDate;
        } else {
            $data['created_at'] = null; // Will use database default
        }
        
        return $data;
    }

    /**
     * Insert site into database
     */
    private function insertSite(array $data): void
    {
        $sql = '
            INSERT INTO sites (
                name, company_id, region_id, address, postcode,
                site_type, floor_area, latitude, longitude, is_active, created_at
            ) VALUES (
                :name, :company_id, :region_id, :address, :postcode,
                :site_type, :floor_area, :latitude, :longitude, :is_active, :created_at
            )
        ';
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'name' => $data['name'],
            'company_id' => $data['company_id'],
            'region_id' => $data['region_id'],
            'address' => $data['address'],
            'postcode' => $data['postcode'],
            'site_type' => $data['site_type'],
            'floor_area' => $data['floor_area'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'is_active' => $data['is_active'],
            'created_at' => $data['created_at'],
        ]);
    }
}
