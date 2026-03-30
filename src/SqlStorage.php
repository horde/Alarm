<?php

declare(strict_types=1);

/**
 * Copyright 2007-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author     Jan Schneider <jan@horde.org>
 * @category   Horde
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Alarm
 */

namespace Horde\Alarm;

use Horde\Alarm\AlarmConfig;
use Horde\Alarm\AlarmException;
use Horde_Date;
use Horde\Db\Adapter;
use Horde\Db\Exception as DbException;
use Horde\Db\Value\Text as DbText;
use Horde_Alarm_Translation;

/**
 * SQL alarm storage backend using Horde Db.
 *
 * @author     Jan Schneider <jan@horde.org>
 * @category   Horde
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Alarm
 */
class SqlStorage implements StorageInterface
{
    private readonly string $tableName;
    private readonly string $charset;

    /**
     * @param Adapter $db Database adapter instance
     * @param string $tableName Table name for alarm storage
     * @param string $charset Database charset for encoding conversion
     */
    public function __construct(
        private readonly Adapter $db,
        string $tableName = 'horde_alarms',
        string $charset = 'UTF-8',
    ) {
        $this->tableName = $tableName;
        $this->charset = $charset;
    }

    public function listAlarms(?string $user, Horde_Date $time): array
    {
        $query = sprintf(
            'SELECT alarm_id, alarm_uid, alarm_start, alarm_end, alarm_methods, alarm_params, alarm_title, alarm_text, alarm_snooze, alarm_internal, alarm_instanceid FROM %s WHERE alarm_dismissed = 0 AND ((alarm_snooze IS NULL AND alarm_start <= ?) OR alarm_snooze <= ?) AND (alarm_end IS NULL OR alarm_end >= ?)%s ORDER BY alarm_start, alarm_end',
            $this->tableName,
            $user !== null ? ' AND (alarm_uid IS NULL OR alarm_uid = ? OR alarm_uid = ?)' : ''
        );

        $dt = $time->setTimezone('UTC')->format(Horde_Date::DATE_DEFAULT);
        $values = [$dt, $dt, $dt];
        if ($user !== null) {
            $values[] = '';
            $values[] = $user;
        }

        try {
            $result = $this->db->select($query, $values);
            $alarms = [];
            foreach ($result as $row) {
                $alarms[] = $this->rowToConfig($row);
            }
            return $alarms;
        } catch (DbException $e) {
            throw new AlarmException(
                Horde_Alarm_Translation::t("Server error when querying database."),
                previous: $e
            );
        }
    }

    public function globalAlarms(): array
    {
        $query = sprintf(
            'SELECT alarm_id, alarm_uid, alarm_start, alarm_end, alarm_methods, alarm_params, alarm_title, alarm_text, alarm_snooze, alarm_internal, alarm_instanceid FROM %s WHERE alarm_uid IS NULL OR alarm_uid = \'\' ORDER BY alarm_start, alarm_end',
            $this->tableName
        );

        try {
            $result = $this->db->select($query);
            $alarms = [];
            foreach ($result as $row) {
                $alarms[] = $this->rowToConfig($row);
            }
            return $alarms;
        } catch (DbException $e) {
            throw new AlarmException(
                Horde_Alarm_Translation::t("Server error when querying database."),
                previous: $e
            );
        }
    }

    public function get(string $id, string $user): AlarmConfig
    {
        $query = sprintf(
            'SELECT alarm_id, alarm_uid, alarm_start, alarm_end, alarm_methods, alarm_params, alarm_title, alarm_text, alarm_snooze, alarm_internal, alarm_instanceid FROM %s WHERE alarm_id = ? AND %s',
            $this->tableName,
            $user !== '' ? 'alarm_uid = ?' : '(alarm_uid = ? OR alarm_uid IS NULL)'
        );

        try {
            $row = $this->db->selectOne($query, [$id, $user]);
        } catch (DbException $e) {
            throw new AlarmException(
                Horde_Alarm_Translation::t("Server error when querying database."),
                previous: $e
            );
        }

        if (empty($row)) {
            throw new AlarmException('Alarm not found');
        }

        return $this->rowToConfig($row);
    }

    public function set(AlarmConfig $alarm, bool $keepSnooze = false): void
    {
        // If recurring alarm with new instanceId, delete the old entry
        if ($alarm->instanceId !== null
            && !$this->exists($alarm->id, $alarm->user, $alarm->instanceId)) {
            $this->delete($alarm->id, $alarm->user);
        }

        if ($this->exists($alarm->id, $alarm->user)) {
            $this->update($alarm, $keepSnooze);
        } else {
            $this->add($alarm);
        }
    }

    public function updateInternal(string $id, string $user, array $internal): void
    {
        $query = sprintf(
            'UPDATE %s SET alarm_internal = ? WHERE alarm_id = ? AND %s',
            $this->tableName,
            $user !== '' ? 'alarm_uid = ?' : '(alarm_uid = ? OR alarm_uid IS NULL)'
        );
        $values = [new DbText(serialize($internal)), $id, $user];

        try {
            $this->db->update($query, $values);
        } catch (DbException $e) {
            throw new AlarmException(
                Horde_Alarm_Translation::t("Server error when querying database."),
                previous: $e
            );
        }
    }

