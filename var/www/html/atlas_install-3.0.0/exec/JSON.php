<?php
if (!defined('SERVICES_JSON_LOOSE_TYPE')) define('SERVICES_JSON_LOOSE_TYPE', 16);
if (!defined('SERVICES_JSON_SUPPRESS_ERRORS')) define('SERVICES_JSON_SUPPRESS_ERRORS', 32);
class Services_JSON {
    private int $use;
    public function __construct(int $use = 0) { $this->use = $use; }
    public function encode(mixed $value): string|false {
        try { return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES); }
        catch (JsonException $e) { return false; }
    }
    public function decode(string $value): mixed {
        try { return json_decode($value, (bool)($this->use & SERVICES_JSON_LOOSE_TYPE), 512, JSON_THROW_ON_ERROR); }
        catch (JsonException $e) { return null; }
    }
}
?>
