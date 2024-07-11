//#region FORMAT MODIFIERS
//0. InitializeMessage: resets all variables to start message.(at request function)
//1. Gets the received Chunk.
//2. escape HTML to prevent injection or mistaken rendering.
//3. format text for code blocks.
//4. replace markdown sytaxes for interface rendering

let isInCodeBlock = false;
let lastClosingIndex = -1;
let lastChunk = '';
let summedText = '';

function InitializeMessage() {
    isInCodeBlock = false;
    lastClosingIndex = -1;
    lastChunk = '';
    summedText = '';
}


function FormatChunk(chunk) {
    chunk = escapeHTML(chunk);

    let formattedText = '';
    let prevText = '';
    if (lastClosingIndex !== -1) {
        prevText = summedText.substring(0, lastClosingIndex);
        formattedText = summedText.substring(lastClosingIndex);
    } else {
        formattedText = summedText;
    }

    if (isInCodeBlock) {
        // END OF CODE BLOCK
        if (chunk === '``') {
            isInCodeBlock = false;
            lastClosingIndex = summedText.length + 1;
        } else {
            formattedText = formattedText.replace('</code></pre>', '');
            formattedText += (chunk + '</code></pre>');
        }
    } else {
        // START OF CODE BLOCK
        if (chunk === '```') {
            isInCodeBlock = true;
            formattedText += '<pre><code ignore_Format>';
        }
        else {
            if (chunk.includes('`') && lastChunk === '``') {

                if(lastClosingIndex < summedText.length + 1){
                    isInCodeBlock = true;
                    chunk = '';
                    formattedText = formattedText.slice(0, -3);
                    formattedText += '<pre><code ignore_Format>';
                }
                else{
                    chunk = chunk.replace('`', '');
                }
            }
            // Plain Text
            formattedText += chunk;
        }
    }

    lastChunk = chunk;
	if(isInCodeBlock){
        summedText = prevText + formattedText;
		return summedText;
	}
	else{
        summedText = prevText + ReplaceMarkdownSyntax(formattedText);
		return summedText;
	}
}

function ReplaceMarkdownSyntax(text) {
    // Replace bold and italic (*text* or ___text___)
    text = text.replace(/\*\*\*(.*?)\*\*\*/g, '<b><i>$1</i></b>');
    text = text.replace(/___(.*?)___/g, '<b><i>$1</i></b>');

    // Replace only bold (**text** or __text__)
    text = text.replace(/\*\*(.*?)\*\*/g, '<b>$1</b>');
    text = text.replace(/__(.*?)__/g, '<b>$1</b>');

    // Replace only italic (*text* or _text_)
    text = text.replace(/\*(.*?)\*/g, '<i>$1</i>');
    text = text.replace(/_(.*?)_/g, '<i>$1</i>');

    // Replace Strikethrough
    text = text.replace(/~~(.*?)~~/g, '<del>$1></del>');

    // Links
    text = text.replace(/\[([^\]]+)\]\(([^\)]+)\)/g, '<a href="$2">$1</a>');

    // Headings
    text = text.replace(/(?<![^\s])(######\s?(.*))/g, '<h3>$2></h3>');
    text = text.replace(/(?<![^\s])(#####\s?(.*))/g, '<h3>$2></h3>');
    text = text.replace(/(?<![^\s])(####\s?(.*))/g, '<h3>$2></h3>');
    text = text.replace(/(?<![^\s])(###\s?(.*))/g, '<h3>$2></h3>');
    text = text.replace(/(?<![^\s])(##\s?(.*))/g, '<h3>$2></h3>');
    text = text.replace(/(?<![^\s])(#\s?(.*))/g, '<h3>$2></h3>');

    // HANDLE MARKDOWN TABLES
    const tableRegex = /(\|.*\|)\n(\|.*\|)(\n\|\s*:?-+:?\s*)*\n((\|.*\|)\n*)+/g;
    text = text.replace(tableRegex, (match) => {

        const rows = match.split('\n').filter(Boolean);
        const cells = rows.map(row => row.replace(/^\||\|$/g, '').split('|').map(cell => cell.trim()));
        const filteredCells = cells.filter(row => !row.every(cell => /^-+$/.test(cell)));
        const headerRow = filteredCells.shift();

        let html = '<table>\n<thead>\n<tr>\n';
        html += headerRow.map(cell => `<th>${cell}</th>`).join('\n');
        html += '\n</tr>\n</thead>\n<tbody>\n';

        filteredCells.forEach(row => {
            html += '<tr>\n';
            row.forEach(cell => {
                html += `<td>${cell}</td>\n`;
            });
            html += '</tr>\n';
        });

        html += '</tbody>\n</table>\n';

        return html;
    });

    return text;
}

function escapeHTML(text) {
    return text.replace(/["&'<>]/g, function (match) {
        return {
            '"': '&quot;',
            '&': '&amp;',
            "'": '&#039;',
            '<': '&lt;',
            '>': '&gt;'
        }[match];
    });
}

function FormatMathFormulas() {
    const element = document.querySelector(".message:last-child").querySelector(".message-text");

    renderMathInElement(element, {
        delimiters: [
            { left: '$$', right: '$$', display: true },
            { left: '$', right: '$', display: false },
            { left: '\\(', right: '\\)', display: false },
            { left: '\\[', right: '\\]', display: true }
        ],
        displayMode: true,
        ignoredClasses: ["ignore_Format"],
        throwOnError: true
    });
}

function isJSON(str) {
    try {
        JSON.parse(str);
        return true;
    } catch (e) {
        return false;
    }
}

function FormatWholeMessage(message) {
    const codeBlockRegex = /```(.*?)```/gs;
    const html = message.replace(codeBlockRegex, (match, p1) => {
        return `<pre><code ignore_Format>${p1}</code></pre>`;
    });
    return ReplaceMarkdownSyntax(html);
}
//#endregion