    public function exists(string $id, string $user, ?string $instanceId = null): bool
    {
        $query = sprintf(
            'SELECT 1 FROM %s WHERE alarm_id = ? AND %s',
            $this->tableName,
            ($user !== '' ? 'alarm_uid = ?' : '(alarm_uid = ? OR alarm_uid IS NULL)')
                . ($instanceId !== null ? ' AND alarm_instanceid = ?' : '')
        );
        $params = [$id, $user];
        if ($instanceId !== null) {
            $params[] = $instanceId;
        }

        try {
            return $this->db->selectValue($query, $params) == 1;
        } catch (DbException $e) {
            throw new AlarmException(
                Horde_Alarm_Translation::t("Server error when querying database."),
                previous: $e
            );
        }
    }

    public function snooze(string $id, string $user, Horde_Date $snoozeUntil): void
    {
        $query = sprintf(
            'UPDATE %s SET alarm_snooze = ? WHERE alarm_id = ? AND %s',
            $this->tableName,
            $user !== '' ? 'alarm_uid = ?' : '(alarm_uid = ? OR alarm_uid IS NULL)'
        );
        $values = [$snoozeUntil->setTimezone('UTC')->format(Horde_Date::DATE_DEFAULT), $id, $user];

        try {
            $this->db->update($query, $values);
        } catch (DbException $e) {
            throw new AlarmException(
                Horde_Alarm_Translation::t("Server error when querying database."),
                previous: $e
            );
        }
    }

    public function isSnoozed(string $id, string $user, Horde_Date $time): bool
    {
        $query = sprintf(
            'SELECT 1 FROM %s WHERE alarm_id = ? AND %s AND (alarm_dismissed = 1 OR (alarm_snooze IS NOT NULL AND alarm_snooze >= ?))',
            $this->tableName,
            $user !== '' ? 'alarm_uid = ?' : '(alarm_uid = ? OR alarm_uid IS NULL)'
        );

        try {
            return (bool) $this->db->selectValue(
                $query,
                [$id, $user, $time->setTimezone('UTC')->format(Horde_Date::DATE_DEFAULT)]
            );
        } catch (DbException $e) {
            throw new AlarmException(
                Horde_Alarm_Translation::t("Server error when querying database."),
                previous: $e
            );
        }
    }

    public function dismiss(string $id, string $user): void
    {
        $query = sprintf(
            'UPDATE %s SET alarm_dismissed = 1 WHERE alarm_id = ? AND %s',
            $this->tableName,
            $user !== '' ? 'alarm_uid = ?' : '(alarm_uid = ? OR alarm_uid IS NULL)'
        );
        $values = [$id, $user];

        try {
            $this->db->update($query, $values);
        } catch (DbException $e) {
            throw new AlarmException(
                Horde_Alarm_Translation::t("Server error when querying database."),
                previous: $e
            );
        }
    }

    public function delete(string $id, string $user): void
    {
        $query = sprintf('DELETE FROM %s WHERE alarm_id = ?', $this->tableName);
        $values = [$id];

        if ($user !== '') {
            $query .= ' AND alarm_uid = ?';
            $values[] = $user;
        } else {
            $query .= ' AND (alarm_uid IS NULL OR alarm_uid = ?)';
            $values[] = '';
        }

        try {
            $this->db->delete($query, $values);
        } catch (DbException $e) {
            throw new AlarmException(
                Horde_Alarm_Translation::t("Server error when querying database."),
                previous: $e
            );
        }
    }

    public function gc(): void
    {
        $query = sprintf(
            'DELETE FROM %s WHERE alarm_end IS NOT NULL AND alarm_end < ?',
            $this->tableName
        );
        $end = new Horde_Date(time());

        try {
            $this->db->delete($query, [$end->setTimezone('UTC')->format(Horde_Date::DATE_DEFAULT)]);
        } catch (DbException $e) {
            throw new AlarmException(
                Horde_Alarm_Translation::t("Server error when querying database."),
                previous: $e
            );
        }
    }

    public function initialize(): void
    {
        // Handle database-specific initialization
        try {
            switch ($this->db->adapterName()) {
                case 'PDO_Oci':
                    $this->db->select("ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS'");
                    break;

                case 'PDO_PostgreSQL':
                    $this->db->select("SET datestyle TO 'iso'");
                    break;
            }
        } catch (DbException $e) {
            throw new AlarmException(
                'Failed to initialize database session',
                previous: $e
            );
        }
    }

    /**
     * Adds a new alarm to the database.
     */
    private function add(AlarmConfig $alarm): void
    {
        $values = [
            'alarm_id' => $alarm->id,
            'alarm_uid' => $alarm->user,
            'alarm_start' => $alarm->start->setTimezone('UTC')->format(Horde_Date::DATE_DEFAULT),
            'alarm_end' => $alarm->end?->setTimezone('UTC')->format(Horde_Date::DATE_DEFAULT),
            'alarm_methods' => serialize(array_map(fn($m) => $m->value, $alarm->methods)),
            'alarm_params' => new DbText(base64_encode(serialize($alarm->params))),
            'alarm_title' => $this->toDriver($alarm->title),
            'alarm_text' => $alarm->text !== null ? new DbText($this->toDriver($alarm->text)) : null,
            'alarm_snooze' => null,
            'alarm_instanceid' => $alarm->instanceId,
        ];

        try {
            $this->db->insertBlob($this->tableName, $values);
        } catch (DbException $e) {
            throw new AlarmException(
                Horde_Alarm_Translation::t("Server error when querying database."),
                previous: $e
            );
        }
    }

