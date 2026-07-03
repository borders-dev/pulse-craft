<?php

declare(strict_types=1);

namespace ledgehq\craftledge\migrations;

use craft\db\Migration;
use ledgehq\craftledge\records\AcquisitionRecord;

class Install extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->tableExists(AcquisitionRecord::tableName())) {
            $this->createTable(AcquisitionRecord::tableName(), [
                'id' => $this->primaryKey(),
                'runId' => $this->string(64)->notNull(),
                'status' => $this->string(32)->notNull()->defaultValue(AcquisitionRecord::STATUS_PENDING),
                'step' => $this->string(32),
                'detail' => $this->text(),
                'profile' => $this->string(32)->notNull()->defaultValue('full'),
                'sizeBytes' => $this->bigInteger(),
                'sha256' => $this->string(64),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, AcquisitionRecord::tableName(), ['runId'], true);
        }

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists(AcquisitionRecord::tableName());

        return true;
    }
}
