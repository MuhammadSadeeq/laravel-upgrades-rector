<?php

class DatabaseConnection
{
    public function someMethod()
    {
        $this->getDoctrineConnection();
        $this->getDoctrineSchemaManager();
        $this->getDoctrineColumn('users', 'name');
        $this->registerDoctrineType('custom', 'name', 'type');
        $this->isDoctrineAvailable();
    }
}

?>
-----
<?php

class DatabaseConnection
{
    public function someMethod()
    {
        // Laravel 11: getDoctrineConnection() has been removed. Doctrine DBAL is no longer required.
        $this->getDoctrineConnection();
        // Laravel 11: getDoctrineSchemaManager() has been removed. Doctrine DBAL is no longer required.
        $this->getDoctrineSchemaManager();
        // Laravel 11: getDoctrineColumn() has been removed. Doctrine DBAL is no longer required.
        $this->getDoctrineColumn('users', 'name');
        // Laravel 11: registerDoctrineType() has been removed. Doctrine DBAL is no longer required.
        $this->registerDoctrineType('custom', 'name', 'type');
        // Laravel 11: isDoctrineAvailable() has been removed. Doctrine DBAL is no longer required.
        $this->isDoctrineAvailable();
    }
}

?>
