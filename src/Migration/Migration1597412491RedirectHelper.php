<?php declare(strict_types=1);

namespace Sisow\Payment\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1597412491RedirectHelper extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1597412491;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            CREATE TABLE IF NOT EXISTS sisow_payment_redirect (
                `id` binary(16) NOT NULL PRIMARY KEY,                
                `hash` text NOT NULL,
                `url` text NOT NULL,
                `created_at` datetime(3) NULL                    
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
