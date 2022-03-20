<?php declare(strict_types = 1);
namespace SpawnCore\System\Database\Entity\TableDefinition\DefaultColumns;


use SpawnCore\System\Database\Entity\TableDefinition\AbstractColumn;
use SpawnCore\System\Database\Entity\TableDefinition\Constants\ColumnTypes;

class UpdatedAtColumn extends AbstractColumn {


    public function getName(): string
    {
        return 'updatedAt';
    }

    public function getType(): string
    {
        return ColumnTypes::DATETIME_TZ;
    }

    public function canBeNull(): ?bool
    {
        return false;
    }


    public function getTypeIdentifier()
    {
        return 'datetime'; //php´s \DateTime()
    }
}