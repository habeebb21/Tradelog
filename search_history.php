<?php
$logPath = "C:\\Users\\habee\\.gemini\\antigravity-ide\\brain\\93187322-6220-4144-8fed-85868e2b19e0\\.system_generated\\logs\\transcript.jsonl";
$handle = fopen($logPath, "r");
if ($handle) {
    while (($line = fgets($handle)) !== false) {
        $data = json_decode($line, true);
        if (isset($data['type']) && ($data['type'] === 'CODE_ACTION' || $data['type'] === 'REPLACE_FILE_CONTENT' || $data['type'] === 'WRITE_TO_FILE')) {
            if (strpos(json_encode($data), 'trades.blade.php') !== false) {
                echo "Step: " . $data['step_index'] . ", Type: " . $data['type'] . "\n";
                if (isset($data['tool_calls'])) {
                    foreach ($data['tool_calls'] as $tc) {
                        if (strpos($tc['args']['TargetFile'] ?? '', 'trades.blade.php') !== false) {
                            echo "Instruction: " . ($tc['args']['Instruction'] ?? '') . "\n";
                            echo "Target: " . ($tc['args']['TargetContent'] ?? '') . "\n";
                            echo "Replacement: " . ($tc['args']['ReplacementContent'] ?? '') . "\n";
                            echo "---------------------------------\n";
                        }
                    }
                }
            }
        }
    }
    fclose($handle);
}
