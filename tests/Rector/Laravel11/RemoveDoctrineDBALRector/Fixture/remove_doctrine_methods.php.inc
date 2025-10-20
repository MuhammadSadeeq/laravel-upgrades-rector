<?php

class DatabaseConnection
{
    public function someMethod()
    {
        $this->useNativeSchemaOperationsIfPossible();
        $this->usingNativeSchemaOperations();
        $this->isDoctrineAvailable();
        $this->getDoctrineConnection();
        $this->getDoctrineSchemaManager();
        $this->getDoctrineColumn('name');
        $this->registerDoctrineType('custom');
        $this->getDoctrineTableDiff();
        $this->getColumnType('name');
    }
}

?>
-----
<?php

class DatabaseConnection
{
    public function someMethod()
    {
        $this->useNativeSchemaOperationsIfPossible();
        $this->usingNativeSchemaOperations();
        $this->isDoctrineAvailable();
        $this->getDoctrineConnection();
        $this->getDoctrineSchemaManager();
        $this->getDoctrineColumn('name');
        $this->registerDoctrineType('custom');
        $this->getDoctrineTableDiff();
        $this->getColumnType('name');
    }
}

?>