    /**
     * Updates an existing alarm in the database.
     */
    private function update(AlarmConfig $alarm, bool $keepSnooze): void
    {
        $where = [
            sprintf(
                'alarm_id = ? AND %s',
                $alarm->user !== '' ? 'alarm_uid = ?' : '(alarm_uid = ? OR alarm_uid IS NULL)'
            ),
            [$alarm->id, $alarm->user],
        ];

        $values = [
            'alarm_start' => $alarm->start->setTimezone('UTC')->format(Horde_Date::DATE_DEFAULT),
            'alarm_end' => $alarm->end?->setTimezone('UTC')->format(Horde_Date::DATE_DEFAULT),
            'alarm_methods' => serialize(array_map(fn($m) => $m->value, $alarm->methods)),
            'alarm_params' => new DbText(base64_encode(serialize($alarm->params))),
            'alarm_title' => $this->toDriver($alarm->title),
            'alarm_text' => $alarm->text !== null ? new DbText($this->toDriver($alarm->text)) : null,
            'alarm_instanceid' => $alarm->instanceId,
        ];

        if (!$keepSnooze) {
            $values['alarm_snooze'] = null;
            $values['alarm_dismissed'] = 0;
        }

        try {
            $this->db->updateBlob($this->tableName, $values, $where);
        } catch (DbException $e) {
            throw new AlarmException(
                Horde_Alarm_Translation::t("Server error when querying database."),
                previous: $e
            );
        }
    }

    /**
     * Converts a database row to an AlarmConfig.
     */
    private function rowToConfig(array $row): AlarmConfig
    {
        // Convert binary TEXT columns to strings
        foreach (['params', 'text', 'internal'] as $column) {
            if (isset($row['alarm_' . $column])) {
                $row['alarm_' . $column] = $this->convertBinary(
                    'alarm_' . $column,
                    $row['alarm_' . $column]
                );
            }
        }

        // Decode params
        $params = base64_decode($row['alarm_params']);
        if (!strlen($params) && strlen($row['alarm_params'])) {
            $params = $row['alarm_params'];
        }
        try {
            $params = @unserialize($params);
        } catch (\Exception $e) {
            $params = [];
        }
        if (!is_array($params)) {
            $params = [];
        }

        // Decode internal
        $internal = [];
        if (!empty($row['alarm_internal'])) {
            try {
                $internal = @unserialize($row['alarm_internal']);
            } catch (\Exception $e) {
            }
            if (!is_array($internal)) {
                $internal = [];
            }
        }

        // Decode methods
        $methods = @unserialize($row['alarm_methods']);
        if (!is_array($methods)) {
            $methods = [];
        }

        return new AlarmConfig(
            id: $row['alarm_id'],
            user: $row['alarm_uid'] ?? '',
            start: new Horde_Date($row['alarm_start'], 'UTC'),
            end: !empty($row['alarm_end']) ? new Horde_Date($row['alarm_end'], 'UTC') : null,
            methods: array_map(
                fn(string $method) => \Horde\Alarm\NotificationMethod::from($method),
                $methods
            ),
            params: $params,
            title: $this->fromDriver($row['alarm_title']),
            text: !empty($row['alarm_text']) ? $this->fromDriver($row['alarm_text']) : null,
            snooze: !empty($row['alarm_snooze']) ? new Horde_Date($row['alarm_snooze'], 'UTC') : null,
            internal: $internal,
            instanceId: $row['alarm_instanceid'] ?? null,
        );
    }

    /**
     * Converts a value from the driver's charset to UTF-8.
     */
    private function fromDriver(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if ($this->charset === 'UTF-8') {
            return $value;
        }

        $result = mb_convert_encoding($value, 'UTF-8', $this->charset);
        if ($result === false) {
            throw new AlarmException('Character encoding conversion failed');
        }

        return $result;
    }

    /**
     * Converts a value from UTF-8 to the driver's charset.
     */
    private function toDriver(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        if ($this->charset === 'UTF-8') {
            return $value;
        }

        $result = mb_convert_encoding($value, $this->charset, 'UTF-8');
        if ($result === false) {
            throw new AlarmException('Character encoding conversion failed');
        }

        return $result;
    }

    /**
     * Converts results from TEXT columns to strings.
     */
    private function convertBinary(string $column, mixed $value): string
    {
        try {
            $columns = $this->db->columns($this->tableName);
        } catch (DbException $e) {
            throw new AlarmException(
                Horde_Alarm_Translation::t("Server error when querying database."),
                previous: $e
            );
        }
        return $columns[$column]->binaryToString($value);
    }
}
