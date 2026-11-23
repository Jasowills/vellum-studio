<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('vellum:reconcile-generations')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command('vellum:retry-webhooks')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command('pixelforge:reconcile-usage --days=7')
    ->hourly()
    ->withoutOverlapping(10);
