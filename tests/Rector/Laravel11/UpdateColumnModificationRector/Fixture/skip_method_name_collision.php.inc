<?php

namespace App\Fixtures\Laravel11\UpdateColumnModificationRector;

class SomeOtherClass
{
    public function processData()
    {
        $data = $this->getData();
        $data->change();

        $config = new Configuration();
        $config->change();

        return $result->change();
    }

    public function change()
    {
        return 'changed';
    }
}

?>
