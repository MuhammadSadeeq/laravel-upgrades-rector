<?php

class AppServiceProvider
{
    public function boot(): void
    {
        $this->validateCsrfTokens();
    }
}

?>
