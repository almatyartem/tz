<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;
    const PARAMETER_PATTERN = '/\?(\#|a|d|f)?/';
    const STOP_PHRASE = '__--!!--__';
    const NULLABLE_TYPES = [
        'string',
        'integer',
        'double',
        'NULL'
    ];

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function buildQuery(string $query, array $args = []): string
    {
        $i = 0;
        $query = preg_replace_callback(self::PARAMETER_PATTERN, function ($match) use (&$i, $args) {
            $rawValue = $args[$i];

            if ($rawValue == self::STOP_PHRASE) {
                $value = $rawValue;
            } else {
                switch ($match[1] ?? null) {
                    case 'd':
                        $value = $this->prepareValue($rawValue, 'integer');
                        break;
                    case 'f':
                        $value = $this->prepareValue($rawValue, 'float');
                        break;
                    case 'a':
                        $value = $this->prepareArray($rawValue);
                        break;
                    case '#':
                        $value = is_array($rawValue) ? $this->prepareArray($rawValue, true) : $this->prepareValue(
                            $rawValue,
                            null,
                            true
                        );
                        break;
                    default:
                        $value = $this->prepareValue($rawValue);
                }
            }

            $i++;

            return $value;
        }, $query);

        if(count($args) != $i) {
            throw new Exception('Incorrect template or parameters');
        }

        $query = preg_replace('/\{(.*?)' . self::STOP_PHRASE . '(.*?)\}|\{|\}/i', '', $query);

        return $query;
    }

    public function skip()
    {
        return self::STOP_PHRASE;
    }

    protected function prepareArray(array $value, bool $columnNames = false): string
    {
        $result = [];
        foreach ($value as $k => $item) {
            $result[] = (is_numeric($k) ? '' : $this->prepareValue($k, null, true) . ' = ') .
                $this->prepareValue($item, null, $columnNames);
        }

        return implode(', ', $result);
    }

    protected function prepareValue($rawValue, string $type = null, bool $columnNames = false)
    {
        $type = $type ?? gettype($rawValue);

        if (in_array($type, self::NULLABLE_TYPES) and $rawValue === null) {
            $result = 'NULL';
        } else {
            switch ($type) {
                case 'string':
                    $rawValue = $this->mysqli->real_escape_string($rawValue);
                    $result = $columnNames ? "`" . $rawValue . "`" : "'" . $rawValue . "'";
                    break;
                case 'integer':
                case 'boolean':
                    $result = (int)$rawValue;
                    break;
                case 'double':
                    $result = (float)$rawValue;
                    break;
                default:
                    throw new Exception('Not supported type');
            }
        }

        return $result;
    }
}
