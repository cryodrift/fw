<?php

//declare(strict_types=1);

namespace cryodrift\fw\trait;

trait DbHelperVDelete
{

    public function runvDelete(string $id, string $tablename): bool
    {
        $sql = "UPDATE " . $tablename . " SET deleted = 'y' WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function runvUndelete(string $id, string $tablename): bool
    {
        $sql = "UPDATE " . $tablename . " SET deleted = null WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        return $stmt->execute();
    }

}
