<?php

use App\Providers\AppServiceProvider;
use App\Providers\DiscussionServiceProvider;
use App\Providers\RepositoryServiceProvider;

return [
    AppServiceProvider::class,
    RepositoryServiceProvider::class,
    DiscussionServiceProvider::class,
];
