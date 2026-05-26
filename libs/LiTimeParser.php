<?php

class LiTimeBatteryData
{
    public float $totalVoltage = 0.0;
    public float $cellVoltageSum = 0.0;
    public float $current = 0.0;
    public float $power = 0.0;

    public int $soc = 0;
    public int $soh = 0;

    public int $cellTemp = 0;
    public int $mosfetTemp = 0;

    public float $remainingAh = 0.0;
    public float $fullCapacityAh = 0.0;

    public int $cycleCount = 0;
    public int $dischargesAHCount = 0;

    public int $batteryState = 0;
    public int $equilibriumState = 0;
    public bool $dischargeSwitchState = true;

    public string $protectState = '';
    public string $failureState = '';

    /** @var float[] */
    public array $cellVoltages = [];

    public string $rawHex = '';
}

class LiTimeParser
{
    public static function parse(string $payload): LiTimeBatteryData
    {
        $len = strlen($payload);

        if ($len < 104) {
            throw new Exception("Packet too short: $len bytes");
        }

        $d = self::toBytes($payload);

        $b = new LiTimeBatteryData();
        $b->rawHex = bin2hex($payload);

        //
        // Voltages (u32 little-endian, millivolts)
        //
        $b->totalVoltage   = self::u32le($d, 8) / 1000.0;
        $b->cellVoltageSum = self::u32le($d, 12) / 1000.0;

        //
        // Cell voltages (u16 little-endian, mV) — bytes 16..47
        //
        for ($i = 0; $i < 32; $i += 2) {
            $offset = 16 + $i;

            if ($offset + 1 >= $len) {
                break;
            }

            $mv = self::u16le($d, $offset);

            if ($mv === 0) {
                continue;
            }

            $b->cellVoltages[] = $mv / 1000.0;
        }

        //
        // Current (s32 little-endian, mA) — bytes 48..51
        //
        $rawCurrent = self::s32le($d, 48);
        $b->current = round($rawCurrent / 1000.0, 2);

        //
        // Power (calculated)
        //
        $rawVoltage = self::u32le($d, 12);
        $b->power = round(($rawVoltage * $rawCurrent) / 1000000.0, 2);

        //
        // Temperatures (s16 little-endian) — bytes 52..55
        //
        $b->cellTemp   = self::s16le($d, 52);
        $b->mosfetTemp = self::s16le($d, 54);

        //
        // Capacity (u16 little-endian, centi-Ah)
        //
        $b->remainingAh    = self::u16le($d, 62) / 100.0;
        $b->fullCapacityAh = self::u16le($d, 64) / 100.0;

        //
        // Heat / switch state — bytes 68..71 (reversed)
        //
        $heat = sprintf('%02X%02X%02X%02X', $d[71], $d[70], $d[69], $d[68]);
        $b->dischargeSwitchState = (hexdec($heat[6]) < 8);

        //
        // Protect state — bytes 76..79 (reversed hex)
        //
        $b->protectState = sprintf('%02X%02X%02X%02X', $d[79], $d[78], $d[77], $d[76]);

        //
        // Failure state — bytes 80..83 (reversed hex)
        //
        $b->failureState = sprintf('%02X%02X%02X%02X', $d[83], $d[82], $d[81], $d[80]);

        //
        // Equilibrium state (u32 little-endian) — bytes 84..87
        //
        $b->equilibriumState = self::u32le($d, 84);

        //
        // Battery state (u16 little-endian) — bytes 88..89
        // 0=Idle, 1=Charging, 2=Discharging, 4=Full Charge
        //
        $b->batteryState = self::u16le($d, 88);

        //
        // SOC (u16 little-endian) — bytes 90..91
        //
        $b->soc = self::u16le($d, 90);

        //
        // SOH (u32 little-endian) — bytes 92..95
        //
        $b->soh = self::u32le($d, 92);

        //
        // Discharge/cycle count (u32 little-endian) — bytes 96..99
        //
        $b->cycleCount = self::u32le($d, 96);

        //
        // Discharge AH count (u32 little-endian) — bytes 100..103
        //
        $b->dischargesAHCount = self::u32le($d, 100);

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

    private static function u32le(array $d, int $o): int
    {
        return ($d[$o] ?? 0)
            | (($d[$o + 1] ?? 0) << 8)
            | (($d[$o + 2] ?? 0) << 16)
            | (($d[$o + 3] ?? 0) << 24);
    }

    private static function s32le(array $d, int $o): int
    {
        $v = self::u32le($d, $o);

        if ($v >= 0x80000000) {
            $v -= 0x100000000;
        }

        return $v;
    }
}