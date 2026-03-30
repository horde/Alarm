<?php

class HordeAlarmsTable extends Horde_Db_Migration_Base
{
    public function up()
    {
        if (!in_array('horde_alarms', $this->tables())) {
            $t = $this->createTable('horde_alarms');
            $t->column('alarm_id', 'string', ['limit' => 255, 'null' => false]);
            $t->column('alarm_uid', 'string', ['limit' => 255]);
            $t->column('alarm_start', 'datetime', ['null' => false]);
            $t->column('alarm_end', 'datetime');
            $t->column('alarm_methods', 'string', ['limit' => 255]);
            $t->column('alarm_params', 'text');
            $t->column('alarm_title', 'string', ['limit' => 255, 'null' => false]);
            $t->column('alarm_text', 'text');
            $t->column('alarm_snooze', 'datetime');
            $t->column('alarm_dismissed', 'integer', ['limit' => 1, 'null' => false, 'default' => 0]);
            $t->column('alarm_internal', 'text');
            $t->end();

            $this->addIndex('horde_alarms', ['alarm_id']);
            $this->addIndex('horde_alarms', ['alarm_uid']);
            $this->addIndex('horde_alarms', ['alarm_start']);
            $this->addIndex('horde_alarms', ['alarm_end']);
            $this->addIndex('horde_alarms', ['alarm_snooze']);
            $this->addIndex('horde_alarms', ['alarm_dismissed']);
        }
    }

    public function down()
    {
        $this->dropTable('horde_alarms');
    }
}
