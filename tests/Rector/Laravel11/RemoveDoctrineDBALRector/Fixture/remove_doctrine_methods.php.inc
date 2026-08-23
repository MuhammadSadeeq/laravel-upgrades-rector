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
        $this->getDoctrineConnection();
        $this->getDoctrineSchemaManager();
        $this->getDoctrineColumn('users', 'name');
        $this->registerDoctrineType('custom', 'name', 'type');
        $this->isDoctrineAvailable();
    }
}

?>
