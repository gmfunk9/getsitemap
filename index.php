<?php
$siteUrl = 'https://getsitemap.funkpd.com';
$siteTitle = 'Get Sitemap: Terminal Tool to Extract Sitemap URLs';
$siteDescription = 'Get sitemap URLs fast. SitemapScanner is a terminal-style tool that fetches and parses XML sitemaps. Just type a URL and get the links.';
$openGraphTitle = 'SitemapScanner: Instantly Extract Sitemap Links';
$openGraphDescription = 'Scan any site’s XML sitemap. Paste a URL and get all indexed pages in seconds. Built by FunkPd for devs and SEOs.';
$twitterTitle = 'Get Sitemap Links Instantly';
$twitterDescription = 'Paste a URL. Get every sitemap page. No login. No noise. Just URLs.';
$previewImageUrl = 'https://funkpd.com/get_sitemap.webp';
$brandName = 'FunkPd';
$brandUrl = 'https://funkpd.com';
$twitterHandle = '@funk_pd';
$schemaData = [
    '@context' => 'https://schema.org',
    '@type' => 'SoftwareApplication',
    'name' => 'Sitemap Scanner',
    'alternateName' => 'Get Sitemap',
    'operatingSystem' => 'All',
    'applicationCategory' => 'DeveloperTool',
    'description' => 'Terminal-style sitemap fetcher and parser for devs and SEOs. Enter a URL, get sitemap links instantly.',
    'url' => $siteUrl,
    'publisher' => [
        '@type' => 'Organization',
        'name' => $brandName,
        'url' => $brandUrl,
    ],
];
$schemaJson = json_encode($schemaData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($siteTitle, ENT_QUOTES) ?></title>
  <meta name="description" content="<?= htmlspecialchars($siteDescription, ENT_QUOTES) ?>" />
  <meta name="robots" content="index, follow" />
  <link rel="canonical" href="<?= htmlspecialchars($siteUrl, ENT_QUOTES) ?>" />
  <link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <!-- OpenGraph -->
  <meta property="og:title" content="<?= htmlspecialchars($openGraphTitle, ENT_QUOTES) ?>" />
  <meta property="og:description" content="<?= htmlspecialchars($openGraphDescription, ENT_QUOTES) ?>" />
  <meta property="og:url" content="<?= htmlspecialchars($siteUrl, ENT_QUOTES) ?>" />
  <meta property="og:site_name" content="<?= htmlspecialchars($brandName, ENT_QUOTES) ?>" />
  <meta property="og:image" content="<?= htmlspecialchars($previewImageUrl, ENT_QUOTES) ?>" />
  <meta property="og:type" content="website" />
  <!-- Twitter -->
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="<?= htmlspecialchars($twitterTitle, ENT_QUOTES) ?>" />
  <meta name="twitter:description" content="<?= htmlspecialchars($twitterDescription, ENT_QUOTES) ?>" />
  <meta name="twitter:image" content="<?= htmlspecialchars($previewImageUrl, ENT_QUOTES) ?>" />
  <meta name="twitter:site" content="<?= htmlspecialchars($twitterHandle, ENT_QUOTES) ?>" />
  <!-- Schema -->
  <script type="application/ld+json"><?= $schemaJson ?></script>
  <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div id="terminal" class="terminal-container">
    <div id="outputArea" class="terminal-output" role="log" aria-live="polite" aria-label="Terminal output">
      <h1 class="typing-effect-line success">Get Sitemap URLs <br>Instantly with a Fast <br>Terminal-Style Scanner</h1>
      <p class="typing-effect-line success">Get Sitemap is a fast XML sitemap parser <br>for developers and SEOs. <br>Also known as SitemapScanner by FunkPd.</p>
      <a class="typing-effect-line success" href="https://funkpd.com">Website by FunkPd. internet with soul.</a>
      <p class="typing-effect-line" style="animation-delay: 0.2s;">Type 'help' for commands.</p>
    </div>
    <div class="terminal-input-line">
      <span class="terminal-prompt">
        <span class="prompt-segment prompt-user-host">user@dev</span>
        <span class="prompt-segment prompt-path">~/sitemap</span>
        <span class="prompt-arrow">❯</span>
      </span>
      <input 
        type="text" 
        id="commandInput" 
        class="terminal-input blinking-cursor" 
        autofocus 
        autocomplete="off"
        autocapitalize="off"
        autocorrect="off"
        spellcheck="false"
        aria-label="Command input"
        placeholder="Enter command or URL..."
      />
        <button id="copyAllBtn" class="copy-all" aria-label="Copy URLs">copy</button>
    </div>
  </div>
  <div id="flash" class="flash opacity-0" aria-live="polite"></div>
  <script>
    (function() {
      'use strict';
      
      let state = {
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

      function normalizeUrl(input) {
        if (!input || typeof input !== 'string') {
          throw new Error('Invalid input: URL must be a non-empty string');
        }

        let cleanInput = input.trim();
        
        if (!cleanInput) {
          throw new Error('Invalid input: URL cannot be empty');
        }

        if (/[<>'"{}|\\^`\[\]]/.test(cleanInput)) {
          throw new Error('Invalid input: URL contains invalid characters');
        }

        if (!cleanInput.match(/^https?:\/\//i)) {
          cleanInput = 'https://' + cleanInput;
        }

        let url;
        try {
          url = new URL(cleanInput);
        } catch {
          throw new Error('Invalid URL format');
        }

        if (!['http:', 'https:'].includes(url.protocol)) {
          throw new Error('Invalid protocol: only HTTP and HTTPS are supported');
        }

        return url.toString();
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

      function addLine(text, className = '', child) {
        const outputArea = document.getElementById('outputArea');
        const line = document.createElement('p');

        line.className = 'typing-effect-line' + (className ? ' ' + className : '');
        if (child) {
          line.appendChild(child);
        } else {
          line.textContent = text;
        }
        line.style.animationDelay = "0.02s";

        const len = child ? child.textContent.length : text.length;
        if (len > 80) {
          line.classList.add('full-width');
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
        setTimeout(() => box.classList.add('opacity-0'), 800);
      }

function clearOutput() {
  const outputArea = document.getElementById('outputArea');
  outputArea.innerHTML = '';
  state.currentAnimationDelay = 0;
  state.currentUrls = []; // safe to clear here
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
        
        state.crawlHistory.slice(-10).forEach(entry => {
          const timestamp = new Date(entry.timestamp).toLocaleTimeString();
          const status = entry.result === 'success' ? '✓' : '✗';
          addLine(`${timestamp} ${status} ${entry.action}: ${entry.url}`);
        });
      }

      function showStatus() {
        addLine('System Status:', 'success');
        addLine(`Processing: ${state.isProcessing ? 'Yes' : 'No'}`);
        addLine(`History entries: ${state.crawlHistory.length}`);
        addLine(`Browser: ${navigator.userAgent.split(' ')[0]}`);
      }

      function showFortune() {
        const msg = fortunes[Math.floor(Math.random() * fortunes.length)];
        addLine(msg, 'success');
      }

      function showUptime() {
        addLine('up 10 years, fan RPM 9001', 'success');
      }

      async function fetchSitemap(url) {
        const response = await fetch(`./get_sitemap.php?url=${encodeURIComponent(url)}`);
        const data = await response.json();
        
        if (!data.success) {
          throw new Error(data.message || 'Failed to fetch sitemap');
        }
        
        return data.sitemap;
      }

      async function processCommand(input) {
        if (state.isProcessing) {
          addLine('Another operation is in progress. Please wait...', 'warning');
          return;
        }

        const command = input.trim().toLowerCase();
        const originalInput = escapeHtml(input);
        
        addLine(`user@dev:~/sitemap ❯ ${originalInput}`);
        
        if (command === 'help') {
          showHelp();
          return;
        }
        
        if (command === 'clear') {
          setTimeout(clearOutput, 100);
          return;
        }
        
        if (command === 'history') {
          showHistory();
          return;
        }

        if (command === 'status') {
          showStatus();
          return;
        }

        if (command === 'fortune') {
          showFortune();
          return;
        }

        if (command === 'uptime') {
          showUptime();
          return;
        }

        if (command.startsWith('copy ')) {
          const idx = parseInt(command.split(' ')[1], 10);
          if (!idx) {
            addLine('Missing index; use copy <n>', 'error');
            return;
          }
          const entry = state.crawlHistory[idx - 1];
          if (!entry) {
            addLine('Index not found in history.', 'error');
            return;
          }
          navigator.clipboard.writeText(entry.url).then(() => addLine('\u2611 copied'));
          return;
        }
        
        if (!command) {
          return;
        }
        
        state.isProcessing = true;
        
        try {
          const normalizedUrl = normalizeUrl(input);
          addLine(`Scanning: ${normalizedUrl}`, 'success');
          
          const urls = await fetchSitemap(normalizedUrl);
          
          const uniqueUrls = [...new Set(urls)].sort();
          
          state.currentUrls = uniqueUrls;
          addLine('');
          addLine(`Found ${uniqueUrls.length} unique URLs:`, 'hsuccess');
          addLine('');
          
          if (uniqueUrls.length === 0) {
            addLine('No URLs found in sitemap.', 'warning');
          } else {
            const displayUrls = uniqueUrls.slice(0, 1000);
            displayUrls.forEach(url => {
              addLine(url);
            });
            
            if (uniqueUrls.length > 1000) {
              addLine(`... and ${uniqueUrls.length - 1000} more URLs (truncated for display)`, 'warning');
            }
          }
          
          addLine('');
          addLine('Scan complete.', 'success');
          logActivity('scan', normalizedUrl, 'success');
          
        } catch (error) {
          addLine(`Error: ${error.message}`, 'error');
          logActivity('scan', input, error.message);
        } finally {
        //   state.currentUrls = []; // do not reset here or clicktocopy breaks.
          state.isProcessing = false;
        }
      }

      document.addEventListener('DOMContentLoaded', function() {
        const input = document.getElementById('commandInput');
        
        input.addEventListener('keydown', function(e) {
          if (e.key === 'Enter') {
            const command = input.value;
            input.value = '';

            if (command.trim()) {
              processCommand(command);
            }
          }

          if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
            e.preventDefault();
          }
        });

        const term = document.getElementById('terminal');
        let selecting = false;

        term.addEventListener('pointerdown', () => selecting = true);
        window.addEventListener('pointerup', () => selecting = false);

        const copyBtn = document.getElementById("copyAllBtn");
        setInterval(() => {
          if (!selecting && document.activeElement !== input && document.activeElement !== copyBtn) input.focus();
        }, 300);
        copyBtn.addEventListener("click", () => {
          const list = state.currentUrls || [];
          if (!list.length) {
            flash("no urls");
            return;
          }
          navigator.clipboard.writeText(list.join("\n")).then(() => flash("copied"));
        });

        window.addEventListener('keydown', function(e) {
          if (e.key === konamiSeq[konamiStep]) {
            konamiStep += 1;
            if (konamiStep === konamiSeq.length) {
              konamiStep = 0;
              addLine('FUNKPD DEV MODE ENABLED', 'success');
              document.body.classList.add('high-contrast');
              setTimeout(() => document.body.classList.remove('high-contrast'), 5000);
            }
          } else {
            konamiStep = 0;
          }
        });

        input.focus();
      });
      


    })();
  </script>
</body>
</html>
