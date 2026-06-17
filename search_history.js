const fs = require('fs');
const path = require('path');

const logPath = "C:\\Users\\habee\\.gemini\\antigravity-ide\\brain\\93187322-6220-4144-8fed-85868e2b19e0\\.system_generated\\logs\\transcript.jsonl";

try {
    const fileContent = fs.readFileSync(logPath, 'utf8');
    const lines = fileContent.split('\n');
    for (const line of lines) {
        if (!line.trim()) continue;
        const data = JSON.parse(line);
        if (data.type === 'CODE_ACTION' || data.type === 'REPLACE_FILE_CONTENT' || data.type === 'WRITE_TO_FILE') {
            const str = JSON.stringify(data);
            if (str.includes('trades.blade.php')) {
                console.log(`Step: ${data.step_index}, Type: ${data.type}`);
                if (data.tool_calls) {
                    for (const tc of data.tool_calls) {
                        if (tc.args && JSON.stringify(tc.args).includes('trades.blade.php')) {
                            console.log(`Instruction: ${tc.args.Instruction}`);
                            console.log(`Target: ${tc.args.TargetContent}`);
                            console.log(`Replacement: ${tc.args.ReplacementContent}`);
                            console.log("---------------------------------");
                        }
                    }
                }
            }
        }
    }
} catch (e) {
    console.error(e);
}
