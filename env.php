<?php
// env.php

/**
 * Load environment variables from a .env file.
 *
 * @param string $path Path to the .env file
 * @return void
 */
function loadEnv($path) {
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Skip comments and empty lines
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        // Find the first '='
        $equalsPos = strpos($line, '=');
        if ($equalsPos === false) {
            continue;
        }

        $key = trim(substr($line, 0, $equalsPos));
        $value = trim(substr($line, $equalsPos + 1));

        // Remove inline comments if not wrapped in quotes
        $isWrapped = false;
        if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) || 
            (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
            $value = substr($value, 1, -1);
            $isWrapped = true;
        }

        if (!$isWrapped) {
            // If not wrapped, strip any inline comment starting with #
            $commentPos = strpos($value, '#');
            if ($commentPos !== false) {
                $value = trim(substr($value, 0, $commentPos));
            }
        }

        // Handle boolean or null strings
        $lowerVal = strtolower($value);
        if ($lowerVal === 'true') {
            $value = true;
        } elseif ($lowerVal === 'false') {
            $value = false;
        } elseif ($lowerVal === 'null') {
            $value = null;
        }

        // Set environment variables if not already defined
        if (getenv($key) === false) {
            putenv("$key=" . (is_bool($value) ? ($value ? 'true' : 'false') : $value));
        }
        if (!isset($_ENV[$key])) {
            $_ENV[$key] = $value;
        }
        if (!isset($_SERVER[$key])) {
            $_SERVER[$key] = $value;
        }
    }
}

/**
 * Get the value of an environment variable or a default value.
 *
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
if (!function_exists('env')) {
    function env($key, $default = null) {
        $value = getenv($key);
        
        if ($value === false) {
            return $default;
        }
        
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
        }
        
        // Remove wrapping quotes if present
        if (preg_match('/^([\'"])(.*)\1$/', $value, $matches)) {
            return $matches[2];
        }
        
        return $value;
    }
}
