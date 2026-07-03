<?php

declare(strict_types=1);

namespace ledgehq\craftledge\migrations;

use craft\db\Migration;

class m260702_100000_add_acquisitions_table extends Migration
{
    public function safeUp(): bool
    {
        return (new Install())->safeUp();
    }

    public function safeDown(): bool
    {
        return (new Install())->safeDown();
    }
}
