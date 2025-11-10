<?php
// config/holiday-taxis.php - API Configuration (Support both Production and Test)
class HolidayTaxisConfig
{
    // Production API (Default)
    const API_KEY = 'htscon_b6fede289eec88ec1a481ebdb689347e8d9353bafc037e74de7ffd2f1cf86a0f2ee5c0fd8d337304';
    const API_ENDPOINT = 'https://suppliers.holidaytaxis.com';
    const API_VERSION = '2025-01';

    // Test API
    const TEST_API_KEY = 'htscon_ad65644d53f6fcad58ef405997193f3afe3ff89aed2f5895a30bf8007f1630917cce092a4128bf5f';
    const TEST_API_ENDPOINT = 'https://suppliers.htxstaging.com';
    const TEST_API_VERSION = '2025-01';

    // Helper methods to get API config
    public static function getApiKey($useTest = false)
    {
        return $useTest ? self::TEST_API_KEY : self::API_KEY;
    }

    public static function getApiEndpoint($useTest = false)
    {
        return $useTest ? self::TEST_API_ENDPOINT : self::API_ENDPOINT;
    }

    public static function getApiVersion($useTest = false)
    {
        return $useTest ? self::TEST_API_VERSION : self::API_VERSION;
    }
}
