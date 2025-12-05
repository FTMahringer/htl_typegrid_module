<?php

declare(strict_types=1);

namespace Drupal\htl_typegrid\Helper;

use Drupal\Core\Logger\RfcLogLevel;

/**
 * Simple logger helper for the HTL TypeGrid module with config-based toggle.
 *
 * When the "debug_logging" setting in "htl_typegrid.settings" is disabled,
 * all logs routed through this helper are suppressed. This affects only
 * logs emitted by this helper (i.e., the module itself).
 *
 * Usage:
 *   DebugLogger::notice('Something happened: @value', ['@value' => 123]);
 *   DebugLogger::warning('Potential issue: @id', ['@id' => 42]);
 *   DebugLogger::error('Fatal problem: @msg', ['@msg' => '...']);
 *   DebugLogger::info('FYI: @context', ['@context' => '...']);
 *   DebugLogger::debug('Verbose details: @data', ['@data' => '...']);
 */
final class DebugLogger {

  /**
   * Name of the module-specific logger channel.
   */
  private const CHANNEL = 'htl_typegrid';

  /**
   * Configuration name for this module.
   */
  private const CONFIG = 'htl_typegrid.settings';

  /**
   * Config key controlling debug logging.
   */
  private const CONFIG_DEBUG_KEY = 'debug_logging';

  /**
   * Returns TRUE if module logging is enabled via configuration.
   *
   * If the setting is missing, defaults to TRUE (logging enabled).
   */
  public static function isEnabled(): bool {
    try {
      $config = \Drupal::config(self::CONFIG);
      $value = $config->get(self::CONFIG_DEBUG_KEY);
      return $value === null ? true : (bool) $value;
    }
    catch (\Throwable $e) {
      // Fail open: if config is unavailable for any reason, do not block logs.
      return true;
    }
  }

  /**
   * Logs a message at the given level when logging is enabled.
   *
   * @param int|string $level
   *   PSR-3/RFC level constant (int) or string level (e.g., 'debug', 'info', 'notice', 'warning', 'error', 'critical').
   * @param string $message
   *   The message to log.
   * @param array $context
   *   Context variables for placeholder replacements.
   */
  public static function log(int|string $level, string $message, array $context = []): void {
    if (!self::isEnabled()) {
      return;
    }

    // Ensure the level is a valid RFC level string (fallback to 'notice').
    $level = self::normalizeLevel($level);
    \Drupal::logger(self::CHANNEL)->log($level, $message, $context);
  }

  /**
   * Convenience: log a debug message.
   */
  public static function debug(string $message, array $context = []): void {
    self::log(RfcLogLevel::DEBUG, $message, $context);
  }

  /**
   * Convenience: log an info message.
   */
  public static function info(string $message, array $context = []): void {
    self::log(RfcLogLevel::INFO, $message, $context);
  }

  /**
   * Convenience: log a notice message.
   */
  public static function notice(string $message, array $context = []): void {
    self::log(RfcLogLevel::NOTICE, $message, $context);
  }

  /**
   * Convenience: log a warning message.
   */
  public static function warning(string $message, array $context = []): void {
    self::log(RfcLogLevel::WARNING, $message, $context);
  }

  /**
   * Convenience: log an error message.
   */
  public static function error(string $message, array $context = []): void {
    self::log(RfcLogLevel::ERROR, $message, $context);
  }

  /**
   * Normalize a provided level to a valid RFC level string.
   */
  private static function normalizeLevel(int|string $level): string {
    // Map of RFC integer constants to string levels
    $intToString = [
      RfcLogLevel::EMERGENCY => 'emergency',
      RfcLogLevel::ALERT => 'alert',
      RfcLogLevel::CRITICAL => 'critical',
      RfcLogLevel::ERROR => 'error',
      RfcLogLevel::WARNING => 'warning',
      RfcLogLevel::NOTICE => 'notice',
      RfcLogLevel::INFO => 'info',
      RfcLogLevel::DEBUG => 'debug',
    ];

    // If it's an integer, convert to string
    if (is_int($level)) {
      return $intToString[$level] ?? 'notice';
    }

    // If it's a string, validate it
    $valid = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];
    $lower = strtolower($level);
    return in_array($lower, $valid, true) ? $lower : 'notice';
  }

}
