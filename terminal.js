(function () {
    'use strict';

    const apiModule = window.sitemapApi;
    if (!apiModule) {
        throw new Error('Missing sitemapApi module; include sitemap-api.js before terminal.js.');
    }

    const state = {
        currentAnimationDelay: 0.4,
        crawlHistory: [],
        isProcessing: false,
        currentUrls: []
    };

    const fortunes = [
        'Build fast, break nothing.',
        'Less code, more clarity.',
        'Automate the boring stuff.'
    ];

    const konamiSeq = [
        'ArrowUp',
        'ArrowUp',
        'ArrowDown',
        'ArrowDown',
        'ArrowLeft',
        'ArrowRight',
        'ArrowLeft',
        'ArrowRight',
        'b',
        'a'
    ];
    let konamiStep = 0;

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function addLine(text, className, child) {
        const outputArea = document.getElementById('outputArea');
        const line = document.createElement('p');
        let finalClass = 'typing-effect-line';
        if (className) {
            finalClass = finalClass + ' ' + className;
        }

        line.className = finalClass;

        if (child) {
            line.appendChild(child);
        } else {
            line.textContent = text;
        }

        line.style.animationDelay = '0.02s';

        let displayTarget = text;
        if (child) {
            displayTarget = child.textContent;
        }

        if (displayTarget) {
            if (displayTarget.length > 80) {
                line.classList.add('full-width');
            }
        }

        outputArea.appendChild(line);
        setTimeout(() => {
            outputArea.scrollTop = outputArea.scrollHeight;
        }, 20);
        return line;
    }

    function flash(text) {
        const box = document.getElementById('flash');
        box.textContent = text;
        box.classList.remove('opacity-0');
        setTimeout(() => {
            box.classList.add('opacity-0');
        }, 800);
    }

    function clearOutput() {
        const outputArea = document.getElementById('outputArea');
        outputArea.innerHTML = '';
        state.currentAnimationDelay = 0;
        state.currentUrls = [];
        addLine('Welcome to SitemapScanner v2.0', 'success');
        addLine('Type \'help\' for commands.');
    }

    function showHelp() {
        addLine('Available commands:', 'success');
        addLine('  help      - Show this help message');
        addLine('  clear     - Clear the terminal');
        addLine('  history   - Show crawl history');
        addLine('  status    - Show current status');
        addLine('  <url>     - Scan sitemap for the given URL');
        addLine('');
        addLine('Examples:');
        addLine('  example.com');
        addLine('  https://example.com/sitemap.xml');
        addLine('  https://blog.example.com/sitemap_index.xml');
    }

    function showHistory() {
        if (state.crawlHistory.length === 0) {
            addLine('No crawl history available.', 'warning');
            return;
        }

        addLine('Recent crawl history:', 'success');
        addLine('');

        const recent = state.crawlHistory.slice(-10);
        recent.forEach((entry) => {
            const timestamp = new Date(entry.timestamp).toLocaleTimeString();
            const status = entry.result === 'success' ? '✓' : '✗';
            addLine(timestamp + ' ' + status + ' ' + entry.action + ': ' + entry.url);
        });
    }

    function showStatus() {
        addLine('System Status:', 'success');
        const processingText = state.isProcessing ? 'Yes' : 'No';
        addLine('Processing: ' + processingText);
        addLine('History entries: ' + state.crawlHistory.length);
        const userAgent = navigator.userAgent.split(' ')[0];
        addLine('Browser: ' + userAgent);
    }

    function showFortune() {
        const index = Math.floor(Math.random() * fortunes.length);
        const msg = fortunes[index];
        addLine(msg, 'success');
    }

    function showUptime() {
        addLine('up 10 years, fan RPM 9001', 'success');
    }

    function logActivity(action, url, result) {
        state.crawlHistory.push({
            timestamp: new Date().toISOString(),
            action,
            url,
            result: result || 'success'
        });

        if (state.crawlHistory.length > 100) {
            state.crawlHistory = state.crawlHistory.slice(-100);
        }
    }

    function handleCopyCommand(command) {
        const parts = command.split(' ');
        const indexText = parts[1];
        if (!indexText) {
            addLine('Missing index; use copy <n>', 'error');
            return;
        }

        const parsedIndex = parseInt(indexText, 10);
        const invalidIndex = Number.isNaN(parsedIndex);
        if (invalidIndex) {
            addLine('Invalid index; provide a number.', 'error');
            return;
        }

        const entry = state.crawlHistory[parsedIndex - 1];
        if (!entry) {
            addLine('Index not found in history.', 'error');
            return;
        }

        navigator.clipboard.writeText(entry.url).then(() => {
            addLine('☑ copied');
        });
    }

    async function processCommand(input) {
        if (state.isProcessing) {
            addLine('Another operation is in progress. Please wait...', 'warning');
            return;
        }

        const trimmedCommand = input.trim();
        const lowered = trimmedCommand.toLowerCase();
        const original = escapeHtml(input);
        addLine('user@dev:~/sitemap ❯ ' + original);

        if (lowered === '') {
            return;
        }

        if (lowered === 'help') {
            showHelp();
            return;
        }

        if (lowered === 'clear') {
            setTimeout(clearOutput, 100);
            return;
        }

        if (lowered === 'history') {
            showHistory();
            return;
        }

        if (lowered === 'status') {
            showStatus();
            return;
        }

        if (lowered === 'fortune') {
            showFortune();
            return;
        }

        if (lowered === 'uptime') {
            showUptime();
            return;
        }

        const startsWithCopy = lowered.startsWith('copy ');
        if (startsWithCopy) {
            handleCopyCommand(lowered);
            return;
        }

        state.isProcessing = true;

        try {
            const normalizedUrl = apiModule.normalizeInput(trimmedCommand);
            addLine('Scanning: ' + normalizedUrl, 'success');

            const urls = await apiModule.fetchSitemap(normalizedUrl);
            const uniqueUrls = Array.from(new Set(urls)).sort();
            state.currentUrls = uniqueUrls;

            addLine('');
            addLine('Found ' + uniqueUrls.length + ' unique URLs:', 'hsuccess');
            addLine('');

            if (uniqueUrls.length === 0) {
                addLine('No URLs found in sitemap.', 'warning');
                logActivity('scan', normalizedUrl, 'empty');
                return;
            }

            const displayUrls = uniqueUrls.slice(0, 1000);
            displayUrls.forEach((url) => {
                addLine(url);
            });

            if (uniqueUrls.length > 1000) {
                const remainder = uniqueUrls.length - 1000;
                addLine('... and ' + remainder + ' more URLs (truncated for display)', 'warning');
            }

            addLine('');
            addLine('Scan complete.', 'success');
            logActivity('scan', normalizedUrl, 'success');
        } catch (error) {
            addLine('Error: ' + error.message, 'error');
            logActivity('scan', trimmedCommand, error.message);
        } finally {
            state.isProcessing = false;
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const input = document.getElementById('commandInput');
        const terminal = document.getElementById('terminal');
        const copyBtn = document.getElementById('copyAllBtn');
        let selecting = false;

        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                const command = input.value;
                input.value = '';
                if (command.trim() === '') {
                    return;
                }

                processCommand(command);
                return;
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
            }
        });

        terminal.addEventListener('pointerdown', () => {
            selecting = true;
        });

        window.addEventListener('pointerup', () => {
            selecting = false;
        });

        copyBtn.addEventListener('click', () => {
            let list = state.currentUrls;
            if (!Array.isArray(list)) {
                list = [];
            }

            if (list.length === 0) {
                flash('no urls');
                return;
            }

            const joined = list.join('\n');
            navigator.clipboard.writeText(joined).then(() => {
                flash('copied');
            });
        });

        setInterval(() => {
            if (selecting) {
                return;
            }

            if (document.activeElement === input) {
                return;
            }

            if (document.activeElement === copyBtn) {
                return;
            }

            input.focus();
        }, 300);

        window.addEventListener('keydown', (event) => {
            const expectedKey = konamiSeq[konamiStep];
            if (event.key === expectedKey) {
                konamiStep = konamiStep + 1;
                if (konamiStep === konamiSeq.length) {
                    konamiStep = 0;
                    addLine('FUNKPD DEV MODE ENABLED', 'success');
                    document.body.classList.add('high-contrast');
                    setTimeout(() => {
                        document.body.classList.remove('high-contrast');
                    }, 5000);
                }

                return;
            }

            konamiStep = 0;
        });

        input.focus();
    });
})();
