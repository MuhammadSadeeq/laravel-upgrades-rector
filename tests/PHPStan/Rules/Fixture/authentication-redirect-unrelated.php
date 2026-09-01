<?php

namespace App;

final class UnrelatedRedirectException
{
    public function redirectTo(): ?string
    {
        return null;
    }
}

function unrelatedRedirect(UnrelatedRedirectException $exception): ?string
{
    return $exception->redirectTo();
}
