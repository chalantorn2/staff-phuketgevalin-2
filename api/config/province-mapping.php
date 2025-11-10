<?php
// api/config/province-mapping.php
// Province detection mapping for Thailand

class ProvinceMapping
{
    /**
     * Airport to Province mapping
     * Covers major airports in Thailand
     */
    public static $airportMap = [
        // Bangkok
        'Suvarnabhumi' => 'Bangkok',
        'Suvarnabhumi International' => 'Bangkok',
        'Suvarnabhumi Airport' => 'Bangkok',
        'BKK' => 'Bangkok',
        'Don Mueang' => 'Bangkok',
        'Don Mueang International' => 'Bangkok',
        'Don Muang' => 'Bangkok',
        'DMK' => 'Bangkok',

        // Phuket - สนามบินภูเก็ต
        'Phuket' => 'Phuket',
        'Phuket International' => 'Phuket',
        'Phuket Airport' => 'Phuket',
        'สนามบินภูเก็ต' => 'Phuket',
        'ภูเก็ต' => 'Phuket',
        'HKT' => 'Phuket',

        // Krabi - สนามบินกระบี่
        'Krabi' => 'Krabi',
        'Krabi International' => 'Krabi',
        'Krabi Airport' => 'Krabi',
        'สนามบินกระบี่' => 'Krabi',
        'กระบี่' => 'Krabi',
        'KBV' => 'Krabi',

        // Surat Thani (Koh Samui) - สนามบินสมุย
        'Samui' => 'Surat Thani',
        'Koh Samui' => 'Surat Thani',
        'Ko Samui' => 'Surat Thani',
        'Samui Airport' => 'Surat Thani',
        'Ko Samui Airport' => 'Surat Thani',
        'สนามบินสมุย' => 'Surat Thani',
        'เกอะสมุย' => 'Surat Thani',
        'สมุย' => 'Surat Thani',
        'USM' => 'Surat Thani',

        // Chiang Mai - สนามบินเชียงใหม่
        'Chiang Mai' => 'Chiang Mai',
        'Chiang Mai International' => 'Chiang Mai',
        'Chiangmai' => 'Chiang Mai',
        'สนามบินเชียงใหม่' => 'Chiang Mai',
        'เชียงใหม่' => 'Chiang Mai',
        'CNX' => 'Chiang Mai',

        // Chiang Rai - เชียงราย
        'Chiang Rai' => 'Chiang Rai',
        'Chiang Rai International' => 'Chiang Rai',
        'Chiangrai' => 'Chiang Rai',
        'Mae Fah Luang' => 'Chiang Rai',
        'Mae Fah Luang Chiang Rai' => 'Chiang Rai',
        'สนามบินเชียงราย' => 'Chiang Rai',
        'เชียงราย' => 'Chiang Rai',
        'CEI' => 'Chiang Rai',

        // Pattaya/Rayong (U-Tapao) - อู่ตะเภา
        'U-Tapao' => 'Rayong',
        'U-Tapao International' => 'Rayong',
        'U-Tapao Rayong-Pattaya' => 'Rayong',
        'Utapao' => 'Rayong',
        'อู่ตะเภา' => 'Rayong',
        'UTP' => 'Rayong',

        // Kanchanaburi - กาญจนบุรี (ไม่มีสนามบิน แต่เพิ่มเผื่อมี Resort/Accommodation ชื่อนี้)
        'Kanchanaburi' => 'Kanchanaburi',
        'กาญจนบุรี' => 'Kanchanaburi',

        // Hat Yai (Songkhla)
        'Hat Yai' => 'Songkhla',
        'Hat Yai International' => 'Songkhla',
        'HDY' => 'Songkhla',

        // Other airports
        'Trat Airport' => 'Trat',
        'TDX' => 'Trat',

        'Phitsanulok Airport' => 'Phitsanulok',
        'PHS' => 'Phitsanulok',

        'Udon Thani' => 'Udon Thani',
        'Udon Thani International' => 'Udon Thani',
        'UTH' => 'Udon Thani',

        'Khon Kaen' => 'Khon Kaen',
        'KKC' => 'Khon Kaen',

        'Ubon Ratchathani' => 'Ubon Ratchathani',
        'UBP' => 'Ubon Ratchathani',

        'Nakhon Si Thammarat' => 'Nakhon Si Thammarat',
        'NST' => 'Nakhon Si Thammarat',

        'Surat Thani Airport' => 'Surat Thani',
        'URT' => 'Surat Thani',

        'Hua Hin Airport' => 'Prachuap Khiri Khan',
        'HHQ' => 'Prachuap Khiri Khan',
    ];

