<?php

class SomeOtherClass
{
    public function processData()
    {
        // These should not be changed as they're not table column modifications
        $data = $this->getData();
        $data->change();

        $config = new Configuration();
        $config->change();

        return $result->change();
    }

    public function change()
    {
        // Method definition should not be changed
        return 'changed';
    }
}

?>