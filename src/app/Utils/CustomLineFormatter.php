<?php

namespace D4m111\SnmpManager\App\Utils;

use Monolog\Formatter\LineFormatter;

use Monolog\Utils;
use Monolog\LogRecord;
use Throwable;

class CustomLineFormatter extends LineFormatter

{

    public function format(LogRecord $record): string
    {
        $vars = $this->normalizeRecord($record);

        if ($this->maxLevelNameLength !== null) {
            $vars['level_name'] = substr($vars['level_name'], 0, $this->maxLevelNameLength);
        }

        $output = $this->format;
        foreach ($vars['extra'] as $var => $val) {
            if (false !== strpos($output, '%extra.'.$var.'%')) {
                $output = str_replace('%extra.'.$var.'%', $this->stringify($val), $output);
                unset($vars['extra'][$var]);
            }
        }

        foreach ($vars['context'] as $var => $val) {
            if (false !== strpos($output, '%context.'.$var.'%')) {
                str_replace('%context.'.$var.'%', $this->stringify($val), $output);
                unset($vars['context'][$var]);
            }
        }

        if ($this->ignoreEmptyContextAndExtra) {
            if (\count($vars['context']) === 0) {
                unset($vars['context']);
                $output = str_replace('%context%', '', $output);
            }

            if (\count($vars['extra']) === 0) {
                unset($vars['extra']);
                $output = str_replace('%extra%', '', $output);
            }
        }

        foreach ($vars as $var => $val) {

            if (false !== strpos($output, '%'.$var.'%')) {
                if ($var == 'context') {
                    $output = str_replace('%'.$var.'%', ltrim($this->stringify($val),'{'), $output);
                }else{
                    $output = str_replace('%'.$var.'%', $this->stringify($val), $output);
                }
                
            }
        }

        // remove leftover %extra.xxx% and %context.xxx% if any
        if (false !== strpos($output, '%')) {
            $output = preg_replace('/%(?:extra|context)\..+?%/', '', $output);
            if (null === $output) {
                $pcreErrorCode = preg_last_error();

                throw new \RuntimeException('Failed to run preg_replace: ' . $pcreErrorCode . ' / ' . Utils::pcreLastErrorMessage($pcreErrorCode));
            }
        }

        return $output;
    }

    protected function normalizeRecord(LogRecord $record): array
    {
        /** @var array<mixed> $normalized */
        $normalized = $this->normalize($record->toArray());

        return $normalized;
    }

    /**
     * @return null|scalar|array<mixed[]|scalar|null>
     */
    protected function normalize(mixed $data, int $depth = 0): mixed
    {
        if ($depth > $this->maxNormalizeDepth) {
            return 'Over ' . $this->maxNormalizeDepth . ' levels deep, aborting normalization';
        }

        if (null === $data || \is_scalar($data)) {
            if (\is_float($data)) {
                if (is_infinite($data)) {
                    return ($data > 0 ? '' : '-') . 'INF';
                }
                if (is_nan($data)) {
                    return 'NaN';
                }
            }

            return $data;
        }

        if (\is_array($data)) {
            $normalized = [];

            $count = 1;
            foreach ($data as $key => $value) {
                if ($count++ > $this->maxNormalizeItemCount) {
                    $normalized['...'] = 'Over ' . $this->maxNormalizeItemCount . ' items ('.\count($data).' total), aborting normalization';
                    break;
                }

                $normalized[$key] = $this->normalize($value, $depth + 1);
            }

            return $normalized;
        }

        if ($data instanceof \DateTimeInterface) {
            return $this->formatDate($data);
        }

        if (\is_object($data)) {
            if ($data instanceof Throwable) {
                return $this->normalizeException($data, $depth);
            }

            if ($data instanceof \JsonSerializable) {
                /** @var null|scalar|array<mixed[]|scalar|null> $value */
                $value = $data->jsonSerialize();
            } elseif (\get_class($data) === '__PHP_Incomplete_Class') {
                $accessor = new \ArrayObject($data);
                $value = (string) $accessor['__PHP_Incomplete_Class_Name'];
            } elseif (method_exists($data, '__toString')) {
                try {
                    /** @var string $value */
                    $value = $data->__toString();
                } catch (\Throwable) {
                    // if the toString method is failing, use the default behavior
                    /** @var null|scalar|array<mixed[]|scalar|null> $value */
                    $value = json_decode($this->toJson($data, true), true);
                }
            } else {
                // the rest is normalized by json encoding and decoding it
                /** @var null|scalar|array<mixed[]|scalar|null> $value */
                $value = json_decode($this->toJson($data, true), true);
            }

            return [Utils::getClass($data) => $value];
        }

        if (\is_resource($data)) {
            return sprintf('[resource(%s)]', get_resource_type($data));
        }

        return '[unknown('.\gettype($data).')]';
    }
}