<?php

declare(strict_types=1);

namespace bordersdev\craftpulse\helpers;

use DateTime;

class LogParser
{
    public static function parse(mixed $line): array
    {
        if (is_string($line)) {
            return ['message' => $line];
        }

        if (!is_object($line)) {
            return ['message' => (string) $line];
        }

        $data = [];

        $getters = [
            'date' => ['getDate', 'date'],
            'level' => ['getLevel', 'level'],
            'logger' => ['getLogger', 'logger', 'getType', 'type'],
            'message' => ['getMessage', 'message'],
        ];

        foreach ($getters as $key => $methods) {
            foreach ($methods as $method) {
                if (method_exists($line, $method)) {
                    $value = $line->$method();
                    if ($value instanceof DateTime) {
                        $value = $value->format('Y-m-d H:i:s');
                    }
                    $data[$key] = $value;
                    break;
                }
            }
        }

        return $data ?: ['message' => (string) $line];
    }

    public static function parseMany(iterable $lines, int $limit = 20): array
    {
        $errors = [];
        $count = 0;

        foreach ($lines as $line) {
            if ($count >= $limit) {
                break;
            }
            $errors[] = self::parse($line);
            $count++;
        }

        return $errors;
    }
}
