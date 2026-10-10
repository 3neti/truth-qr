<?php

namespace LBHurtado\TruthQr\Core;

final class CanonicalJson
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function encode(array $data): string
    {
        $normalized = $this->normalize($data);

        return json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function hash(array $data): string
    {
        return hash('sha256', $this->encode($data));
    }

    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = $this->normalize($item);
        }

        return $value;
    }
}