    /**
     * Postal Code (first 2 digits) to Province mapping
     * Based on Thailand postal code system
     */
    public static $postalMap = [
        '10' => 'Bangkok',
        '11' => 'Samut Prakan',
        '12' => 'Nonthaburi',
        '13' => 'Pathum Thani',
        '14' => 'Phra Nakhon Si Ayutthaya',
        '15' => 'Ang Thong',
        '16' => 'Lop Buri',
        '17' => 'Sing Buri',
        '18' => 'Chai Nat',
        '19' => 'Saraburi',
        '20' => 'Chonburi',
        '21' => 'Rayong',
        '22' => 'Chanthaburi',
        '23' => 'Trat',
        '24' => 'Chachoengsao',
        '25' => 'Prachin Buri',
        '26' => 'Nakhon Nayok',
        '27' => 'Sa Kaeo',
        '30' => 'Nakhon Ratchasima',
        '31' => 'Buri Ram',
        '32' => 'Surin',
        '33' => 'Si Sa Ket',
        '34' => 'Ubon Ratchathani',
        '35' => 'Yasothon',
        '36' => 'Chaiyaphum',
        '37' => 'Amnat Charoen',
        '38' => 'Bueng Kan',
        '39' => 'Nong Bua Lam Phu',
        '40' => 'Khon Kaen',
        '41' => 'Udon Thani',
        '42' => 'Loei',
        '43' => 'Nong Khai',
        '44' => 'Maha Sarakham',
        '45' => 'Roi Et',
        '46' => 'Kalasin',
        '47' => 'Sakon Nakhon',
        '48' => 'Nakhon Phanom',
        '49' => 'Mukdahan',
        '50' => 'Chiang Mai',
        '51' => 'Lamphun',
        '52' => 'Lampang',
        '53' => 'Uttaradit',
        '54' => 'Phrae',
        '55' => 'Nan',
        '56' => 'Phayao',
        '57' => 'Chiang Rai',
        '58' => 'Mae Hong Son',
        '60' => 'Nakhon Sawan',
        '61' => 'Uthai Thani',
        '62' => 'Kamphaeng Phet',
        '63' => 'Tak',
        '64' => 'Sukhothai',
        '65' => 'Phitsanulok',
        '66' => 'Phichit',
        '67' => 'Phetchabun',
        '70' => 'Ratchaburi',
        '71' => 'Kanchanaburi',
        '72' => 'Suphan Buri',
        '73' => 'Nakhon Pathom',
        '74' => 'Samut Sakhon',
        '75' => 'Samut Songkhram',
        '76' => 'Phetchaburi',
        '77' => 'Prachuap Khiri Khan',
        '80' => 'Nakhon Si Thammarat',
        '81' => 'Krabi',
        '82' => 'Phang Nga',
        '83' => 'Phuket',
        '84' => 'Surat Thani',
        '85' => 'Ranong',
        '86' => 'Chumphon',
        '90' => 'Songkhla',
        '91' => 'Satun',
        '92' => 'Trang',
        '93' => 'Phatthalung',
        '94' => 'Pattani',
        '95' => 'Yala',
        '96' => 'Narathiwat',
    ];

    /**
     * Get all unique provinces (sorted alphabetically)
     * Returns only active/common provinces for dropdown
     */
    public static function getAllProvinces()
    {
        // Return only the provinces that the business actually uses
        // Based on user request: only show specific provinces
        $activeProvinces = [
            'Bangkok',
            'Chiang Mai',
            'Chiang Rai',
            'Kanchanaburi',
            'Krabi',
            'Phuket',
            'Rayong',
            'Surat Thani', // สมุย
        ];

        sort($activeProvinces);
        return $activeProvinces;
    }

    /**
     * Detect province from airport name/code
     * Case-insensitive matching
     */
    public static function detectFromAirport($airportName)
    {
        if (empty($airportName)) {
            return null;
        }

        // Try exact match first (case-insensitive)
        foreach (self::$airportMap as $key => $province) {
            if (strcasecmp($key, $airportName) === 0) {
                return [
                    'province' => $province,
                    'source' => 'airport',
                    'confidence' => 'high',
                    'matched_key' => $key
                ];
            }
        }

        // Try partial match (contains)
        foreach (self::$airportMap as $key => $province) {
            if (stripos($airportName, $key) !== false || stripos($key, $airportName) !== false) {
                return [
                    'province' => $province,
                    'source' => 'airport',
                    'confidence' => 'high',
                    'matched_key' => $key
                ];
            }
        }

        return null;
    }

    /**
     * Detect province from postal code
     * Extract 5-digit postal code from address string
     */
    public static function detectFromPostalCode($address)
    {
        if (empty($address)) {
            return null;
        }

        // Find 5-digit postal code pattern
        if (preg_match('/\b(\d{5})\b/', $address, $matches)) {
            $postalCode = $matches[1];
            $provinceCode = substr($postalCode, 0, 2);

            if (isset(self::$postalMap[$provinceCode])) {
                return [
                    'province' => self::$postalMap[$provinceCode],
                    'source' => 'postal',
                    'confidence' => 'medium',
                    'postal_code' => $postalCode
                ];
            }
        }

        return null;
    }

