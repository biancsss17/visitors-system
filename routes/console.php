<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment('Keep building.');
})->purpose('Display an inspiring message');
