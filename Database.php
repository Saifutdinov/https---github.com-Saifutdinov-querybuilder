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

    private string $skipParam = "SKIP";

    public function __construct(?mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function buildQuery(string $query, array $args = []): string
    {
        // если количество параметров не указано вернуть запрос как есть
        if (empty($args)) return $query;

        // декомпозиция штоб ее
        $sqlQuery = $this->parseQuery($query, $args);

        return $sqlQuery;
    }

    public function skip(): string
    {
        return $this->skipParam;
    }

    private function handlerConditionalBlock(string $sqlQuery, array $params): string
    {
        if (($pos = strpos($sqlQuery, "{")) === false) return $sqlQuery;

        if (in_array($this->skipParam, $params['values'], true))
            return substr($sqlQuery, 0, $pos);

        return str_replace(['{', '}'], '', $sqlQuery);
    }

    private function parseQuery(string $query, array $args): string
    {
        $matches = [];
        $pattern = "/\?([#adf])?/";
        $find = preg_match_all($pattern, $query, $matches);

        if (!$find) return $query;

        $params = $this->convert($matches[0], $args);
        $query = $this->handlerConditionalBlock($query, $params);
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

            if ($args[$key] != 'SKIP')
                if ($argtype == 'array')
                    if (!in_array($param, $this->assoc))
                        $result['values'][] = implode(", ", array_map(fn ($item) => "`$item`", $args[$key]));
                    else
                        $result['values'][] = implode(", ", $this->buildArrayQuery($args[$key]));
                else
                    $result['values'][] = $this->getConvetedValue($args[$key], $argtype, in_array($param, $this->stringQuotes) ? 'spec' : 'base');
            else
                $result['values'][] = $args[$key];
        }
        return $result;
    }

    private function replace(string $query, array $params): string
    {
        foreach ($params['names'] as $i => $param) {
            $query = $this->replaceIfExists($param, $params['values'][$i], $query);
        }
        return $query;
    }

    private function replaceIfExists(string $search, string $replace, string $subject): string
    {
        if (($pos = strpos($subject, $search)) !== false) {
            return substr_replace($subject, $replace, $pos, strlen($search));
        }
        return $subject;
    }

    private function buildString(mixed $str, string $subtype): string
    {
        return match ($subtype) {
            'base' => "'$str'",
            'spec' => "`$str`"
        };
    }

    private function buildArrayQuery(array $args): array
    {
        if (array_is_list($args))
            return array_values($args);

        $result = [];
        foreach ($args as $key => $value) {
            $result[] = "`$key` = " . $this->getConvetedValue($value, gettype($value), 'base');
        }
        return $result;
    }

    private function getConvetedValue(mixed $value, string $type, string $subtype): mixed
    {
        return match ($type) {
            'boolean', 'integer' => (int)$value,
            'string' => $this->buildString($value, $subtype),
            'float' => (float)$value,
            'NULL' => 'NULL'
        };
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
}
