<?php
/** Limited compatibility helpers for functions removed from PHP 7/8. */
if (!function_exists('split')) {
    function split(string $pattern, string $string, int $limit = -1): array {
        $delimiter = '~';
        $regex = $delimiter . str_replace($delimiter, '\\'.$delimiter, $pattern) . $delimiter;
        return preg_split($regex, $string, $limit) ?: [];
    }
}
if (!function_exists('ereg_replace')) {
    function ereg_replace(string $pattern, string $replacement, string $string): string {
        $delimiter = '~';
        $regex = $delimiter . str_replace($delimiter, '\\'.$delimiter, $pattern) . $delimiter;
        $result = preg_replace($regex, $replacement, $string);
        return $result === null ? $string : $result;
    }
}
if (!function_exists('each')) {
    function each(array &$array): array|false {
        $key = key($array);
        if ($key === null) return false;
        $value = current($array);
        next($array);
        return [0 => $key, 1 => $value, 'key' => $key, 'value' => $value];
    }
}
