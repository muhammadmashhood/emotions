<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('forms:health reconcile')->hourly()->withoutOverlapping();
Schedule::command('forms:health structure')->everySixHours()->withoutOverlapping();
Schedule::command('forms:health webhooks')->daily()->withoutOverlapping();
Schedule::command('deliveries:prune')->daily();
