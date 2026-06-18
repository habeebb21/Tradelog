<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AppController extends Controller
{
    /**
     * Shut down the Docker container gracefully.
     * Runs `docker compose down` in the background then returns so the
     * browser gets a response before the server goes away.
     */
    public function exit(Request $request)
    {
        // Find the project root (one level above /public)
        $projectRoot = base_path();

        // Fire-and-forget: run docker compose down after a short delay
        // so this response can reach the browser first.
        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen("start /B cmd /C \"cd /d \"{$projectRoot}\" && docker compose down\"", 'r'));
        } else {
            exec("cd \"{$projectRoot}\" && docker compose down > /dev/null 2>&1 &");
        }

        return response()->json(['success' => true]);
    }
}
