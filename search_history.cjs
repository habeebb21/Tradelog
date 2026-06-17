const fs = require('fs');

const logPath = "C:\\Users\\habee\\.gemini\\antigravity-ide\\brain\\93187322-6220-4144-8fed-85868e2b19e0\\.system_generated\\logs\\transcript.jsonl";

try {
    const fileContent = fs.readFileSync(logPath, 'utf8');
    const lines = fileContent.split('\n');
    for (const line of lines) {
        if (!line.trim()) continue;
        const data = JSON.parse(line);
        if (data.tool_calls) {
            for (const tc of data.tool_calls) {
                if (tc.name === 'replace_file_content' || tc.name === 'multi_replace_file_content') {
                    const argsStr = JSON.stringify(tc.args);
                    if (argsStr.includes('TradesController.php')) {
                        console.log(`Step: ${data.step_index}`);
                        console.log(`Args: ${JSON.stringify(tc.args, null, 2)}`);
                        console.log("=========================================");
                    }
                }
            }
        }
    }
} catch (e) {
    console.error(e);
}
