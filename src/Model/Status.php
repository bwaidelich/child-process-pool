<?php
declare(strict_types=1);
namespace Wwwision\ChildProcessPool\Model;

final class Status
{

    public function __construct(
        public readonly int $uptime,
        public readonly int $running,
        public readonly int $queued,
        public readonly int $failed,
        public readonly int $succeeded,
    ) {}

    public static function fromJson(string $data): self
    {
        try {
            $decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException(sprintf('Failed to decode JSON: %s', $e->getMessage()), 1675850150, $e);
        }
        return new self(
            $decoded['uptime'] ?? -1,
            $decoded['running'] ?? -1,
            $decoded['queued'] ?? -1,
            $decoded['failed'] ?? -1,
            $decoded['succeeded'] ?? -1,
        );
    }

    public function toJson(): string
    {
        try {
            $encoded = json_encode(get_object_vars($this), JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException(sprintf('Failed to encode JSON: %s', $e->getMessage()), 1675851319, $e);
        }
        return $encoded;
    }
}
