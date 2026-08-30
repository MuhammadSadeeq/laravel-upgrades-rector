<?php

namespace App;

final class OAuthServer
{
    public static function routes(): void {}
}

function registerOtherRoutes(): void
{
    OAuthServer::routes();
}
