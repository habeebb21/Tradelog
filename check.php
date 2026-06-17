<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Position;
use App\Models\Instrument;
use App\Models\User;

echo "Users in DB:\n";
foreach (User::all() as $u) {
    echo "User ID: {$u->id}, Email: {$u->email}\n";
}

echo "\nPositions and Instruments:\n";
$positions = Position::all();
foreach ($positions as $p) {
    $inst = $p->instrument;
    echo "Position ID: {$p->id}, Instrument ID: {$p->instrument_id}, Inst Symbol: " . ($inst ? $inst->symbol : 'NULL') . ", Inst User ID: " . ($inst ? $inst->user_id : 'NULL') . "\n";
}
