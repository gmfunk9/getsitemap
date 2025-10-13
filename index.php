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
  <script src="sitemap-api.js" defer></script>
  <script src="terminal.js" defer></script>
</body>
</html>
