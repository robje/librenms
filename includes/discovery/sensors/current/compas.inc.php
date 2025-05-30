<?php

/**
 * compas.inc.php
 *
 * LibreNMS current sensor discovery module for Alpha Comp@s UPS
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @link       https://www.librenms.org
 *
 * @copyright  2019 Paul Parsons
 * @author     Paul Parsons <paul@cppmonkey.net>
 */
$rectifiersOutputCurrent = snmp_get($device, 'es1dc1DataRectifiersRectifiersOutCurrent.0', '-Ovqe', 'SITE-MONITORING-MIB');
$rectifiersOutputCurrentMax = snmp_get($device, 'es1dc1DataRectifiersRectifiersOutCurrentMax.0', '-Ovqe', 'SITE-MONITORING-MIB');
$curOID = '.1.3.6.1.4.1.26854.3.2.1.20.1.20.1.13.3.22.0';
$index = 'es1dc1DataRectifiersRectifiersOutCurrent';
if (is_numeric($rectifiersOutputCurrent) && is_numeric($rectifiersOutputCurrentMax)) {
    $sensorType = 'compas';
    $descr = 'Output Current';
    discover_sensor(null, 'current', $device, $curOID, $index, $sensorType, $descr, '1', '1', null, null, null, $rectifiersOutputCurrentMax, $rectifiersOutputCurrent);
}
$loadCurrent = snmp_get($device, 'es1dc1DataLoadLoadCurrent.0', '-Ovqe', 'SITE-MONITORING-MIB');
$curOID = '.1.3.6.1.4.1.26854.3.2.1.20.1.20.1.13.3.52.0';
$index = 'es1dc1DataLoadLoadCurrent';
if (is_numeric($loadCurrent)) {
    $sensorType = 'compas';
    $descr = 'Load Current';
    discover_sensor(null, 'current', $device, $curOID, $index, $sensorType, $descr, '1', '1', null, null, null, null, $loadCurrent);
}
$batteryInputCurrent = snmp_get($device, 'es1dc1DataBatBatInputCurrent.0', '-Ovqe', 'SITE-MONITORING-MIB');
$curOID = '.1.3.6.1.4.1.26854.3.2.1.20.1.20.1.13.3.61.0';
$index = 'es1dc1DataBatBatInputCurrent';
if (is_numeric($batteryInputCurrent)) {
    $sensorType = 'compas';
    $descr = 'Battery Input Current';
    discover_sensor(null, 'current', $device, $curOID, $index, $sensorType, $descr, '1', '1', null, null, null, null, $batteryInputCurrent);
}
