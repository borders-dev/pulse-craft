<?php

declare(strict_types=1);

namespace ledgehq\craftledge\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property string $runId
 * @property string $status
 * @property string|null $step
 * @property string|null $detail
 * @property string $profile
 * @property int|null $sizeBytes
 * @property string|null $sha256
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class AcquisitionRecord extends ActiveRecord
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public static function tableName(): string
    {
        return '{{%ledge_acquisitions}}';
    }
}