    /**
     * Detect province from province name (for Quote bookings)
     * Matches Thai and English province names
     */
    public static function matchProvinceName($provinceName)
    {
        if (empty($provinceName)) {
            return null;
        }

        $provinceMap = [
            // English names
            'Bangkok' => 'Bangkok',
            'Chiang Mai' => 'Chiang Mai',
            'Chiang Rai' => 'Chiang Rai',
            'Kanchanaburi' => 'Kanchanaburi',
            'Krabi' => 'Krabi',
            'Phuket' => 'Phuket',
            'Rayong' => 'Rayong',
            'Surat Thani' => 'Surat Thani',
            'Phang Nga' => 'Phang Nga',
            'Songkhla' => 'Songkhla',

            // Thai names
            'กรุงเทพ' => 'Bangkok',
            'กรุงเทพมหานคร' => 'Bangkok',
            'เชียงใหม่' => 'Chiang Mai',
            'เชียงราย' => 'Chiang Rai',
            'กาญจนบุรี' => 'Kanchanaburi',
            'กระบี่' => 'Krabi',
            'ภูเก็ต' => 'Phuket',
            'ระยอง' => 'Rayong',
            'สุราษฎร์ธานี' => 'Surat Thani',
            'พังงา' => 'Phang Nga',
            'สงขลา' => 'Songkhla',
        ];

        // Try exact match (case-insensitive)
        foreach ($provinceMap as $key => $province) {
            if (strcasecmp($key, trim($provinceName)) === 0) {
                return [
                    'province' => $province,
                    'source' => 'province_name',
                    'confidence' => 'high',
                    'matched_key' => $key
                ];
            }
        }

        // Try partial match
        foreach ($provinceMap as $key => $province) {
            if (stripos($provinceName, $key) !== false || stripos($key, $provinceName) !== false) {
                return [
                    'province' => $province,
                    'source' => 'province_name',
                    'confidence' => 'medium',
                    'matched_key' => $key
                ];
            }
        }

        return null;
    }

    /**
     * Detect province for Quote bookings (Point-to-Point transfers)
     * Uses pickup/dropoff addresses instead of airport
     */
    public static function detectProvinceForQuote($quoteData)
    {
        // Priority 1: Province name from pickup_address3 (highest confidence)
        if (!empty($quoteData['pickup_address3'])) {
            $result = self::matchProvinceName($quoteData['pickup_address3']);
            if ($result) {
                return $result;
            }
        }

        // Priority 2: Province name from pickup_address2 (city/area)
        if (!empty($quoteData['pickup_address2'])) {
            $result = self::matchProvinceName($quoteData['pickup_address2']);
            if ($result) {
                return $result;
            }
        }

        // Priority 3: Postal code from pickup_address4
        if (!empty($quoteData['pickup_address4'])) {
            $postalCode = trim($quoteData['pickup_address4']);
            if (preg_match('/^\d{5}$/', $postalCode)) {
                $provinceCode = substr($postalCode, 0, 2);
                if (isset(self::$postalMap[$provinceCode])) {
                    return [
                        'province' => self::$postalMap[$provinceCode],
                        'source' => 'postal',
                        'confidence' => 'medium',
                        'postal_code' => $postalCode
                    ];
                }
            }
        }

        // Priority 4: Try dropoff location (if pickup failed)
        if (!empty($quoteData['dropoff_address3'])) {
            $result = self::matchProvinceName($quoteData['dropoff_address3']);
            if ($result) {
                $result['source'] = 'dropoff_province';
                return $result;
            }
        }

        // Unknown
        return [
            'province' => null,
            'source' => 'unknown',
            'confidence' => 'low'
        ];
    }

    /**
     * Main detection function - tries airport first, then postal code
     * Returns province info or null
     */
    public static function detectProvince($bookingData)
    {
        // Priority 1: Airport detection (90% coverage)
        $airportFields = [
            $bookingData['airport'] ?? null,
            $bookingData['from_airport'] ?? null,
            $bookingData['to_airport'] ?? null,
            $bookingData['airport_code'] ?? null,
        ];

        foreach ($airportFields as $airport) {
            if (!empty($airport)) {
                $result = self::detectFromAirport($airport);
                if ($result) {
                    return $result;
                }
            }
        }

        // Priority 2: Postal code detection (5-8% coverage)
        $addressFields = [
            ($bookingData['accommodation_address1'] ?? '') . ' ' . ($bookingData['accommodation_address2'] ?? ''),
        ];

        foreach ($addressFields as $address) {
            if (!empty($address)) {
                $result = self::detectFromPostalCode($address);
                if ($result) {
                    return $result;
                }
            }
        }

        // Priority 3: Unknown (2-5% coverage)
        return [
            'province' => null,
            'source' => 'unknown',
            'confidence' => 'low'
        ];
    }
}
