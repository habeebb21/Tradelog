const fs = require('fs');

const logPath = "C:\\Users\\habee\\.gemini\\antigravity-ide\\brain\\320a9cde-81e1-4cfd-a9a0-33e6af8a0cbd\\.system_generated\\logs\\transcript.jsonl";

try {
    const fileContent = fs.readFileSync(logPath, 'utf8');
    const lines = fileContent.split('\n');
    for (const line of lines) {
        if (!line.trim()) continue;
        const data = JSON.parse(line);
        const str = JSON.stringify(data);
        if (str.includes('visiblePositions') || str.includes('tradeSummary')) {
            console.log(`Step: ${data.step_index}`);
            if (data.tool_calls) {
                for (const tc of data.tool_calls) {
                    console.log(`Tool: ${tc.name}`);
                    if (tc.args) console.log(JSON.stringify(tc.args, null, 2));
                }
            }
        }
    }
} catch (e) {
    console.error(e);
}
