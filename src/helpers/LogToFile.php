<?php

namespace superbig\vipps\helpers;

use Craft;
use craft\helpers\FileHelper;
use craft\helpers\Json;
use yii\base\ErrorException;
use yii\log\Logger;

class LogToFile
{
    // Constants
    // =========================================================================

    /**
     * Message levels
     *
     * @see https://www.yiiframework.com/doc/api/2.0/yii-log-logger#constants
     */
    public const MESSAGE_LEVELS = [
        'error' => Logger::LEVEL_ERROR,
        'info' => Logger::LEVEL_INFO,
        'trace' => Logger::LEVEL_TRACE,
        'profile' => Logger::LEVEL_PROFILE,
        'profileBegin' => Logger::LEVEL_PROFILE_BEGIN,
        'profileEnd' => Logger::LEVEL_PROFILE_END,
    ];

    // Static Properties
    // =========================================================================

    /**
     * @var string
     */
    public static string $handle = 'vipps';

    /**
     * @var bool
     */
    public static bool $logToCraft = true;

    /**
     * @var bool
     * @deprecated in 1.1.0
     */
    public static bool $logUserIp = false;

    /**
     * Logs an info message to a file with the provided handle.
     *
     * @param array|string $message
     * @param string|null  $handle
     */
    public static function info(array|string $message, string $handle = null): void
    {
        self::log($message, $handle, 'info');
    }

    /**
     * Logs an error message to a file with the provided handle.
     *
     * @param array|string $message
     * @param string|null  $handle
     */
    public static function error(array|string $message, string $handle = null): void
    {
        self::log($message, $handle, 'error');
    }

    /**
     * Logs the message to a file with the provided handle and level.
     *
     * @param array|string $message
     * @param string|null  $handle
     * @param string       $level
     */
    public static function log(array|string $message, string $handle = null, string $level = 'info'): void
    {
        $ip = '';
        $userId = '';

        // Default to class value if none provided
        if ($handle === null) {
            $handle = self::$handle;
        }

        // Don't continue if handle is still empty
        if (empty($handle)) {
            return;
        }

        $file = Craft::getAlias('@storage/logs/' . $handle . '.log');

        // Set IP address
        if (Craft::$app->getConfig()->getGeneral()->storeUserIps) {
            $ip = Craft::$app->getRequest()->getUserIP();
        }

        // Set user ID
        $user = Craft::$app->getUser()->getIdentity();

        if ($user !== null) {
            $userId = $user->id;
        }

        if (is_array($message)) {
            $message = Json::encode($message);
        }

        // Trim message to remove whitespace and empty lines
        $message = trim($message);

        $log = date('Y-m-d H:i:s') . ' [' . $ip . '][' . $userId . '][' . $level . '] ' . $message . "\n";

        try {
            FileHelper::writeToFile($file, $log, ['append' => true]);
        } catch (ErrorException $e) {
            Craft::warning('Failed to write log to file `' . $file . '`.');
        }

        if (self::$logToCraft) {
            // Convert level to a message level that the Yii logger might understand
            $level = self::MESSAGE_LEVELS[ $level ] ?? $level;

            Craft::getLogger()->log($message, $level, $handle);
        }
    }

    public static function encodeForLog($data): string
    {
        return Json::encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function formatLogVariables($variables = []): array
    {
        return array_map(function($value) {
            if (is_array($value)) {
                $value = Json::encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }

            return $value;
        }, $variables);
    }

    public static function formatLogMessage($message, $variables = []): string
    {
        if (is_array($message)) {
            $message = Json::encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } else {
            $message = Craft::t('vipps', $message, $variables);
        }

        return $message;
    }
}
