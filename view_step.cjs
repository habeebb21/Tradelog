const fs = require('fs');

const logPath = "C:\\Users\\habee\\.gemini\\antigravity-ide\\brain\\320a9cde-81e1-4cfd-a9a0-33e6af8a0cbd\\.system_generated\\logs\\transcript.jsonl";

try {
    const fileContent = fs.readFileSync(logPath, 'utf8');
    const lines = fileContent.split('\n');
    for (const line of lines) {
        if (!line.trim()) continue;
        const data = JSON.parse(line);
        if (data.step_index === 958) {
            console.log(JSON.stringify(data, null, 2));
            break;
        }
    }
} catch (e) {
    console.error(e);
}
