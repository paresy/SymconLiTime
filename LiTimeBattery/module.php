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

        // Create profiles
        if (!IPS_VariableProfileExists('LTBAT.BatteryState')) {
            IPS_CreateVariableProfile('LTBAT.BatteryState', VARIABLETYPE_INTEGER);
            IPS_SetVariableProfileAssociation('LTBAT.BatteryState', 0, $this->Translate('Idle'), '', -1);
            IPS_SetVariableProfileAssociation('LTBAT.BatteryState', 1, $this->Translate('Charging'), '', 0x00FF00);
            IPS_SetVariableProfileAssociation('LTBAT.BatteryState', 2, $this->Translate('Discharging'), '', 0xFF8000);
            IPS_SetVariableProfileAssociation('LTBAT.BatteryState', 4, $this->Translate('Full Charge'), '', 0x00FF00);
        }

        // Battery state
        $this->RegisterVariableInteger('SOC', $this->Translate('State of Charge'), '~Battery.100', 0);
        $this->RegisterVariableInteger('SOH', $this->Translate('State of Health'), '~Battery.100', 1);
        $this->RegisterVariableFloat('TotalVoltage', $this->Translate('Total Voltage'), '~Volt', 2);
        $this->RegisterVariableFloat('CellVoltageSum', $this->Translate('Cell Voltage Sum'), '~Volt', 3);
        $this->RegisterVariableFloat('Current', $this->Translate('Current'), '~Ampere', 4);
        $this->RegisterVariableFloat('Power', $this->Translate('Power'), '~Watt.3680', 5);

        // Temperatures
        $this->RegisterVariableInteger('CellTemp', $this->Translate('Cell Temperature'), '~Temperature', 6);
        $this->RegisterVariableInteger('MosfetTemp', $this->Translate('MOSFET Temperature'), '~Temperature', 7);

        // Capacity
        $this->RegisterVariableFloat('RemainingAh', $this->Translate('Remaining Capacity'), '', 8);
        $this->RegisterVariableFloat('FullCapacityAh', $this->Translate('Full Capacity'), '', 9);

        // Cycles
        $this->RegisterVariableInteger('CycleCount', $this->Translate('Cycle Count'), '', 10);
        $this->RegisterVariableInteger('DischargesAHCount', $this->Translate('Discharge Ah Count'), '', 11);

        // States
        $this->RegisterVariableInteger('BatteryState', $this->Translate('Battery State'), 'LTBAT.BatteryState', 12);
        $this->RegisterVariableBoolean('DischargeSwitchState', $this->Translate('Discharge Switch'), '~Switch', 13);
        $this->RegisterVariableInteger('EquilibriumState', $this->Translate('Balancing State'), '', 14);
        $this->RegisterVariableString('ProtectState', $this->Translate('Protection State'), '', 15);
        $this->RegisterVariableString('FailureState', $this->Translate('Failure State'), '', 16);

        // Timer
        $this->RegisterTimer('Update', 0, 'LTBAT_RequestData($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->SetTimerInterval('Update', $this->ReadPropertyInteger('Poller') * 1000);
    }

    public function ReceiveData(string $JSONString): string
    {
        $data = json_decode($JSONString, true);
        $buffer = hex2bin($data['Buffer']);

        $this->SendDebug('Received', $buffer, 1);

        $battery = LiTimeParser::parse($buffer);
        $this->SetValue('SOC', $battery->soc);
        $this->SetValue('SOH', $battery->soh);
        $this->SetValue('TotalVoltage', $battery->totalVoltage);
        $this->SetValue('CellVoltageSum', $battery->cellVoltageSum);
        $this->SetValue('Current', $battery->current);
        $this->SetValue('Power', $battery->power);
        $this->SetValue('CellTemp', $battery->cellTemp);
        $this->SetValue('MosfetTemp', $battery->mosfetTemp);
        $this->SetValue('RemainingAh', $battery->remainingAh);
        $this->SetValue('FullCapacityAh', $battery->fullCapacityAh);
        $this->SetValue('CycleCount', $battery->cycleCount);
        $this->SetValue('DischargesAHCount', $battery->dischargesAHCount);
        $this->SetValue('BatteryState', $battery->batteryState);
        $this->SetValue('DischargeSwitchState', $battery->dischargeSwitchState);
        $this->SetValue('EquilibriumState', $battery->equilibriumState);
        $this->SetValue('ProtectState', $battery->protectState);
        $this->SetValue('FailureState', $battery->failureState);

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
