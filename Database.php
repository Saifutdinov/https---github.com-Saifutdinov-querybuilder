<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private ?mysqli $mysqli;

    private array $specials = ['?d' => 'integer', '?f' => 'float', '?a' => 'array', '?#' => 'string|array', '?' => 'types'];

    private array $stringQuotes = ['?#'];

    private array $assoc = ['?a'];

    private array $types = ['string', 'integer', 'float', 'boolean', 'null'];

    private string $query;

    public function __construct(?mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function buildQuery(string $query, array $args = []): string
    {
        // если количество параметров не указано вернуть запрос как есть
        if (count($args) == 0) return $query;

        // декомпозиция штоб ее
        $sqlQuery = $this->parseQuery($query, $args);

        return $sqlQuery;
    }

    public function skip()
    {
        // throw new Exception();
    }

    private function parseQuery(string $query, array $args): string
    {
        $matches = [];
        $pattern = "/\?([#adf])?/";
        $find = preg_match_all($pattern, $query, $matches);

        if (!$find) {
            return $query;
        }

        $params = $this->convert($matches[0], $args);

        return $this->replace($query, $params);
    }

    private function convert(array $params, array $args): array
    {
        $argsKeys = array_keys($args);
        $result = [
            'names' => [],
            'values' => [],
        ];
        foreach ($params as $i => $param) {
            $key = $argsKeys[$i];
            $argtype = gettype($args[$key]);

            $this->validateType($argtype, $param);

            $result['names'][] = $param;

            var_dump($argtype);

            if ($argtype == 'array')
                if (!in_array($param, $this->assoc))
                    $result['values'][] = implode(", ", array_map(fn ($item) => "`$item`", $args[$key]));
                else
                    $result['values'][] = implode(", ", $this->buildArrayQuery($args[$key]));
            else
                $result['values'][] = $this->getConvetedValue($args[$key], $argtype, in_array($param, $this->stringQuotes) ? 'spec' : 'base');
        }
        return $result;
    }

    private function replace(string $query, array $params): string
    {
        $replace = function ($search, $replace, $subject) {
            if (($pos = strpos($subject, $search)) !== false) {
                return substr_replace($subject, $replace, $pos, strlen($search));
            }
            return $subject;
        };
        foreach ($params['names'] as $i => $param) {
            $query = $replace($param, $params['values'][$i], $query);
        }
        return $query;
    }

    private function buildString(mixed $str, string $subtype): string
    {
        switch ($subtype) {
            case 'base':
                return "'$str'";
            case 'spec':
                return "`$str`";
        }
    }

    private function buildArrayQuery(array $args): array
    {
        if ($this->detectSequential($args))
            return array_values($args);

        $result = [];
        foreach ($args as $key => $value) {
            $result[] = "`$key` = " . $this->getConvetedValue($value, gettype($value), 'base');
        }
        return $result;
    }

    private function getConvetedValue(mixed $value, string $type, string $subtype): mixed
    {
        switch ($type) {
            case 'boolean':
            case 'integer':
                return (int)$value;
            case 'string':
                return $this->buildString($value, $subtype);
            case 'float':
                return (float)$value;
            case 'NULL':
                return 'NULL';
        }
        return false;
    }

    private function validateType(string $type, string $param)
    {
        $needtype = explode('|', $this->specials[$param]);
        if (
            (count($needtype) > 1 && !in_array($type, $needtype)) ||
            ($needtype == 'types' && !in_array($type, $this->types))
        ) {
            throw new Exception("variable type error: waiting for $needtype, got $type.");
        }
    }
    private function detectSequential(array $args): bool
    {
        $keys = array_keys($args);
        return $keys === array_keys($keys);
    }
}
