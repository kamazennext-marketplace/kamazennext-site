<?php
class Config
{
    private static $apiKey = null;

    public static function enableErrorLogging()
    {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
    }

    public static function getApiKey()
    {
        if (self::$apiKey !== null) {
            return self::$apiKey;
        }

        // 1️⃣ Try environment variable (rarely works on cPanel)
        $envKey = getenv('OPENAI_API_KEY');
        if ($envKey) {
            self::$apiKey = trim($envKey);
            return self::$apiKey;
        }

        // 2️⃣ Manually read .env file (cPanel-safe)
        $envFile = __DIR__ . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, 'OPENAI_API_KEY=') === 0) {
                    self::$apiKey = trim(substr($line, 15));
                    return self::$apiKey;
                }
            }
        }

        return '';
    }

    public static function isConfigured()
    {
        return self::getApiKey() !== '';
    }

    public static function getSystemPrompt()
    {
        return "You are KAMA ZENNEXT AI, an expert software marketplace assistant.
Respond with clear headings, bullet points, comparisons, and business-focused recommendations.";
    }

    public static function getAllowedOrigins()
    {
        return [
            'https://kamazennext.com',
            'https://www.kamazennext.com'
        ];
    }
}
