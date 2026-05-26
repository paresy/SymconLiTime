<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/LiTimeParser.php';

class LiTimeBattery extends IPSModuleStrict
{
    private const BT_TX_GUID = '{C3D4E5F6-7A8B-9C0D-1E2F-3A4B5C6D7E8F}';
    private const BT_RX_GUID = '{A6E2B2F0-4B7A-4E3C-9C1D-8F5A3D6E7B90}';

    private const REQUEST_TX_UUID = 'FFE1';
    private const REQUEST_RX_UUID = 'FFE2';
    private const REQUEST_PAYLOAD = "\x00\x00\x04\x01\x13\x55\xAA\x17";

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyInteger('Poller', 30);

        // Battery state
        $this->RegisterVariableInteger('SOC', $this->Translate('State of Charge'), '~Battery.100', 0);
        $this->RegisterVariableFloat('TotalVoltage', $this->Translate('Total Voltage'), '~Volt', 1);
        $this->RegisterVariableFloat('CellVoltageSum', $this->Translate('Cell Voltage Sum'), '~Volt', 2);
        $this->RegisterVariableFloat('Current', $this->Translate('Current'), '~Ampere', 3);

        // Temperatures
        $this->RegisterVariableInteger('MosfetTemp', $this->Translate('MOSFET Temperature'), '~Temperature', 4);
        $this->RegisterVariableInteger('CellTemp', $this->Translate('Cell Temperature'), '~Temperature', 5);

        // Capacity
        $this->RegisterVariableFloat('RemainingAh', $this->Translate('Remaining Capacity'), '', 6);
        $this->RegisterVariableFloat('FullCapacityAh', $this->Translate('Full Capacity'), '', 7);

        // Cycles
        $this->RegisterVariableInteger('CycleCount', $this->Translate('Cycle Count'), '', 8);

        // Timer
        $this->RegisterTimer('Update', 0, 'LTBAT_RequestData($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->SetTimerInterval('Update', $this->HasActiveParent() ? $this->ReadPropertyInteger('Poller') * 1000 : 0);
    }

    public function ReceiveData(string $JSONString): string
    {
        $data = json_decode($JSONString, true);
        $buffer = hex2bin($data['Buffer']);

        $this->SendDebug('Received', $buffer, 1);

        $battery = LiTimeParser::parse($buffer);
        $this->SetValue('SOC', $battery->soc);
        $this->SetValue('TotalVoltage', $battery->totalVoltage);
        $this->SetValue('CellVoltageSum', $battery->cellVoltageSum);
        $this->SetValue('Current', $battery->current);
        $this->SetValue('MosfetTemp', $battery->mosfetTemp);
        $this->SetValue('CellTemp', $battery->cellTemp);
        $this->SetValue('RemainingAh', $battery->remainingAh);
        $this->SetValue('FullCapacityAh', $battery->fullCapacityAh);
        $this->SetValue('CycleCount', $battery->cycleCount);

        // We don't know how many cells there are, so we dynamically create variables for each observed cell voltage
        foreach ($battery->cellVoltages as $i => $voltage) {
            $ident = 'CellVoltage' . ($i + 1);
            $this->RegisterVariableFloat($ident, sprintf($this->Translate('Cell Voltage %d'), $i + 1), '~Volt', 100 + $i);
            $this->SetValue($ident, $voltage);
        }

        return '';
    }

    public function RequestData(): void
    {
        if (!$this->HasActiveParent()) {
            return;
        }

        $this->SendDataToParent(json_encode([
            'DataID'   => self::BT_TX_GUID,
            'UUID'     => self::REQUEST_TX_UUID,
            'Buffer'   => "",
        ]));

        $this->SendDataToParent(json_encode([
            'DataID'   => self::BT_TX_GUID,
            'UUID'     => self::REQUEST_RX_UUID,
            'Buffer'   => bin2hex(self::REQUEST_PAYLOAD),
        ]));
    }
}
