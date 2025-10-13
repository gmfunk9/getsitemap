const elements = getElements();
const apiModule = window.sitemapApi;

if (!apiModule) {
    throw new Error('Missing sitemapApi module; include sitemap-api.js before main.js.');
}

function getElements() {
    const form = document.getElementById('sitemapForm');
    const urlInput = document.getElementById('url');
    const submitButton = document.getElementById('submitBtn');
    const submitText = document.getElementById('submitBtnText');
    const spinner = document.getElementById('spinner');
    const responseField = document.getElementById('response');

    return { form, urlInput, submitButton, submitText, spinner, responseField };
}

function hasButtonControls(state) {
    if (!state.submitButton) {
        return false;
    }

    if (!state.submitText) {
        return false;
    }

    if (!state.spinner) {
        return false;
    }

    return true;
}

function setButtonLoading(state) {
    const ready = hasButtonControls(state);
    if (!ready) {
        return;
    }

    state.submitButton.disabled = true;
    state.spinner.style.display = 'inline-block';
}

function setButtonIdle(state) {
    const ready = hasButtonControls(state);
    if (!ready) {
        return;
    }

    state.submitButton.disabled = false;
    state.submitText.textContent = 'Create Sitemap';
    state.spinner.style.display = 'none';
}

function updateButtonText(state, text) {
    if (!state.submitText) {
        return;
    }

    state.submitText.textContent = text;
}

function formatUrls(urls) {
    const unique = Array.from(new Set(urls));
    unique.sort();
    return unique.join('\n');
}

function writeResponse(state, text) {
    if (!state.responseField) {
        return;
    }

    state.responseField.value = text;
}

function handleError(state, error) {
    const message = error instanceof Error ? error.message : 'Unknown error occurred.';
    writeResponse(state, 'Error: ' + message);
}

async function processSubmission(state) {
    let rawValue = '';
    if (state.urlInput) {
        rawValue = state.urlInput.value;
    }

    const normalizedUrl = apiModule.normalizeInput(rawValue);

    setButtonLoading(state);
    updateButtonText(state, 'Fetching data...');

    const urls = await apiModule.fetchSitemap(normalizedUrl);
    updateButtonText(state, 'Parsing sitemap...');

    const formatted = formatUrls(urls);
    updateButtonText(state, 'Finalizing...');
    writeResponse(state, formatted);
}

function attachHandlers(state) {
    if (!state.form) {
        return;
    }

    state.form.addEventListener('submit', async (event) => {
        event.preventDefault();
        writeResponse(state, '');

        try {
            await processSubmission(state);
        } catch (error) {
            handleError(state, error);
        } finally {
            setButtonIdle(state);
        }
    });
}

attachHandlers(elements);

function copyText() {
    const output = document.getElementById('response');
    if (!output) {
        return;
    }

    output.select();
    output.setSelectionRange(0, 99999);
    document.execCommand('copy');
}
