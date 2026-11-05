<?php

namespace App\Services\Sms\Modem;

/** Past darajadagi ketma-ket port (testda soxta port bilan almashtiriladi). */
interface ModemPort
{
    public function write(string $data): void;

    /** Berilgan regex'lardan biri (butun yig'ilgan javobga nisbatan) ko'ringuncha yoki vaqt tugaguncha o'qiydi. */
    public function readUntil(string $pattern, float $timeout): string;

    public function close(): void;
}
