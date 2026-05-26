<?php

class LiTimeBatteryData
{
    public float $totalVoltage = 0.0;
    public float $cellVoltageSum = 0.0;
    public float $current = 0.0;

    public int $soc = 0;

    public int $mosfetTemp = 0;
    public int $cellTemp = 0;

    public float $remainingAh = 0.0;
    public float $fullCapacityAh = 0.0;

    public int $cycleCount = 0;

    /** @var float[] */
    public array $cellVoltages = [];

    public string $rawHex = '';
}

class LiTimeParser
{
    public static function parse(string $payload): LiTimeBatteryData
    {
        $len = strlen($payload);

        if ($len < 60) {
            throw new Exception("Packet too short: $len bytes");
        }

        $d = self::toBytes($payload);

        $b = new LiTimeBatteryData();
        $b->rawHex = bin2hex($payload);

        //
        // SOC (observed early byte in your packet)
        //
        $b->soc = $d[2];

        //
        // Voltages (little-endian, millivolts)
        //
        $b->totalVoltage    = self::u16le($d, 8) / 1000.0;
        $b->cellVoltageSum  = self::u16le($d, 12) / 1000.0;

        //
        // Cell voltages (mV)
        //
        for ($i = 0; $i < 32; $i += 2) {
            $offset = 16 + $i;

            if ($offset + 1 >= $len) {
                break;
            }

            $mv = self::u16le($d, $offset);

            // heuristic stop: zero or invalid
            if ($mv === 0) {
                continue;
            }

            $b->cellVoltages[] = $mv / 1000.0;
        }

        //
        // Current (signed, centi-amps or deci-amps depending firmware)
        //
        $b->current = self::s16le($d, 68) / 100.0;

        //
        // Temperatures (likely signed or offset encoded)
        //
        $b->mosfetTemp = self::s16le($d, 52);
        $b->cellTemp   = self::s16le($d, 54);

        //
        // Capacity
        //
        $b->remainingAh   = self::u16le($d, 66) / 100.0;
        $b->fullCapacityAh = self::u16le($d, 64) / 100.0;

        //
        // Cycle count
        //
        $b->cycleCount = self::u16le($d, 96);

        return $b;
    }

    private static function toBytes(string $payload): array
    {
        return array_values(unpack('C*', $payload));
    }

    private static function u16le(array $d, int $o): int
    {
        return ($d[$o] ?? 0) | (($d[$o + 1] ?? 0) << 8);
    }

    private static function s16le(array $d, int $o): int
    {
        $v = self::u16le($d, $o);

        if ($v >= 0x8000) {
            $v -= 0x10000;
        }

        return $v;
    }
}